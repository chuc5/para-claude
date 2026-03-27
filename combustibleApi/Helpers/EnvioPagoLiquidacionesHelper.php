<?php

namespace App\combustibleApi\Helpers;

use PDO;

/**
 * EnvioPagoLiquidacionesHelper - Lógica de negocio para la vista de envío
 * y aprobación de pago de liquidaciones.
 *
 * Genera la tabla principal con:
 *   - Una fila por usuario
 *   - Columnas dinámicas por comprobante/mes existente en el año
 *   - Detalle expandible con periodos UCF y sus liquidaciones
 *
 * ESTRUCTURA DE RESPUESTA (tabla principal):
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ ID · Centro · Puesto · NoCuenta · Nombre · Estado · Periodos · Agencia │
 * │ [Ene LIQ-001] [Feb LIQ-002] ... [Sin Comprobante]                      │
 * │ Ppto.Ejecutado · Ppto.Anual · Sobregiro                                │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * RESPONSABILIDADES:
 * ┌──────────────────────────────┬─────────────────────────────────────────┐
 * │ Grupo                        │ Métodos                                 │
 * ├──────────────────────────────┼─────────────────────────────────────────┤
 * │ Comprobantes (columnas)      │ obtenerComprobantesDelAnio              │
 * ├──────────────────────────────┼─────────────────────────────────────────┤
 * │ Usuarios y periodos          │ obtenerUsuariosConLiquidaciones,        │
 * │                              │ obtenerPeriodosResumenPorUsuarios       │
 * ├──────────────────────────────┼─────────────────────────────────────────┤
 * │ Liquidaciones                │ obtenerLiquidacionesAgrupadas,          │
 * │                              │ obtenerLiquidacionesPorUsuario          │
 * ├──────────────────────────────┼─────────────────────────────────────────┤
 * │ Construcción de filas        │ construirFilaUsuario                    │
 * ├──────────────────────────────┼─────────────────────────────────────────┤
 * │ Detalle modal                │ construirDetalleUsuario                 │
 * └──────────────────────────────┴─────────────────────────────────────────┘
 *
 * @package App\combustibleApi\Helpers
 * @version 1.0.0
 */
class EnvioPagoLiquidacionesHelper
{
    /** @var PDO */
    private $connect;

    /**
     * Estados de liquidación que se consideran para esta vista.
     * - aprobada  : aprobada pero aún sin comprobante asignado
     * - en_lote   : ya tiene comprobantecontableid asignado
     */
    private const ESTADOS_VALIDOS = ['aprobada', 'en_lote'];

    /**
     * @param PDO $connect Conexión PDO activa
     */
    public function __construct(PDO $connect)
    {
        $this->connect = $connect;
    }

    // =========================================================================
    // COMPROBANTES — COLUMNAS DINÁMICAS
    // =========================================================================

    /**
     * Obtiene todos los comprobantes de tipo 'liquidacion' del año indicado,
     * ordenados por mes. Estos definen las columnas dinámicas de la tabla.
     *
     * @param int $anio
     * @return array [
     *   idComprobantesContables => int,
     *   numero_comprobante      => string,
     *   mes_anio_comprobante    => string  (Y-m-d, primer día del mes),
     *   mes_label               => string  (e.g. "Enero 2026"),
     *   mes_key                 => string  (e.g. "2026-01"),
     *   monto_total             => float,
     * ]
     */
    public function obtenerComprobantesDelAnio(int $anio): array
    {
        $sql = "SELECT
                    cc.idComprobantesContables,
                    cc.numero_comprobante,
                    cc.mes_anio_comprobante,
                    cc.monto_total
                FROM apoyo_combustibles.comprobantescontables AS cc
                WHERE cc.tipo = 'liquidacion'
                  AND YEAR(cc.mes_anio_comprobante) = ?
                ORDER BY cc.mes_anio_comprobante ASC";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$anio]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meses_es = [
            1  => 'Enero',   2  => 'Febrero',  3  => 'Marzo',
            4  => 'Abril',   5  => 'Mayo',      6  => 'Junio',
            7  => 'Julio',   8  => 'Agosto',    9  => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return array_map(function ($row) use ($meses_es, $anio) {
            $dt  = new \DateTime($row['mes_anio_comprobante']);
            $mes = (int) $dt->format('n');

            return [
                'idComprobantesContables' => (int) $row['idComprobantesContables'],
                'numero_comprobante'      => $row['numero_comprobante'],
                'mes_anio_comprobante'    => $row['mes_anio_comprobante'],
                'mes_label'               => $meses_es[$mes] . ' ' . $anio,
                'mes_key'                 => $dt->format('Y-m'),
                'monto_total'             => (float) $row['monto_total'],
            ];
        }, $rows);
    }

