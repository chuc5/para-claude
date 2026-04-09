<?php
namespace App\combustibleApi\Helpers;

use Exception;
use PDO;

class ConsumoHelper
{
    private $connect;
    private $idUsuario;

    public function __construct(PDO $connect, $idUsuario)
    {
        $this->connect   = $connect;
        $this->idUsuario = $idUsuario;
    }

    // =========================================================================
    // RANGO DEL PERÍODO DE CONSUMO
    // =========================================================================

    /**
     * Retorna el rango del período de consumo mensual:
     * - Desde: fecha_ingreso del UCF activo
     * - Hasta: created_at del último comprobante de tipo 'liquidacion'
     *          (null si no existe ninguno)
     *
     * @return array ['desde' => string|null, 'hasta' => string|null]
     */
    public function obtenerRangoPeriodoConsumo(): array
    {
        try {
            // Inicio: created_at del último comprobante registrado
            // Si no existe ninguno, inicio de año
            $sqlDesde = "SELECT MAX(created_at)
                     FROM apoyo_combustibles.comprobantescontables
                     WHERE tipo = 'liquidacion'";
            $stmt = $this->connect->prepare($sqlDesde);
            $stmt->execute();
            $desde = $stmt->fetchColumn() ?: date('Y') . '-01-01';

            // Fin: sin límite superior (cuenta hasta hoy)
            // El siguiente comprobante aún no existe, por eso el usuario
            // está consumiendo en este período abierto
            return [
                'desde' => $desde,
                'hasta' => null,
            ];

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::obtenerRangoPeriodoConsumo: " . $e->getMessage());
            return ['desde' => date('Y') . '-01-01', 'hasta' => null];
        }
    }

    // =========================================================================
    // CONSUMO MENSUAL
    // Rango: desde fecha_ingreso del UCF activo
    //        hasta created_at del último comprobante (o ahora si no hay)
    // Solo tipos de apoyo con aplica_limite_mensual = 1
    // =========================================================================

    public function obtenerConsumoMensual(): float
    {
        try {
            $rango = $this->obtenerRangoPeriodoConsumo();
            if (!$rango['desde']) return 0.0;

            $params = [$this->idUsuario, $rango['desde']];
            $condHasta = '';

            if ($rango['hasta']) {
                $condHasta = 'AND l.fecha_liquidacion <= ?';
                $params[]  = $rango['hasta'];
            }
            // Si no hay comprobante posterior al UCF, cuenta todo desde el ingreso hasta hoy

            $sql = "SELECT COALESCE(SUM(l.monto), 0)
                FROM apoyo_combustibles.liquidaciones l
                INNER JOIN apoyo_combustibles.tiposapoyo ta
                    ON l.tipoapoyoid = ta.idTiposApoyo
                WHERE l.usuarioid = ?
                  AND l.estado NOT IN ('eliminada', 'rechazada')
                  AND ta.aplica_limite_mensual = 1
                  AND l.fecha_liquidacion >= ?
                  {$condHasta}";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);
            return (float) $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::obtenerConsumoMensual: " . $e->getMessage());
            return 0.0;
        }
    }

