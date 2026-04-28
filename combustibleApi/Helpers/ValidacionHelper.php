<?php

namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

/**
 * ValidacionHelper - Validaciones de facturas y presupuesto
 *
 * Centraliza las validaciones necesarias antes de registrar
 * una liquidación de combustible.
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class ValidacionHelper
{
    /** @var PDO */
    private $connect;

    /** @var int */
    private $idUsuario;

    /** @var ConsumoHelper */
    private $consumoHelper;

    /** @var PresupuestoAnualHelper */
    private $presupuestoAnualHelper;

    /** @var PresupuestoMensualHelper */
    private $presupuestoMensualHelper;

    /**
     * @param PDO                      $connect
     * @param int                      $idUsuario
     * @param ConsumoHelper            $consumoHelper
     * @param PresupuestoAnualHelper   $presupuestoAnualHelper
     * @param PresupuestoMensualHelper $presupuestoMensualHelper
     */
    public function __construct(
        PDO $connect,
            $idUsuario,
        ConsumoHelper $consumoHelper,
        PresupuestoAnualHelper $presupuestoAnualHelper,
        PresupuestoMensualHelper $presupuestoMensualHelper
    ) {
        $this->connect = $connect;
        $this->idUsuario = $idUsuario;
        $this->consumoHelper = $consumoHelper;
        $this->presupuestoAnualHelper = $presupuestoAnualHelper;
        $this->presupuestoMensualHelper = $presupuestoMensualHelper;
    }

    /**
     * Valida si hay presupuesto disponible para una liquidación
     *
     * Verifica:
     * 1. Que exista configuración de presupuesto
     * 2. Que el monto no exceda el presupuesto anual
     * 3. Que el monto no exceda el presupuesto mensual (si aplica)
     *
     * @param float $monto       Monto de la liquidación
     * @param int   $tipoapoyoid ID del tipo de apoyo
     * @return array ['valido' => bool, 'mensaje' => string]
     */
    public function validarPresupuestoDisponible($monto, $tipoapoyoid)
    {
        try {
            $anio = date('Y');
            $presupuesto = $this->presupuestoAnualHelper->obtener($anio, true);

            if (!$presupuesto) {
                return [
                    'valido'  => false,
                    'mensaje' => 'No existe presupuesto configurado para su agencia y puesto en el año actual'
                ];
            }

            // Validar anual — solo si el tipo de apoyo tiene límite mensual.
            // Si no tiene límite mensual, se permite aunque quede negativo.
            $aplicaLimite = $this->consumoHelper->aplicaLimiteMensual($tipoapoyoid);

            if ($aplicaLimite) {
                $consumidoAnual = $this->consumoHelper->obtenerConsumoAnual(
                    $anio,
                    $presupuesto['detalle_periodo_actual']['fecha_consumo_desde'] ?? null,
                    $presupuesto['detalle_periodo_actual']['fecha_consumo_hasta'] ?? null
                );
                $disponibleAnual = $presupuesto['monto_anual'] - $consumidoAnual;

                if ($monto > $disponibleAnual) {
                    return [
                        'valido'  => false,
                        'mensaje' => 'El monto excede el presupuesto anual disponible (Disponible: Q'
                            . number_format($disponibleAnual, 2) . ')'
                    ];
                }

                // Validar mensual
                $presupuestoMensual = $this->presupuestoMensualHelper->calcular($presupuesto);

                if ($monto > $presupuestoMensual['disponible']) {
                    return [
                        'valido'  => false,
                        'mensaje' => 'El monto excede el presupuesto mensual disponible (Disponible: Q'
                            . number_format($presupuestoMensual['disponible'], 2) . ')'
                    ];
                }
            }

            return ['valido' => true, 'mensaje' => ''];

        } catch (Exception $e) {
            error_log("Error en ValidacionHelper::validarPresupuestoDisponible: " . $e->getMessage());
            return ['valido' => false, 'mensaje' => 'Error al validar presupuesto disponible'];
        }
    }

    /**
     * Verifica si una factura ya fue liquidada
     *
     * @param string $numeroFactura Número de factura
     * @return bool True si ya está liquidada
     */
    public function facturaYaLiquidada($numeroFactura)
    {
        try {
            $sql = "SELECT COUNT(*)
                    FROM apoyo_combustibles.liquidaciones
                    WHERE numero_factura = ?
                        AND estado NOT IN ('eliminada')";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$numeroFactura]);
            return $stmt->fetchColumn() > 0;

        } catch (Exception $e) {
            error_log("Error en ValidacionHelper::facturaYaLiquidada: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene la solicitud de autorización para una factura
     *
     * @param string $numeroFactura Número de factura
     * @param int    $usuarioid     ID del usuario
     * @return array|null Datos de la solicitud o null
     */
    public function obtenerSolicitudAutorizacion($numeroFactura, $usuarioid)
    {
        try {
            $sql = "SELECT
                    sa.idSolicitudesAutorizacion, sa.usuarioid, sa.numero_factura,
                    sa.dias_habiles_excedidos, sa.justificacion, sa.estado,
                    sa.fecha_solicitud, sa.fecha_respuesta, sa.autorizado_por,
                    sa.motivo_rechazo, dtp.nombres as nombre_autorizador
                FROM apoyo_combustibles.solicitudesautorizacion sa
                LEFT JOIN dbintranet.usuarios us ON sa.autorizado_por = us.idUsuarios
                LEFT JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                WHERE sa.numero_factura = ?
                    AND sa.usuarioid = ?
                ORDER BY sa.fecha_solicitud DESC
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$numeroFactura, $usuarioid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (Exception $e) {
            error_log("Error en ValidacionHelper::obtenerSolicitudAutorizacion: " . $e->getMessage());
            return null;
        }
    }
}