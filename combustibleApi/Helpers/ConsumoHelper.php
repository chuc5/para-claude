<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * ConsumoHelper - Consultas de consumo de presupuesto
 *
 * Centraliza las consultas SQL de consumo anual y mensual
 * evitando duplicación de queries en el controller.
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class ConsumoHelper
{
    /** @var PDO Conexión a base de datos */
    private $connect;

    /** @var int ID del usuario actual */
    private $idUsuario;

    /**
     * @param PDO $connect   Conexión PDO activa (viene del controller)
     * @param int $idUsuario ID del usuario
     */
    public function __construct(PDO $connect, $idUsuario)
    {
        $this->connect = $connect;
        $this->idUsuario = $idUsuario;
    }

    /**
     * Obtiene el total consumido en el año por el usuario
     *
     * Suma los montos de liquidaciones activas (excluye eliminadas y rechazadas)
     *
     * @param int $anio Año a consultar
     * @return float Monto total consumido en el año
     */
    public function obtenerConsumoAnual($anio, $fechaDesde = null, $fechaHasta = null)
    {
        try {
            if ($fechaDesde && $fechaHasta) {
                $sql = "SELECT COALESCE(SUM(monto), 0)
                    FROM apoyo_combustibles.liquidaciones
                    WHERE usuarioid = ?
                        AND fecha_liquidacion BETWEEN ? AND ?
                        AND estado NOT IN ('eliminada', 'rechazada')";
                $stmt = $this->connect->prepare($sql);
                $stmt->execute([$this->idUsuario, $fechaDesde, $fechaHasta]);
            } else {
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
     * Obtiene el total consumido en el mes actual por el usuario
     *
     * Solo cuenta liquidaciones cuyo tipo de apoyo aplica límite mensual.
     *
     * @return float Monto total consumido en el mes
     */
    public function obtenerConsumoMensual()
    {
        try {
            $sql = "SELECT COALESCE(SUM(l.monto), 0) as consumido
                    FROM apoyo_combustibles.liquidaciones l
                    INNER JOIN apoyo_combustibles.tiposapoyo ta
                        ON l.tipoapoyoid = ta.idTiposApoyo
                    WHERE l.usuarioid = ?
                        AND YEAR(l.fecha_liquidacion) = YEAR(CURRENT_DATE)
                        AND MONTH(l.fecha_liquidacion) = MONTH(CURRENT_DATE)
                        AND l.estado NOT IN ('eliminada', 'rechazada')
                        AND ta.aplica_limite_mensual = 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$this->idUsuario]);

            return (float) $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::obtenerConsumoMensual: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verifica si un tipo de apoyo aplica límite mensual
     *
     * @param int $tipoapoyoid ID del tipo de apoyo
     * @return bool True si aplica límite mensual
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
