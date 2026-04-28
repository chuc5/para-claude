<?php

namespace App\combustibleApi\Helpers;

use PDO;

/**
 * MantenimientoLiquidacionesHelper - Lógica de negocio del mantenimiento
 *
 * Centraliza todos los helpers privados que utilizan los endpoints
 * de mantenimiento de liquidaciones:
 *   - listarMantenimientoPorPeriodos
 *   - obtenerDetalleMantenimientoPorPeriodo
 *
 * DEPENDENCIAS:
 *   - PresupuestoRangoHelper: cálculo de presupuesto por rango y consulta base
 *
 * RESPONSABILIDADES:
 * ┌──────────────────────────────┬────────────────────────────────────────────┐
 * │ Grupo                        │ Métodos                                    │
 * ├──────────────────────────────┼────────────────────────────────────────────┤
 * │ Rango y datos base           │ rangoAnalisis, obtenerDatosUsuario,        │
 * │                              │ obtenerDatosMultiplesUsuarios              │
 * ├──────────────────────────────┼────────────────────────────────────────────┤
 * │ Períodos UCF                 │ obtenerPeriodosConPresupuesto,             │
 * │                              │ obtenerTodosLosPeriodosEnRango,            │
 * │                              │ generarPeriodoGenerico                     │
 * ├──────────────────────────────┼────────────────────────────────────────────┤
 * │ División de períodos         │ dividirEnSubPeriodos                       │
 * ├──────────────────────────────┼────────────────────────────────────────────┤
 * │ Liquidaciones                │ obtenerLiquidacionesEnRango,               │
 * │                              │ obtenerLiquidacionesRangoGlobal,           │
 * │                              │ filtrarLiquidacionesPorRango               │
 * ├──────────────────────────────┼────────────────────────────────────────────┤
 * │ Construcción de filas        │ construirFilaPeriodo                       │
 * ├──────────────────────────────┼────────────────────────────────────────────┤
 * │ Períodos en curso            │ calcularPeriodosEnCurso,                   │
 * │                              │ calcularPeriodoEnCursoDetalle              │
 * └──────────────────────────────┴────────────────────────────────────────────┘
 *
 * @package App\combustibleApi\Helpers
 * @author  Sistema de Combustibles
 * @version 1.0.0
 */
class MantenimientoLiquidacionesHelper
{
    /** @var PDO Conexión a base de datos */
    private $connect;

    /** @var PresupuestoRangoHelper Helper centralizado de presupuesto por rango */
    private $presupuestoRangoHelper;

    /**
     * @param PDO $connect Conexión PDO activa (viene del controller)
     */
    public function __construct(PDO $connect)
    {
        $this->connect = $connect;
        $this->presupuestoRangoHelper = new PresupuestoRangoHelper($connect);
    }

    // =========================================================================
    // RANGO Y DATOS BASE
    // =========================================================================

    /**
     * Devuelve el rango de análisis estándar: primer día del mes de hace
     * 2 meses hasta hoy.
     *
     * @return array [fechaDesde: string Y-m-d, fechaHasta: string Y-m-d, anio: int]
     */
    public function rangoAnalisis(): array
    {
        $hoy = new \DateTime();
        $hace2Meses = (clone $hoy)->modify('-2 months')->modify('first day of this month');
        return [
            $hace2Meses->format('Y-m-d'),
            $hoy->format('Y-m-d'),
            (int) $hoy->format('Y'),
        ];
    }

