<?php

namespace App\combustibleApi\Helpers;

/**
 * MensajeContextualHelper - Generación de mensajes según período
 *
 * Genera mensajes descriptivos para el usuario basándose
 * en su período laboral actual (prueba, post-prueba, normal, etc.)
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class MensajeContextualHelper
{
    /**
     * Genera un mensaje contextual según el período actual del usuario
     *
     * @param array $presupuesto Datos del presupuesto con detalle_periodo_actual
     * @return string Mensaje descriptivo para el usuario
     */
    public static function obtener($presupuesto)
    {
        if (!isset($presupuesto['detalle_periodo_actual'])) {
            return 'Presupuesto anual completo';
        }

        $detalle = $presupuesto['detalle_periodo_actual'];
        $periodo = $detalle['periodo_actual'] ?? 'desconocido';

        switch ($periodo) {
            case 'prueba':
                return self::_mensajePrueba($detalle);
            case 'post_prueba':
                return self::_mensajePostPrueba($detalle);
            case 'normal':
                return self::_mensajeNormal($detalle);
            case 'post_egreso':
                return self::_mensajePostEgreso($detalle);
            case 'sin_registros':
                return 'Presupuesto anual completo disponible.';
            default:
                return 'Presupuesto calculado según su período actual.';
        }
    }

    private static function _mensajePrueba($detalle)
    {
        return sprintf(
            'Está en período de prueba. El presupuesto mostrado es el total del período de prueba (hasta el %s) con %d%% del presupuesto diario. Quedan %d días de prueba.',
            $detalle['fecha_fin_restriccion'],
            $detalle['porcentaje_restriccion'],
            $detalle['dias_restantes_prueba']
        );
    }

    private static function _mensajePostPrueba($detalle)
    {
        return sprintf(
            'Ya finalizó su período de prueba el %s. El presupuesto mostrado es desde el %s hasta el %s (%d días totales con presupuesto completo).',
            $detalle['fecha_fin_restriccion'],
            $detalle['fecha_inicio_calculo'],
            $detalle['fecha_fin_calculo'],
            $detalle['dias_restantes']
        );
    }

    private static function _mensajeNormal($detalle)
    {
        return sprintf(
            'El presupuesto mostrado es desde el %s hasta el %s. Quedan %d días en este período.',
            $detalle['fecha_inicio_calculo'],
            $detalle['fecha_fin_calculo'],
            $detalle['dias_restantes']
        );
    }

    private static function _mensajePostEgreso($detalle)
    {
        return sprintf(
            'Su período como %s finalizó el %s. El presupuesto mostrado corresponde desde el %s hasta el %s (%d días restantes del año).',
            $detalle['puesto'],
            $detalle['fecha_egreso_anterior'],
            $detalle['fecha_inicio_calculo'],
            $detalle['fecha_fin_calculo'],
            $detalle['dias_restantes']
        );
    }
}