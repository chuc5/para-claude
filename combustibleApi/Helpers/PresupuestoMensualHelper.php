<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * PresupuestoMensualHelper - Cálculo de presupuesto mensual de combustible
 *
 * CAMBIO: La tarifa diaria ahora es monto_mensual / días_del_mes_actual,
 * en lugar del monto_diario fijo (monto_anual/365) anterior.
 * Esto da una tarifa exacta para cada mes: 28, 29, 30 o 31 días.
 */
class PresupuestoMensualHelper
{
    private $connect;
    private $idUsuario;
    private $consumoHelper;

    public function __construct(PDO $connect, $idUsuario, ConsumoHelper $consumoHelper)
    {
        $this->connect       = $connect;
        $this->idUsuario     = $idUsuario;
        $this->consumoHelper = $consumoHelper;
    }

    // ═══════════════════════════════════════════════════════════════
    // MÉTODO PRINCIPAL
    // ═══════════════════════════════════════════════════════════════

    public function calcular($presupuesto)
    {
        try {
            $contexto  = $this->_prepararContextoMes($presupuesto);
            $resultado = $this->_resolverCaso($presupuesto, $contexto);
            $consumido = $this->consumoHelper->obtenerConsumoMensual();

            return [
                'dias_transcurridos'    => $resultado['dias_transcurridos'],
                'presupuesto_calculado' => round($resultado['presupuesto'], 2),
                'consumido'             => round($consumido, 2),
                'disponible'            => round($resultado['presupuesto'] - $consumido, 2),
                'detalle_caso'          => $resultado['caso'],
                'tiene_egreso_este_mes' => $contexto['tiene_egreso_este_mes'],
                'fecha_fin_calculo'     => $contexto['fecha_fin_calculo_mes']->format('Y-m-d'),
            ];

        } catch (Exception $e) {
            error_log("Error en PresupuestoMensualHelper::calcular: " . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // PREPARACIÓN DE CONTEXTO
    // ═══════════════════════════════════════════════════════════════

    private function _prepararContextoMes($presupuesto)
    {
        $hoy          = new \DateTime();
        $primerDiaMes = new \DateTime($hoy->format('Y-m-01'));
        $ultimoDiaMes = new \DateTime($hoy->format('Y-m-t'));
        $diasDelMes   = (int) $hoy->format('t');
        $mesActual    = $hoy->format('Y-m');

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

        if (isset($detalle['fecha_ingreso'])) {
            $contexto['fecha_ingreso'] = new \DateTime($detalle['fecha_ingreso']);
            if ($contexto['fecha_ingreso']->format('Y-m') === $mesActual) {
                $contexto['tiene_ingreso_este_mes'] = true;
            }
        }

        if (isset($detalle['fecha_egreso'])) {
            $contexto['fecha_egreso'] = new \DateTime($detalle['fecha_egreso']);
            if ($contexto['fecha_egreso']->format('Y-m') === $mesActual) {
                $contexto['tiene_egreso_este_mes']  = true;
                $contexto['fecha_fin_calculo_mes']  = clone $contexto['fecha_egreso'];
            }
        }

        if (isset($detalle['fecha_fin_restriccion'])) {
            $contexto['fecha_fin_prueba'] = new \DateTime($detalle['fecha_fin_restriccion']);
            if ($contexto['fecha_fin_prueba']->format('Y-m') === $mesActual) {
                $contexto['tuvo_prueba_este_mes'] = true;
                $contexto['dias_prueba']          = $detalle['dias_restriccion'] ?? 0;
            }
        }

        // El porcentaje aplica siempre que esté en prueba O que la prueba haya
        // terminado este mes — no solo en el segundo caso.
        if ($contexto['en_periodo_prueba'] || $contexto['tuvo_prueba_este_mes']) {
            $contexto['porcentaje_presupuesto'] = ($detalle['porcentaje_restriccion'] ?? 100) / 100;
        }

        return $contexto;
    }

    // ═══════════════════════════════════════════════════════════════
    // RESOLUCIÓN DE CASOS
    // ═══════════════════════════════════════════════════════════════

    private function _resolverCaso($presupuesto, $contexto)
    {
        $detalle = $presupuesto['detalle_periodo_actual'] ?? null;

        if ($contexto['en_periodo_prueba'] && $contexto['fecha_fin_prueba']) {
            return $this->_caso1_EnPrueba($presupuesto, $contexto);
        }
        if ($contexto['tuvo_prueba_este_mes'] && $contexto['fecha_fin_prueba']) {
            return $this->_caso2_PostPruebaEsteMes($presupuesto, $contexto);
        }
        if ($contexto['tiene_ingreso_este_mes'] && $contexto['fecha_ingreso']) {
            return $this->_caso3_IngresoEsteMes($presupuesto, $contexto);
        }
        return $this->_caso4_Normal($presupuesto, $contexto, $detalle);
    }

    /**
     * CASO 1: Usuario actualmente en período de prueba.
     * Tarifa = monto_mensual / días_del_mes × porcentaje
     */
    private function _caso1_EnPrueba($presupuesto, $contexto)
    {
        // Tarifa diaria del mes actual con % de prueba aplicado
        $montoDiario = ($presupuesto['monto_mensual'] / $contexto['dias_del_mes'])
            * $contexto['porcentaje_presupuesto'];

        // 1a: prueba termina este mes
        if ($contexto['tuvo_prueba_este_mes']) {
            $inicioEfectivo = ($contexto['tiene_ingreso_este_mes']
                && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes'])
                ? clone $contexto['fecha_ingreso']
                : clone $contexto['primer_dia_mes'];

            $finEfectivo = ($contexto['tiene_egreso_este_mes']
                && $contexto['fecha_egreso'] < $contexto['fecha_fin_prueba'])
                ? clone $contexto['fecha_egreso']
                : clone $contexto['fecha_fin_prueba'];

            $dias  = (int) $inicioEfectivo->diff($finEfectivo)->days + 1;
            $total = ($presupuesto['monto_mensual'] / $contexto['dias_del_mes']) * $dias
                * $contexto['porcentaje_presupuesto'];

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 1a: en prueba (mes parcial)'
                    . ($contexto['tiene_egreso_este_mes'] ? ' con egreso' : ''),
            ];
        }

        // 1b con egreso este mes
        if ($contexto['tiene_egreso_este_mes']) {
            $inicioEfectivo = ($contexto['tiene_ingreso_este_mes']
                && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes'])
                ? clone $contexto['fecha_ingreso']
                : clone $contexto['primer_dia_mes'];

            $dias  = (int) $inicioEfectivo->diff($contexto['fecha_egreso'])->days + 1;
            $total = ($presupuesto['monto_mensual'] / $contexto['dias_del_mes']) * $dias
                * $contexto['porcentaje_presupuesto'];

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 1b: en prueba con egreso este mes',
            ];
        }

        // 1b sin egreso: mes completo (o desde ingreso si entró este mes)
        if ($contexto['tiene_ingreso_este_mes']
            && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes']
        ) {
            $dias  = (int) $contexto['fecha_ingreso']->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
            $total = ($presupuesto['monto_mensual'] / $contexto['dias_del_mes'])
                * $dias
                * $contexto['porcentaje_presupuesto'];
        } else {
            $dias  = $contexto['dias_del_mes'];
            $total = $presupuesto['monto_mensual'] * $contexto['porcentaje_presupuesto'];
        }

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $dias,
            'caso'               => 'Caso 1b: en prueba'
                . ($contexto['tiene_ingreso_este_mes'] ? ' (ingreso parcial)' : ' mes completo'),
        ];
    }

    /**
     * CASO 2: Prueba terminó este mes.
     * Combina tramo con % + tramo normal.
     */
    private function _caso2_PostPruebaEsteMes($presupuesto, $contexto)
    {
        $tarifaDiaria = $presupuesto['monto_mensual'] / $contexto['dias_del_mes'];
        $porcentaje   = $contexto['porcentaje_presupuesto'];

        $inicioEfectivo = ($contexto['tiene_ingreso_este_mes']
            && $contexto['fecha_ingreso'] > $contexto['primer_dia_mes'])
            ? clone $contexto['fecha_ingreso']
            : clone $contexto['primer_dia_mes'];

        $diasPruebaEnMes = (int) $inicioEfectivo->diff($contexto['fecha_fin_prueba'])->days + 1;
        $presupuestoPrueba = $tarifaDiaria * $diasPruebaEnMes * $porcentaje;

        $diaSiguientePrueba = (clone $contexto['fecha_fin_prueba'])->modify('+1 day');

        // Egreso antes o durante la prueba
        if ($contexto['tiene_egreso_este_mes']
            && $contexto['fecha_egreso'] <= $contexto['fecha_fin_prueba']
        ) {
            $dias  = (int) $inicioEfectivo->diff($contexto['fecha_egreso'])->days + 1;
            $total = $tarifaDiaria * $dias * $porcentaje;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 2: egreso durante prueba',
            ];
        }

        // Egreso después de la prueba
        if ($contexto['tiene_egreso_este_mes']) {
            $diasDespues = (int) $diaSiguientePrueba->diff($contexto['fecha_egreso'])->days + 1;
            $total       = $presupuestoPrueba + ($tarifaDiaria * $diasDespues);

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $diasPruebaEnMes + $diasDespues,
                'caso'               => 'Caso 2: pasó prueba con egreso este mes',
            ];
        }

        // Sin egreso: hasta fin de mes
        $diasDespues = (int) $diaSiguientePrueba->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
        $total       = $presupuestoPrueba + ($tarifaDiaria * $diasDespues);

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $diasPruebaEnMes + $diasDespues,
            'caso'               => 'Caso 2: pasó prueba este mes sin egreso',
        ];
    }

    /**
     * CASO 3: Ingreso este mes sin restricción de prueba.
     */
    private function _caso3_IngresoEsteMes($presupuesto, $contexto)
    {
        $tarifaDiaria = $presupuesto['monto_mensual'] / $contexto['dias_del_mes'];

        if ($contexto['tiene_egreso_este_mes']) {
            $dias  = (int) $contexto['fecha_ingreso']->diff($contexto['fecha_egreso'])->days + 1;
            $total = $tarifaDiaria * $dias;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 3: ingreso y egreso este mes',
            ];
        }

        $diasDesdeIngreso = (int) $contexto['fecha_ingreso']->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
        $total            = $tarifaDiaria * $diasDesdeIngreso;

        return [
            'presupuesto'        => $total,
            'dias_transcurridos' => $contexto['dias_del_mes'],
            'caso'               => 'Caso 3: ingreso este mes sin egreso',
        ];
    }

    /**
     * CASO 4: Normal / post-egreso / sin registro especial.
     */
    private function _caso4_Normal($presupuesto, $contexto, $detalle)
    {
        $tarifaDiaria = $presupuesto['monto_mensual'] / $contexto['dias_del_mes'];

        $fechaInicioPostEgreso = null;
        $inicioEsEsteMes       = false;

        if (isset($detalle['periodo_actual'])
            && $detalle['periodo_actual'] === 'post_egreso'
            && isset($detalle['fecha_inicio_calculo'])
        ) {
            $fechaInicioPostEgreso = new \DateTime($detalle['fecha_inicio_calculo']);
            $inicioEsEsteMes = ($fechaInicioPostEgreso->format('Y-m') === $contexto['hoy']->format('Y-m'));
        }

        if ($contexto['tiene_egreso_este_mes']) {
            $fechaBase = ($inicioEsEsteMes && $fechaInicioPostEgreso)
                ? $fechaInicioPostEgreso
                : $contexto['primer_dia_mes'];

            $dias  = (int) $fechaBase->diff($contexto['fecha_egreso'])->days + 1;
            $total = $tarifaDiaria * $dias;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 4: con egreso este mes',
            ];
        }

        if ($inicioEsEsteMes && $fechaInicioPostEgreso) {
            $dias  = (int) $fechaInicioPostEgreso->diff($contexto['fecha_fin_calculo_mes'])->days + 1;
            $total = $tarifaDiaria * $dias;

            return [
                'presupuesto'        => $total,
                'dias_transcurridos' => $dias,
                'caso'               => 'Caso 4: post-egreso con inicio este mes',
            ];
        }

        // Mes completo normal: presupuesto completo del mes
        return [
            'presupuesto'        => $presupuesto['monto_mensual'],
            'dias_transcurridos' => $contexto['dias_del_mes'],
            'caso'               => 'Caso 4: mes completo normal',
        ];
    }
}