    /**
     * Obtiene los datos generales de un usuario para el encabezado del modal.
     *
     * @param string $usuarioid
     * @return array|null
     */
    public function obtenerDatosUsuario(string $usuarioid): ?array
    {
        $sql = "SELECT
            us.idUsuarios AS usuarioid,
            cli.CodigoCliente,
            dtp.nombres    AS NombreUsuario,
            ag.nombre      AS Agencia,
            pu.nombre      AS Puesto,
            (
                SELECT cap2.NumeroCuenta
                FROM asociado_t24.ccomndatoscaptaciones AS cap2
                WHERE cap2.Cliente = cli.CodigoCliente
                    AND cap2.Producto = '114A.AHORRO.DISPONIBLE'
                ORDER BY cap2.NumeroCuenta
                LIMIT 1
            ) AS PrimeraCuenta
        FROM dbintranet.usuarios AS us
        LEFT JOIN dbintranet.datospersonales AS dtp
            ON dtp.idDatosPersonales = us.idDatosPersonales
        LEFT JOIN asociado_t24.comndatosclientes AS cli
            ON dtp.dpi = cli.Dpi
        INNER JOIN dbintranet.agencia AS ag
            ON ag.idAgencia = us.idAgencia
        INNER JOIN dbintranet.puesto AS pu
            ON pu.idPuesto = us.idPuesto
        WHERE us.idUsuarios = ?
        LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$usuarioid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene datos básicos de múltiples usuarios en una sola consulta.
     * Retorna un array indexado por usuarioid.
     *
     * @param array $usuariosIds
     * @return array [ usuarioid => [ CodigoCliente, NombreUsuario, PrimeraCuenta ] ]
     */
    public function obtenerDatosMultiplesUsuarios(array $usuariosIds): array
    {
        if (empty($usuariosIds)) return [];

        $placeholders = implode(',', array_fill(0, count($usuariosIds), '?'));

        $sql = "SELECT
            us.idUsuarios AS usuarioid,
            cli.CodigoCliente,
            dtp.nombres AS NombreUsuario,
            (
                SELECT cap2.NumeroCuenta
                FROM asociado_t24.ccomndatoscaptaciones AS cap2
                WHERE cap2.Cliente = cli.CodigoCliente
                    AND cap2.Producto = '114A.AHORRO.DISPONIBLE'
                ORDER BY cap2.NumeroCuenta
                LIMIT 1
            ) AS PrimeraCuenta
        FROM dbintranet.usuarios AS us
        LEFT JOIN dbintranet.datospersonales AS dtp
            ON dtp.idDatosPersonales = us.idDatosPersonales
        LEFT JOIN asociado_t24.comndatosclientes AS cli
            ON dtp.dpi = cli.Dpi
        WHERE us.idUsuarios IN ($placeholders)";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute($usuariosIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $row) {
            $resultado[$row['usuarioid']] = [
                'CodigoCliente' => $row['CodigoCliente'],
                'NombreUsuario' => $row['NombreUsuario'],
                'PrimeraCuenta' => $row['PrimeraCuenta'],
            ];
        }
        return $resultado;
    }

    // =========================================================================
    // PERÍODOS UCF
    // =========================================================================

