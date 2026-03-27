<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * PresupuestoMensualHelper - Cálculo de presupuesto mensual de combustible
 *
 * Calcula el presupuesto disponible del mes actual considerando
 * períodos de prueba, ingresos, egresos y sus combinaciones.
 *
 * CASOS MANEJADOS:
 * ┌──────┬─────────────────────────────────────────────────────────────┐
 * │ Caso │ Descripción                                               │
 * ├──────┼─────────────────────────────────────────────────────────────┤
 * │ 1a   │ En prueba que termina este mes                             │
 * │ 1b   │ En prueba que continúa al siguiente mes (con/sin egreso)   │
 * │ 2    │ Ya pasó la prueba este mes (con/sin egreso)                │
 * │ 3    │ Ingreso este mes sin restricción (con/sin egreso)          │
 * │ 4    │ Sin registro / Post-egreso / Mes completo normal           │
 * └──────┴─────────────────────────────────────────────────────────────┘
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class PresupuestoMensualHelper
{
    /** @var PDO Conexión a base de datos */
    private $connect;

    /** @var int ID del usuario actual */
    private $idUsuario;

    /** @var ConsumoHelper */
    private $consumoHelper;

    /**
     * @param PDO           $connect       Conexión PDO activa (viene del controller)
     * @param int           $idUsuario     ID del usuario
     * @param ConsumoHelper $consumoHelper Instancia del helper de consumo
     */
    public function __construct(PDO $connect, $idUsuario, ConsumoHelper $consumoHelper)
    {
        $this->connect = $connect;
        $this->idUsuario = $idUsuario;
        $this->consumoHelper = $consumoHelper;
    }

    // ════════════════════════════════════════════════════════════════════
    // MÉTODO PRINCIPAL
    // ════════════════════════════════════════════════════════════════════

    /**
     * Calcula el presupuesto mensual disponible
     *
     * @param array $presupuesto Datos del presupuesto anual (de PresupuestoAnualHelper)
     * @return array|null Datos del presupuesto mensual con consumo y disponible
     */
    public function calcular($presupuesto)
    {
        try {
            $contexto = $this->_prepararContextoMes($presupuesto);
            $resultado = $this->_resolverCaso($presupuesto, $contexto);
            $consumido = $this->consumoHelper->obtenerConsumoMensual();

            return [
                'dias_transcurridos'    => $resultado['dias_transcurridos'],
                'presupuesto_calculado' => round($resultado['presupuesto'], 2),
                'consumido'             => round($consumido, 2),
                'disponible'            => round($resultado['presupuesto'] - $consumido, 2),
                'detalle_caso'          => $resultado['caso'],
                'tiene_egreso_este_mes' => $contexto['tiene_egreso_este_mes'],
                'fecha_fin_calculo'     => $contexto['fecha_fin_calculo_mes']->format('Y-m-d')
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoMensualHelper::calcular: " . $e->getMessage());
            return null;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // PREPARACIÓN DE CONTEXTO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Extrae y prepara todas las variables del mes necesarias para el cálculo
     *
     * @param array $presupuesto Datos del presupuesto anual
     * @return array Contexto completo del mes
     */
    private function _prepararContextoMes($presupuesto)
    {
        $hoy = new \DateTime();
        $primerDiaMes = new \DateTime($hoy->format('Y-m-01'));
        $ultimoDiaMes = new \DateTime($hoy->format('Y-m-t'));
        $diasDelMes = (int) $hoy->format('t');
        $mesActual = $hoy->format('Y-m');

        $contexto = [
            'hoy'                    => $hoy,
            'primer_dia_mes'         => $primerDiaMes,
            'ultimo_dia_mes'         => $ultimoDiaMes,
            'dias_del_mes'           => $diasDelMes,
            'fecha_fin_calculo_mes'  => clone $ultimoDiaMes,
            'en_periodo_prueba'      => false,
            'tuvo_prueba_este_mes'   => false,
            'tiene_ingreso_este_mes' => false,
            'tiene_egreso_este_mes'  => false,
            'porcentaje_presupuesto' => 1.0,
            'fecha_fin_prueba'       => null,
            'fecha_ingreso'          => null,
            'fecha_egreso'           => null,
            'dias_prueba'            => 0,
        ];

        if (!isset($presupuesto['detalle_periodo_actual'])) {
            return $contexto;
        }

        $detalle = $presupuesto['detalle_periodo_actual'];
        $contexto['en_periodo_prueba'] = $detalle['en_periodo_prueba'] ?? false;

        // Verificar ingreso
        if (isset($detalle['fecha_ingreso'])) {
            $contexto['fecha_ingreso'] = new \DateTime($detalle['fecha_ingreso']);
            if ($contexto['fecha_ingreso']->format('Y-m') === $mesActual) {
                $contexto['tiene_ingreso_este_mes'] = true;
            }
        }

        // Verificar egreso
        if (isset($detalle['fecha_egreso'])) {
            $contexto['fecha_egreso'] = new \DateTime($detalle['fecha_egreso']);
            if ($contexto['fecha_egreso']->format('Y-m') === $mesActual) {
                $contexto['tiene_egreso_este_mes'] = true;
                $contexto['fecha_fin_calculo_mes'] = clone $contexto['fecha_egreso'];
            }
        }

        // Verificar período de prueba
        if (isset($detalle['fecha_fin_restriccion'])) {
            $contexto['fecha_fin_prueba'] = new \DateTime($detalle['fecha_fin_restriccion']);
            if ($contexto['fecha_fin_prueba']->format('Y-m') === $mesActual) {
                $contexto['tuvo_prueba_este_mes'] = true;
                $contexto['porcentaje_presupuesto'] = ($detalle['porcentaje_restriccion'] ?? 100) / 100;
                $contexto['dias_prueba'] = $detalle['dias_restriccion'] ?? 0;
            }
        }

        return $contexto;
    }

    // ════════════════════════════════════════════════════════════════════
    // RESOLUCIÓN DE CASOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Determina qué caso aplica y delega el cálculo
     *
     * @param array $presupuesto Datos del presupuesto anual
     * @param array $contexto    Contexto del mes
     * @return array ['presupuesto' => float, 'dias_transcurridos' => int, 'caso' => string]
     */
    private function _resolverCaso($presupuesto, $contexto)
    {
        $detalle = $presupuesto['detalle_periodo_actual'] ?? null;

        // CASO 1: Actualmente en período de prueba
        if ($contexto['en_periodo_prueba'] && $contexto['fecha_fin_prueba']) {
            return $this->_caso1_EnPrueba($presupuesto, $contexto);
        }

        // CASO 2: Ya pasó la prueba ESTE mes
        if ($contexto['tuvo_prueba_este_mes'] && $contexto['fecha_fin_prueba']) {
            return $this->_caso2_PostPruebaEsteMes($presupuesto, $contexto);
        }

        // CASO 3: Ingreso este mes sin restricción
        if ($contexto['tiene_ingreso_este_mes'] && $contexto['fecha_ingreso']) {
            return $this->_caso3_IngresoEsteMes($presupuesto, $contexto);
        }

        // CASO 4: Normal / Post-egreso
        return $this->_caso4_Normal($presupuesto, $contexto, $detalle);
    }

    /**
     * CASO 1: Usuario actualmente en período de prueba
     *
     * Sub-caso 1a: La prueba termina este mes
     * Sub-caso 1b: La prueba continúa (con/sin egreso)
     */
    private function _caso1_EnPrueba($presupuesto, $contexto)
    {
        $montoDiario = $presupuesto['monto_diario'];
        $detalle = $presupuesto['detalle_periodo_actual'] ?? [];
        $porcentaje = ($detalle['porcentaje_restriccion'] ?? 100) / 100;

        // 1a: Prueba termina este mes → SOLO dar presupuesto de prueba en este mes
        if ($contexto['tuvo_prueba_este_mes']) {

            $inicioEfectivo = ($contexto['tiene_ingreso_este_mes']
                && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes'])
                ? clone $contexto['fecha_ingreso']
                : clone $contexto['primer_dia_mes'];

            // Si hay egreso antes del fin de prueba, usar fecha de egreso
            $finEfectivo = ($contexto['tiene_egreso_este_mes']
                && $contexto['fecha_egreso'] < $contexto['fecha_fin_prueba'])
                ? clone $contexto['fecha_egreso']
                : clone $contexto['fecha_fin_prueba'];

            $diasPruebaEnMes = $inicioEfectivo->diff($finEfectivo)->days + 1;
            $total = $montoDiario * $diasPruebaEnMes * $porcentaje;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $diasPruebaEnMes,
                'caso'               => 'Caso 1a: En prueba (solo período restringido del mes)'
                    . ($contexto['tiene_egreso_este_mes'] ? ' con egreso' : '')
            ];
        }

        // 1b con egreso (prueba NO termina este mes)
        if ($contexto['tiene_egreso_este_mes']) {
            $inicioEfectivo = ($contexto['tiene_ingreso_este_mes']
                && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes'])
                ? clone $contexto['fecha_ingreso']
                : clone $contexto['primer_dia_mes'];

            $diasHastaEgreso = $inicioEfectivo->diff($contexto['fecha_egreso'])->days + 1;
            $total = $montoDiario * $diasHastaEgreso * $porcentaje;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $diasHastaEgreso,
                'caso'               => 'Caso 1b: En prueba con egreso este mes'
            ];
        }

        // 1b sin egreso → mes completo con restricción
        $dias = $contexto['dias_del_mes'];
        $total = $montoDiario * $dias * $porcentaje;

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $dias,
            'caso'               => 'Caso 1b: En prueba que continúa siguiente mes'
        ];
    }

    /**
     * CASO 2: El período de prueba terminó ESTE mes
     *
     * Combina presupuesto con restricción + presupuesto sin restricción
     */
    private function _caso2_PostPruebaEsteMes($presupuesto, $contexto)
    {
        $montoDiario = $presupuesto['monto_diario'];
        $porcentaje = $contexto['porcentaje_presupuesto'];

        // ═══ Calcular días de prueba DENTRO de este mes ═══
        $inicioEfectivo = ($contexto['tiene_ingreso_este_mes']
            && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes'])
            ? clone $contexto['fecha_ingreso']
            : clone $contexto['primer_dia_mes'];

        $diasPruebaEnMes = $inicioEfectivo->diff($contexto['fecha_fin_prueba'])->days + 1;
        $presupuestoPrueba = $montoDiario * $diasPruebaEnMes * $porcentaje;

        $diaSiguientePrueba = clone $contexto['fecha_fin_prueba'];
        $diaSiguientePrueba->modify('+1 day');

        // Con egreso este mes
        if ($contexto['tiene_egreso_este_mes']) {

            // Egreso ANTES o igual al fin de prueba
            if ($contexto['fecha_egreso'] <= $contexto['fecha_fin_prueba']) {
                $diasHastaEgreso = $inicioEfectivo->diff($contexto['fecha_egreso'])->days + 1;
                $total = $montoDiario * $diasHastaEgreso * $porcentaje;

                return [
                    'presupuesto'        => $total,
                    'dias_transcurridos' => $diasHastaEgreso,
                    'caso'               => 'Caso 2: Egreso durante periodo de prueba'
                ];
            }

            // Egreso DESPUÉS del fin de prueba
            $diasDespues = $diaSiguientePrueba->diff($contexto['fecha_egreso'])->days + 1;
            $presupuestoDespues = $montoDiario * $diasDespues;
            $total = $presupuestoPrueba + $presupuestoDespues;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $diasPruebaEnMes + $diasDespues,
                'caso'               => 'Caso 2: Pasó prueba con egreso este mes'
            ];
        }

        // Sin egreso → hasta fin de mes
        $diasDespues = $diaSiguientePrueba->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
        $presupuestoDespues = $montoDiario * $diasDespues;
        $total = $presupuestoPrueba + $presupuestoDespues;

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $diasPruebaEnMes + $diasDespues,
            'caso'               => 'Caso 2: Pasó prueba este mes sin egreso'
        ];
    }

    /**
     * CASO 3: Ingreso este mes sin restricción
     */
    private function _caso3_IngresoEsteMes($presupuesto, $contexto)
    {
        $montoDiario = $presupuesto['monto_diario'];

        // Con egreso este mes
        if ($contexto['tiene_egreso_este_mes']) {
            $dias = $contexto['fecha_ingreso']->diff($contexto['fecha_egreso'])->days + 1;
            $total = $montoDiario * $dias;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 3: Ingreso y egreso este mes sin restricción'
            ];
        }

        // Sin egreso → hasta fin de mes
        $diasDesdeIngreso = $contexto['fecha_ingreso']->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
        $total = $montoDiario * $diasDesdeIngreso;

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $contexto['dias_del_mes'],
            'caso'               => 'Caso 3: Ingreso este mes sin restricción ni egreso'
        ];
    }

    /**
     * CASO 4: Sin registro especial, post-egreso o mes completo normal
     */
    private function _caso4_Normal($presupuesto, $contexto, $detalle)
    {
        $montoDiario = $presupuesto['monto_diario'];

        // Verificar si hay inicio post-egreso en este mes
        $fechaInicioPostEgreso = null;
        $inicioEsEsteMes = false;

        if (isset($detalle['periodo_actual'])
            && $detalle['periodo_actual'] === 'post_egreso'
            && isset($detalle['fecha_inicio_calculo'])
        ) {
            $fechaInicioPostEgreso = new \DateTime($detalle['fecha_inicio_calculo']);
            $inicioEsEsteMes = ($fechaInicioPostEgreso->format('Y-m') === $contexto['hoy']->format('Y-m'));
        }

        // Con egreso este mes
        if ($contexto['tiene_egreso_este_mes']) {
            $fechaBase = ($inicioEsEsteMes && $fechaInicioPostEgreso)
                ? $fechaInicioPostEgreso
                : $contexto['primer_dia_mes'];

            $dias = $fechaBase->diff($contexto['fecha_egreso'])->days + 1;
            $total = $montoDiario * $dias;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 4: Post-egreso con egreso este mes'
            ];
        }

        // Post-egreso con inicio este mes
        if ($inicioEsEsteMes && $fechaInicioPostEgreso) {
            $dias = $fechaInicioPostEgreso->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
            $total = $montoDiario * $dias;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 4: Post-egreso con inicio en este mes'
            ];
        }

        // Mes completo normal
        $dias = $contexto['dias_del_mes'];
        $total = $montoDiario * $dias;

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $dias,
            'caso'               => 'Caso 4: Sin registro o registro anterior sin egreso'
        ];
    }
}
