<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * PresupuestoAnualHelper - Cálculo de presupuesto anual de combustible
 *
 * Gestiona el cálculo dinámico del presupuesto anual considerando
 * múltiples escenarios laborales del usuario.
 *
 * ESCENARIOS MANEJADOS:
 * ┌─────────┬──────────────────────────────────────────────────────────┐
 * │ Caso    │ Descripción                                            │
 * ├─────────┼──────────────────────────────────────────────────────────┤
 * │ 1       │ Sin registros: presupuesto completo del año             │
 * │ 2       │ Período activo normal: ingreso → egreso/fin de año      │
 * │ 3       │ En período de prueba: días × monto_diario × porcentaje  │
 * │ 3b      │ Post-prueba: fin de prueba → egreso/fin de año          │
 * │ 4       │ Post-egreso: día después del egreso → fin de año        │
 * │ 5       │ Histórico: suma de todos los períodos del año           │
 * └─────────┴──────────────────────────────────────────────────────────┘
 *
 * FLUJO DE DECISIÓN:
 *   obtener($anio)
 *     ├── ¿Año diferente al actual? → _historico()
 *     ├── ¿Tiene período activo?    → _dinamico()
 *     ├── ¿Tiene período vencido?   → _postEgreso()
 *     └── Sin registros             → _sinRegistros()
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class PresupuestoAnualHelper
{
    /** @var PDO Conexión a base de datos */
    private $connect;

    /** @var int ID del usuario actual */
    private $idUsuario;

    /** @var int ID de la agencia del usuario */
    private $idAgencia;

    /** @var int ID del puesto del usuario */
    private $puesto;

    /**
     * @param PDO $connect Conexión PDO activa (viene del controller)
     * @param int $idUsuario ID del usuario
     * @param int $idAgencia ID de la agencia del usuario
     * @param int $puesto ID del puesto del usuario
     */
    public function __construct(PDO $connect, $idUsuario, $idAgencia, $puesto)
    {
        $this->connect = $connect;
        $this->idUsuario = $idUsuario;
        $this->idAgencia = $idAgencia;
        $this->puesto = $puesto;
    }

    // ════════════════════════════════════════════════════════════════════
    // MÉTODO PRINCIPAL
    // ════════════════════════════════════════════════════════════════════

    /**
     * Obtiene el presupuesto del año solicitado según el contexto del usuario
     *
     * Determina automáticamente qué estrategia de cálculo aplicar
     * basándose en los períodos laborales registrados.
     *
     * @param int $anio Año del presupuesto a consultar
     * @return array|null Datos del presupuesto o null si no existe configuración
     */
    public function obtener($anio)
    {
        try {
            $hoy = new \DateTime();
            $anioHoy = (int)$hoy->format('Y');

            // Año histórico
            if ($anio != $anioHoy) {
                return $this->_historico($anio);
            }

            // Buscar período activo
            $periodoActivo = $this->_buscarPeriodoActivo($anio);
            if ($periodoActivo) {
                return $this->_dinamico($periodoActivo, $anio);
            }

            // Buscar período vencido
            $periodoVencido = $this->_buscarUltimoPeriodoVencido($anio);
            if ($periodoVencido) {
                return $this->_postEgreso($periodoVencido, $anio);
            }

            // Sin registros
            return $this->_sinRegistros($anio);

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::obtener: " . $e->getMessage());
            return null;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ESTRATEGIAS DE CÁLCULO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Calcula el presupuesto dinámico según el período activo actual
     *
     * Maneja tres sub-escenarios:
     * - En período de prueba: presupuesto restringido
     * - Post-prueba: presupuesto completo desde fin de prueba
     * - Normal: presupuesto desde ingreso hasta egreso/fin de año
     *
     * @param array $periodo Datos del período activo
     * @param int $anio Año del presupuesto
     * @return array Datos del presupuesto calculado
     */
    private function _dinamico($periodo, $anio)
    {
        try {
            $hoy = new \DateTime();
            $hoy->setTime(0, 0, 0);
            $inicioAnio = new \DateTime("$anio-01-01");
            $finAnio = new \DateTime("$anio-12-31");

            // ── Determinar fechas del período ──
            $fechaIngreso = $periodo['fecha_ingreso']
                ? new \DateTime($periodo['fecha_ingreso'])
                : clone $inicioAnio;

            $fechaEgreso = $periodo['fecha_egreso']
                ? new \DateTime($periodo['fecha_egreso'])
                : clone $finAnio;

            $presupuestoDiario = (float)$periodo['monto_diario'];

            // ── Verificar período de prueba ──
            $enPeriodoPrueba = false;
            $fechaFinPrueba = null;

            if ($periodo['es_nuevo'] && $periodo['dias_presupuesto'] > 0) {
                $fechaFinPrueba = clone $fechaIngreso;
                $fechaFinPrueba->modify("+{$periodo['dias_presupuesto']} days");
                $fechaFinPrueba->modify("-1 day");

                if ($hoy >= $fechaIngreso && $hoy <= $fechaFinPrueba) {
                    $enPeriodoPrueba = true;
                }
            }

            // ── Detalle base ──
            $detalle = [
                'periodo_id' => $periodo['idUsuariosControlFechas'],
                'fecha_ingreso' => $fechaIngreso->format('Y-m-d'),
                'fecha_egreso' => $fechaEgreso->format('Y-m-d'),
                'agencia' => $periodo['agencia'],
                'puesto' => $periodo['puesto'],
                'presupuesto_anual_puesto' => (float)$periodo['monto_anual'],
                'presupuesto_diario' => round($presupuestoDiario, 2),
                'es_nuevo' => (bool)$periodo['es_nuevo'],
                'en_periodo_prueba' => $enPeriodoPrueba
            ];

            $presupuestoTotal = 0;

            if ($enPeriodoPrueba) {
                $presupuestoTotal = $this->_calcularPeriodoPrueba(
                    $periodo, $presupuestoDiario, $hoy, $fechaFinPrueba, $detalle
                );
            } else {
                $presupuestoTotal = $this->_calcularPeriodoNormal(
                    $periodo, $presupuestoDiario, $hoy, $fechaIngreso,
                    $fechaEgreso, $fechaFinPrueba, $inicioAnio, $detalle
                );
            }

            $detalle['presupuesto_total_disponible'] = round($presupuestoTotal, 2);

            return [
                'idPresupuestoGeneral' => null,
                'agenciaid' => $periodo['agenciaid'],
                'puestoid' => $periodo['puestoid'],
                'anio' => $anio,
                'monto_anual' => $presupuestoTotal,
                'monto_diario' => $presupuestoDiario,
                'agencia' => $periodo['agencia'],
                'puesto' => $periodo['puesto'],
                'tiene_periodos' => true,
                'detalle_periodo_actual' => $detalle
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_dinamico: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcula presupuesto cuando el usuario está EN período de prueba
     *
     * Fórmula: monto_diario × días_prueba × (porcentaje / 100)
     *
     * @param array $periodo Datos del período
     * @param float $presupuestoDiario Monto diario del puesto
     * @param \DateTime $hoy Fecha actual
     * @param \DateTime $fechaFinPrueba Fecha fin del período de prueba
     * @param array     &$detalle Referencia al array de detalle (se modifica)
     * @return float Presupuesto total calculado
     */
    private function _calcularPeriodoPrueba($periodo, $presupuestoDiario, $hoy, $fechaFinPrueba, &$detalle)
    {
        $porcentaje = $periodo['porcentaje_presupuesto'] / 100;
        $diasPrueba = $periodo['dias_presupuesto'];

        $presupuestoTotalPrueba = $presupuestoDiario * $diasPrueba * $porcentaje;
        $diasRestantesPrueba = max(0, $hoy->diff($fechaFinPrueba)->days + 1);

        $detalle['tiene_restriccion'] = true;
        $detalle['dias_restriccion'] = $diasPrueba;
        $detalle['porcentaje_restriccion'] = $periodo['porcentaje_presupuesto'];
        $detalle['fecha_fin_restriccion'] = $fechaFinPrueba->format('Y-m-d');
        $detalle['dias_restantes_prueba'] = $diasRestantesPrueba;
        $detalle['dias_totales_prueba'] = $diasPrueba;
        $detalle['presupuesto_total_prueba'] = round($presupuestoTotalPrueba, 2);
        $detalle['periodo_actual'] = 'prueba';

        // Ventana de consumo: desde ingreso hasta fin de prueba
        $detalle['fecha_consumo_desde'] = $detalle['fecha_ingreso'];
        $detalle['fecha_consumo_hasta'] = $fechaFinPrueba->format('Y-m-d');

        return $presupuestoTotalPrueba;
    }

    /**
     * Calcula presupuesto desde el día siguiente al egreso hasta fin de año
     *
     * Se usa cuando el usuario no tiene período activo pero tuvo uno
     * que ya venció dentro del mismo año.
     *
     * @param array $periodoVencido Datos del período que ya terminó
     * @param int $anio Año del presupuesto
     * @return array|null Datos del presupuesto o null
     */
    private function _postEgreso($periodoVencido, $anio)
    {
        try {
            $finAnio = new \DateTime("$anio-12-31");
            $fechaEgreso = new \DateTime($periodoVencido['fecha_egreso']);

            // El presupuesto empieza el día SIGUIENTE al egreso
            $fechaInicio = clone $fechaEgreso;
            $fechaInicio->modify('+1 day');

            if ($fechaInicio > $finAnio) {
                return null;
            }

            $presupuestoDiario = (float)$periodoVencido['monto_diario'];
            $diasRestantes = $fechaInicio->diff($finAnio)->days + 1;
            $presupuestoTotal = $presupuestoDiario * $diasRestantes;

            $detalle = [
                'periodo_actual' => 'post_egreso',
                'periodo_origen_id' => $periodoVencido['idUsuariosControlFechas'],
                'fecha_egreso_anterior' => $fechaEgreso->format('Y-m-d'),
                'fecha_inicio_calculo' => $fechaInicio->format('Y-m-d'),
                'fecha_fin_calculo' => $finAnio->format('Y-m-d'),
                'dias_restantes' => $diasRestantes,
                'presupuesto_diario' => round($presupuestoDiario, 2),
                'presupuesto_total_disponible' => round($presupuestoTotal, 2),
                'agencia' => $periodoVencido['agencia'],
                'puesto' => $periodoVencido['puesto'],
            ];

            return [
                'idPresupuestoGeneral' => null,
                'agenciaid' => $periodoVencido['agenciaid'],
                'puestoid' => $periodoVencido['puestoid'],
                'anio' => $anio,
                'monto_anual' => round($presupuestoTotal, 2),
                'monto_diario' => $presupuestoDiario,
                'agencia' => $periodoVencido['agencia'],
                'puesto' => $periodoVencido['puesto'],
                'tiene_periodos' => true,
                'detalle_periodo_actual' => $detalle,
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_postEgreso: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calcula presupuesto histórico sumando todos los períodos de un año pasado
     *
     * @param int $anio Año histórico a consultar
     * @return array|null Datos del presupuesto consolidado
     */
    private function _historico($anio)
    {
        try {
            $periodos = $this->_buscarTodosLosPeriodos($anio);

            if (empty($periodos)) {
                return $this->_sinRegistros($anio);
            }

            return $this->_calcularSumaPeriodos($periodos, $anio);

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_historico: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Suma presupuestos de todos los períodos de un año (para histórico)
     *
     * @param array $periodos Array de períodos del año
     * @param int $anio Año consultado
     * @return array Presupuesto consolidado
     */
    private function _calcularSumaPeriodos($periodos, $anio)
    {
        try {
            $esBisiesto = (($anio % 4 == 0 && $anio % 100 != 0) || ($anio % 400 == 0));
            $diasAnio = $esBisiesto ? 366 : 365;

            $inicioAnio = new \DateTime("$anio-01-01");
            $finAnio = new \DateTime("$anio-12-31");

            $presupuestoTotal = 0;
            $detallePeriodos = [];
            $ultimoPeriodo = end($periodos);

            foreach ($periodos as $periodo) {
                $detalle = $this->_calcularUnPeriodoHistorico($periodo, $inicioAnio, $finAnio, $diasAnio);
                $presupuestoTotal += $detalle['presupuesto_periodo'];
                $detallePeriodos[] = $detalle;
            }

            return [
                'idPresupuestoGeneral' => $ultimoPeriodo['idPresupuestoGeneral'] ?? null,
                'agenciaid' => $ultimoPeriodo['agenciaid'],
                'puestoid' => $ultimoPeriodo['puestoid'],
                'anio' => $anio,
                'monto_anual' => $presupuestoTotal,
                'monto_diario' => $presupuestoTotal / $diasAnio,
                'agencia' => $ultimoPeriodo['agencia'],
                'puesto' => $ultimoPeriodo['puesto'],
                'tiene_periodos' => true,
                'detalle_periodos' => $detallePeriodos,
                'es_historico' => true
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_calcularSumaPeriodos: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene presupuesto cuando no hay períodos registrados
     *
     * @param int $anio Año a consultar
     * @return array|null Datos del presupuesto o null
     */
    private function _sinRegistros($anio)
    {
        try {
            $sql = "SELECT
                    pg.idPresupuestoGeneral, pg.agenciaid, pg.puestoid,
                    pg.anio, pg.monto_anual, pg.monto_diario,
                    a.nombre as agencia, p.nombre as puesto
                FROM apoyo_combustibles.presupuestogeneral pg
                LEFT JOIN dbintranet.agencia a ON pg.agenciaid = a.idAgencia
                LEFT JOIN dbintranet.puesto p ON pg.puestoid = p.idPuesto
                WHERE pg.activo = 1
                    AND pg.anio = ?
                    AND pg.agenciaid = ?
                    AND pg.puestoid = ?
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$anio, $this->idAgencia, $this->puesto]);
            $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$presupuesto) return null;

            return [
                'idPresupuestoGeneral' => $presupuesto['idPresupuestoGeneral'],
                'agenciaid' => $presupuesto['agenciaid'],
                'puestoid' => $presupuesto['puestoid'],
                'anio' => $presupuesto['anio'],
                'monto_anual' => $presupuesto['monto_anual'],
                'monto_diario' => $presupuesto['monto_diario'],
                'agencia' => $presupuesto['agencia'],
                'puesto' => $presupuesto['puesto'],
                'tiene_periodos' => false,
                'detalle_periodo_actual' => [
                    'periodo_actual' => 'sin_registros',
                    'presupuesto_total_disponible' => $presupuesto['monto_anual']
                ]
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_sinRegistros: " . $e->getMessage());
            return null;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // CONSULTAS A BASE DE DATOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Busca el período activo actual del usuario
     *
     * @param int $anio Año del presupuesto
     * @return array|null Datos del período activo o null
     */
    private function _buscarPeriodoActivo($anio)
    {
        try {
            $sql = "SELECT
                    ucf.idUsuariosControlFechas, ucf.usuarioid, ucf.agenciaid,
                    ucf.puestoid, ucf.fecha_ingreso, ucf.fecha_egreso,
                    ucf.es_nuevo, ucf.porcentaje_presupuesto, ucf.dias_presupuesto,
                    pg.monto_anual, pg.monto_diario,
                    a.nombre as agencia, p.nombre as puesto
                FROM apoyo_combustibles.usuarioscontrolfechas ucf
                INNER JOIN apoyo_combustibles.presupuestogeneral pg
                    ON pg.agenciaid = ucf.agenciaid
                    AND pg.puestoid = ucf.puestoid
                    AND pg.anio = ? AND pg.activo = 1
                LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
                LEFT JOIN dbintranet.puesto p ON ucf.puestoid = p.idPuesto
                WHERE ucf.usuarioid = ?
                    AND ucf.activo = 1
                    AND ucf.fecha_ingreso <= CURDATE()
                    AND (ucf.fecha_egreso IS NULL OR ucf.fecha_egreso >= CURDATE())
                ORDER BY ucf.fecha_ingreso DESC
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$anio, $this->idUsuario]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_buscarPeriodoActivo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca el último período vencido del usuario en el año
     *
     * @param int $anio Año a consultar
     * @return array|null Datos del período vencido o null
     */
    private function _buscarUltimoPeriodoVencido($anio)
    {
        try {
            $sql = "SELECT
                    ucf.idUsuariosControlFechas, ucf.usuarioid, ucf.agenciaid,
                    ucf.puestoid, ucf.fecha_ingreso, ucf.fecha_egreso,
                    ucf.es_nuevo, ucf.porcentaje_presupuesto, ucf.dias_presupuesto,
                    pg.monto_anual, pg.monto_diario,
                    a.nombre as agencia, p.nombre as puesto
                FROM apoyo_combustibles.usuarioscontrolfechas ucf
                INNER JOIN apoyo_combustibles.presupuestogeneral pg
                    ON pg.agenciaid = ucf.agenciaid
                    AND pg.puestoid = ucf.puestoid
                    AND pg.anio = ? AND pg.activo = 1
                LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
                LEFT JOIN dbintranet.puesto p ON ucf.puestoid = p.idPuesto
                WHERE ucf.usuarioid = ?
                    AND ucf.activo = 1
                    AND YEAR(ucf.fecha_egreso) = ?
                    AND ucf.fecha_egreso < CURDATE()
                ORDER BY ucf.fecha_egreso DESC
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$anio, $this->idUsuario, $anio]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_buscarUltimoPeriodoVencido: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todos los períodos laborales del usuario en un año (para histórico)
     *
     * @param int $anio Año a consultar
     * @return array Array de períodos
     */
    private function _buscarTodosLosPeriodos($anio)
    {
        try {
            $sql = "SELECT
                    ucf.idUsuariosControlFechas, ucf.usuarioid, ucf.agenciaid,
                    ucf.puestoid, ucf.fecha_ingreso, ucf.fecha_egreso,
                    ucf.es_nuevo, ucf.porcentaje_presupuesto, ucf.dias_presupuesto,
                    pg.monto_anual, pg.monto_diario,
                    a.nombre as agencia, p.nombre as puesto
                FROM apoyo_combustibles.usuarioscontrolfechas ucf
                INNER JOIN apoyo_combustibles.presupuestogeneral pg
                    ON pg.agenciaid = ucf.agenciaid
                    AND pg.puestoid = ucf.puestoid
                    AND pg.anio = ? AND pg.activo = 1
                LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
                LEFT JOIN dbintranet.puesto p ON ucf.puestoid = p.idPuesto
                WHERE ucf.usuarioid = ?
                    AND ucf.activo = 1
                    AND (
                        YEAR(ucf.fecha_ingreso) = ?
                        OR (ucf.fecha_egreso IS NULL OR YEAR(ucf.fecha_egreso) = ?)
                    )
                ORDER BY ucf.fecha_ingreso ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$anio, $this->idUsuario, $anio, $anio]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_buscarTodosLosPeriodos: " . $e->getMessage());
            return [];
        }
    }


    private function _calcularPeriodoNormal(
        $periodo, $presupuestoDiario, $hoy, $fechaIngreso,
        $fechaEgreso, $fechaFinPrueba, $inicioAnio, &$detalle
    ) {
        $fechaInicioCalculo   = null;
        $presupuestoTramoPrueba = 0;

        if ($fechaFinPrueba && $fechaFinPrueba < $hoy) {
            $fechaInicioCalculo = clone $fechaFinPrueba;
            $fechaInicioCalculo->modify('+1 day');
            $detalle['periodo_actual']         = 'post_prueba';
            $detalle['fecha_fin_restriccion']  = $fechaFinPrueba->format('Y-m-d');
            $detalle['dias_restriccion']       = $periodo['dias_presupuesto'];
            $detalle['porcentaje_restriccion'] = $periodo['porcentaje_presupuesto'];

            $porcentajePrueba       = $periodo['porcentaje_presupuesto'] / 100;
            $diasPrueba             = $periodo['dias_presupuesto'];
            $presupuestoTramoPrueba = $presupuestoDiario * $diasPrueba * $porcentajePrueba;

            $detalle['tramo_prueba'] = [
                'dias'         => $diasPrueba,
                'porcentaje'   => $periodo['porcentaje_presupuesto'],
                'presupuesto'  => round($presupuestoTramoPrueba, 2),
                'fecha_inicio' => $fechaIngreso->format('Y-m-d'),
                'fecha_fin'    => $fechaFinPrueba->format('Y-m-d'),
            ];

            // Ventana completa: desde ingreso (incluye tramo prueba + normal)
            $detalle['fecha_consumo_desde'] = $fechaIngreso->format('Y-m-d');
            $detalle['fecha_consumo_hasta'] = $fechaEgreso->format('Y-m-d');

        } elseif ($periodo['fecha_ingreso']) {
            $fechaInicioCalculo = clone $fechaIngreso;
            $detalle['periodo_actual'] = 'normal';

            $detalle['fecha_consumo_desde'] = $fechaIngreso->format('Y-m-d');
            $detalle['fecha_consumo_hasta'] = $fechaEgreso->format('Y-m-d');

        } else {
            $fechaInicioCalculo = clone $inicioAnio;
            $detalle['periodo_actual'] = 'sin_registro_ingreso';

            // Sin ingreso registrado: año completo (sin filtro de fechas)
            $detalle['fecha_consumo_desde'] = null;
            $detalle['fecha_consumo_hasta'] = null;
        }

        $diasRestantes = ($fechaEgreso < $fechaInicioCalculo)
            ? 0
            : $fechaInicioCalculo->diff($fechaEgreso)->days + 1;

        $presupuestoTramoNormal = $presupuestoDiario * $diasRestantes;

        if ($presupuestoTramoPrueba > 0) {
            $detalle['tramo_normal'] = [
                'dias'         => $diasRestantes,
                'presupuesto'  => round($presupuestoTramoNormal, 2),
                'fecha_inicio' => $fechaInicioCalculo->format('Y-m-d'),
                'fecha_fin'    => $fechaEgreso->format('Y-m-d'),
            ];
        }

        $detalle['tiene_restriccion']    = false;
        $detalle['dias_restantes']       = $diasRestantes;
        $detalle['fecha_inicio_calculo'] = $fechaInicioCalculo->format('Y-m-d');
        $detalle['fecha_fin_calculo']    = $fechaEgreso->format('Y-m-d');

        return $presupuestoTramoPrueba + $presupuestoTramoNormal;
    }
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNCIÓN CORREGIDA #2: _calcularUnPeriodoHistorico
     * ═══════════════════════════════════════════════════════════════════════
     *
     * PROBLEMA:
     *   La fecha fin de restricción se calculaba sin restar 1 día,
     *   inconsistente con _dinamico() donde sí se restaba.
     *   Esto provocaba que en histórico se contara 1 día extra de prueba.
     *
     *   Ejemplo con ingreso 13/ene, prueba 60 días:
     *     ANTES:  fechaFinRestriccion = 14/marzo (61 días contados)
     *     AHORA:  fechaFinRestriccion = 13/marzo (60 días correctos)
     *
     * CAMBIO:
     *   - Se agrega modify("-1 day") después de calcular fechaFinRestriccion
     */
    private function _calcularUnPeriodoHistorico($periodo, $inicioAnio, $finAnio, $diasAnio)
    {
        try {
            $fechaIngreso = new \DateTime($periodo['fecha_ingreso']);
            $fechaEgreso = $periodo['fecha_egreso']
                ? new \DateTime($periodo['fecha_egreso'])
                : clone $finAnio;

            if ($fechaIngreso < $inicioAnio) $fechaIngreso = clone $inicioAnio;
            if ($fechaEgreso > $finAnio) $fechaEgreso = clone $finAnio;

            $diasTrabajados = $fechaIngreso->diff($fechaEgreso)->days + 1;
            $presupuestoDiario = (float) $periodo['monto_diario'];
            $presupuestoPeriodo = 0;

            $detalle = [
                'periodo_id'               => $periodo['idUsuariosControlFechas'],
                'fecha_ingreso'            => $fechaIngreso->format('Y-m-d'),
                'fecha_egreso'             => $fechaEgreso->format('Y-m-d'),
                'dias_trabajados'          => $diasTrabajados,
                'agencia'                  => $periodo['agencia'],
                'puesto'                   => $periodo['puesto'],
                'presupuesto_anual_puesto' => (float) $periodo['monto_anual'],
                'presupuesto_diario'       => round($presupuestoDiario, 2),
                'es_nuevo'                 => (bool) $periodo['es_nuevo']
            ];

            if ($periodo['es_nuevo'] && $periodo['dias_presupuesto'] > 0) {
                $diasRestriccion = $periodo['dias_presupuesto'];
                $porcentaje = $periodo['porcentaje_presupuesto'] / 100;

                // ── CORRECCIÓN: restar 1 día para consistencia con _dinamico ──
                $fechaFinRestriccion = clone $fechaIngreso;
                $fechaFinRestriccion->modify("+{$diasRestriccion} days");
                $fechaFinRestriccion->modify("-1 day"); // ← LÍNEA AGREGADA

                $diasConRestriccion = min($diasRestriccion, $diasTrabajados);
                $presupuestoPrueba = $presupuestoDiario * $diasConRestriccion * $porcentaje;

                $diasNormales = max(0, $diasTrabajados - $diasRestriccion);
                $presupuestoNormal = $presupuestoDiario * $diasNormales;

                $presupuestoPeriodo = $presupuestoPrueba + $presupuestoNormal;

                $detalle['tiene_restriccion']      = true;
                $detalle['dias_restriccion']       = $diasRestriccion;
                $detalle['porcentaje_restriccion'] = $periodo['porcentaje_presupuesto'];
                $detalle['fecha_fin_restriccion']  = $fechaFinRestriccion->format('Y-m-d');
                $detalle['dias_con_restriccion']   = $diasConRestriccion;
                $detalle['presupuesto_prueba']     = round($presupuestoPrueba, 2);
                $detalle['dias_normales']          = $diasNormales;
                $detalle['presupuesto_normal']     = round($presupuestoNormal, 2);
            } else {
                $presupuestoPeriodo = $presupuestoDiario * $diasTrabajados;
                $detalle['tiene_restriccion'] = false;
            }

            $detalle['presupuesto_periodo'] = round($presupuestoPeriodo, 2);
            return $detalle;

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_calcularUnPeriodoHistorico: " . $e->getMessage());
            throw $e;
        }
    }
}
