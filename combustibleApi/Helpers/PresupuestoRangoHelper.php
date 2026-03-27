<?php

namespace App\combustibleApi\Helpers;

use PDO;

/**
 * PresupuestoRangoHelper - Cálculo de presupuesto en rangos arbitrarios
 *
 * Centraliza dos operaciones que son necesarias desde múltiples módulos:
 *
 * 1. calcularPorRango(): Calcula el presupuesto asignado dentro de un rango
 *    arbitrario de fechas, respetando el porcentaje reducido durante prueba.
 *
 * 2. obtenerPorAgenciaPuesto(): Consulta el presupuesto base vigente para
 *    una combinación agencia/puesto/año.
 *
 * CONSUMIDO POR:
 *   - MantenimientoLiquidacionesHelper (construcción de filas y períodos en curso)
 *   - Cualquier módulo futuro que necesite presupuesto proporcional por rango
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class PresupuestoRangoHelper
{
    /** @var PDO Conexión a base de datos */
    private $connect;

    /**
     * @param PDO $connect Conexión PDO activa (viene del controller)
     */
    public function __construct(PDO $connect)
    {
        $this->connect = $connect;
    }

    // ════════════════════════════════════════════════════════════════════
    // CÁLCULO POR RANGO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Calcula el presupuesto asignado dentro de un rango arbitrario de fechas.
     *
     * Respeta el porcentaje reducido durante el período de prueba.
     * Optimiza el cálculo separando los días en prueba y post-prueba
     * con aritmética de fechas (sin iterar día a día).
     *
     * CASOS MANEJADOS:
     * ┌────────────────────────────┬─────────────────────────────────────┐
     * │ Escenario                  │ Resultado                           │
     * ├────────────────────────────┼─────────────────────────────────────┤
     * │ Sin restricción de prueba  │ días × monto_diario                 │
     * │ Todo el rango en prueba    │ días × monto_diario × porcentaje    │
     * │ Todo el rango post-prueba  │ días × monto_diario                 │
     * │ Rango cruza frontera       │ (días_prueba × % ) + días_normal    │
     * └────────────────────────────┴─────────────────────────────────────┘
     *
     * @param array  $periodo     Período mapeado con las claves:
     *   - monto_diario           (float)  Monto diario del puesto
     *   - es_nuevo               (bool)   Si tiene restricción de prueba
     *   - porcentaje_presupuesto (int)    Porcentaje durante prueba (ej: 50)
     *   - dias_presupuesto       (int)    Días totales de restricción
     *   - inicio_efectivo        (string) Fecha inicio real del UCF (Y-m-d)
     * @param string $inicioRango Inicio del rango a calcular (Y-m-d)
     * @param string $finRango    Fin del rango a calcular (Y-m-d)
     * @return float Presupuesto total asignado en el rango
     */
    public function calcularPorRango(
        array  $periodo,
        string $inicioRango,
        string $finRango
    ): float {
        $presupuestoDiario = (float) ($periodo['monto_diario'] ?? 0);

        if ($presupuestoDiario <= 0) {
            return 0.0;
        }

        $dtInicio  = new \DateTime($inicioRango);
        $dtFin     = new \DateTime($finRango);
        $totalDias = $dtInicio->diff($dtFin)->days + 1;

        // ── Sin restricción → cálculo directo ─────────────────────────
        if (!($periodo['es_nuevo'] ?? false) || ($periodo['dias_presupuesto'] ?? 0) <= 0) {
            return $presupuestoDiario * $totalDias;
        }

        // ── Con restricción → separar días prueba / post-prueba ───────
        $fechaFinPrueba = (new \DateTime($periodo['inicio_efectivo']))
            ->modify("+{$periodo['dias_presupuesto']} days")
            ->modify('-1 day');

        $porcentaje = ($periodo['porcentaje_presupuesto'] ?? 100) / 100;

        // Todo el rango cae en prueba
        if ($dtFin <= $fechaFinPrueba) {
            return $presupuestoDiario * $totalDias * $porcentaje;
        }

        // Todo el rango cae después de prueba
        if ($dtInicio > $fechaFinPrueba) {
            return $presupuestoDiario * $totalDias;
        }

        // ── El rango cruza la frontera prueba/post-prueba ─────────────
        $diasEnPrueba   = $dtInicio->diff($fechaFinPrueba)->days + 1;
        $diasPostPrueba = $totalDias - $diasEnPrueba;

        $presupuestoPrueba = $presupuestoDiario * $diasEnPrueba * $porcentaje;
        $presupuestoNormal = $presupuestoDiario * $diasPostPrueba;

        return $presupuestoPrueba + $presupuestoNormal;
    }

    // ════════════════════════════════════════════════════════════════════
    // CONSULTA DE PRESUPUESTO BASE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Obtiene el presupuesto vigente para una combinación agencia/puesto/año.
     *
     * @param int $agenciaid ID de la agencia
     * @param int $puestoid  ID del puesto
     * @param int $anio      Año a consultar
     * @return array|null ['monto_anual', 'monto_diario'] o null si no existe
     */
    public function obtenerPorAgenciaPuesto(
        int $agenciaid,
        int $puestoid,
        int $anio
    ): ?array {
        $sql = "SELECT monto_anual, monto_diario
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
