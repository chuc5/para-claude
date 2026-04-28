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
     * @param array   $periodo
     * @param string  $inicioRango
     * @param string  $finRango
     * @param bool    $modoAcumulado  Si true y el empleado está en prueba,
     *                                 calcula solo hasta $fechaCorte.
     * @param ?string $fechaCorte     Y-m-d. Si null, usa la fecha actual.
     * @return float
     */
    public function calcularPorRango(
        array   $periodo,
        string  $inicioRango,
        string  $finRango,
        bool    $modoAcumulado = false,
        ?string $fechaCorte    = null
    ): float {
        $montoMensual = (float) ($periodo['monto_mensual'] ?? 0);
        if ($montoMensual <= 0) return 0.0;

        $dtInicio = new \DateTime($inicioRango);
        $dtFin    = new \DateTime($finRango);
        if ($dtInicio > $dtFin) return 0.0;

        // Sin restricción de prueba → flujo normal
        if (!($periodo['es_nuevo'] ?? false) || ($periodo['dias_presupuesto'] ?? 0) <= 0) {
            return $this->calcularRangoMensual($inicioRango, $finRango, $montoMensual);
        }

        $fechaFinPrueba = (new \DateTime($periodo['inicio_efectivo']))
            ->modify("+{$periodo['dias_presupuesto']} days")
            ->modify('-1 day');

        $porcentaje = ($periodo['porcentaje_presupuesto'] ?? 100) / 100;

        // Modo acumulado: ajustar el fin al corte si corresponde
        if ($modoAcumulado) {
            $dtCorte = new \DateTime($fechaCorte ?? date('Y-m-d'));

            // Solo aplica si el corte cae dentro del período de prueba
            if ($dtCorte < $fechaFinPrueba && $dtInicio <= $dtCorte) {
                $strFinPrueba = $fechaFinPrueba->format('Y-m-d');
                $strCorte     = $dtCorte->format('Y-m-d');

                // Tramo completo de prueba hasta el corte
                return $this->calcularRangoPruebaAcumulado(
                    $periodo['inicio_efectivo'],
                    $strFinPrueba,
                    $montoMensual,
                    $porcentaje,
                    $strCorte
                );
            }
        }

        // Flujo original (sin modo acumulado o corte ya pasó el fin de prueba)
        $strFinPrueba  = $fechaFinPrueba->format('Y-m-d');
        $strPostPrueba = (clone $fechaFinPrueba)->modify('+1 day')->format('Y-m-d');

        if ($dtFin < $fechaFinPrueba) {
            // Egreso anticipado: el denominador combinado se mantiene igual al período
            // COMPLETO de prueba (diasParcialInicio + diasParcialFin natural), pero
            // solo se cuentan los días laborados hasta la fecha de egreso ($finRango).
            return $this->calcularRangoPruebaAcumulado(
                $periodo['inicio_efectivo'],
                $strFinPrueba,
                $montoMensual,
                $porcentaje,
                $finRango
            );
        }

        if ($dtFin == $fechaFinPrueba) {
            return $this->calcularRangoPrueba($inicioRango, $finRango, $montoMensual, $porcentaje);
        }

        if ($dtInicio > $fechaFinPrueba) {
            return $this->calcularRangoMensual($inicioRango, $finRango, $montoMensual);
        }

        return $this->calcularRangoPrueba($inicioRango, $strFinPrueba, $montoMensual, $porcentaje)
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

    /**
     * Calcula el presupuesto de un período de PRUEBA aplicando la lógica
     * de días parciales combinados cuando el período no inicia el día 1.
     *
     * Caso 1 — inicia el día 1 del mes:
     *   Cada mes es independiente: monto_mensual / días_del_mes × porcentaje
     *
     * Caso 2 — inicia a mitad de mes:
     *   Los días del mes de inicio (parcial) y del mes de fin (parcial) comparten
     *   el mismo denominador (suma de ambos tramos), formando un "mes virtual".
     *     tarifa_parcial = monto_mensual / (días_parcial_inicio + días_parcial_fin) × porcentaje
     *   Los meses completos intermedios se calculan con su propia tarifa.
     *
     * Ejemplo: ingreso 15-ene, fin_prueba 14-mar, porcentaje 50%, Q1,000/mes
     *   - Parciales: ene 15–31 (17d) + mar 1–14 (14d) = 31d combinados
     *     tarifa = 1000/31 × 50% = Q16.13/día
     *   - Feb completo: 1000/28 × 50% × 28 = Q500.00
     *   - Total: Q274.19 + Q500.00 + Q225.81 = Q1,000.00
     *
     * @param string $fechaInicio  Y-m-d — inicio real del UCF (fecha_ingreso)
     * @param string $fechaFin     Y-m-d — fin real del período de prueba
     * @param float  $montoMensual
     * @param float  $porcentaje   Factor 0.0–1.0
     * @return float
     */
    public function calcularRangoPrueba(
        string $fechaInicio,
        string $fechaFin,
        float  $montoMensual,
        float  $porcentaje = 1.0
    ): float {
        if ($montoMensual <= 0 || $porcentaje <= 0) return 0.0;

        $inicio = new \DateTime($fechaInicio);
        $fin    = new \DateTime($fechaFin);

        if ($inicio > $fin) return 0.0;

        // Caso 1: inicia el día 1 → cada mes independiente
        if ($inicio->format('d') === '01') {
            return $this->calcularRangoMensual($fechaInicio, $fechaFin, $montoMensual, $porcentaje);
        }

        // Caso 2: inicia a mitad de mes → combinar parciales de inicio y fin
        $diasDelMesInicio  = (int) $inicio->format('t');
        $diaInicio         = (int) $inicio->format('d');
        $diasParcialInicio = $diasDelMesInicio - $diaInicio + 1;

        $finMesInicio = new \DateTime(
            $inicio->format('Y-m-') . str_pad($diasDelMesInicio, 2, '0', STR_PAD_LEFT)
        );

        // Si el período termina dentro del mismo mes de inicio → usar días del mes propio
        if ($fin <= $finMesInicio) {
            $diasEnRango = (int) $inicio->diff($fin)->days + 1;
            return $montoMensual / $diasDelMesInicio * $diasEnRango * $porcentaje;
        }

        // Días parciales del mes de fin (del día 1 hasta el día de fin)
        $diasParcialFin = (int) $fin->format('d');

        // Denominador combinado: suma de ambos tramos parciales
        $denominadorCombinado = $diasParcialInicio + $diasParcialFin;
        $tarifaParcial        = $montoMensual / $denominadorCombinado * $porcentaje;

        $total = 0.0;

        // Tramo parcial del mes de inicio
        $total += $tarifaParcial * $diasParcialInicio;

        // Meses completos intermedios (cada uno con su propia tarifa diaria)
        $cursorMes    = new \DateTime($inicio->format('Y-m-01'));
        $cursorMes->modify('+1 month');
        $inicioMesFin = new \DateTime($fin->format('Y-m-01'));

        while ($cursorMes < $inicioMesFin) {
            $diasMes = (int) $cursorMes->format('t');
            $total  += $montoMensual / $diasMes * $porcentaje * $diasMes;
            $cursorMes->modify('+1 month');
        }

        // Tramo parcial del mes de fin
        $total += $tarifaParcial * $diasParcialFin;

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

    /**
     * Calcula el presupuesto de prueba ACUMULADO hasta una fecha de corte.
     *
     * El denominador combinado se calcula siempre sobre el período COMPLETO
     * (diasParcialInicio + diasParcialFin del período total), garantizando
     * consistencia con calcularRangoPrueba. Solo los días contados se cortan
     * en $fechaCorte.
     *
     * Ejemplo: ingreso 15-ene, fin_prueba 14-mar, corte 10-mar
     *   denominador = 17d (ene) + 14d (mar) = 31 → tarifa = 1000/31 × 50%
     *   ene 15-31 (17d) → Q274.19
     *   feb completo    → Q500.00
     *   mar 1-10 (10d)  → Q161.29  ← corte aquí, no se llega a 14d
     *   Total acumulado → Q935.48
     *
     * @param string $fechaInicio  Y-m-d — inicio real del período de prueba
     * @param string $fechaFin     Y-m-d — fin real del período de prueba
     * @param float  $montoMensual
     * @param float  $porcentaje   Factor 0.0–1.0
     * @param string $fechaCorte   Y-m-d — fecha hasta donde calcular (ej: hoy)
     * @return float
     */
    public function calcularRangoPruebaAcumulado(
        string $fechaInicio,
        string $fechaFin,
        float  $montoMensual,
        float  $porcentaje,
        string $fechaCorte
    ): float {
        if ($montoMensual <= 0 || $porcentaje <= 0) return 0.0;

        $inicio   = new \DateTime($fechaInicio);
        $fin      = new \DateTime($fechaFin);
        $dtCorte  = new \DateTime($fechaCorte);

        if ($inicio > $dtCorte) return 0.0;

        // Si el corte alcanza o supera el fin → período completo
        if ($dtCorte >= $fin) {
            return $this->calcularRangoPrueba($fechaInicio, $fechaFin, $montoMensual, $porcentaje);
        }

        // Caso 1: inicia el día 1 → delegar con corte (sin denominador combinado)
        if ($inicio->format('d') === '01') {
            return $this->calcularRangoMensual($fechaInicio, $fechaCorte, $montoMensual, $porcentaje);
        }

        // Caso 2: inicio a mitad de mes
        // Denominador combinado basado en el período COMPLETO (no en el corte)
        $diasDelMesInicio  = (int) $inicio->format('t');
        $diaInicio         = (int) $inicio->format('d');
        $diasParcialInicio = $diasDelMesInicio - $diaInicio + 1;
        $diasParcialFin    = (int) $fin->format('d');
        $denominador       = $diasParcialInicio + $diasParcialFin;
        $tarifaParcial     = $montoMensual / $denominador * $porcentaje;

        $finMesInicio = new \DateTime(
            $inicio->format('Y-m-') . str_pad($diasDelMesInicio, 2, '0', STR_PAD_LEFT)
        );

        // El corte cae dentro del mes de inicio (mismo mes)
        if ($dtCorte <= $finMesInicio) {
            $diasEnRango = (int) $inicio->diff($dtCorte)->days + 1;
            return $tarifaParcial * $diasEnRango;
        }

        $total = 0.0;

        // Tramo completo del mes de inicio
        $total += $tarifaParcial * $diasParcialInicio;

        // Meses intermedios
        $cursorMes    = new \DateTime($inicio->format('Y-m-01'));
        $cursorMes->modify('+1 month');
        $inicioMesFin = new \DateTime($fin->format('Y-m-01'));

        while ($cursorMes < $inicioMesFin) {
            $diasMes      = (int) $cursorMes->format('t');
            $ultimoDiaMes = str_pad($diasMes, 2, '0', STR_PAD_LEFT);
            $finMesActual = new \DateTime($cursorMes->format('Y-m-') . $ultimoDiaMes);

            // El corte cae dentro de este mes intermedio
            if ($dtCorte <= $finMesActual) {
                $diasHastaCorte = (int) $cursorMes->diff($dtCorte)->days + 1;
                $total += $montoMensual / $diasMes * $porcentaje * $diasHastaCorte;
                return $total;
            }

            // Mes completo (los $diasMes se cancelan → monto_mensual × porcentaje)
            $total += $montoMensual * $porcentaje;
            $cursorMes->modify('+1 month');
        }

        // El corte cae en el mes de fin (tramo parcial final)
        $diaCorte = (int) $dtCorte->format('d');
        $total   += $tarifaParcial * $diaCorte;

        return $total;
    }
}