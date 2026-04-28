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
 * │ Construcción de filas        │ construirFilasUsuario (multi-agencia),  │
 * │                              │ construirFilaUsuario (fila individual)  │
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

    /** @var PresupuestoRangoHelper */
    private $presupuestoRangoHelper;

    /**
     * Estados de liquidación que se consideran para esta vista.
     * - aprobada  : aprobada pero aún sin comprobante asignado
     * - en_lote   : ya tiene comprobantecontableid asignado
     */
    private const ESTADOS_VALIDOS = ['aprobada', 'en_lote', 'de_baja'];

    /**
     * @param PDO $connect Conexión PDO activa
     */
    public function __construct(PDO $connect)
    {
        $this->connect                = $connect;
        $this->presupuestoRangoHelper = new PresupuestoRangoHelper($connect);
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
                    us.idUsuarios AS usuarioid,
                    cli.CodigoCliente,
                    dtp.nombres AS NombreUsuario,
                    ag.nombre AS agencia,
                    pu.nombre AS puesto_actual,
                    us.idEstados AS idestado,
                    (
                        SELECT
                            cap2.NumeroCuenta
                        FROM
                            asociado_t24.ccomndatoscaptaciones AS cap2
                        WHERE
                            cap2.Cliente = cli.CodigoCliente
                            AND cap2.Producto = '114A.AHORRO.DISPONIBLE'
                        ORDER BY
                            cap2.NumeroCuenta
                            LIMIT 1
                    ) AS PrimeraCuenta,
                    es.estado AS estado_usuario
                FROM
                    apoyo_combustibles.liquidaciones AS l
                    INNER JOIN dbintranet.usuarios AS us ON us.idUsuarios = l.usuarioid
                    LEFT JOIN dbintranet.datospersonales AS dtp ON dtp.idDatosPersonales = us.idDatosPersonales
                    LEFT JOIN asociado_t24.comndatosclientes AS cli ON dtp.dpi = cli.Dpi
                    INNER JOIN dbintranet.agencia AS ag ON ag.idAgencia = us.idAgencia
                    INNER JOIN dbintranet.puesto AS pu ON pu.idPuesto = us.idPuesto
                    INNER JOIN dbintranet.estados AS es ON es.idEstados = us.idEstados
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
                    ucf.agenciaid,
                    ucf.puestoid,
                    ucf.fecha_ingreso,
                    ucf.fecha_egreso,
                    ucf.es_nuevo,
                    ucf.porcentaje_presupuesto,
                    ucf.dias_presupuesto,
                    ag.nombre  AS agencia,
                    pu.nombre  AS puesto,
                    pg.monto_anual,
                    pg.monto_mensual
                FROM apoyo_combustibles.usuarioscontrolfechas AS ucf
                LEFT JOIN dbintranet.agencia AS ag
                    ON ag.idAgencia = ucf.agenciaid
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
                'idUsuariosControlFechas' => (int)   $row['idUsuariosControlFechas'],
                'agenciaid'               => (int)   $row['agenciaid'],
                'puestoid'                => (int)   $row['puestoid'],
                'agencia'                 =>          $row['agencia'],
                'puesto'                  =>          $row['puesto'],
                'fecha_ingreso'           =>          $row['fecha_ingreso'],
                // inicio_efectivo: alias requerido por PresupuestoRangoHelper::calcularPorRango
                'inicio_efectivo'         =>          $row['fecha_ingreso'],
                'fecha_egreso'            =>          $row['fecha_egreso'],
                'es_nuevo'                => (bool)   $row['es_nuevo'],
                'porcentaje_presupuesto'  => (int)    $row['porcentaje_presupuesto'],
                'dias_presupuesto'        => (int)    $row['dias_presupuesto'],
                'monto_anual'             => (float) ($row['monto_anual']   ?? 0),
                'monto_mensual'           => (float) ($row['monto_mensual'] ?? 0),
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
                        WHEN l.comprobantecontableid IS NULL AND l.estado = 'aprobada' THEN 1 ELSE 0
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
                        WHEN l.comprobantecontableid IS NULL AND l.estado = 'aprobada' THEN 1 ELSE 0
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
    // CONFIGURACIÓN PORCENTAJE ANUAL
    // =========================================================================

    /**
     * Obtiene el porcentaje activo de la tabla configuracionporcentajeanual.
     * Retorna 0.0 si no hay configuración activa.
     *
     * @return float  Porcentaje (ej: 50.00 = 50%)
     */
    public function obtenerPorcentajeEjercicio(): float
    {
        $sql = "SELECT porcentaje
                FROM apoyo_combustibles.configuracionporcentajeanual
                WHERE activo = 1
                ORDER BY created_at DESC
                LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute();
        return (float) ($stmt->fetchColumn() ?: 0.0);
    }



    /**
     * Retorna un mapa [ ucfId => monto_total ] para múltiples UCFs.
     * Los que no tienen registros quedan en 0.0.
     *
     * @param int[] $ucfIds
     * @param int   $anio   Filtra por año de created_at del extraordinario
     * @return array [ ucfId => float ]
     */
    public function obtenerExtaordinariosAgrupados(array $ucfIds, int $anio): array
    {
        if (empty($ucfIds)) return [];

        $placeholders = implode(',', array_fill(0, count($ucfIds), '?'));

        $sql = "SELECT re.ucfid, COALESCE(SUM(re.monto), 0) AS total
                FROM apoyo_combustibles.registros_extraordinarios re
                WHERE re.ucfid  IN ($placeholders)
                  AND re.activo  = 1
                  AND re.estado != 'cancelado'
                  AND YEAR(re.created_at) = ?
                GROUP BY re.ucfid";

        $params = array_merge($ucfIds, [$anio]);
        $stmt   = $this->connect->prepare($sql);
        $stmt->execute($params);

        $resultado = array_fill_keys($ucfIds, 0.0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resultado[(int) $row['ucfid']] = (float) $row['total'];
        }
        return $resultado;
    }

    /**
     * Retorna los registros extraordinarios de UN usuario en el año,
     * con detalle completo para el modal.
     *
     * @param string $usuarioid
     * @param int    $anio
     * @return array  [ ucfId => [ registro, ... ] ]  — indexado por ucfid
     */
    public function obtenerExtaordinariosPorUsuario(string $usuarioid, int $anio): array
    {
        $sql = "SELECT
                    re.idRegistroExtraordinario,
                    re.ucfid,
                    re.usuarioid,
                    re.monto,
                    re.descripcion,
                    re.tipo,
                    re.estado,
                    re.activo,
                    re.created_at
                FROM apoyo_combustibles.registros_extraordinarios re
                WHERE re.usuarioid  = ?
                  AND re.activo     = 1
                  AND re.estado    != 'cancelado'
                  AND YEAR(re.created_at) = ?
                ORDER BY re.ucfid ASC, re.created_at ASC";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$usuarioid, $anio]);

        $agrupado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ucfid = (int) $row['ucfid'];
            if (!isset($agrupado[$ucfid])) $agrupado[$ucfid] = [];
            $agrupado[$ucfid][] = [
                'idRegistroExtraordinario' => (int)   $row['idRegistroExtraordinario'],
                'ucfid'                    => $ucfid,
                'monto'                    => (float) $row['monto'],
                'descripcion'              =>         $row['descripcion'],
                'tipo'                     =>         $row['tipo'],
                'estado'                   =>         $row['estado'],
                'created_at'               =>         $row['created_at'],
            ];
        }
        return $agrupado;
    }

    // =========================================================================
    // CONFIGURACIÓN DE PORCENTAJE ANUAL
    // =========================================================================

    /**
     * Retorna el porcentaje activo de configuracion_porcentaje_anual.
     * Se aplica sobre el resultado favorable (presupuesto - ejecutado) cuando no hay sobregiro.
     *
     * @return float  ej: 15.00 = 15%
     */
    public function obtenerPorcentajeConfiguracion(): float
    {
        $sql = "SELECT porcentaje
                FROM apoyo_combustibles.configuracionporcentajeanual
                WHERE activo = 1
                ORDER BY created_at DESC
                LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result ? (float) $result : 0.0;
    }

    /**
     * Construye una fila por cada combinación agencia/puesto que el usuario
     * haya tenido durante el año. Si el usuario nunca cambió de agencia ni de
     * puesto, devuelve un array con una única fila (comportamiento equivalente
     * a construirFilaUsuario).
     *
     * Algoritmo:
     *  1. Agrupa los periodos UCF por clave "{agenciaid}_{puestoid}".
     *  2. Para cada grupo, filtra las liquidaciones cuya fecha_liquidacion cae
     *     dentro del rango total de ese grupo (min fecha_ingreso – max fecha_egreso).
     *  3. Delega la construcción de cada fila a construirFilaUsuario.
     *
     * @param string $usuarioid
     * @param array  $datosUsuario         [ CodigoCliente, NombreUsuario, ... ]
     * @param array  $periodosUsuario      Periodos UCF del usuario (con agenciaid, puestoid, agencia)
     * @param array  $liquidacionesUsuario Todas las liquidaciones del año del usuario
     * @param array  $comprobantes         Comprobantes del año (columnas dinámicas)
     * @param int    $anio
     * @return array  [ fila, ... ]  — una fila por grupo agencia/puesto
     */
    public function construirFilasUsuario(
        string $usuarioid,
        array  $datosUsuario,
        array  $periodosUsuario,
        array  $liquidacionesUsuario,
        array  $comprobantes,
        int    $anio
    ): array {
        // ── 1. Agrupar periodos por combinación agencia/puesto ────────────────
        $grupos = [];
        foreach ($periodosUsuario as $periodo) {
            $clave = $periodo['agenciaid'] . '_' . $periodo['puestoid'];

            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'agenciaid' => $periodo['agenciaid'],
                    'puestoid'  => $periodo['puestoid'],
                    'agencia'   => $periodo['agencia'],
                    'puesto'    => $periodo['puesto'],
                    'periodos'  => [],
                ];
            }
            $grupos[$clave]['periodos'][] = $periodo;
        }

        // ── 2. Construir una fila por grupo ───────────────────────────────────
        $filas = [];
        foreach ($grupos as $grupo) {
            // Rango efectivo del grupo dentro del año
            $inicioGrupo = min(array_column($grupo['periodos'], 'fecha_ingreso'));
            $finGrupo    = max(array_map(
                fn($p) => $p['fecha_egreso'] ?? "{$anio}-12-31",
                $grupo['periodos']
            ));

            // Liquidaciones que caen dentro del rango del grupo
            $liqGrupo = $this->_filtrarLiquidacionesPorRango(
                $liquidacionesUsuario, $inicioGrupo, $finGrupo
            );

            // Sobreescribir agencia y puesto_actual con los del grupo
            $datosGrupo = array_merge($datosUsuario, [
                'agencia'       => $grupo['agencia'],
                'puesto_actual' => $grupo['puesto'],
            ]);

            $filas[] = $this->construirFilaUsuario(
                $usuarioid,
                $datosGrupo,
                $grupo['periodos'],
                $liqGrupo,
                $comprobantes,
                $anio
            );
        }

        return $filas;
    }

    /**
     * Construye la fila completa de un usuario para la tabla principal.
     *
     * - Columnas dinámicas de meses: suma de liquidaciones por comprobante
     * - Sin comprobante: suma de liquidaciones sin comprobantecontableid
     * - Presupuesto ejecutado: total anual de todas sus liquidaciones válidas
     * - Presupuesto anual: calculado por días con tarifa mensual variable
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
        int    $anio,
        array  $extraordinariosPorUcf = [],   // [ ucfId => monto_total ]
        float  $porcentajeEjercicio   = 0.0   // porcentaje activo de configuracionporcentajeanual
    ): array {
        // ── Montos por comprobante ────────────────────────────────────────────
        $montosPorComprobante = [];
        foreach ($comprobantes as $comp) {
            $montosPorComprobante[$comp['idComprobantesContables']] = 0.0;
        }

        $totalSinComprobante = 0.0;
        $totalLiquidado      = 0.0;
        $totalDeBaja         = 0.0;

        foreach ($liquidacionesUsuario as $liq) {
            $totalLiquidado += $liq['monto'];

            // de_baja: se acumula por separado y NO entra en columnas de comprobante
            if ($liq['estado'] === 'de_baja') {
                $totalDeBaja += $liq['monto'];
                continue;
            }

            // aprobada / en_lote: distribuir según si tiene comprobante o no
            if ($liq['sin_comprobante']) {
                $totalSinComprobante += $liq['monto'];
            } elseif (isset($montosPorComprobante[$liq['comprobantecontableid']])) {
                $montosPorComprobante[$liq['comprobantecontableid']] += $liq['monto'];
            }
        }

        // ── Extraordinarios: suma de todos los UCFs del usuario ───────────────
        $totalExtaordinarios = 0.0;
        foreach ($periodosUsuario as $p) {
            $ucfid = $p['idUsuariosControlFechas'];
            if ($ucfid && isset($extraordinariosPorUcf[$ucfid])) {
                $totalExtaordinarios += $extraordinariosPorUcf[$ucfid];
            }
        }

        // ── Total ejecutado = liquidaciones + extraordinarios ─────────────────
        $totalEjecutado = $totalLiquidado + $totalExtaordinarios;

        // ── Presupuesto anual (suma de todos los periodos UCF) ────────────────
        $presupuestoAnual = $this->_calcularPresupuestoAnualPorDias($periodosUsuario, $anio);

        // ── Resumen de periodos para la columna "Periodos" ────────────────────
        $periodosResumen = $this->_construirResumenPeriodos($periodosUsuario);

        // ── Diferencia y resultado del ejercicio ──────────────────────────────
        $diferencia = $presupuestoAnual - $totalEjecutado;

        // Si hay saldo a favor, se aplica el porcentaje configurado.
        // Si hay sobregiro, el resultado es la diferencia negativa directamente.
        $resultadoEjercicio = $diferencia > 0
            ? $diferencia * ($porcentajeEjercicio / 100)
            : $diferencia;

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
            'montos_por_comprobante' => $montosPorComprobante,

            // Totales
            'sin_comprobante'        => round($totalSinComprobante, 2),
            'total_de_baja'          => round($totalDeBaja, 2),
            'total_extraordinarios'  => round($totalExtaordinarios, 2),
            'presupuesto_ejecutado'  => round($totalEjecutado, 2),
            'presupuesto_anual'      => round($presupuestoAnual, 2),
            'sobregiro'              => round($sobregiro, 2),
            'tiene_sobregiro'        => $sobregiro > 0,

            // Resultado del ejercicio
            'diferencia'             => round($diferencia, 2),
            'porcentaje_ejercicio'   => $porcentajeEjercicio,
            'resultado_ejercicio'    => round($resultadoEjercicio, 2),
            'tiene_resultado_favor'  => $diferencia > 0,
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
        int   $anio,
        array $extraordinariosPorUcf = []   // [ ucfId => [ registro, ... ] ]
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
            $ucfid       = $periodo['idUsuariosControlFechas'];

            // Extraordinarios del UCF (solo se muestran en el primer sub-período, prueba o normal)
            $extrasUcf      = $ucfid ? ($extraordinariosPorUcf[$ucfid] ?? []) : [];
            $montoExtras    = array_sum(array_column($extrasUcf, 'monto'));
            $extrasAsignados = false;

            foreach ($subPeriodos as $sub) {
                // Liquidaciones que caen en este sub-periodo
                $liqSub = $this->_filtrarLiquidacionesPorRango(
                    $liquidacionesUsuario, $sub['inicio'], $sub['fin']
                );

                // Agrupar por comprobante para display en el modal.
                // Se pasan TODAS las liquidaciones (incluye de_baja con estilo rojo).
                // El subtotal del grupo excluye de_baja internamente.
                $liqPorComprobante = $this->_agruparLiquidacionesPorComprobante(
                    $liqSub, $comprobantesIdx
                );

                // Totales del sub-periodo
                $subtotalLiq  = array_sum(array_column($liqSub, 'monto'));
                $totalDeBaja  = array_sum(
                    array_column(array_filter($liqSub, fn($l) => $l['estado'] === 'de_baja'), 'monto')
                );
                // sin_comprobante: solo aprobada sin comprobante (el SQL ya lo garantiza,
                // pero filtramos explícitamente para consistencia)
                $totalSinComp = array_sum(
                    array_column(array_filter($liqSub, fn($l) => $l['sin_comprobante'] && $l['estado'] !== 'de_baja'), 'monto')
                );

                // Los extraordinarios se asignan solo al primer sub-período del UCF
                $extrasSubPeriodo = 0.0;
                $extrasDetalle    = [];
                if (!$extrasAsignados && $montoExtras > 0) {
                    $extrasSubPeriodo = $montoExtras;
                    $extrasDetalle    = $extrasUcf;
                    $extrasAsignados  = true;
                }

                $subtotalTotal = $subtotalLiq + $extrasSubPeriodo;

                // Presupuesto asignado para este sub-periodo
                $presupuestoSub = $this->_calcularPresupuestoSubPeriodo(
                    $periodo, $sub['inicio'], $sub['fin'], $sub['tipo_periodo']
                );

                $periodosDetalle[] = [
                    'idUsuariosControlFechas' => $ucfid,
                    'puesto'                  => $periodo['puesto'],
                    'tipo_periodo'            => $sub['tipo_periodo'],
                    'fecha_inicio'            => $sub['inicio'],
                    'fecha_fin'               => $sub['fin'],
                    'porcentaje_presupuesto'  => $sub['tipo_periodo'] === 'prueba'
                        ? $periodo['porcentaje_presupuesto']
                        : 100,
                    'presupuesto_asignado'    => round($presupuestoSub, 2),
                    'subtotal_liquidado'      => round($subtotalLiq, 2),
                    'total_de_baja'           => round($totalDeBaja, 2),
                    'sin_comprobante'         => round($totalSinComp, 2),
                    'total_extraordinarios'   => round($extrasSubPeriodo, 2),
                    'subtotal_ejecutado'      => round($subtotalTotal, 2),
                    'diferencia'              => round($presupuestoSub - $subtotalTotal, 2),

                    // Extraordinarios con detalle (solo en el sub-período que los tiene)
                    'extraordinarios'               => $extrasDetalle,

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

                $fechaEgreso      = $p['fecha_egreso'] ?? null;
                $egresoAnticipado = $fechaEgreso && $fechaEgreso < $fechaFinPrueba;

                // Entrada prueba: si hubo egreso anticipado, mostrar la fecha real de salida
                $resumen[] = [
                    'tipo'                   => 'prueba',
                    'puesto'                 => $p['puesto'],
                    'fecha_inicio'           => $p['fecha_ingreso'],
                    'fecha_fin'              => $egresoAnticipado ? $fechaEgreso : $fechaFinPrueba,
                    'porcentaje_presupuesto' => $p['porcentaje_presupuesto'],
                ];

                // Post-prueba: solo existe si el usuario completó la prueba
                // (si hubo egreso anticipado, no hay tramo post-prueba)
                if (!$egresoAnticipado) {
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
     * Calcula el presupuesto anual real de un usuario delegando en
     * PresupuestoRangoHelper::calcularPorRango, que ya gestiona internamente
     * la división prueba/post-prueba y la tarifa mensual variable.
     *
     * @param array $periodos Periodos UCF del usuario (con monto_mensual e inicio_efectivo)
     * @param int   $anio
     * @return float
     */
    private function _calcularPresupuestoAnualPorDias(array $periodos, int $anio): float
    {
        $fechaDesde = "{$anio}-01-01";
        $fechaHasta = "{$anio}-12-31";
        $total      = 0.0;

        foreach ($periodos as $periodo) {
            // Recortar el período al año en análisis
            $inicio = max($periodo['fecha_ingreso'], $fechaDesde);
            $fin    = min($periodo['fecha_egreso'] ?? $fechaHasta, $fechaHasta);

            if ($inicio > $fin) continue;

            $total += $this->presupuestoRangoHelper->calcularPorRango($periodo, $inicio, $fin);
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
     * Calcula el presupuesto asignado a un sub-período específico
     * usando tarifa mensual variable (monto_mensual / días_del_mes).
     * En 'prueba' aplica el porcentaje reducido; en 'post_prueba'/'normal' usa 100%.
     *
     * @param array  $periodo
     * @param string $inicio      Y-m-d
     * @param string $fin         Y-m-d
     * @param string $tipoPeriodo 'prueba' | 'post_prueba' | 'normal'
     * @return float
     */
    private function _calcularPresupuestoSubPeriodo(
        array  $periodo,
        string $inicio,
        string $fin,
        string $tipoPeriodo = 'normal'
    ): float {
        $montoMensual = (float) ($periodo['monto_mensual'] ?? 0);
        $porcentaje   = ($tipoPeriodo === 'prueba')
            ? ($periodo['porcentaje_presupuesto'] ?? 100) / 100
            : 1.0;

        // Delegar a calcularPorRango, que internamente decide:
        // - prueba completa (fin == fin natural): calcularRangoPrueba (denominador combinado)
        // - prueba con egreso anticipado (fin < fin natural): calcularRangoPruebaAcumulado
        //   (mismo denominador pero solo días laborados hasta el egreso)
        // - post_prueba / normal: calcularRangoMensual (tarifa mensual variable)
        return $this->presupuestoRangoHelper->calcularPorRango($periodo, $inicio, $fin);
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
     * Todas las liquidaciones (incluyendo de_baja) se muestran en la tabla,
     * pero el subtotal del grupo excluye las de_baja (que tienen su propio total).
     *
     * @param array $liquidaciones     Liquidaciones del sub-periodo (todos los estados)
     * @param array $comprobantesIdx   Comprobantes indexados por id
     * @return array
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
                    'idComprobante'  => $cid,
                    'numero'         => $comp['numero_comprobante'] ?? null,
                    'mes_label'      => $comp['mes_label'] ?? null,
                    'mes_key'        => $comp['mes_key'] ?? null,
                    'subtotal'       => 0.0,   // solo aprobada/en_lote
                    'total_de_baja'  => 0.0,   // solo de_baja del grupo
                    'liquidaciones'  => [],     // todas para display
                ];
            }

            // El subtotal del grupo excluye de_baja (se acumulan aparte)
            if ($liq['estado'] === 'de_baja') {
                $grupos[$key]['total_de_baja'] += $liq['monto'];
            } else {
                $grupos[$key]['subtotal'] += $liq['monto'];
            }

            $grupos[$key]['liquidaciones'][] = $liq;
        }

        foreach ($grupos as &$g) {
            $g['subtotal']      = round($g['subtotal'], 2);
            $g['total_de_baja'] = round($g['total_de_baja'], 2);
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

    /**
     * Genera un período sintético para usuarios sin registros en usuarioscontrolfechas.
     * Usa su agencia/puesto actual y el presupuestogeneral del año.
     *
     * @param array $usuariosIds  Solo los IDs que NO tienen periodos UCF
     * @param int   $anio
     * @return array [ usuarioid => [ periodo_sintetico ] ]
     */
    public function obtenerPeriodoFallbackPorUsuarios(array $usuariosIds, int $anio): array
    {
        if (empty($usuariosIds)) return [];

        $placeholders = implode(',', array_fill(0, count($usuariosIds), '?'));

        $sql = "SELECT
                us.idUsuarios   AS usuarioid,
                us.idAgencia    AS agenciaid,
                us.idPuesto     AS puestoid,
                ag.nombre       AS agencia,
                pu.nombre       AS puesto,
                pg.monto_anual,
                pg.monto_mensual
            FROM dbintranet.usuarios AS us
            INNER JOIN dbintranet.agencia AS ag
                ON ag.idAgencia = us.idAgencia
            INNER JOIN dbintranet.puesto AS pu
                ON pu.idPuesto = us.idPuesto
            LEFT JOIN apoyo_combustibles.presupuestogeneral AS pg
                ON  pg.agenciaid = us.idAgencia
                AND pg.puestoid  = us.idPuesto
                AND pg.anio      = ?
                AND pg.activo    = 1
            WHERE us.idUsuarios IN ($placeholders)";

        $params = array_merge([$anio], $usuariosIds);
        $stmt   = $this->connect->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $row) {
            $uid = $row['usuarioid'];
            $resultado[$uid] = [[
                'idUsuariosControlFechas' => null,        // sintético, no existe en BD
                'agenciaid'               => (int)  $row['agenciaid'],
                'puestoid'                => (int)  $row['puestoid'],
                'agencia'                 =>         $row['agencia'],
                'puesto'                  =>         $row['puesto'],
                'fecha_ingreso'           => "{$anio}-01-01",
                'inicio_efectivo'         => "{$anio}-01-01",  // alias requerido por PresupuestoRangoHelper
                'fecha_egreso'            => null,
                'es_nuevo'                => false,
                'porcentaje_presupuesto'  => 100,
                'dias_presupuesto'        => 0,
                'monto_anual'             => (float) ($row['monto_anual']   ?? 0),
                'monto_mensual'           => (float) ($row['monto_mensual'] ?? 0),
                'tipo_periodo'            => 'normal',
            ]];
        }

        return $resultado;
    }