    // =========================================================================
    // USUARIOS Y PERÍODOS
    // =========================================================================

    /**
     * Obtiene todos los usuarios que tienen al menos una liquidación válida
     * en el año, con sus datos completos para la tabla principal.
     * Retorna array indexado por usuarioid.
     *
     * @param int $anio
     * @return array [ usuarioid => [ CodigoCliente, NombreUsuario, ... ] ]
     */
    public function obtenerUsuariosConLiquidaciones(int $anio): array
    {
        $estadosIn = implode(',', array_fill(0, count(self::ESTADOS_VALIDOS), '?'));

        $sql = "SELECT DISTINCT
                    us.idUsuarios       AS usuarioid,
                    cli.CodigoCliente,
                    dtp.nombres         AS NombreUsuario,
                    ag.nombre           AS agencia,
                    pu.nombre           AS puesto_actual,
                    us.idEstados           AS estado_usuario,
                    (
                        SELECT cap2.NumeroCuenta
                        FROM asociado_t24.ccomndatoscaptaciones AS cap2
                        WHERE cap2.Cliente  = cli.CodigoCliente
                          AND cap2.Producto = '114A.AHORRO.DISPONIBLE'
                        ORDER BY cap2.NumeroCuenta
                        LIMIT 1
                    ) AS PrimeraCuenta
                FROM apoyo_combustibles.liquidaciones AS l
                INNER JOIN dbintranet.usuarios AS us
                    ON us.idUsuarios = l.usuarioid
                LEFT JOIN dbintranet.datospersonales AS dtp
                    ON dtp.idDatosPersonales = us.idDatosPersonales
                LEFT JOIN asociado_t24.comndatosclientes AS cli
                    ON dtp.dpi = cli.Dpi
                INNER JOIN dbintranet.agencia AS ag
                    ON ag.idAgencia = us.idAgencia
                INNER JOIN dbintranet.puesto AS pu
                    ON pu.idPuesto = us.idPuesto
                WHERE l.estado IN ($estadosIn)
                  AND YEAR(l.fecha_liquidacion) = ?
                ORDER BY ag.nombre ASC, dtp.nombres ASC";

        $params = array_merge(self::ESTADOS_VALIDOS, [$anio]);
        $stmt   = $this->connect->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $row) {
            $resultado[$row['usuarioid']] = [
                'CodigoCliente'  => $row['CodigoCliente'],
                'NombreUsuario'  => $row['NombreUsuario'],
                'agencia'        => $row['agencia'],
                'puesto_actual'  => $row['puesto_actual'],
                'estado_usuario' => strtoupper($row['estado_usuario'] ?? 'ACTIVO'),
                'PrimeraCuenta'  => $row['PrimeraCuenta'],
            ];
        }
        return $resultado;
    }

    /**
     * Obtiene todos los periodos UCF de múltiples usuarios en el año,
     * para construir el resumen de periodos en la columna "Periodos".
     * Retorna array indexado por usuarioid.
     *
     * @param array $usuariosIds
     * @param int   $anio
     * @return array [ usuarioid => [ periodo, ... ] ]
     */
    public function obtenerPeriodosResumenPorUsuarios(array $usuariosIds, int $anio): array
    {
        if (empty($usuariosIds)) return [];

        $placeholders = implode(',', array_fill(0, count($usuariosIds), '?'));

        // Rango del año completo
        $fechaDesde = "{$anio}-01-01";
        $fechaHasta = "{$anio}-12-31";

        $sql = "SELECT
                    ucf.idUsuariosControlFechas,
                    ucf.usuarioid,
                    ucf.fecha_ingreso,
                    ucf.fecha_egreso,
                    ucf.es_nuevo,
                    ucf.porcentaje_presupuesto,
                    ucf.dias_presupuesto,
                    pu.nombre  AS puesto,
                    pg.monto_anual,
                    pg.monto_diario
                FROM apoyo_combustibles.usuarioscontrolfechas AS ucf
                LEFT JOIN dbintranet.puesto AS pu
                    ON pu.idPuesto = ucf.puestoid
                LEFT JOIN apoyo_combustibles.presupuestogeneral AS pg
                    ON  pg.agenciaid = ucf.agenciaid
                    AND pg.puestoid  = ucf.puestoid
                    AND pg.anio      = ?
                    AND pg.activo    = 1
                WHERE ucf.usuarioid IN ($placeholders)
                  AND ucf.activo       = 1
                  AND ucf.fecha_ingreso <= ?
                  AND (ucf.fecha_egreso IS NULL OR ucf.fecha_egreso >= ?)
                ORDER BY ucf.usuarioid ASC, ucf.fecha_ingreso ASC";

        $params = array_merge([$anio], $usuariosIds, [$fechaHasta, $fechaDesde]);
        $stmt   = $this->connect->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por usuario y resolver tipo de cada periodo
        $agrupado = [];
        $hoy      = date('Y-m-d');

        foreach ($rows as $row) {
            $uid   = $row['usuarioid'];
            $tipo  = $this->_resolverTipoPeriodo($row, $hoy);

            if (!isset($agrupado[$uid])) {
                $agrupado[$uid] = [];
            }

            $agrupado[$uid][] = [
                'idUsuariosControlFechas' => (int) $row['idUsuariosControlFechas'],
                'puesto'                  => $row['puesto'],
                'fecha_ingreso'           => $row['fecha_ingreso'],
                'fecha_egreso'            => $row['fecha_egreso'],
                'es_nuevo'                => (bool) $row['es_nuevo'],
                'porcentaje_presupuesto'  => (int) $row['porcentaje_presupuesto'],
                'dias_presupuesto'        => (int) $row['dias_presupuesto'],
                'monto_anual'             => (float) ($row['monto_anual'] ?? 0),
                'monto_diario'            => (float) ($row['monto_diario'] ?? 0),
                'tipo_periodo'            => $tipo,
            ];
        }

        return $agrupado;
    }

    // =========================================================================
    // LIQUIDACIONES
    // =========================================================================

    /**
     * Obtiene todas las liquidaciones del año para múltiples usuarios,
     * agrupadas por usuarioid.
     * Incluye datos del comprobante contable asociado.
     *
     * @param array $usuariosIds
     * @param int   $anio
     * @return array [ usuarioid => [ liquidacion, ... ] ]
     */
    public function obtenerLiquidacionesAgrupadas(array $usuariosIds, int $anio): array
    {
        if (empty($usuariosIds)) return [];

        $estadosIn    = implode(',', array_fill(0, count(self::ESTADOS_VALIDOS), '?'));
        $placeholders = implode(',', array_fill(0, count($usuariosIds), '?'));

        $sql = "SELECT
                    l.idLiquidaciones,
                    l.usuarioid,
                    l.numero_factura,
                    l.monto,
                    l.monto_factura,
                    l.descripcion,
                    l.detalle,
                    l.estado,
                    l.fecha_liquidacion,
                    l.comprobantecontableid,
                    l.aprobaciongerenciaid,
                    cc.numero_comprobante,
                    cc.mes_anio_comprobante,
                    ta.nombre  AS tipo_apoyo,
                    v.marca    AS vehiculo,
                    v.placa,
                    CASE
                        WHEN l.comprobantecontableid IS NULL THEN 1 ELSE 0
                    END AS sin_comprobante
                FROM apoyo_combustibles.liquidaciones AS l
                LEFT JOIN apoyo_combustibles.comprobantescontables AS cc
                    ON cc.idComprobantesContables = l.comprobantecontableid
                LEFT JOIN apoyo_combustibles.tiposapoyo AS ta
                    ON ta.idTiposApoyo = l.tipoapoyoid
                LEFT JOIN apoyo_combustibles.vehiculos AS v
                    ON v.idVehiculos = l.vehiculoid
                WHERE l.estado      IN ($estadosIn)
                  AND l.usuarioid   IN ($placeholders)
                  AND YEAR(l.fecha_liquidacion) = ?
                ORDER BY l.usuarioid ASC, l.fecha_liquidacion ASC";

        $params = array_merge(self::ESTADOS_VALIDOS, $usuariosIds, [$anio]);
        $stmt   = $this->connect->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $agrupado = [];
        foreach ($rows as $row) {
            $uid = $row['usuarioid'];
            if (!isset($agrupado[$uid])) {
                $agrupado[$uid] = [];
            }
            $agrupado[$uid][] = $this->_mapearLiquidacion($row);
        }
        return $agrupado;
    }

    /**
     * Obtiene todas las liquidaciones del año de UN usuario.
     * Usado en el endpoint de detalle del modal.
     *
     * @param string $usuarioid
     * @param int    $anio
     * @return array
     */
    public function obtenerLiquidacionesPorUsuario(string $usuarioid, int $anio): array
    {
        $estadosIn = implode(',', array_fill(0, count(self::ESTADOS_VALIDOS), '?'));

        $sql = "SELECT
                    l.idLiquidaciones,
                    l.usuarioid,
                    l.numero_factura,
                    l.monto,
                    l.monto_factura,
                    l.descripcion,
                    l.detalle,
                    l.estado,
                    l.fecha_liquidacion,
                    l.comprobantecontableid,
                    l.aprobaciongerenciaid,
                    cc.numero_comprobante,
                    cc.mes_anio_comprobante,
                    ta.nombre AS tipo_apoyo,
                    v.marca   AS vehiculo,
                    v.placa,
                    CASE
                        WHEN l.comprobantecontableid IS NULL THEN 1 ELSE 0
                    END AS sin_comprobante
                FROM apoyo_combustibles.liquidaciones AS l
                LEFT JOIN apoyo_combustibles.comprobantescontables AS cc
                    ON cc.idComprobantesContables = l.comprobantecontableid
                LEFT JOIN apoyo_combustibles.tiposapoyo AS ta
                    ON ta.idTiposApoyo = l.tipoapoyoid
                LEFT JOIN apoyo_combustibles.vehiculos AS v
                    ON v.idVehiculos = l.vehiculoid
                WHERE l.estado    IN ($estadosIn)
                  AND l.usuarioid  = ?
                  AND YEAR(l.fecha_liquidacion) = ?
                ORDER BY l.fecha_liquidacion ASC";

        $params = array_merge(self::ESTADOS_VALIDOS, [$usuarioid, $anio]);
        $stmt   = $this->connect->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, '_mapearLiquidacion'], $rows);
    }

    // =========================================================================
    // CONSTRUCCIÓN DE FILAS — TABLA PRINCIPAL
    // =========================================================================

    /**
     * Construye la fila completa de un usuario para la tabla principal.
     *
     * - Columnas dinámicas de meses: suma de liquidaciones por comprobante
     * - Sin comprobante: suma de liquidaciones sin comprobantecontableid
     * - Presupuesto ejecutado: total anual de todas sus liquidaciones válidas
     * - Presupuesto anual: suma de monto_anual de todos sus periodos UCF
     * - Sobregiro: ejecutado - anual (si > 0)
     *
     * @param string $usuarioid
     * @param array  $datosUsuario         [ CodigoCliente, NombreUsuario, ... ]
     * @param array  $periodosUsuario      Periodos UCF del usuario
     * @param array  $liquidacionesUsuario Liquidaciones del usuario
     * @param array  $comprobantes         Comprobantes del año (columnas dinámicas)
     * @return array
     */
    public function construirFilaUsuario(
        string $usuarioid,
        array  $datosUsuario,
        array  $periodosUsuario,
        array  $liquidacionesUsuario,
        array  $comprobantes,
        int    $anio
    ): array {
        // ── Montos por comprobante ────────────────────────────────────────────
        $montosPorComprobante = [];
        foreach ($comprobantes as $comp) {
            $montosPorComprobante[$comp['idComprobantesContables']] = 0.0;
        }

        $totalSinComprobante  = 0.0;
        $totalEjecutado       = 0.0;

        foreach ($liquidacionesUsuario as $liq) {
            $totalEjecutado += $liq['monto'];

            if ($liq['sin_comprobante']) {
                $totalSinComprobante += $liq['monto'];
            } elseif (isset($montosPorComprobante[$liq['comprobantecontableid']])) {
                $montosPorComprobante[$liq['comprobantecontableid']] += $liq['monto'];
            }
        }

        // ── Presupuesto anual (suma de todos los periodos UCF) ──────────────── // el año ya se conoce en el contexto, pero el método lo recibe
        $presupuestoAnual = $this->_calcularPresupuestoAnualPorDias($periodosUsuario, $anio);

        // ── Resumen de periodos para la columna "Periodos" ────────────────────
        $periodosResumen = $this->_construirResumenPeriodos($periodosUsuario);

        // ── Sobregiro ─────────────────────────────────────────────────────────
        $sobregiro = max(0, $totalEjecutado - $presupuestoAnual);

        return [
            // Identificación
            'usuarioid'      => $usuarioid,
            'CodigoCliente'  => $datosUsuario['CodigoCliente'],
            'NombreUsuario'  => $datosUsuario['NombreUsuario'],
            'PrimeraCuenta'  => $datosUsuario['PrimeraCuenta'],
            'agencia'        => $datosUsuario['agencia'],
            'puesto_actual'  => $datosUsuario['puesto_actual'],
            'estado_usuario' => $datosUsuario['estado_usuario'],

            // Resumen de todos los periodos (para la columna "Periodos")
            'periodos_resumen' => $periodosResumen,

            // Montos por comprobante/mes (columnas dinámicas)
            // Estructura: [ idComprobante => monto, ... ]
            'montos_por_comprobante' => $montosPorComprobante,

            // Totales
            'sin_comprobante'      => round($totalSinComprobante, 2),
            'presupuesto_ejecutado' => round($totalEjecutado, 2),
            'presupuesto_anual'     => round($presupuestoAnual, 2),
            'sobregiro'             => round($sobregiro, 2),
            'tiene_sobregiro'       => $sobregiro > 0,
        ];
    }

    // =========================================================================
    // DETALLE MODAL
    // =========================================================================

    /**
     * Construye el detalle completo de un usuario para el modal.
     * Divide sus periodos UCF en sub-periodos y asigna las liquidaciones
     * correspondientes a cada uno.
     *
     * @param array  $periodosUsuario      Periodos UCF del usuario
     * @param array  $liquidacionesUsuario Todas las liquidaciones del año
     * @param array  $comprobantes         Comprobantes del año
     * @param int    $anio
     * @return array
     */
    public function construirDetalleUsuario(
        array $periodosUsuario,
        array $liquidacionesUsuario,
        array $comprobantes,
        int   $anio
    ): array {
        $fechaDesde = "{$anio}-01-01";
        $fechaHasta = "{$anio}-12-31";

        // Indexar comprobantes por id para búsqueda rápida
        $comprobantesIdx = [];
        foreach ($comprobantes as $comp) {
            $comprobantesIdx[$comp['idComprobantesContables']] = $comp;
        }

        $periodosDetalle = [];

        foreach ($periodosUsuario as $periodo) {
            $subPeriodos = $this->_dividirEnSubPeriodos($periodo, $fechaDesde, $fechaHasta);

            foreach ($subPeriodos as $sub) {
                // Liquidaciones que caen en este sub-periodo
                $liqSub = $this->_filtrarLiquidacionesPorRango(
                    $liquidacionesUsuario, $sub['inicio'], $sub['fin']
                );

                // Agrupar las liquidaciones del sub-periodo por comprobante
                $liqPorComprobante = $this->_agruparLiquidacionesPorComprobante(
                    $liqSub, $comprobantesIdx
                );

                // Totales del sub-periodo
                $subtotal          = array_sum(array_column($liqSub, 'monto'));
                $totalSinComp      = array_sum(
                    array_column(array_filter($liqSub, fn($l) => $l['sin_comprobante']), 'monto')
                );

                // Presupuesto asignado para este sub-periodo
                $presupuestoSub = $this->_calcularPresupuestoSubPeriodo(
                    $periodo, $sub['inicio'], $sub['fin']
                );

                $periodosDetalle[] = [
                    'idUsuariosControlFechas' => $periodo['idUsuariosControlFechas'],
                    'puesto'                  => $periodo['puesto'],
                    'tipo_periodo'            => $sub['tipo_periodo'],
                    'fecha_inicio'            => $sub['inicio'],
                    'fecha_fin'               => $sub['fin'],
                    'porcentaje_presupuesto'  => $sub['tipo_periodo'] === 'prueba'
                        ? $periodo['porcentaje_presupuesto']
                        : 100,
                    'presupuesto_asignado'    => round($presupuestoSub, 2),
                    'subtotal_liquidado'      => round($subtotal, 2),
                    'sin_comprobante'         => round($totalSinComp, 2),
                    'diferencia'              => round($presupuestoSub - $subtotal, 2),

                    // Liquidaciones agrupadas por comprobante/mes
                    'liquidaciones_por_comprobante' => $liqPorComprobante,

                    // Todas las liquidaciones planas del sub-periodo
                    'liquidaciones' => $liqSub,
                ];
            }
        }

        return $periodosDetalle;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Resuelve el tipo de período (prueba / post_prueba / normal)
     * basándose en es_nuevo, dias_presupuesto y la fecha de hoy.
     *
     * @param array  $row
     * @param string $hoy Y-m-d
     * @return string
     */
    private function _resolverTipoPeriodo(array $row, string $hoy): string
    {
        if (!$row['es_nuevo'] || (int) $row['dias_presupuesto'] <= 0) {
            return 'normal';
        }

        $fechaFinPrueba = (new \DateTime($row['fecha_ingreso']))
            ->modify("+{$row['dias_presupuesto']} days")
            ->modify('-1 day')
            ->format('Y-m-d');

        return ($hoy <= $fechaFinPrueba) ? 'prueba' : 'post_prueba';
    }

    /**
     * Construye el array de resumen de periodos para la columna "Periodos"
     * de la tabla principal.
     *
     * @param array $periodos
     * @return array [ [ tipo, puesto, inicio, fin, porcentaje ], ... ]
     */
    private function _construirResumenPeriodos(array $periodos): array
    {
        $hoy     = date('Y-m-d');
        $resumen = [];

        foreach ($periodos as $p) {
            // Si tiene periodo de prueba, dividir en 2 entradas
            if ($p['es_nuevo'] && $p['dias_presupuesto'] > 0) {
                $fechaFinPrueba = (new \DateTime($p['fecha_ingreso']))
                    ->modify("+{$p['dias_presupuesto']} days")
                    ->modify('-1 day')
                    ->format('Y-m-d');

                // Entrada prueba
                $resumen[] = [
                    'tipo'                   => 'prueba',
                    'puesto'                 => $p['puesto'],
                    'fecha_inicio'           => $p['fecha_ingreso'],
                    'fecha_fin'              => $fechaFinPrueba,
                    'porcentaje_presupuesto' => $p['porcentaje_presupuesto'],
                ];

                // Entrada post-prueba (solo si ya terminó la prueba)
                $inicioPP = (new \DateTime($fechaFinPrueba))
                    ->modify('+1 day')
                    ->format('Y-m-d');

                $finReal = $p['fecha_egreso'] ?? date('Y-12-31');

                if ($inicioPP <= $finReal) {
                    $resumen[] = [
                        'tipo'                   => 'post_prueba',
                        'puesto'                 => $p['puesto'],
                        'fecha_inicio'           => $inicioPP,
                        'fecha_fin'              => $finReal,
                        'porcentaje_presupuesto' => 100,
                    ];
                }
            } else {
                $resumen[] = [
                    'tipo'                   => 'normal',
                    'puesto'                 => $p['puesto'],
                    'fecha_inicio'           => $p['fecha_ingreso'],
                    'fecha_fin'              => $p['fecha_egreso'] ?? date('Y-12-31'),
                    'porcentaje_presupuesto' => 100,
                ];
            }
        }

        return $resumen;
    }

    /**
     * Calcula el presupuesto anual real de un usuario sumando el presupuesto
     * de cada sub-periodo (prueba con %, post_prueba/normal al 100%)
     * recortado al rango del año indicado.
     *
     * Reemplaza a _calcularPresupuestoAnual (que sumaba monto_anual directamente
     * y no reflejaba días reales ni porcentajes de prueba).
     *
     * @param array $periodos  Periodos UCF del usuario
     * @param int   $anio
     * @return float
     */
    private function _calcularPresupuestoAnualPorDias(array $periodos, int $anio): float
    {
        $fechaDesde = "{$anio}-01-01";
        $fechaHasta = "{$anio}-12-31";
        $total      = 0.0;

        foreach ($periodos as $periodo) {
            $subPeriodos = $this->_dividirEnSubPeriodos($periodo, $fechaDesde, $fechaHasta);

            foreach ($subPeriodos as $sub) {
                $total += $this->_calcularPresupuestoSubPeriodo(
                    $periodo,
                    $sub['inicio'],
                    $sub['fin']
                );
            }
        }

        return $total;
    }

    /**
     * Calcula el presupuesto anual total de un usuario
     * sumando el monto_anual de cada periodo UCF (sin duplicar).
     *
     * @param array $periodos
     * @return float
     */
    private function _calcularPresupuestoAnual(array $periodos): float
    {
        $seen  = [];
        $total = 0.0;

        foreach ($periodos as $p) {
            $key = $p['idUsuariosControlFechas'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $total     += $p['monto_anual'];
            }
        }
        return $total;
    }

    /**
     * Divide un período UCF en sub-períodos (prueba / post_prueba / normal)
     * recortados al rango del año indicado.
     * Misma lógica que MantenimientoLiquidacionesHelper::dividirEnSubPeriodos.
     *
     * @param array  $periodo
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @return array [ ['inicio', 'fin', 'tipo_periodo'], ... ]
     */
    private function _dividirEnSubPeriodos(
        array  $periodo,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $inicio = max($periodo['fecha_ingreso'], $fechaDesde);
        $fin    = min($periodo['fecha_egreso'] ?? date('Y-12-31'), $fechaHasta);

        if (!$periodo['es_nuevo'] || $periodo['dias_presupuesto'] <= 0) {
            return [[ 'inicio' => $inicio, 'fin' => $fin, 'tipo_periodo' => 'normal' ]];
        }

        $fechaFinPrueba = (new \DateTime($periodo['fecha_ingreso']))
            ->modify("+{$periodo['dias_presupuesto']} days")
            ->modify('-1 day')
            ->format('Y-m-d');

        $subPeriodos = [];

        // Sub-período prueba
        $finSub1 = min($fechaFinPrueba, $fin);
        if ($inicio <= $finSub1) {
            $subPeriodos[] = [
                'inicio'       => $inicio,
                'fin'          => $finSub1,
                'tipo_periodo' => 'prueba',
            ];
        }

        // Sub-período post-prueba
        $inicioPP = (new \DateTime($fechaFinPrueba))
            ->modify('+1 day')
            ->format('Y-m-d');
        $inicioPP = max($inicioPP, $fechaDesde);

        if ($inicioPP <= $fin) {
            $subPeriodos[] = [
                'inicio'       => $inicioPP,
                'fin'          => $fin,
                'tipo_periodo' => 'post_prueba',
            ];
        }

        return $subPeriodos ?: [[ 'inicio' => $inicio, 'fin' => $fin, 'tipo_periodo' => 'normal' ]];
    }

    /**
     * Calcula el presupuesto asignado a un sub-período específico.
     * En prueba aplica el porcentaje, en normal/post_prueba usa el 100%.
     *
     * @param array  $periodo
     * @param string $inicio Y-m-d
     * @param string $fin    Y-m-d
     * @return float
     */
    private function _calcularPresupuestoSubPeriodo(
        array  $periodo,
        string $inicio,
        string $fin
    ): float {
        $dias       = (new \DateTime($inicio))->diff(new \DateTime($fin))->days + 1;
        $diario     = $periodo['monto_diario'];
        $porcentaje = $periodo['porcentaje_presupuesto'] / 100;

        // En prueba se aplica el porcentaje; post_prueba/normal = 100%
        $tipoPeriodo = $this->_resolverTipoPeriodo(
            ['es_nuevo' => $periodo['es_nuevo'], 'dias_presupuesto' => $periodo['dias_presupuesto'], 'fecha_ingreso' => $inicio],
            $fin
        );

        $factor = ($tipoPeriodo === 'prueba') ? $porcentaje : 1.0;

        return $diario * $dias * $factor;
    }

    /**
     * Filtra en memoria las liquidaciones que caen en un rango de fechas.
     *
     * @param array  $liquidaciones
     * @param string $desde Y-m-d
     * @param string $hasta Y-m-d
     * @return array
     */
    private function _filtrarLiquidacionesPorRango(
        array  $liquidaciones,
        string $desde,
        string $hasta
    ): array {
        return array_values(array_filter($liquidaciones, function ($liq) use ($desde, $hasta) {
            $fecha = substr($liq['fecha_liquidacion'], 0, 10);
            return $fecha >= $desde && $fecha <= $hasta;
        }));
    }

    /**
     * Agrupa liquidaciones por comprobante contable para el modal de detalle.
     * Las que no tienen comprobante van al grupo 'sin_comprobante'.
     *
     * @param array $liquidaciones     Liquidaciones del sub-periodo
     * @param array $comprobantesIdx   Comprobantes indexados por id
     * @return array [
     *   [
     *     idComprobante    => int|null,
     *     numero           => string|null,
     *     mes_label        => string|null,
     *     subtotal         => float,
     *     liquidaciones    => array,
     *   ], ...
     * ]
     */
    private function _agruparLiquidacionesPorComprobante(
        array $liquidaciones,
        array $comprobantesIdx
    ): array {
        $grupos = [];

        foreach ($liquidaciones as $liq) {
            $cid = $liq['comprobantecontableid'];
            $key = $cid ?? 'sin_comprobante';

            if (!isset($grupos[$key])) {
                $comp = $cid ? ($comprobantesIdx[$cid] ?? null) : null;
                $grupos[$key] = [
                    'idComprobante' => $cid,
                    'numero'        => $comp['numero_comprobante'] ?? null,
                    'mes_label'     => $comp['mes_label'] ?? null,
                    'mes_key'       => $comp['mes_key'] ?? null,
                    'subtotal'      => 0.0,
                    'liquidaciones' => [],
                ];
            }

            $grupos[$key]['subtotal']       += $liq['monto'];
            $grupos[$key]['liquidaciones'][] = $liq;
        }

        // Redondear subtotales y reindexar
        foreach ($grupos as &$g) {
            $g['subtotal'] = round($g['subtotal'], 2);
        }

        return array_values($grupos);
    }

    /**
     * Mapea una fila cruda de liquidación al formato interno estandarizado.
     *
     * @param array $row
     * @return array
     */
    private function _mapearLiquidacion(array $row): array
    {
        return [
            'idLiquidaciones'         => (int)   $row['idLiquidaciones'],
            'numero_factura'          =>          $row['numero_factura'],
            'monto'                   => (float)  $row['monto'],
            'monto_factura'           => (float)  $row['monto_factura'],
            'descripcion'             =>          $row['descripcion'],
            'detalle'                 =>          $row['detalle'],
            'estado'                  =>          $row['estado'],
            'fecha_liquidacion'       =>          $row['fecha_liquidacion'],
            'comprobantecontableid'   => isset($row['comprobantecontableid'])
                ? (int) $row['comprobantecontableid']
                : null,
            'numero_comprobante'      =>          $row['numero_comprobante'] ?? null,
            'mes_anio_comprobante'    =>          $row['mes_anio_comprobante'] ?? null,
            'aprobaciongerenciaid'    => isset($row['aprobaciongerenciaid'])
                ? (int) $row['aprobaciongerenciaid']
                : null,
            'tipo_apoyo'              =>          $row['tipo_apoyo'],
            'vehiculo'                =>          $row['vehiculo'],
            'placa'                   =>          $row['placa'],
            'sin_comprobante'         => (bool)   $row['sin_comprobante'],
        ];
    }
}