    /**
     * Obtiene los UCF de UN usuario que se cruzan con el rango,
     * junto con el presupuesto diario del puesto/agencia de cada período.
     *
     * @param string $usuarioid
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @return array
     */
    public function obtenerPeriodosConPresupuesto(
        string $usuarioid,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $anio = date('Y');

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
            pg.monto_mensual,
            pg.monto_anual
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
        WHERE ucf.usuarioid   = ?
            AND ucf.activo    = 1
            AND ucf.fecha_ingreso <= ?
            AND (ucf.fecha_egreso IS NULL OR ucf.fecha_egreso >= ?)
        ORDER BY ucf.fecha_ingreso ASC";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$anio, $usuarioid, $fechaHasta, $fechaDesde]);
        return $this->_mapearPeriodos($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtiene los UCF de TODOS los usuarios que se cruzan con el rango.
     *
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @param int    $anio
     * @return array
     */
    public function obtenerTodosLosPeriodosEnRango(
        string $fechaDesde,
        string $fechaHasta,
        int    $anio
    ): array {
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
            pg.monto_mensual,
            pg.monto_anual
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
        WHERE ucf.activo      = 1
            AND ucf.fecha_ingreso <= ?
            AND (ucf.fecha_egreso IS NULL OR ucf.fecha_egreso >= ?)
        ORDER BY ag.nombre ASC, ucf.usuarioid ASC, ucf.fecha_ingreso ASC";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$anio, $fechaHasta, $fechaDesde]);
        return $this->_mapearPeriodos($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Genera un período genérico para usuarios sin registros en UCF.
     *
     * @param array  $usuario    Datos del usuario
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @return array
     */
    public function generarPeriodoGenerico(
        array  $usuario,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $anio = date('Y');
        $uid = $usuario['usuarioid'] ?? null;

        $sql = "SELECT
            pg.monto_anual,
            ag.nombre    AS agencia,
            pu.nombre    AS puesto,
            pg.agenciaid,
            pg.puestoid
        FROM apoyo_combustibles.presupuestogeneral AS pg
        INNER JOIN dbintranet.agencia AS ag ON ag.idAgencia = pg.agenciaid
        INNER JOIN dbintranet.puesto  AS pu ON pu.idPuesto  = pg.puestoid
        WHERE pg.activo     = 1
            AND pg.anio     = ?
            AND pg.agenciaid = (SELECT idAgencia FROM dbintranet.usuarios WHERE idUsuarios = ? LIMIT 1)
            AND pg.puestoid  = (SELECT idPuesto  FROM dbintranet.usuarios WHERE idUsuarios = ? LIMIT 1)
        LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$anio, $uid, $uid]);
        $pg = $stmt->fetch(PDO::FETCH_ASSOC);

        return [[
            'idUsuariosControlFechas' => 0,
            'usuarioid'               => $uid,
            'agenciaid'               => (int) ($pg['agenciaid'] ?? 0),
            'puestoid'                => (int) ($pg['puestoid'] ?? 0),
            'agencia'                 => $pg['agencia'] ?? $usuario['Agencia'],
            'puesto'                  => $pg['puesto'] ?? $usuario['Puesto'],
            'monto_anual'             => (float) ($pg['monto_anual'] ?? 0),
            'monto_mensual'           => (float) ($pg['monto_mensual'] ?? 0),
            'es_nuevo'                => false,
            'porcentaje_presupuesto'  => 100,
            'dias_presupuesto'        => 0,
            'inicio_efectivo'         => $fechaDesde,
            'fin_efectivo'            => $fechaHasta,
            'tipo_periodo'            => 'sin_registros',
        ]];
    }

    // =========================================================================
    // DIVISIÓN DE PERÍODOS
    // =========================================================================

    /**
     * Divide un período UCF en sub-períodos según su etapa de prueba.
     *
     * Casos posibles:
     *   A) Sin prueba           → [ normal ]
     *   B) Prueba aún activa    → [ prueba ]
     *   C) Prueba ya terminada  → [ prueba, post_prueba ]
     *   D) Solo post-prueba     → [ post_prueba ]
     *
     * Todos los rangos se recortan al rango de análisis.
     *
     * @param array  $periodo    Período mapeado (de _mapearPeriodos)
     * @param string $fechaDesde Inicio del rango de análisis Y-m-d
     * @param string $fechaHasta Fin del rango de análisis Y-m-d
     * @return array [ ['inicio', 'fin', 'tipo_periodo'], ... ]
     */
    public function dividirEnSubPeriodos(
        array  $periodo,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        // Rango efectivo del período recortado al rango de análisis
        $inicioPeriodo = max($periodo['inicio_efectivo'], $fechaDesde);
        $finPeriodo    = min($periodo['fin_efectivo'], $fechaHasta);

        // Sin restricción de prueba → una sola fila normal
        if (!$periodo['es_nuevo'] || $periodo['dias_presupuesto'] <= 0) {
            return [[
                'inicio'       => $inicioPeriodo,
                'fin'          => $finPeriodo,
                // inicio_real: inicio real del UCF (puede ser antes del rango de análisis)
                // fin_real: cappado al fin del rango de análisis — evita calcular presupuesto
                //           hasta el egreso anual cuando el período sigue activo
                'inicio_real'  => $periodo['inicio_efectivo'],
                'fin_real'     => $finPeriodo,
                'tipo_periodo' => $periodo['tipo_periodo'] === 'sin_registros'
                    ? 'sin_registros'
                    : 'normal',
            ]];
        }

        // Fecha fin de prueba calculada desde el inicio REAL del UCF
        $fechaFinPrueba = (new \DateTime($periodo['inicio_efectivo']))
            ->modify("+{$periodo['dias_presupuesto']} days")
            ->modify('-1 day');
        $strFinPrueba = $fechaFinPrueba->format('Y-m-d');

        $subPeriodos = [];

        // ── Sub-período PRUEBA ────────────────────────────────────────────
        $inicioSub1 = $inicioPeriodo;
        $finSub1    = min($strFinPrueba, $finPeriodo);

        if ($inicioSub1 <= $finSub1) {
            $subPeriodos[] = [
                // inicio/fin: recortados al rango de análisis (para filtrar liquidaciones)
                'inicio'      => $inicioSub1,
                'fin'         => $finSub1,
                // inicio_real: inicio real del UCF (puede ser antes del rango de análisis)
                // fin_real: fin real del sub-período de prueba. Si el usuario tuvo egreso
                //           anticipado (antes del fin natural de prueba), se usa ese egreso
                //           para que calcularPorRango aplique calcularRangoPruebaAcumulado
                //           con el denominador correcto pero solo los días laborados.
                'inicio_real' => $periodo['inicio_efectivo'],
                'fin_real'    => $finSub1,
                'tipo_periodo' => 'prueba',
            ];
        }

        // ── Sub-período POST-PRUEBA ───────────────────────────────────────
        $strPostPrueba = (clone $fechaFinPrueba)->modify('+1 day')->format('Y-m-d');
        $inicioSub2    = max($strPostPrueba, $fechaDesde);
        $finSub2       = $finPeriodo;

        if ($inicioSub2 <= $finSub2) {
            $subPeriodos[] = [
                'inicio'      => $inicioSub2,
                'fin'         => $finSub2,
                // inicio_real: inicio real del post-prueba (día siguiente al fin de prueba)
                // fin_real: cappado al fin del rango de análisis — sin esto se calcula hasta
                //           el egreso anual (ej: dic 31) inflando días y presupuesto
                'inicio_real' => $strPostPrueba,
                'fin_real'    => $finSub2,
                'tipo_periodo' => 'post_prueba',
            ];
        }

        // Fallback: si no se generó ningún sub-período (caso borde)
        if (empty($subPeriodos)) {
            return [[
                'inicio'       => $inicioPeriodo,
                'fin'          => $finPeriodo,
                'inicio_real'  => $periodo['inicio_efectivo'],
                'fin_real'     => $periodo['fin_efectivo'],
                'tipo_periodo' => 'normal',
            ]];
        }

        return $subPeriodos;
    }

    // =========================================================================
    // LIQUIDACIONES
    // =========================================================================

    /**
     * Obtiene las liquidaciones (aprobadas + de_baja) de UN usuario en el rango.
     * Incluye el flag es_editable.
     *
     * @param string $usuarioid
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @return array
     */
    public function obtenerLiquidacionesEnRango(
        string $usuarioid,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $sql = "SELECT
            l.idLiquidaciones,
            l.numero_factura,
            l.monto,
            l.monto_factura,
            l.descripcion,
            l.detalle,
            l.fecha_liquidacion,
            l.estado,
            l.comprobantecontableid,
            l.aprobaciongerenciaid,
            ta.nombre  AS tipo_apoyo,
            v.marca    AS vehiculo,
            v.placa,
            CASE
                WHEN l.comprobantecontableid IS NULL
                 AND l.aprobaciongerenciaid  IS NULL
                THEN 1 ELSE 0
            END AS es_editable
        FROM apoyo_combustibles.liquidaciones AS l
        LEFT JOIN apoyo_combustibles.tiposapoyo AS ta
            ON ta.idTiposApoyo = l.tipoapoyoid
        LEFT JOIN apoyo_combustibles.vehiculos AS v
            ON v.idVehiculos = l.vehiculoid
        WHERE l.usuarioid  = ?
            AND l.estado   IN ('aprobada', 'de_baja')
            AND DATE(l.fecha_liquidacion) >= ?
            AND DATE(l.fecha_liquidacion) <= ?
        ORDER BY l.estado ASC, l.fecha_liquidacion ASC";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$usuarioid, $fechaDesde, $fechaHasta]);
        return $this->_mapearLiquidaciones($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Obtiene las liquidaciones de MÚLTIPLES usuarios en el rango
     * y las devuelve agrupadas por usuarioid.
     *
     * @param array  $usuariosIds
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @return array [ usuarioid => [ liquidacion, ... ] ]
     */
    public function obtenerLiquidacionesRangoGlobal(
        array  $usuariosIds,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        if (empty($usuariosIds)) return [];

        $placeholders = implode(',', array_fill(0, count($usuariosIds), '?'));

        $sql = "SELECT
            l.idLiquidaciones,
            l.usuarioid,
            l.numero_factura,
            l.monto,
            l.monto_factura,
            l.descripcion,
            l.detalle,
            l.fecha_liquidacion,
            l.estado,
            l.comprobantecontableid,
            l.aprobaciongerenciaid,
            ta.nombre AS tipo_apoyo,
            v.marca   AS vehiculo,
            v.placa,
            CASE
                WHEN l.comprobantecontableid IS NULL
                 AND l.aprobaciongerenciaid  IS NULL
                THEN 1 ELSE 0
            END AS es_editable
        FROM apoyo_combustibles.liquidaciones AS l
        LEFT JOIN apoyo_combustibles.tiposapoyo AS ta
            ON ta.idTiposApoyo = l.tipoapoyoid
        LEFT JOIN apoyo_combustibles.vehiculos AS v
            ON v.idVehiculos = l.vehiculoid
        WHERE l.usuarioid IN ($placeholders)
            AND l.estado  IN ('aprobada', 'de_baja')
            AND DATE(l.fecha_liquidacion) >= ?
            AND DATE(l.fecha_liquidacion) <= ?
        ORDER BY l.usuarioid ASC, l.fecha_liquidacion ASC";

        $params = array_merge($usuariosIds, [$fechaDesde, $fechaHasta]);
        $stmt = $this->connect->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $agrupado = [];
        foreach ($rows as $row) {
            $uid = $row['usuarioid'];
            if (!isset($agrupado[$uid])) $agrupado[$uid] = [];
            $agrupado[$uid][] = $this->_mapearLiquidacion($row);
        }
        return $agrupado;
    }

    /**
     * Filtra en memoria las liquidaciones que caen dentro de un rango exacto.
     *
     * @param array  $liquidaciones Liquidaciones ya cargadas
     * @param string $desde Y-m-d
     * @param string $hasta Y-m-d
     * @return array
     */
    public function filtrarLiquidacionesPorRango(
        array  $liquidaciones,
        string $desde,
        string $hasta
    ): array {
        return array_values(array_filter($liquidaciones, function ($liq) use ($desde, $hasta) {
            $fecha = substr($liq['fecha_liquidacion'], 0, 10);
            return $fecha >= $desde && $fecha <= $hasta;
        }));
    }

    // =========================================================================
    // CONSTRUCCIÓN DE FILAS
    // =========================================================================
    /**
     * Construye el array de una fila de período (sin liquidaciones detalladas).
     * Reutilizado por listarMantenimientoPorPeriodos y obtenerDetalle.
     *
     * Delega el cálculo de presupuesto a PresupuestoRangoHelper.
     *
     * Modo acumulado: cuando tipo_periodo = 'prueba' y el período de prueba
     * aún está activo (hoy <= fin_real), calcula el presupuesto y el consumo
     * solo hasta la fecha actual usando calcularRangoPruebaAcumulado().
     * Si ya terminó la prueba, siempre se usa el total completo.
     *
     * @param array $periodo              Período mapeado completo
     * @param array $sub                  Sub-período [ inicio, fin, tipo_periodo, ... ]
     * @param array $usuario              Datos del usuario
     * @param array $liquidacionesUsuario Liquidaciones ya cargadas del usuario
     * @param float $montoExtaordinarios  Monto de registros_extraordinarios del UCF
     * @param bool  $modoAcumulado        Si true, aplica corte a hoy en pruebas activas
     */
    public function construirFilaPeriodo(
        array $periodo,
        array $sub,
        array $usuario,
        array $liquidacionesUsuario,
        float $montoExtaordinarios = 0.0,
        bool  $modoAcumulado       = true   // true: en prueba activa muestra acumulado hasta hoy
    ): array {
        $hoy           = date('Y-m-d');
        $inicioFiltro  = $sub['inicio_real'] ?? $sub['inicio'];
        $finFiltro     = $sub['fin_real']    ?? $sub['fin'];
        $inicioCalculo = $inicioFiltro;
        $finCalculo    = $finFiltro;

        // Determinar si la prueba sigue activa y si aplica el modo acumulado.
        // Condición: sub-período de prueba + hoy está antes del fin real de prueba.
        $enPruebaActiva = $sub['tipo_periodo'] === 'prueba' && $hoy < $finFiltro;
        $aplicarCorte   = $modoAcumulado && $enPruebaActiva;

        // En modo acumulado con prueba activa, el filtro de liquidaciones
        // también se corta a hoy para que consumo y presupuesto sean consistentes.
        $finFiltroEfectivo = $aplicarCorte ? $hoy : $finFiltro;

        $liquidacionesSub = $this->filtrarLiquidacionesPorRango(
            $liquidacionesUsuario, $inicioFiltro, $finFiltroEfectivo
        );

        // Calcular totales del sub-período
        $totalAprobadas = 0.0;
        $totalDeBaja    = 0.0;
        $cantAprobadas  = 0;
        $cantDeBaja     = 0;

        foreach ($liquidacionesSub as $liq) {
            if ($liq['estado'] === 'aprobada') {
                $totalAprobadas += $liq['monto'];
                $cantAprobadas++;
            }
            if ($liq['estado'] === 'de_baja') {
                $totalDeBaja += $liq['monto'];
                $cantDeBaja++;
            }
        }

        // Los extraordinarios se suman al consumo solo en el sub-período de prueba
        $totalExtaordinarios = ($sub['tipo_periodo'] === 'prueba') ? $montoExtaordinarios : 0.0;
        $totalLiquidado      = $totalAprobadas + $totalDeBaja + $totalExtaordinarios;

        // ── PRESUPUESTO ───────────────────────────────────────────────────────
        if ($aplicarCorte) {
            // Acumulado hasta hoy: denominador combinado del período completo,
            // pero días contados hasta la fecha actual.
            $porcentaje          = $periodo['porcentaje_presupuesto'] / 100;
            $presupuestoAsignado = $this->presupuestoRangoHelper->calcularRangoPruebaAcumulado(
                $inicioCalculo,
                $finCalculo,
                (float) ($periodo['monto_mensual'] ?? 0),
                $porcentaje,
                $hoy
            );
        } else {
            // Comportamiento original: presupuesto total del sub-período
            $presupuestoAsignado = $this->presupuestoRangoHelper->calcularPorRango(
                $periodo, $inicioCalculo, $finCalculo
            );
        }

        $diferencia  = $presupuestoAsignado - $totalLiquidado;

        // Los días se calculan sobre el rango efectivo (recortado a hoy si aplica)
        $finDias     = $aplicarCorte ? $hoy : $finCalculo;
        $diasPeriodo = (new \DateTime($inicioCalculo))->diff(new \DateTime($finDias))->days + 1;

        // El porcentaje visible solo aplica en sub-períodos de prueba
        $porcentajeVisible = $sub['tipo_periodo'] === 'prueba'
            ? (float) $periodo['porcentaje_presupuesto']
            : 100.0;

        return [
            // Identificación del usuario
            'usuarioid'      => $periodo['usuarioid'] ?? null,
            'CodigoCliente'  => $usuario['CodigoCliente'] ?? null,
            'NombreUsuario'  => $usuario['NombreUsuario'] ?? null,
            'PrimeraCuenta'  => $usuario['PrimeraCuenta'] ?? null,

            // Identificación del período
            'idUsuariosControlFechas' => (int) ($periodo['idUsuariosControlFechas'] ?? 0),
            'tipo_periodo'            => $sub['tipo_periodo'],
            'agencia'                 => $periodo['agencia'],
            'puesto'                  => $periodo['puesto'],

            // Fechas del UCF completo
            'fecha_inicio_periodo' => $periodo['inicio_efectivo'],
            'fecha_fin_periodo'    => $periodo['fin_efectivo'],

            // Fechas del sub-período (lo que se muestra en la fila)
            'fecha_inicio_rango' => $sub['inicio'],
            'fecha_fin_rango'    => $sub['fin'],
            'dias_periodo'       => $diasPeriodo,

            // Configuración presupuestal
            'es_nuevo'               => (bool) ($periodo['es_nuevo'] ?? false),
            'porcentaje_presupuesto' => $porcentajeVisible,
            'dias_presupuesto'       => (int) ($periodo['dias_presupuesto'] ?? 0),
            'presupuesto_diario'     => round(
                (float) ($periodo['monto_mensual'] ?? 0) / (int) (new \DateTime($inicioCalculo))->format('t'),
                2
            ),

            // Modo acumulado: informa al frontend si el dato está cortado a hoy
            'es_acumulado'        => $aplicarCorte,
            'fecha_corte'         => $aplicarCorte ? $hoy : null,

            // Comparativa financiera
            'presupuesto_asignado'  => round($presupuestoAsignado, 2),
            'total_aprobadas'       => round($totalAprobadas, 2),
            'total_de_baja'         => round($totalDeBaja, 2),
            'total_extraordinarios' => round($totalExtaordinarios, 2),
            'total_liquidado'       => round($totalLiquidado, 2),
            'diferencia'            => round($diferencia, 2),
            'requiere_ajuste'       => $diferencia < 0,

            // Conteos
            'cant_aprobadas' => $cantAprobadas,
            'cant_de_baja'   => $cantDeBaja,
            'cant_total'     => $cantAprobadas + $cantDeBaja,
        ];
    }

    // =========================================================================
    // PERÍODOS EN CURSO
    // =========================================================================

    /**
     * Detecta el gap entre el último UCF de cada usuario y la fecha actual.
     * Genera filas de tipo 'en_curso' para esos gaps.
     *
     * Usado por listarMantenimientoPorPeriodos.
     *
     * @param array  $periodos               Todos los UCF ya mapeados
     * @param array  $datosUsuarios           Datos indexados por usuarioid
     * @param array  $liquidacionesAgrupadas  Liquidaciones indexadas por usuarioid
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d (hoy)
     * @param int    $anio
     * @return array
     */
    public function calcularPeriodosEnCurso(
        array  $periodos,
        array  $datosUsuarios,
        array  $liquidacionesAgrupadas,
        string $fechaDesde,
        string $fechaHasta,
        int    $anio
    ): array {
        $resultado = [];

        // Encontrar el UCF más reciente de cada usuario
        $ultimoPorUsuario = [];
        foreach ($periodos as $p) {
            $uid = $p['usuarioid'];
            if (!isset($ultimoPorUsuario[$uid]) ||
                $p['fin_efectivo'] > $ultimoPorUsuario[$uid]['fin_efectivo']) {
                $ultimoPorUsuario[$uid] = $p;
            }
        }

        foreach ($ultimoPorUsuario as $uid => $ultimo) {
            $usuario = $datosUsuarios[$uid] ?? null;
            if (!$usuario) continue;

            $filaCurso = $this->_generarFilaEnCurso(
                $ultimo, $liquidacionesAgrupadas[$uid] ?? [],
                $fechaDesde, $fechaHasta, $anio
            );

            if (!$filaCurso) continue;

            // Agregar datos del usuario a la fila
            $filaCurso['usuarioid']     = $uid;
            $filaCurso['CodigoCliente'] = $usuario['CodigoCliente'];
            $filaCurso['NombreUsuario'] = $usuario['NombreUsuario'];
            $filaCurso['PrimeraCuenta'] = $usuario['PrimeraCuenta'];

            $resultado[] = $filaCurso;
        }

        return $resultado;
    }

    /**
     * Versión para el modal de detalle por período.
     * Devuelve el período en curso con sus liquidaciones incluidas.
     *
     * @param array  $periodos            UCF del usuario
     * @param array  $todasLiquidaciones  Liquidaciones ya cargadas del usuario
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d
     * @param int    $anio
     * @return array|null
     */
    public function calcularPeriodoEnCursoDetalle(
        array  $periodos,
        array  $todasLiquidaciones,
        string $fechaDesde,
        string $fechaHasta,
        int    $anio
    ): ?array {
        if (empty($periodos)) return null;

        // UCF con la fecha de egreso más reciente
        $ultimo = null;
        foreach ($periodos as $p) {
            if ($ultimo === null || $p['fin_efectivo'] > $ultimo['fin_efectivo']) {
                $ultimo = $p;
            }
        }

        if (!$ultimo) return null;

        $fila = $this->_generarFilaEnCurso(
            $ultimo, $todasLiquidaciones, $fechaDesde, $fechaHasta, $anio
        );

        if (!$fila) return null;

        // Agregar liquidaciones del período en curso al detalle
        $fila['liquidaciones'] = $this->filtrarLiquidacionesPorRango(
            $todasLiquidaciones,
            $fila['fecha_inicio_rango'],
            $fila['fecha_fin_rango']
        );

        // Quitar campos de usuario (van en el encabezado del modal)
        unset($fila['usuarioid'], $fila['CodigoCliente'],
            $fila['NombreUsuario'], $fila['PrimeraCuenta']);

        return $fila;
    }


    // =========================================================================
    // REGISTROS EXTRAORDINARIOS
    // =========================================================================

    /**
     * Retorna el monto total de extraordinarios activos y no cancelados
     * vinculados a un UCF específico.
     *
     * @param int $ucfId
     * @return float
     */
    public function obtenerExtaordinariosPorUCF(int $ucfId): float
    {
        if ($ucfId <= 0) return 0.0;

        $sql = "SELECT COALESCE(SUM(re.monto), 0)
                FROM apoyo_combustibles.registros_extraordinarios re
                WHERE re.ucfid  = ?
                  AND re.activo = 1
                  AND re.estado != 'cancelado'";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$ucfId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Retorna un mapa [ ucfId => monto_extraordinarios ] para un conjunto
     * de UCFs. Los que no tienen registros quedan en 0.0.
     *
     * @param int[] $ucfIds
     * @return array [ ucfId => float ]
     */
    public function obtenerExtaordinariosMultiplesUCFs(array $ucfIds): array
    {
        if (empty($ucfIds)) return [];

        $placeholders = implode(',', array_fill(0, count($ucfIds), '?'));

        $sql = "SELECT re.ucfid, COALESCE(SUM(re.monto), 0) AS total
                FROM apoyo_combustibles.registros_extraordinarios re
                WHERE re.ucfid  IN ($placeholders)
                  AND re.activo = 1
                  AND re.estado != 'cancelado'
                GROUP BY re.ucfid";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute($ucfIds);

        $resultado = array_fill_keys($ucfIds, 0.0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resultado[(int) $row['ucfid']] = (float) $row['total'];
        }
        return $resultado;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Mapea las filas crudas de UCF al formato interno estandarizado.
     *
     * @param array $rows Filas crudas del SELECT
     * @return array
     */
    private function _mapearPeriodos(array $rows): array
    {
        $hoy = date('Y-m-d');

        return array_map(function ($row) use ($hoy) {
            $inicio = $row['fecha_ingreso'];
            $fin    = $row['fecha_egreso'] ?? date('Y-12-31');

            $tipoPeriodo = 'normal';
            if ($row['es_nuevo'] && (int) $row['dias_presupuesto'] > 0) {
                $fechaFinPrueba = (new \DateTime($inicio))
                    ->modify("+{$row['dias_presupuesto']} days")
                    ->modify('-1 day')
                    ->format('Y-m-d');

                $tipoPeriodo = ($hoy <= $fechaFinPrueba) ? 'prueba' : 'post_prueba';
            }

            return [
                'idUsuariosControlFechas' => (int) $row['idUsuariosControlFechas'],
                'usuarioid'               => $row['usuarioid'],
                'agenciaid'               => (int) $row['agenciaid'],
                'puestoid'                => (int) $row['puestoid'],
                'agencia'                 => $row['agencia'],
                'puesto'                  => $row['puesto'],
                'monto_mensual'           => (float) ($row['monto_mensual'] ?? 0),
                'monto_anual'             => (float) ($row['monto_anual']   ?? 0),

                'es_nuevo'                => (bool)  $row['es_nuevo'],
                'porcentaje_presupuesto'  => (int)   $row['porcentaje_presupuesto'],
                'dias_presupuesto'        => (int)   $row['dias_presupuesto'],
                'inicio_efectivo'         => $inicio,
                'fin_efectivo'            => $fin,
                'tipo_periodo'            => $tipoPeriodo,
            ];
        }, $rows);
    }

    /**
     * Mapea un array de filas crudas de liquidaciones al formato interno.
     *
     * @param array $rows
     * @return array
     */
    private function _mapearLiquidaciones(array $rows): array
    {
        return array_map([$this, '_mapearLiquidacion'], $rows);
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
            'idLiquidaciones'     => (int) $row['idLiquidaciones'],
            'numero_factura'      => $row['numero_factura'],
            'monto'               => (float) $row['monto'],
            'monto_factura'       => (float) $row['monto_factura'],
            'descripcion'         => $row['descripcion'],
            'detalle'             => $row['detalle'],
            'fecha_liquidacion'   => $row['fecha_liquidacion'],
            'estado'              => $row['estado'],
            'comprobantecontableid' => $row['comprobantecontableid'],
            'aprobaciongerenciaid'  => $row['aprobaciongerenciaid'],
            'tipo_apoyo'          => $row['tipo_apoyo'],
            'vehiculo'            => $row['vehiculo'],
            'placa'               => $row['placa'],
            'es_editable'         => (bool) $row['es_editable'],
        ];
    }

    /**
     * Genera la fila base del período en curso a partir del último UCF.
     * Devuelve null si no hay gap.
     *
     * Delega consulta de presupuesto a PresupuestoRangoHelper::obtenerPorAgenciaPuesto()
     *
     * @param array  $ultimoPeriodo        Último UCF del usuario
     * @param array  $liquidacionesUsuario Liquidaciones del usuario
     * @param string $fechaDesde Y-m-d
     * @param string $fechaHasta Y-m-d (hoy)
     * @param int    $anio
     * @return array|null
     */
    private function _generarFilaEnCurso(
        array  $ultimoPeriodo,
        array  $liquidacionesUsuario,
        string $fechaDesde,
        string $fechaHasta,
        int    $anio
    ): ?array {
        $finUltimo = $ultimoPeriodo['fin_efectivo'];

        if ($finUltimo >= $fechaHasta) return null;

        $inicioCurso = (new \DateTime($finUltimo))->modify('+1 day')->format('Y-m-d');

        if ($inicioCurso > $fechaHasta) return null;

        $inicioCurso = max($inicioCurso, $fechaDesde);
        $finCurso    = $fechaHasta;

        $pg = $this->presupuestoRangoHelper->obtenerPorAgenciaPuesto(
            $ultimoPeriodo['agenciaid'],
            $ultimoPeriodo['puestoid'],
            $anio
        );

        if (!$pg) return null;

        // Presupuesto con tarifa mensual variable (monto_mensual / días_del_mes)
        $periodoSimple = [
            'monto_mensual'          => (float) ($pg['monto_mensual'] ?? 0),
            'es_nuevo'               => false,
            'dias_presupuesto'       => 0,
            'porcentaje_presupuesto' => 100,
            'inicio_efectivo'        => $inicioCurso,
        ];
        $presupuestoAsignado = $this->presupuestoRangoHelper->calcularPorRango(
            $periodoSimple, $inicioCurso, $finCurso
        );
        $dtI  = new \DateTime($inicioCurso);
        $dtF  = new \DateTime($finCurso);
        $presupuestoDiario = round((float) ($pg['monto_mensual'] ?? 0) / (int) $dtI->format('t'), 2);
        $dias = $dtI->diff($dtF)->days + 1;

        $liquidacionesCurso = $this->filtrarLiquidacionesPorRango(
            $liquidacionesUsuario, $inicioCurso, $finCurso
        );

        $totalAprobadas = 0.0;
        $totalDeBaja    = 0.0;
        $cantAprobadas  = 0;
        $cantDeBaja     = 0;

        foreach ($liquidacionesCurso as $liq) {
            if ($liq['estado'] === 'aprobada') {
                $totalAprobadas += $liq['monto'];
                $cantAprobadas++;
            }
            if ($liq['estado'] === 'de_baja') {
                $totalDeBaja += $liq['monto'];
                $cantDeBaja++;
            }
        }

        $totalLiquidado = $totalAprobadas + $totalDeBaja;
        $diferencia     = $presupuestoAsignado - $totalLiquidado;

        return [
            'usuarioid'     => null,
            'CodigoCliente' => null,
            'NombreUsuario' => null,
            'PrimeraCuenta' => null,

            'idUsuariosControlFechas' => 0,
            'tipo_periodo'            => 'en_curso',
            'agencia'                 => $ultimoPeriodo['agencia'],
            'puesto'                  => $ultimoPeriodo['puesto'],

            'fecha_inicio_periodo' => $inicioCurso,
            'fecha_fin_periodo'    => $finCurso,
            'fecha_inicio_rango'   => $inicioCurso,
            'fecha_fin_rango'      => $finCurso,
            'dias_periodo'         => $dias,

            'es_nuevo'               => false,
            'porcentaje_presupuesto' => 100.0,
            'dias_presupuesto'       => 0,
            'presupuesto_diario'     => round($presupuestoDiario, 2),

            'presupuesto_asignado' => round($presupuestoAsignado, 2),
            'total_aprobadas'      => round($totalAprobadas, 2),
            'total_de_baja'        => round($totalDeBaja, 2),
            'total_liquidado'      => round($totalLiquidado, 2),
            'diferencia'           => round($diferencia, 2),
            'requiere_ajuste'      => $diferencia < 0,

            'cant_aprobadas' => $cantAprobadas,
            'cant_de_baja'   => $cantDeBaja,
            'cant_total'     => $cantAprobadas + $cantDeBaja,
        ];
    }
}