// ============================================================================
// MÉTODOS DE APROBACIÓN — agregar a EnvioPagoLiquidacionesHelper
// ============================================================================
// Pegar estos métodos dentro de la clase EnvioPagoLiquidacionesHelper,
// debajo de la sección "DETALLE MODAL".
// ============================================================================

// =========================================================================
// FLUJO DE APROBACIÓN — SOLICITUDES A GERENCIA
// =========================================================================

    /**
     * Devuelve el idEstadosAprobacion de un código dado.
     * Lanza excepción si el código no existe (indica configuración incompleta).
     *
     * @param string $codigo  'pendiente' | 'aprobada' | 'rechazada'
     * @return int
     */
    private function _obtenerIdEstado(string $codigo): int
    {
        $sql  = "SELECT idEstadosAprobacion
             FROM apoyo_combustibles.estadosaprobacion
             WHERE codigo = ? AND activo = 1
             LIMIT 1";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$codigo]);
        $id   = $stmt->fetchColumn();

        if (!$id) {
            throw new \RuntimeException("Estado de aprobación '{$codigo}' no encontrado en BD.");
        }

        return (int) $id;
    }

    /**
     * Registra un cambio de estado en aprobacionesgerencialog.
     *
     * @param int         $aprobacionId
     * @param int|null    $estadoAnterior
     * @param int         $estadoNuevo
     * @param string      $usuarioid
     * @param string|null $motivoRechazo
     * @param string|null $observaciones
     */
    private function _registrarLog(
        int     $aprobacionId,
        ?int    $estadoAnterior,
        int     $estadoNuevo,
        string  $usuarioid,
        ?string $motivoRechazo = null,
        ?string $observaciones = null
    ): void {
        $sql  = "INSERT INTO apoyo_combustibles.aprobacionesgerencialog
                 (aprobaciongerenciaid, estadoaprobacion_anterior, estadoaprobacion_nuevo,
                  usuarioid, motivo_rechazo, observaciones)
             VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([
            $aprobacionId,
            $estadoAnterior,
            $estadoNuevo,
            $usuarioid,
            $motivoRechazo,
            $observaciones,
        ]);
    }

