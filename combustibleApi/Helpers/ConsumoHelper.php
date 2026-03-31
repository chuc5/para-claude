<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * ConsumoHelper - Consultas de consumo de presupuesto
 *
 * CAMBIO: El consumo ahora incluye liquidaciones sin comprobante asignado
 * (comprobantecontableid IS NULL) para capturar liquidaciones de fin de mes
 * que aún no han sido procesadas contablemente.
 */
class ConsumoHelper
{
    private $connect;
    private $idUsuario;

    public function __construct(PDO $connect, $idUsuario)
    {
        $this->connect   = $connect;
        $this->idUsuario = $idUsuario;
    }

    /**
     * Obtiene el total consumido en el año por el usuario.
     *
     * Lógica de inclusión:
     * - Si se pasa rango de fechas: cuenta las del rango MÁS las que no tienen
     *   comprobante asignado en el año (pendientes de pago).
     * - Si no se pasa rango: cuenta todas las del año.
     *
     * @param int         $anio
     * @param string|null $fechaDesde
     * @param string|null $fechaHasta
     * @return float
     */
    public function obtenerConsumoAnual($anio, $fechaDesde = null, $fechaHasta = null)
    {
        try {
            if ($fechaDesde && $fechaHasta) {
                // Cuenta las del período definido + las sin comprobante del año
                // (OR evita doble conteo: una liquidación del rango sin comprobante
                // satisface ambas condiciones pero se suma una sola vez)
                $sql = "SELECT COALESCE(SUM(monto), 0)
                        FROM apoyo_combustibles.liquidaciones
                        WHERE usuarioid = ?
                            AND estado NOT IN ('eliminada', 'rechazada')
                            AND (
                                fecha_liquidacion BETWEEN ? AND ?
                                OR (
                                    comprobantecontableid IS NULL
                                    AND YEAR(fecha_liquidacion) = ?
                                )
                            )";
                $stmt = $this->connect->prepare($sql);
                $stmt->execute([$this->idUsuario, $fechaDesde, $fechaHasta, $anio]);
            } else {
                // Sin rango: todo el año (ya incluye sin comprobante)
                $sql = "SELECT COALESCE(SUM(monto), 0)
                        FROM apoyo_combustibles.liquidaciones
                        WHERE usuarioid = ?
                            AND YEAR(fecha_liquidacion) = ?
                            AND estado NOT IN ('eliminada', 'rechazada')";
                $stmt = $this->connect->prepare($sql);
                $stmt->execute([$this->idUsuario, $anio]);
            }

            return (float) $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::obtenerConsumoAnual: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene el total consumido en el mes actual por el usuario.
     *
     * Incluye:
     * - Liquidaciones del mes actual (con o sin comprobante)
     * - Liquidaciones sin comprobante de cualquier mes del año actual
     *   (para capturar las de fin de mes previo aún sin procesar)
     * Solo para tipos de apoyo con aplica_limite_mensual = 1.
     *
     * @return float
     */
    public function obtenerConsumoMensual()
    {
        try {
            $sql = "SELECT COALESCE(SUM(l.monto), 0) AS consumido
                    FROM apoyo_combustibles.liquidaciones l
                    INNER JOIN apoyo_combustibles.tiposapoyo ta
                        ON l.tipoapoyoid = ta.idTiposApoyo
                    WHERE l.usuarioid = ?
                        AND l.estado NOT IN ('eliminada', 'rechazada')
                        AND ta.aplica_limite_mensual = 1
                        AND (
                            (
                                YEAR(l.fecha_liquidacion)  = YEAR(CURRENT_DATE)
                                AND MONTH(l.fecha_liquidacion) = MONTH(CURRENT_DATE)
                            )
                            OR (
                                l.comprobantecontableid IS NULL
                                AND YEAR(l.fecha_liquidacion) = YEAR(CURRENT_DATE)
                            )
                        )";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$this->idUsuario]);

            return (float) $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::obtenerConsumoMensual: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verifica si un tipo de apoyo aplica límite mensual.
     *
     * @param int $tipoapoyoid
     * @return bool
     */
    public function aplicaLimiteMensual($tipoapoyoid)
    {
        try {
            $sql = "SELECT aplica_limite_mensual
                    FROM apoyo_combustibles.tiposapoyo
                    WHERE idTiposApoyo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$tipoapoyoid]);

            return (bool) $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::aplicaLimiteMensual: " . $e->getMessage());
            return false;
        }
    }
}