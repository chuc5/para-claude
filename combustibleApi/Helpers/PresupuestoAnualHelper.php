<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * PresupuestoAnualHelper - Cálculo de presupuesto anual de combustible
 *
 * CAMBIO PRINCIPAL: Usa tarifa diaria variable según el mes:
 *   tarifa_diaria(mes) = monto_mensual / días_del_mes
 *
 * ESCENARIOS:
 * ┌──────────┬────────────────────────────────────────────────────┐
 * │ 1        │ Sin registros UCF → presupuesto mensual completo   │
 * │ 2/3/3b   │ Período activo → calcula según etapa              │
 * │          │ + suma UCFs completados del mismo año              │
 * │ 4        │ Sin período activo pero con históricos en el año   │
 * │          │ → solo días efectivamente activos (sin post-egreso)│
 * │ 5        │ Histórico → suma todos los períodos del año        │
 * └──────────┴────────────────────────────────────────────────────┘
 */
class PresupuestoAnualHelper
{
    private $connect;
    private $idUsuario;
    private $idAgencia;
    private $puesto;

    public function __construct(PDO $connect, $idUsuario, $idAgencia, $puesto)
    {
        $this->connect    = $connect;
        $this->idUsuario  = $idUsuario;
        $this->idAgencia  = $idAgencia;
        $this->puesto     = $puesto;
    }

    // ═══════════════════════════════════════════════════════════════
    // MÉTODO PRINCIPAL
    // ═══════════════════════════════════════════════════════════════