// ─────────────────────────────────────────────────────────────────────────────

    /**
     * Obtiene la solicitud de aprobación más reciente (sin importar estado).
     * Incluye nombre del estado, conteo de liquidaciones vinculadas y log.
     *
     * @return array|null  null si nunca se ha creado una solicitud.
     */
    public function obtenerSolicitudActiva(): ?array
    {
        $sql = "SELECT
                ag.idAprobacionesGerencia,
                ag.descripcion,
                ag.estadoaprobacionid,
                ea.codigo  AS estado_codigo,
                ea.nombre  AS estado_nombre,
                -- Datos del Solicitante
                dp_solicita.nombres AS enviado_por,
                ag.fecha_envio,
                -- Datos del Revisor
                dp_revisa.nombres AS revisado_por,
                ag.fecha_revision,
                ag.motivo_rechazo,
                ag.observaciones,
                ag.created_at,
                ag.updated_at,
                -- Liquidaciones vinculadas a esta solicitud
                COUNT(l.idLiquidaciones)    AS total_liquidaciones,
                COALESCE(SUM(l.monto), 0)  AS monto_total,
                -- Liquidaciones sin vincular (aprobadas, sin comprobante, sin solicitud)
                COALESCE(lsv.total_sin_vincular, 0) AS liquidaciones_sin_vincular,
                COALESCE(lsv.monto_sin_vincular, 0) AS monto_sin_vincular
            FROM apoyo_combustibles.aprobacionesgerencia ag
            INNER JOIN apoyo_combustibles.estadosaprobacion ea
                ON ea.idEstadosAprobacion = ag.estadoaprobacionid
            -- Solicitante
            LEFT JOIN dbintranet.usuarios u_solicita
                ON u_solicita.idUsuarios = ag.enviado_por
            LEFT JOIN dbintranet.datospersonales dp_solicita
                ON dp_solicita.idDatosPersonales = u_solicita.idDatosPersonales
            -- Revisor
            LEFT JOIN dbintranet.usuarios u_revisa
                ON u_revisa.idUsuarios = ag.revisado_por
            LEFT JOIN dbintranet.datospersonales dp_revisa
                ON dp_revisa.idDatosPersonales = u_revisa.idDatosPersonales
            -- Liquidaciones vinculadas a la solicitud
            LEFT JOIN apoyo_combustibles.liquidaciones l
                ON l.aprobaciongerenciaid = ag.idAprobacionesGerencia
            -- Tabla derivada: liquidaciones aprobadas sin comprobante y sin solicitud
            LEFT JOIN (
                SELECT
                    COUNT(*)           AS total_sin_vincular,
                    COALESCE(SUM(monto), 0) AS monto_sin_vincular
                FROM apoyo_combustibles.liquidaciones
                WHERE estado                = 'aprobada'
                  AND comprobantecontableid IS NULL
                  AND aprobaciongerenciaid  IS NULL
            ) AS lsv ON TRUE
            GROUP BY
                ag.idAprobacionesGerencia,
                ea.idEstadosAprobacion,
                dp_solicita.idDatosPersonales,
                dp_revisa.idDatosPersonales,
                lsv.total_sin_vincular,
                lsv.monto_sin_vincular
            ORDER BY ag.idAprobacionesGerencia DESC
            LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute();
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) return null;

        // ── Log de cambios de estado ──────────────────────────────────────────────
        $sqlLog = "SELECT
                    agl.idAprobacionesGerenciaLog,
                    agl.created_at,
                    dp.nombres AS usuarioid,
                    agl.motivo_rechazo,
                    agl.observaciones,
                    ea_ant.codigo AS estado_anterior_codigo,
                    ea_ant.nombre AS estado_anterior_nombre,
                    ea_nvo.codigo AS estado_nuevo_codigo,
                    ea_nvo.nombre AS estado_nuevo_nombre
                FROM apoyo_combustibles.aprobacionesgerencialog agl
                LEFT JOIN dbintranet.usuarios u
                    ON u.idUsuarios = agl.usuarioid
                LEFT JOIN dbintranet.datospersonales dp
                    ON dp.idDatosPersonales = u.idDatosPersonales
                LEFT JOIN apoyo_combustibles.estadosaprobacion ea_ant
                    ON ea_ant.idEstadosAprobacion = agl.estadoaprobacion_anterior
                INNER JOIN apoyo_combustibles.estadosaprobacion ea_nvo
                    ON ea_nvo.idEstadosAprobacion = agl.estadoaprobacion_nuevo
                WHERE agl.aprobaciongerenciaid = ?
                ORDER BY agl.idAprobacionesGerenciaLog ASC";

        $stmtLog = $this->connect->prepare($sqlLog);
        $stmtLog->execute([(int) $row['idAprobacionesGerencia']]);
        $log = $stmtLog->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'idAprobacionesGerencia'     => (int)   $row['idAprobacionesGerencia'],
            'descripcion'                =>          $row['descripcion'],
            'estadoaprobacionid'         => (int)   $row['estadoaprobacionid'],
            'estado_codigo'              =>          $row['estado_codigo'],
            'estado_nombre'              =>          $row['estado_nombre'],
            'enviado_por'                =>          $row['enviado_por'],
            'fecha_envio'                =>          $row['fecha_envio'],
            'revisado_por'               =>          $row['revisado_por'],
            'fecha_revision'             =>          $row['fecha_revision'],
            'motivo_rechazo'             =>          $row['motivo_rechazo'],
            'observaciones'              =>          $row['observaciones'],
            'created_at'                 =>          $row['created_at'],
            'updated_at'                 =>          $row['updated_at'],
            'total_liquidaciones'        => (int)   $row['total_liquidaciones'],
            'monto_total'                => (float) $row['monto_total'],
            'liquidaciones_sin_vincular' => (int)   $row['liquidaciones_sin_vincular'],
            'monto_sin_vincular'         => (float) $row['monto_sin_vincular'],
            'log'                        =>          $log,
        ];
    }


