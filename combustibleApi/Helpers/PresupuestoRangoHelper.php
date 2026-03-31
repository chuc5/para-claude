<?php

namespace App\combustibleApi\Helpers;

use PDO;

/**
 * PresupuestoRangoHelper - Cálculo de presupuesto en rangos arbitrarios
 *
 * CAMBIO: Ahora usa tarifa mensual variable en lugar de tarifa diaria fija.
 * Formula: tarifa_diaria(mes) = monto_mensual / días_del_mes
 * Esto respeta la variación de días entre meses (28, 29, 30, 31).
 */
class PresupuestoRangoHelper
{
    private $connect;

    public function __construct(PDO $connect)
    {
        $this->connect = $connect;
    }

    // ═══════════════════════════════════════════════════════════════
    // CÁLCULO POR RANGO
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcula el presupuesto asignado dentro de un rango arbitrario de fechas.
     * Respeta el porcentaje reducido durante período de prueba.
     *
     * @param array  $periodo     Debe incluir: monto_mensual, es_nuevo,
     *                            porcentaje_presupuesto, dias_presupuesto,
     *                            inicio_efectivo
     * @param string $inicioRango Y-m-d
     * @param string $finRango    Y-m-d
     * @return float
     */
    public function calcularPorRango(
        array  $periodo,
        string $inicioRango,
        string $finRango
    ): float {
        $montoMensual = (float) ($periodo['monto_mensual'] ?? 0);

        if ($montoMensual <= 0) return 0.0;

        $dtInicio = new \DateTime($inicioRango);
        $dtFin    = new \DateTime($finRango);

        if ($dtInicio > $dtFin) return 0.0;

        // Sin restricción de prueba
        if (!($periodo['es_nuevo'] ?? false) || ($periodo['dias_presupuesto'] ?? 0) <= 0) {
            return $this->calcularRangoMensual($inicioRango, $finRango, $montoMensual);
        }

        // Con restricción: calcular fecha fin de prueba
        $fechaFinPrueba = (new \DateTime($periodo['inicio_efectivo']))
            ->modify("+{$periodo['dias_presupuesto']} days")
            ->modify('-1 day');

        $porcentaje = ($periodo['porcentaje_presupuesto'] ?? 100) / 100;

        // Todo el rango cae en prueba
        if ($dtFin <= $fechaFinPrueba) {
            return $this->calcularRangoMensual($inicioRango, $finRango, $montoMensual, $porcentaje);
        }

        // Todo el rango cae después de prueba
        if ($dtInicio > $fechaFinPrueba) {
            return $this->calcularRangoMensual($inicioRango, $finRango, $montoMensual);
        }

        // El rango cruza la frontera prueba/post-prueba
        $strFinPrueba  = $fechaFinPrueba->format('Y-m-d');
        $strPostPrueba = (clone $fechaFinPrueba)->modify('+1 day')->format('Y-m-d');

        return $this->calcularRangoMensual($inicioRango, $strFinPrueba, $montoMensual, $porcentaje)
            + $this->calcularRangoMensual($strPostPrueba, $finRango, $montoMensual);
    }

    /**
     * Calcula el presupuesto para un rango usando tarifa mensual variable.
     * Para cada mes del rango: tarifa = monto_mensual / días_del_mes.
     *
     * @param string $fechaInicio Y-m-d
     * @param string $fechaFin    Y-m-d
     * @param float  $montoMensual
     * @param float  $porcentaje  Factor (0.0–1.0), default 1.0
     * @return float
     */
    public function calcularRangoMensual(
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
        // Posicionarse en el 1er día del mes de inicio
        $cursor = new \DateTime($inicio->format('Y-m-01'));

        while ($cursor <= $fin) {
            $diasDelMes   = (int) $cursor->format('t');
            $tarifaDiaria = $montoMensual / $diasDelMes;

            // Inicio efectivo en este mes
            $iniEf = ($cursor < $inicio) ? clone $inicio : clone $cursor;

            // Fin del mes actual
            $finMes = new \DateTime($cursor->format('Y-m-') . str_pad($diasDelMes, 2, '0', STR_PAD_LEFT));

            // Fin efectivo en este mes
            $finEf = ($finMes < $fin) ? clone $finMes : clone $fin;

            $dias = (int) $iniEf->diff($finEf)->days + 1;

            if ($dias > 0) {
                $total += $tarifaDiaria * $dias * $porcentaje;
            }

            // Avanzar al primer día del mes siguiente
            $cursor->modify('+1 month');
        }

        return $total;
    }

    // ═══════════════════════════════════════════════════════════════
    // CONSULTA DE PRESUPUESTO BASE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Obtiene el presupuesto vigente para agencia/puesto/año.
     * Retorna monto_mensual, monto_anual y monto_diario.
     *
     * @param int $agenciaid
     * @param int $puestoid
     * @param int $anio
     * @return array|null
     */
    public function obtenerPorAgenciaPuesto(int $agenciaid, int $puestoid, int $anio): ?array
    {
        $sql = "SELECT monto_mensual, monto_anual, monto_diario
                FROM apoyo_combustibles.presupuestogeneral
                WHERE agenciaid = ?
                  AND puestoid  = ?
                  AND anio      = ?
                  AND activo    = 1
                LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$agenciaid, $puestoid, $anio]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}