    // =========================================================================
    // CONSUMO ANUAL
    // - En período de prueba: solo suma lo liquidado dentro de ese período
    // - Post-prueba / normal: suma TODOS los períodos activos del año
    // =========================================================================
    public function obtenerConsumoAnual(
        int     $anio,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): float {
        try {
            $infoPrueba = $this->_obtenerInfoPeriodoPrueba($anio);

            if ($infoPrueba['en_prueba']) {
                // Solo el tramo de prueba (liquidaciones + extraordinarios del UCF)
                $sql = "SELECT COALESCE(SUM(monto), 0)
                    FROM apoyo_combustibles.liquidaciones
                    WHERE usuarioid = ?
                      AND estado NOT IN ('eliminada', 'rechazada')
                      AND fecha_liquidacion BETWEEN ? AND ?";
                $stmt = $this->connect->prepare($sql);
                $stmt->execute([
                    $this->idUsuario,
                    $infoPrueba['fecha_inicio'],
                    $infoPrueba['fecha_fin_prueba'] . ' 23:59:59',
                ]);
                $consumoLiquidaciones = (float) $stmt->fetchColumn();

                // Extraordinarios del período de prueba activo
                $sqlExtra = "SELECT COALESCE(SUM(re.monto), 0)
                         FROM apoyo_combustibles.registros_extraordinarios re
                         INNER JOIN apoyo_combustibles.usuarioscontrolfechas ucf
                             ON re.ucfid = ucf.idUsuariosControlFechas
                         WHERE re.usuarioid = ?
                           AND re.activo    = 1
                           AND re.estado   != 'cancelado'
                           AND ucf.fecha_ingreso = ?";
                $stmt = $this->connect->prepare($sqlExtra);
                $stmt->execute([$this->idUsuario, $infoPrueba['fecha_inicio']]);
                $consumoExtra = (float) $stmt->fetchColumn();

                return $consumoLiquidaciones + $consumoExtra;

            } else {
                // Todo el año: liquidaciones + todos los extraordinarios no cancelados
                $sql = "SELECT COALESCE(SUM(monto), 0)
                    FROM apoyo_combustibles.liquidaciones
                    WHERE usuarioid = ?
                      AND estado NOT IN ('eliminada', 'rechazada')
                      AND YEAR(fecha_liquidacion) = ?";
                $stmt = $this->connect->prepare($sql);
                $stmt->execute([$this->idUsuario, $anio]);
                $consumoLiquidaciones = (float) $stmt->fetchColumn();

                $sqlExtra = "SELECT COALESCE(SUM(monto), 0)
                         FROM apoyo_combustibles.registros_extraordinarios
                         WHERE usuarioid = ?
                           AND activo    = 1
                           AND estado   != 'cancelado'
                           AND YEAR(created_at) = ?";
                $stmt = $this->connect->prepare($sqlExtra);
                $stmt->execute([$this->idUsuario, $anio]);
                $consumoExtra = (float) $stmt->fetchColumn();

                return $consumoLiquidaciones + $consumoExtra;
            }

        } catch (Exception $e) {
            error_log("Error en ConsumoHelper::obtenerConsumoAnual: " . $e->getMessage());
            return 0.0;
        }
    }

    // =========================================================================
    // HELPERS PARA LÍMITE MENSUAL Y PERÍODO DE PRUEBA
    // =========================================================================

    public function aplicaLimiteMensual($tipoapoyoid): bool
    {
        try {
            $sql  = "SELECT aplica_limite_mensual
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

    /**
     * Detecta si el usuario está actualmente en período de prueba
     * y devuelve sus fechas delimitadoras.
     */
    private function _obtenerInfoPeriodoPrueba(int $anio): array
    {
        $hoy = date('Y-m-d');

        $sql = "SELECT fecha_ingreso, dias_presupuesto, es_nuevo
                FROM apoyo_combustibles.usuarioscontrolfechas
                WHERE usuarioid = ?
                  AND activo    = 1
                  AND YEAR(fecha_ingreso) = ?
                ORDER BY fecha_ingreso DESC
                LIMIT 1";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$this->idUsuario, $anio]);
        $ucf  = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ucf || !$ucf['es_nuevo'] || (int) $ucf['dias_presupuesto'] <= 0) {
            return ['en_prueba' => false];
        }

        $fechaFinPrueba = (new \DateTime($ucf['fecha_ingreso']))
            ->modify("+{$ucf['dias_presupuesto']} days")
            ->modify('-1 day')
            ->format('Y-m-d');

        return [
            'en_prueba'        => $hoy <= $fechaFinPrueba,
            'fecha_inicio'     => $ucf['fecha_ingreso'],
            'fecha_fin_prueba' => $fechaFinPrueba,
        ];
    }
}