// ─────────────────────────────────────────────────────────────────────────────

// ════════════════════════════════════════════════════════════════════════════
// MÉTODO 1: enviarSolicitud() — en EnvioPagoLiquidacionesHelper
// ════════════════════════════════════════════════════════════════════════════
// CAMBIO: Agregar AND aprobaciongerenciaid IS NULL tanto en el COUNT como
//         en el UPDATE de vinculación.
//
// Razón: Al crear una nueva solicitud, solo se deben vincular liquidaciones
//        que no pertenecen a ningún ciclo previo. Las que ya tienen
//        aprobaciongerenciaid pertenecen a un ciclo anterior (aprobado) y
//        aún esperan su comprobante contable — no deben mezclarse.
// ════════════════════════════════════════════════════════════════════════════

    public function enviarSolicitud(
        string  $enviadoPor,
        ?string $descripcion   = null,
        ?string $observaciones = null
    ): array {
        // ── Verificar que no haya una solicitud pendiente activa ──────────────
        $sqlCheck = "SELECT idAprobacionesGerencia
                     FROM apoyo_combustibles.aprobacionesgerencia ag
                     INNER JOIN apoyo_combustibles.estadosaprobacion ea
                         ON ea.idEstadosAprobacion = ag.estadoaprobacionid
                     WHERE ea.codigo = 'pendiente'
                     ORDER BY ag.idAprobacionesGerencia DESC
                     LIMIT 1";
        $stmt = $this->connect->prepare($sqlCheck);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            throw new \RuntimeException(
                'Ya existe una solicitud pendiente de revisión. No se puede enviar una nueva.'
            );
        }

        // ── Verificar que existan liquidaciones SIN solicitud previa ──────────
        // CAMBIO CLAVE: AND aprobaciongerenciaid IS NULL
        // Solo cuenta liquidaciones genuinamente nuevas (sin ciclo previo).
        $sqlCnt = "SELECT COUNT(*), COALESCE(SUM(monto), 0)
                   FROM apoyo_combustibles.liquidaciones
                   WHERE estado = 'aprobada'
                     AND comprobantecontableid IS NULL
                     AND aprobaciongerenciaid IS NULL";   // ← CAMBIO
        $stmt = $this->connect->prepare($sqlCnt);
        $stmt->execute();
        [$totalLiq, $montoTotal] = $stmt->fetch(\PDO::FETCH_NUM);

        if ((int) $totalLiq === 0) {
            throw new \RuntimeException(
                'No hay liquidaciones nuevas sin solicitud asignada para enviar a aprobación.'
            );
        }

        $idEstadoPendiente = $this->_obtenerIdEstado('pendiente');

        // ── Crear registro de aprobación ──────────────────────────────────────
        $sqlInsert = "INSERT INTO apoyo_combustibles.aprobacionesgerencia
                          (descripcion, estadoaprobacionid, enviado_por, observaciones)
                      VALUES (?, ?, ?, ?)";
        $stmt = $this->connect->prepare($sqlInsert);
        $stmt->execute([$descripcion, $idEstadoPendiente, $enviadoPor, $observaciones]);
        $idAprobacion = (int) $this->connect->lastInsertId();

        // ── Vincular SOLO las liquidaciones sin solicitud previa ──────────────
        // CAMBIO CLAVE: AND aprobaciongerenciaid IS NULL
        $sqlVincular = "UPDATE apoyo_combustibles.liquidaciones
                        SET aprobaciongerenciaid = ?
                        WHERE estado = 'aprobada'
                          AND comprobantecontableid IS NULL
                          AND aprobaciongerenciaid IS NULL";    // ← CAMBIO
        $stmt = $this->connect->prepare($sqlVincular);
        $stmt->execute([$idAprobacion]);
        $vinculadas = $stmt->rowCount();

        // ── Log: null → pendiente ─────────────────────────────────────────────
        $this->_registrarLog($idAprobacion, null, $idEstadoPendiente, $enviadoPor, null, $observaciones);

        return [
            'idAprobacionesGerencia' => $idAprobacion,
            'total_liquidaciones'    => $vinculadas,
            'monto_total'            => (float) $montoTotal,
        ];
    }