    /**
     * @param int  $anio
     * @param bool $soloProba  Si true y el usuario está en período de prueba activo,
     *                         solo devuelve el presupuesto de ese período de prueba
     *                         (sin sumar períodos anteriores del año).
     *                         Usar true en el flujo de liquidaciones del usuario.
     *                         Usar false (default) en vistas administrativas.
     */
    public function obtener($anio, bool $soloProba = false)
    {
        try {
            $hoy     = new \DateTime();
            $anioHoy = (int) $hoy->format('Y');

            if ($anio != $anioHoy) {
                return $this->_historico($anio);
            }

            $periodoActivo = $this->_buscarPeriodoActivo($anio);

            if ($periodoActivo) {
                $resultado = $this->_dinamico($periodoActivo, $anio);
                if (!$resultado) return null;

                // En modo soloProba + prueba activa: NO sumar períodos anteriores.
                // El usuario solo ve el presupuesto de su período de prueba actual.
                $enPrueba = $resultado['detalle_periodo_actual']['en_periodo_prueba'] ?? false;

                if (!$soloProba || !$enPrueba) {
                    // Fuera de prueba (o vista admin): sumar períodos anteriores del año
                    $extras = $this->_sumarPeriodosAnterioresEnAnio($periodoActivo, $anio);
                    if ($extras > 0) {
                        $resultado['monto_anual'] = round(
                            ($resultado['monto_anual'] ?? 0) + $extras, 2
                        );
                        if (isset($resultado['detalle_periodo_actual']['presupuesto_total_disponible'])) {
                            $resultado['detalle_periodo_actual']['presupuesto_total_disponible'] =
                                $resultado['monto_anual'];
                        }
                    }
                }

                return $resultado;
            }

            // Sin período activo → verificar si hubo períodos en el año
            $todosPeriodos = $this->_buscarTodosLosPeriodos($anio);
            if (!empty($todosPeriodos)) {
                // Solo sumar los días que estuvo activo, sin post-egreso
                return $this->_soloHistoricosAnioActual($todosPeriodos, $anio);
            }

            return $this->_sinRegistros($anio);

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::obtener: " . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ESTRATEGIAS DE CÁLCULO
    // ═══════════════════════════════════════════════════════════════

    private function _dinamico($periodo, $anio)
    {
        try {
            $hoy         = new \DateTime();
            $hoy->setTime(0, 0, 0);
            $inicioAnio  = new \DateTime("$anio-01-01");
            $finAnio     = new \DateTime("$anio-12-31");
            $montoMensual = (float) $periodo['monto_mensual'];

            $fechaIngreso = $periodo['fecha_ingreso']
                ? new \DateTime($periodo['fecha_ingreso'])
                : clone $inicioAnio;

            $fechaEgreso = $periodo['fecha_egreso']
                ? new \DateTime($periodo['fecha_egreso'])
                : clone $finAnio;

            // Detectar período de prueba
            $enPeriodoPrueba = false;
            $fechaFinPrueba  = null;

            if ($periodo['es_nuevo'] && $periodo['dias_presupuesto'] > 0) {
                $fechaFinPrueba = clone $fechaIngreso;
                $fechaFinPrueba->modify("+{$periodo['dias_presupuesto']} days");
                $fechaFinPrueba->modify('-1 day');

                if ($hoy >= $fechaIngreso && $hoy <= $fechaFinPrueba) {
                    $enPeriodoPrueba = true;
                }
            }

            $detalle = [
                'periodo_id'               => $periodo['idUsuariosControlFechas'],
                'fecha_ingreso'            => $fechaIngreso->format('Y-m-d'),
                'fecha_egreso'             => $fechaEgreso->format('Y-m-d'),
                'agencia'                  => $periodo['agencia'],
                'puesto'                   => $periodo['puesto'],
                'presupuesto_mensual'      => round($montoMensual, 2),
                'presupuesto_anual_puesto' => (float) $periodo['monto_anual'],
                'es_nuevo'                 => (bool) $periodo['es_nuevo'],
                'en_periodo_prueba'        => $enPeriodoPrueba,
            ];

            if ($enPeriodoPrueba) {
                $presupuestoTotal = $this->_calcularPeriodoPrueba(
                    $periodo, $montoMensual, $hoy, $fechaIngreso, $fechaFinPrueba, $inicioAnio, $detalle
                );
            } else {
                $presupuestoTotal = $this->_calcularPeriodoNormal(
                    $periodo, $montoMensual, $hoy, $fechaIngreso,
                    $fechaEgreso, $fechaFinPrueba, $inicioAnio, $detalle
                );
            }

            $detalle['presupuesto_total_disponible'] = round($presupuestoTotal, 2);

            return [
                'idPresupuestoGeneral'  => null,
                'agenciaid'             => $periodo['agenciaid'],
                'puestoid'              => $periodo['puestoid'],
                'anio'                  => $anio,
                'monto_mensual'         => $montoMensual,
                'monto_anual'           => $presupuestoTotal,
                'monto_diario'          => $periodo['monto_diario'],
                'agencia'               => $periodo['agencia'],
                'puesto'                => $periodo['puesto'],
                'tiene_periodos'        => true,
                'detalle_periodo_actual' => $detalle,
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_dinamico: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Presupuesto cuando el usuario está EN período de prueba.
     * Calcula desde fechaIngreso hasta fechaFinPrueba con % reducido.
     * La tarifa varía mes a mes: monto_mensual / días_del_mes.
     * Si el trial inició en el año anterior, solo se calcula la porción
     * que cae dentro del año actual (proporcional por día).
     */
    private function _calcularPeriodoPrueba(
        $periodo,
        float $montoMensual,
        \DateTime $hoy,
        \DateTime $fechaIngreso,
        \DateTime $fechaFinPrueba,
        \DateTime $inicioAnio,
        array &$detalle
    ): float {
        $porcentaje = $periodo['porcentaje_presupuesto'] / 100;
        $diasPrueba = $periodo['dias_presupuesto'];

        // Si el trial cruzó el año (ingreso en año anterior), calcular solo la
        // porción dentro del año actual usando tarifa proporcional por día.
        if ($fechaIngreso < $inicioAnio) {
            $presupuestoTotalPrueba = $this->_calcularPorRangoMensual(
                $inicioAnio->format('Y-m-d'),
                $fechaFinPrueba->format('Y-m-d'),
                $montoMensual,
                $porcentaje
            );
        } else {
            $presupuestoTotalPrueba = $this->_calcularPresupuestoPruebaTotal(
                $fechaIngreso->format('Y-m-d'),
                $fechaFinPrueba->format('Y-m-d'),
                $montoMensual,
                $porcentaje
            );
        }

        $diasRestantesPrueba = max(0, (int) $hoy->diff($fechaFinPrueba)->days + 1);

        $detalle['tiene_restriccion']        = true;
        $detalle['dias_restriccion']         = $diasPrueba;
        $detalle['porcentaje_restriccion']   = $periodo['porcentaje_presupuesto'];
        $detalle['fecha_fin_restriccion']    = $fechaFinPrueba->format('Y-m-d');
        $detalle['dias_restantes_prueba']    = $diasRestantesPrueba;
        $detalle['dias_totales_prueba']      = $diasPrueba;
        $detalle['presupuesto_total_prueba'] = round($presupuestoTotalPrueba, 2);
        $detalle['periodo_actual']           = 'prueba';
        $detalle['fecha_consumo_desde']      = $fechaIngreso->format('Y-m-d');
        $detalle['fecha_consumo_hasta']      = $fechaFinPrueba->format('Y-m-d');

        return $presupuestoTotalPrueba;
    }

    /**
     * Presupuesto para período normal, post-prueba o sin prueba.
     * Calcula el tramo de prueba (si existió) + el tramo normal.
     */
    private function _calcularPeriodoNormal(
        $periodo,
        float $montoMensual,
        \DateTime $hoy,
        \DateTime $fechaIngreso,
        \DateTime $fechaEgreso,
        ?\DateTime $fechaFinPrueba,
        \DateTime $inicioAnio,
        array &$detalle
    ): float {
        $fechaInicioCalculo     = null;
        $presupuestoTramoPrueba = 0.0;

        if ($fechaFinPrueba && $fechaFinPrueba < $hoy) {
            $fechaInicioCalculo = (clone $fechaFinPrueba)->modify('+1 day');
            $porcentajePrueba   = $periodo['porcentaje_presupuesto'] / 100;

            // Si el trial inició en el año anterior, solo contabilizar la porción
            // del trial que cae dentro del año actual (tarifa proporcional por día).
            if ($fechaIngreso < $inicioAnio) {
                $presupuestoTramoPrueba = $this->_calcularPorRangoMensual(
                    $inicioAnio->format('Y-m-d'),
                    $fechaFinPrueba->format('Y-m-d'),
                    $montoMensual,
                    $porcentajePrueba
                );
            } else {
                $presupuestoTramoPrueba = $this->_calcularPresupuestoPruebaTotal(
                    $fechaIngreso->format('Y-m-d'),
                    $fechaFinPrueba->format('Y-m-d'),
                    $montoMensual,
                    $porcentajePrueba
                );
            }

            $detalle['periodo_actual']         = 'post_prueba';
            $detalle['fecha_fin_restriccion']  = $fechaFinPrueba->format('Y-m-d');
            $detalle['dias_restriccion']       = $periodo['dias_presupuesto'];
            $detalle['porcentaje_restriccion'] = $periodo['porcentaje_presupuesto'];
            $detalle['tramo_prueba']           = [
                'dias'         => $periodo['dias_presupuesto'],
                'porcentaje'   => $periodo['porcentaje_presupuesto'],
                'presupuesto'  => round($presupuestoTramoPrueba, 2),
                'fecha_inicio' => $fechaIngreso->format('Y-m-d'),
                'fecha_fin'    => $fechaFinPrueba->format('Y-m-d'),
            ];
            $detalle['fecha_consumo_desde'] = $fechaIngreso->format('Y-m-d');
            $detalle['fecha_consumo_hasta'] = $fechaEgreso->format('Y-m-d');

        } elseif ($periodo['fecha_ingreso']) {
            $fechaInicioCalculo             = clone $fechaIngreso;
            $detalle['periodo_actual']      = 'normal';
            $detalle['fecha_consumo_desde'] = $fechaIngreso->format('Y-m-d');
            $detalle['fecha_consumo_hasta'] = $fechaEgreso->format('Y-m-d');
        } else {
            $fechaInicioCalculo             = clone $inicioAnio;
            $detalle['periodo_actual']      = 'sin_registro_ingreso';
            $detalle['fecha_consumo_desde'] = null;
            $detalle['fecha_consumo_hasta'] = null;
        }

        $presupuestoTramoNormal = 0.0;
        if ($fechaEgreso >= $fechaInicioCalculo) {
            $presupuestoTramoNormal = $this->_calcularPorRangoMensual(
                $fechaInicioCalculo->format('Y-m-d'),
                $fechaEgreso->format('Y-m-d'),
                $montoMensual,
                1.0
            );
        }

        $diasRestantes = ($fechaEgreso < $fechaInicioCalculo)
            ? 0
            : (int) $fechaInicioCalculo->diff($fechaEgreso)->days + 1;

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
     * Presupuesto histórico: suma todos los períodos del año pasado.
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

    private function _calcularSumaPeriodos($periodos, $anio)
    {
        try {
            $esBisiesto  = (($anio % 4 == 0 && $anio % 100 != 0) || ($anio % 400 == 0));
            $diasAnio    = $esBisiesto ? 366 : 365;
            $inicioAnio  = new \DateTime("$anio-01-01");
            $finAnio     = new \DateTime("$anio-12-31");

            $presupuestoTotal = 0.0;
            $detallePeriodos  = [];
            $ultimoPeriodo    = end($periodos);

            foreach ($periodos as $periodo) {
                $detalle           = $this->_calcularUnPeriodoHistorico($periodo, $inicioAnio, $finAnio);
                $presupuestoTotal += $detalle['presupuesto_periodo'];
                $detallePeriodos[] = $detalle;
            }

            return [
                'idPresupuestoGeneral' => $ultimoPeriodo['idPresupuestoGeneral'] ?? null,
                'agenciaid'            => $ultimoPeriodo['agenciaid'],
                'puestoid'             => $ultimoPeriodo['puestoid'],
                'anio'                 => $anio,
                'monto_mensual'        => (float) $ultimoPeriodo['monto_mensual'],
                'monto_anual'          => $presupuestoTotal,
                'monto_diario'         => $presupuestoTotal / $diasAnio,
                'agencia'              => $ultimoPeriodo['agencia'],
                'puesto'               => $ultimoPeriodo['puesto'],
                'tiene_periodos'       => true,
                'detalle_periodos'     => $detallePeriodos,
                'es_historico'         => true,
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_calcularSumaPeriodos: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sin período activo pero con UCFs históricos en el año actual.
     * Suma solo los días efectivamente activos; no agrega presupuesto post-egreso.
     *
     * @param array $periodos UCFs del año (ya completados)
     * @param int   $anio
     * @return array|null
     */
    private function _soloHistoricosAnioActual(array $periodos, int $anio): ?array
    {
        try {
            $inicioAnio = new \DateTime("$anio-01-01");
            $finAnio    = new \DateTime("$anio-12-31");

            $presupuestoTotal = 0.0;
            $detallePeriodos  = [];
            $ultimoPeriodo    = end($periodos);
            $primerPeriodo    = reset($periodos);

            foreach ($periodos as $periodo) {
                $detalle           = $this->_calcularUnPeriodoHistorico($periodo, $inicioAnio, $finAnio);
                $presupuestoTotal += $detalle['presupuesto_periodo'];
                $detallePeriodos[] = $detalle;
            }

            $esBisiesto = (($anio % 4 === 0 && $anio % 100 !== 0) || ($anio % 400 === 0));
            $diasAnio   = $esBisiesto ? 366 : 365;

            // fecha_consumo_desde: mayor entre inicio de año y fecha_ingreso del primer UCF
            $fechaConsumoDesde = max($primerPeriodo['fecha_ingreso'], "$anio-01-01");
            $fechaConsumoHasta = $ultimoPeriodo['fecha_egreso'];   // ya egresó, tiene fecha

            return [
                'idPresupuestoGeneral'   => null,
                'agenciaid'              => $ultimoPeriodo['agenciaid'],
                'puestoid'               => $ultimoPeriodo['puestoid'],
                'anio'                   => $anio,
                'monto_mensual'          => (float) $ultimoPeriodo['monto_mensual'],
                'monto_anual'            => round($presupuestoTotal, 2),
                'monto_diario'           => round($presupuestoTotal / $diasAnio, 6),
                'agencia'                => $ultimoPeriodo['agencia'],
                'puesto'                 => $ultimoPeriodo['puesto'],
                'tiene_periodos'         => true,
                'detalle_periodo_actual' => [
                    'periodo_actual'               => 'sin_periodo_activo',
                    'presupuesto_total_disponible' => round($presupuestoTotal, 2),
                    'fecha_consumo_desde'          => $fechaConsumoDesde,
                    'fecha_consumo_hasta'          => $fechaConsumoHasta,
                    'en_periodo_prueba'            => false,
                    'agencia'                      => $ultimoPeriodo['agencia'],
                    'puesto'                       => $ultimoPeriodo['puesto'],
                ],
                'detalle_periodos'       => $detallePeriodos,
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_soloHistoricosAnioActual: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Suma el presupuesto de todos los UCFs completados en el año,
     * excluyendo el período activo (que ya fue calculado en _dinamico).
     *
     * @param array $periodoActivo UCF activo actual
     * @param int   $anio
     * @return float Suma del presupuesto de períodos anteriores
     */
    private function _sumarPeriodosAnterioresEnAnio(array $periodoActivo, int $anio): float
    {
        try {
            $todos      = $this->_buscarTodosLosPeriodos($anio);
            $inicioAnio = new \DateTime("$anio-01-01");
            $finAnio    = new \DateTime("$anio-12-31");
            $total      = 0.0;

            foreach ($todos as $p) {
                // Saltar el período activo, ya fue calculado en _dinamico
                if ((int) $p['idUsuariosControlFechas'] === (int) $periodoActivo['idUsuariosControlFechas']) {
                    continue;
                }

                $detalle = $this->_calcularUnPeriodoHistorico($p, $inicioAnio, $finAnio);
                $total  += $detalle['presupuesto_periodo'];
            }

            return $total;

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_sumarPeriodosAnterioresEnAnio: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * NUEVO MÉTODO — Calcula el presupuesto total del período de prueba.
     *
     * Lógica del documento Excel:
     * - Cada mes COMPLETO dentro del período = prueba_mensual (monto_mensual × porcentaje)
     * - Los meses PARCIALES (inicio y/o fin) juntos = 1 × prueba_mensual
     *
     * Esta fórmula NO usa tarifa diaria variable; es intencional.
     *
     * Ejemplo: Feb 15 → Abr 14  (50%, Q1,000 mensual)
     *   - Feb parcial + Abr parcial = 1 × Q500
     *   - Mar completo              = 1 × Q500
     *   - Total                     = Q1,000
     *
     * @param string $fechaInicio  Y-m-d  (fecha_ingreso)
     * @param string $fechaFin     Y-m-d  (fecha_fin_prueba)
     * @param float  $montoMensual
     * @param float  $porcentaje   Factor 0.0–1.0
     * @return float
     */
    private function _calcularPresupuestoPruebaTotal(
        string $fechaInicio,
        string $fechaFin,
        float  $montoMensual,
        float  $porcentaje
    ): float {
        if ($montoMensual <= 0 || $porcentaje <= 0) return 0.0;

        $pruebaMensual = $montoMensual * $porcentaje;
        $inicio        = new \DateTime($fechaInicio);
        $fin           = new \DateTime($fechaFin);

        if ($inicio > $fin) return 0.0;

        $totalMesesCompletos = 0;
        $hayMesesParciales   = false;

        $cursor = new \DateTime($inicio->format('Y-m-01'));

        while ($cursor <= $fin) {
            $diasDelMes = (int) $cursor->format('t');
            $primerDia  = clone $cursor;
            $ultimoDia  = new \DateTime(
                $cursor->format('Y-m-') . str_pad($diasDelMes, 2, '0', STR_PAD_LEFT)
            );

            if ($primerDia >= $inicio && $ultimoDia <= $fin) {
                $totalMesesCompletos++;
            } else {
                $hayMesesParciales = true;
            }

            $cursor->modify('+1 month');
        }

        return ($totalMesesCompletos + ($hayMesesParciales ? 1 : 0)) * $pruebaMensual;
    }

    private function _sinRegistros($anio)
    {
        try {
            $sql = "SELECT
                        pg.idPresupuestoGeneral, pg.agenciaid, pg.puestoid,
                        pg.anio, pg.monto_mensual, pg.monto_anual, pg.monto_diario,
                        a.nombre AS agencia, p.nombre AS puesto
                    FROM apoyo_combustibles.presupuestogeneral pg
                    LEFT JOIN dbintranet.agencia a ON pg.agenciaid = a.idAgencia
                    LEFT JOIN dbintranet.puesto  p ON pg.puestoid  = p.idPuesto
                    WHERE pg.activo    = 1
                        AND pg.anio      = ?
                        AND pg.agenciaid = ?
                        AND pg.puestoid  = ?
                    LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$anio, $this->idAgencia, $this->puesto]);
            $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$presupuesto) return null;

            return [
                'idPresupuestoGeneral'  => $presupuesto['idPresupuestoGeneral'],
                'agenciaid'             => $presupuesto['agenciaid'],
                'puestoid'              => $presupuesto['puestoid'],
                'anio'                  => $presupuesto['anio'],
                'monto_mensual'         => (float) $presupuesto['monto_mensual'],
                'monto_anual'           => (float) $presupuesto['monto_anual'],
                'monto_diario'          => (float) $presupuesto['monto_diario'],
                'agencia'               => $presupuesto['agencia'],
                'puesto'                => $presupuesto['puesto'],
                'tiene_periodos'        => false,
                'detalle_periodo_actual' => [
                    'periodo_actual'               => 'sin_registros',
                    'presupuesto_total_disponible' => (float) $presupuesto['monto_anual'],
                ],
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_sinRegistros: " . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CÁLCULO HISTÓRICO POR PERÍODO
    // ═══════════════════════════════════════════════════════════════

    private function _calcularUnPeriodoHistorico($periodo, \DateTime $inicioAnio, \DateTime $finAnio): array
    {
        try {
            $fechaIngreso = new \DateTime($periodo['fecha_ingreso']);
            $fechaEgreso  = $periodo['fecha_egreso']
                ? new \DateTime($periodo['fecha_egreso'])
                : clone $finAnio;

            if ($fechaIngreso < $inicioAnio) $fechaIngreso = clone $inicioAnio;
            if ($fechaEgreso  > $finAnio)    $fechaEgreso  = clone $finAnio;

            $montoMensual   = (float) $periodo['monto_mensual'];
            $diasTrabajados = (int) $fechaIngreso->diff($fechaEgreso)->days + 1;

            $detalle = [
                'periodo_id'               => $periodo['idUsuariosControlFechas'],
                'fecha_ingreso'            => $fechaIngreso->format('Y-m-d'),
                'fecha_egreso'             => $fechaEgreso->format('Y-m-d'),
                'dias_trabajados'          => $diasTrabajados,
                'agencia'                  => $periodo['agencia'],
                'puesto'                   => $periodo['puesto'],
                'presupuesto_mensual'      => round($montoMensual, 2),
                'presupuesto_anual_puesto' => (float) $periodo['monto_anual'],
                'es_nuevo'                 => (bool) $periodo['es_nuevo'],
            ];

            if ($periodo['es_nuevo'] && $periodo['dias_presupuesto'] > 0) {
                $diasRestriccion     = $periodo['dias_presupuesto'];
                $porcentaje          = $periodo['porcentaje_presupuesto'] / 100;

                $fechaFinRestriccion = (clone $fechaIngreso)
                    ->modify("+{$diasRestriccion} days")
                    ->modify('-1 day');

                $strFinPrueba = $fechaFinRestriccion->format('Y-m-d');
                $strIniNormal = (clone $fechaFinRestriccion)->modify('+1 day')->format('Y-m-d');

                // Si el trial termina después del fin del año histórico, solo
                // calcular la porción del trial dentro de ese año (proporcional
                // por día). No hay tramo normal: la prueba continúa en el año siguiente.
                if ($fechaFinRestriccion > $finAnio) {
                    $presupuestoPrueba = $this->_calcularPorRangoMensual(
                        $fechaIngreso->format('Y-m-d'),
                        $finAnio->format('Y-m-d'),
                        $montoMensual,
                        $porcentaje
                    );
                    $presupuestoNormal = 0.0;
                } else {
                    // Trial completo dentro del año: fórmula original (meses completos/parciales)
                    $presupuestoPrueba = $this->_calcularPresupuestoPruebaTotal(
                        $fechaIngreso->format('Y-m-d'),
                        $strFinPrueba,
                        $montoMensual,
                        $porcentaje
                    );

                    // Tramo normal: tarifa variable
                    $presupuestoNormal = 0.0;
                    $strNormalInicio   = new \DateTime($strIniNormal);
                    if ($strNormalInicio <= $fechaEgreso) {
                        $presupuestoNormal = $this->_calcularPorRangoMensual(
                            $strIniNormal,
                            $fechaEgreso->format('Y-m-d'),
                            $montoMensual,
                            1.0
                        );
                    }
                }

                $presupuestoPeriodo = $presupuestoPrueba + $presupuestoNormal;

                $detalle['tiene_restriccion']      = true;
                $detalle['dias_restriccion']       = $diasRestriccion;
                $detalle['porcentaje_restriccion'] = $periodo['porcentaje_presupuesto'];
                $detalle['fecha_fin_restriccion']  = $strFinPrueba;
                $detalle['presupuesto_prueba']     = round($presupuestoPrueba, 2);
                $detalle['presupuesto_normal']     = round($presupuestoNormal, 2);

            } else {
                // Sin prueba: tarifa variable todo el período
                $presupuestoPeriodo = $this->_calcularPorRangoMensual(
                    $fechaIngreso->format('Y-m-d'),
                    $fechaEgreso->format('Y-m-d'),
                    $montoMensual
                );
                $detalle['tiene_restriccion'] = false;
            }

            $detalle['presupuesto_periodo'] = round($presupuestoPeriodo, 2);
            return $detalle;

        } catch (\Exception $e) {
            error_log("Error en PresupuestoAnualHelper::_calcularUnPeriodoHistorico: " . $e->getMessage());
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CONSULTAS A BASE DE DATOS
    // ═══════════════════════════════════════════════════════════════

    private function _buscarPeriodoActivo($anio)
    {
        try {
            $sql = "SELECT
                        ucf.idUsuariosControlFechas, ucf.usuarioid, ucf.agenciaid,
                        ucf.puestoid, ucf.fecha_ingreso, ucf.fecha_egreso,
                        ucf.es_nuevo, ucf.porcentaje_presupuesto, ucf.dias_presupuesto,
                        pg.monto_mensual, pg.monto_anual, pg.monto_diario,
                        a.nombre AS agencia, p.nombre AS puesto
                    FROM apoyo_combustibles.usuarioscontrolfechas ucf
                    INNER JOIN apoyo_combustibles.presupuestogeneral pg
                        ON  pg.agenciaid = ucf.agenciaid
                        AND pg.puestoid  = ucf.puestoid
                        AND pg.anio      = ? AND pg.activo = 1
                    LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
                    LEFT JOIN dbintranet.puesto  p ON ucf.puestoid  = p.idPuesto
                    WHERE ucf.usuarioid = ?
                        AND ucf.activo  = 1
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

    private function _buscarUltimoPeriodoVencido($anio)
    {
        try {
            $sql = "SELECT
                        ucf.idUsuariosControlFechas, ucf.usuarioid, ucf.agenciaid,
                        ucf.puestoid, ucf.fecha_ingreso, ucf.fecha_egreso,
                        ucf.es_nuevo, ucf.porcentaje_presupuesto, ucf.dias_presupuesto,
                        pg.monto_mensual, pg.monto_anual, pg.monto_diario,
                        a.nombre AS agencia, p.nombre AS puesto
                    FROM apoyo_combustibles.usuarioscontrolfechas ucf
                    INNER JOIN apoyo_combustibles.presupuestogeneral pg
                        ON  pg.agenciaid = ucf.agenciaid
                        AND pg.puestoid  = ucf.puestoid
                        AND pg.anio      = ? AND pg.activo = 1
                    LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
                    LEFT JOIN dbintranet.puesto  p ON ucf.puestoid  = p.idPuesto
                    WHERE ucf.usuarioid = ?
                        AND ucf.activo  = 1
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

    private function _buscarTodosLosPeriodos($anio)
    {
        try {
            $sql = "SELECT
                        ucf.idUsuariosControlFechas, ucf.usuarioid, ucf.agenciaid,
                        ucf.puestoid, ucf.fecha_ingreso, ucf.fecha_egreso,
                        ucf.es_nuevo, ucf.porcentaje_presupuesto, ucf.dias_presupuesto,
                        pg.monto_mensual, pg.monto_anual, pg.monto_diario,
                        a.nombre AS agencia, p.nombre AS puesto
                    FROM apoyo_combustibles.usuarioscontrolfechas ucf
                    INNER JOIN apoyo_combustibles.presupuestogeneral pg
                        ON  pg.agenciaid = ucf.agenciaid
                        AND pg.puestoid  = ucf.puestoid
                        AND pg.anio      = ? AND pg.activo = 1
                    LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
                    LEFT JOIN dbintranet.puesto  p ON ucf.puestoid  = p.idPuesto
                    WHERE ucf.usuarioid = ?
                        AND ucf.activo  = 1
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

    // ═══════════════════════════════════════════════════════════════
    // HELPER: CÁLCULO MENSUAL VARIABLE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcula presupuesto para un rango con tarifa diaria variable por mes.
     * Formula: para cada mes → (monto_mensual / días_del_mes) × días_en_ese_mes × porcentaje
     *
     * @param string $fechaInicio Y-m-d
     * @param string $fechaFin    Y-m-d
     * @param float  $montoMensual
     * @param float  $porcentaje  Factor 0.0–1.0 (default 1.0 = 100%)
     * @return float
     */
    private function _calcularPorRangoMensual(
        string $fechaInicio,
        string $fechaFin,
        float  $montoMensual,
        float  $porcentaje = 1.0
    ): float {
        if ($montoMensual <= 0 || $porcentaje <= 0) return 0.0;

        $inicio = new \DateTime($fechaInicio);
        $fin    = new \DateTime($fechaFin);

        if ($inicio > $fin) return 0.0;

        $total  = 0.0;
        $cursor = new \DateTime($inicio->format('Y-m-01'));

        while ($cursor <= $fin) {
            $diasDelMes   = (int) $cursor->format('t');
            $tarifaDiaria = $montoMensual / $diasDelMes;

            $iniEf = ($cursor < $inicio) ? clone $inicio : clone $cursor;

            $finMes = new \DateTime(
                $cursor->format('Y-m-') . str_pad($diasDelMes, 2, '0', STR_PAD_LEFT)
            );

            $finEf = ($finMes < $fin) ? clone $finMes : clone $fin;

            $dias = (int) $iniEf->diff($finEf)->days + 1;

            if ($dias > 0) {
                $total += $tarifaDiaria * $dias * $porcentaje;
            }

            $cursor->modify('+1 month');
        }

        return $total;
    }
}