// ─────────────────────────────────────────────────────────────────────────────

    /**
     * Aprueba o rechaza una solicitud (uso exclusivo de gerencia).
     *
     * @param int         $aprobacionId
     * @param string      $accion        'aprobar' | 'rechazar'
     * @param string      $revisadoPor   ID del usuario gerencia
     * @param string|null $motivoRechazo Requerido si accion = 'rechazar'
     * @param string|null $observaciones
     * @return array
     */
    public function resolverSolicitud(
        int     $aprobacionId,
        string  $accion,
        string  $revisadoPor,
        ?string $motivoRechazo = null,
        ?string $observaciones = null
    ): array {
        if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
            throw new \InvalidArgumentException("Acción inválida: '{$accion}'.");
        }

        if ($accion === 'rechazar' && empty(trim($motivoRechazo ?? ''))) {
            throw new \InvalidArgumentException('El motivo de rechazo es requerido.');
        }

        // ── Obtener solicitud y verificar que esté pendiente ─────────────────────
        $sqlGet = "SELECT ag.idAprobacionesGerencia, ag.estadoaprobacionid,
                      ea.codigo AS estado_codigo
               FROM apoyo_combustibles.aprobacionesgerencia ag
               INNER JOIN apoyo_combustibles.estadosaprobacion ea
                   ON ea.idEstadosAprobacion = ag.estadoaprobacionid
               WHERE ag.idAprobacionesGerencia = ?";
        $stmt = $this->connect->prepare($sqlGet);
        $stmt->execute([$aprobacionId]);
        $solicitud = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$solicitud) {
            throw new \RuntimeException('Solicitud no encontrada.');
        }

        if ($solicitud['estado_codigo'] !== 'pendiente') {
            throw new \RuntimeException(
                "La solicitud no está en estado pendiente (estado actual: {$solicitud['estado_codigo']})."
            );
        }

        $idEstadoAnterior = (int) $solicitud['estadoaprobacionid'];
        $codigoNuevo      = ($accion === 'aprobar') ? 'aprobada' : 'rechazada';
        $idEstadoNuevo    = $this->_obtenerIdEstado($codigoNuevo);

        // ── Actualizar solicitud ──────────────────────────────────────────────────
        $sqlUpdate = "UPDATE apoyo_combustibles.aprobacionesgerencia
                  SET estadoaprobacionid = ?,
                      revisado_por       = ?,
                      fecha_revision     = NOW(),
                      motivo_rechazo     = ?
                  WHERE idAprobacionesGerencia = ?";
        $stmt = $this->connect->prepare($sqlUpdate);
        $stmt->execute([
            $idEstadoNuevo,
            $revisadoPor,
            $accion === 'rechazar' ? $motivoRechazo : null,
            $aprobacionId,
        ]);

        // ── Log ───────────────────────────────────────────────────────────────────
        $this->_registrarLog(
            $aprobacionId,
            $idEstadoAnterior,
            $idEstadoNuevo,
            $revisadoPor,
            $accion === 'rechazar' ? $motivoRechazo : null,
            $observaciones
        );

        return [
            'idAprobacionesGerencia' => $aprobacionId,
            'estado_nuevo'           => $codigoNuevo,
            'revisado_por'           => $revisadoPor,
        ];
    }

// ─────────────────────────────────────────────────────────────────────────────

    /**
     * Reenvía una solicitud rechazada a gerencia.
     *
     * Operaciones (en transacción):
     *   1. Limpia motivo_rechazo, regresa a 'pendiente', actualiza enviado_por + fecha_envio.
     *   2. Re-vincula TODAS las liquidaciones aprobadas sin comprobante al mismo
     *      idAprobacionesGerencia (captura nuevas o modificadas desde el rechazo).
     *   3. Registra en log.
     *
     * @param int         $aprobacionId
     * @param string      $enviadoPor
     * @param string|null $observaciones
     * @return array
     */
    public function reenviarSolicitud(
        int     $aprobacionId,
        string  $enviadoPor,
        ?string $observaciones = null
    ): array {
        // ── Verificar que la solicitud exista y esté rechazada ───────────────────
        $sqlGet = "SELECT ag.idAprobacionesGerencia, ag.estadoaprobacionid,
                      ea.codigo AS estado_codigo
               FROM apoyo_combustibles.aprobacionesgerencia ag
               INNER JOIN apoyo_combustibles.estadosaprobacion ea
                   ON ea.idEstadosAprobacion = ag.estadoaprobacionid
               WHERE ag.idAprobacionesGerencia = ?";
        $stmt = $this->connect->prepare($sqlGet);
        $stmt->execute([$aprobacionId]);
        $solicitud = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$solicitud) {
            throw new \RuntimeException('Solicitud no encontrada.');
        }

        if ($solicitud['estado_codigo'] !== 'rechazada') {
            throw new \RuntimeException(
                "Solo se puede reenviar una solicitud rechazada (estado actual: {$solicitud['estado_codigo']})."
            );
        }

        $idEstadoAnterior  = (int) $solicitud['estadoaprobacionid'];
        $idEstadoPendiente = $this->_obtenerIdEstado('pendiente');

        // ── 1. Actualizar solicitud ───────────────────────────────────────────────
        $sqlUpdate = "UPDATE apoyo_combustibles.aprobacionesgerencia
                  SET estadoaprobacionid = ?,
                      enviado_por        = ?,
                      fecha_envio        = NOW(),
                      revisado_por       = NULL,
                      fecha_revision     = NULL,
                      motivo_rechazo     = NULL,
                      observaciones      = COALESCE(?, observaciones)
                  WHERE idAprobacionesGerencia = ?";
        $stmt = $this->connect->prepare($sqlUpdate);
        $stmt->execute([
            $idEstadoPendiente,
            $enviadoPor,
            $observaciones,
            $aprobacionId,
        ]);

        // ── 2. Re-vincular liquidaciones actuales (aprobadas sin comprobante) ────
        // Primero desvinculamos las que estaban ligadas a esta solicitud
        // por si alguna fue dada de baja o cambió de estado desde el rechazo.
        $sqlDesvincular = "UPDATE apoyo_combustibles.liquidaciones
                       SET aprobaciongerenciaid = NULL
                       WHERE aprobaciongerenciaid = ?";
        $stmt = $this->connect->prepare($sqlDesvincular);
        $stmt->execute([$aprobacionId]);

        // Luego vinculamos todas las actuales
        $sqlVincular = "UPDATE apoyo_combustibles.liquidaciones
                    SET aprobaciongerenciaid = ?
                    WHERE estado = 'aprobada'
                      AND comprobantecontableid IS NULL";
        $stmt = $this->connect->prepare($sqlVincular);
        $stmt->execute([$aprobacionId]);
        $vinculadas = $stmt->rowCount();

        // ── 3. Log ────────────────────────────────────────────────────────────────
        $this->_registrarLog(
            $aprobacionId,
            $idEstadoAnterior,
            $idEstadoPendiente,
            $enviadoPor,
            null,
            $observaciones ?? 'Reenvío tras rechazo'
        );

        return [
            'idAprobacionesGerencia' => $aprobacionId,
            'estado_nuevo'           => 'pendiente',
            'total_liquidaciones'    => $vinculadas,
        ];
    }
}