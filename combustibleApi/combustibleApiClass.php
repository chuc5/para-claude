<?php

namespace App\combustibleApi;

use App\Core\ApiResponder;
use App\Core\MailerService;
use App\Core\DriveService;
use ConexionBD;
use Exception;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

//use propios de nuevo help
use App\combustibleApi\Helpers\ConsumoHelper;
use App\combustibleApi\Helpers\PresupuestoAnualHelper;
use App\combustibleApi\Helpers\PresupuestoMensualHelper;
use App\combustibleApi\Helpers\ValidacionHelper;
use App\combustibleApi\Helpers\MensajeContextualHelper;
use App\combustibleApi\Helpers\MantenimientoLiquidacionesHelper;
use App\combustibleApi\Helpers\EnvioPagoLiquidacionesHelper;

/**
 * ============================================================================
 * API MÓDULO GENÉRICO
 * ============================================================================
 *
 * Plantilla base para nuevos módulos de la intranet / sistemas.
 *
 * FLUJO GENERAL (EJEMPLO):
 * 1. Listar recursos
 * 2. Crear recurso
 * 3. Editar recurso
 * 4. Eliminar / Desactivar recurso
 *
 * Reemplazar:
 *  - combustibleApiClass -> NombreRealApiClass
 *  - namespace App\combustibleApi -> App\nombreRealApi
 *  - Nombre de tablas / campos en métodos privados
 *
 * @author Tu Nombre
 * @version 1.0 - Plantilla base
 */
final class combustibleApiClass extends ConexionBD
{
    // ========================================================================
    // PROPIEDADES
    // ========================================================================

    protected $idUsuario;
    protected $idAgencia;
    protected $puesto;
    protected $area;
    protected $puestosValidos = [];

    protected ApiResponder $res;
    protected MailerService $mailer;
    protected DriveService $drive;

    //propuiedades de helps
    private $consumoHelper = null;
    private $presupuestoAnualHelper = null;
    private $presupuestoMensualHelper = null;
    private $validacionHelper = null;
    private $mantHelper = null;
    private $envioPagoHelper = null;

    /** Métodos GET permitidos (si usas enrutador dinámico) */
    private array $metodosGet = [
        'listarConfiguraciones',
        'obtenerConfiguracionActiva',
        //tipos de apoyo
        'listarTiposApoyo',
        'listarTiposApoyoActivos',
        // tipos de vehiculos
        'listarTiposVehiculo',
        'listarTiposVehiculoActivos',
        //! dias habiles
        'listarConfiguracionesDiasHabiles',
        'obtenerConfiguracionDiasHabilesActiva',
        // Dias de descanso:
        'listarDiasDescanso',
        'listarDiasDescansoActivos',
        // porcentaje de gracias:
        'listarConfiguracionesPorcentajeAnual',
        'obtenerConfiguracionPorcentajeAnualActiva',
        //Usuarios control fechas
        'listarUsuariosControlFechas',
        'listarUsuariosControlFechasActivos',
        'listarUsuariosDisponibles',
        //Vehiculos
        'listarMisVehiculos',
        //? presupuestos Generales
        'listarPresupuestosGenerales',
        'listarPresupuestosGeneralesActivos',
        'listarAgencias',
        'listarPuestos',
        'descargarPlantillaPresupuestos',

        //? Liquidaciones
        'listarMisLiquidaciones',
        'obtenerPresupuestoDisponible',
        'calcularDiasHabilesTranscurridos',
        //? Autorizaciones
        'obtenerSolicitudesPendientes',
        //? COntabilidad Liquidaciones
        'listarMisSolicitudesCorreccion',
        //? Lotes de pago:
        'listarLiquidacionesPendientesLote',
        //? Asignacion de comprobante
        'listarLiquidacionesSinComprobante',
        //? Mantenimiento de liquidaciones
        'listarMantenimientoPorPeriodos',
        //? Envio para autorizacion
        'obtenerAniosDisponiblesLiquidaciones',
        'listarEnvioPagoLiquidaciones',
        'obtenerDetalleEnvioPago'

    ];

    /** Métodos POST permitidos */
    private array $metodosPost = [
        'crearConfiguracion',
        'editarConfiguracion',
        'desactivarConfiguracion',
        //!tipos de apoyo
        'crearTipoApoyo',
        'editarTipoApoyo',
        'desactivarTipoApoyo',
        'activarTipoApoyo',
        // tipos de vehiculos
        'crearTipoVehiculo',
        'editarTipoVehiculo',
        'desactivarTipoVehiculo',
        'activarTipoVehiculo',
        // dias habiles
        'obtenerDetalleConfiguracionDiasHabiles',
        'crearConfiguracionDiasHabiles',
        'editarConfiguracionDiasHabiles',
        'desactivarConfiguracionDiasHabiles',
        // Día de descanso:
        'listarDiasDescansoPorAnio',
        'crearDiaDescanso',
        'editarDiaDescanso',
        'desactivarDiaDescanso',
        'activarDiaDescanso',
        //!porcentaje anual
        'crearConfiguracionPorcentajeAnual',
        'editarConfiguracionPorcentajeAnual',
        'desactivarConfiguracionPorcentajeAnual',
        // Usuarios control fechas:
        'obtenerHistorialUsuario',
        'crearUsuarioControlFechas',
        'editarUsuarioControlFechas',
        'desactivarUsuarioControlFechas',
        'activarUsuarioControlFechas',
        //vehiculos
        'crearMiVehiculo',
        'editarMiVehiculo',
        'desactivarMiVehiculo',
        'activarMiVehiculo',
        //? presupuestos Generales
        'crearPresupuestoGeneral',
        'editarPresupuestoGeneral',
        'desactivarPresupuestoGeneral',
        'activarPresupuestoGeneral',
        'cargarPresupuestosMasivo',
        //? Liquidaciones
        'buscarFacturaParaLiquidacion',
        'crearLiquidacion',
        'editarLiquidacion',
        'eliminarLiquidacion',
        'crearSolicitudAutorizacion',
        'obtenerDetalleSolicitudAutorizacion',
        //? Autorizaciones
        'aprobarSolicitudAutorizacion',
        'rechazarSolicitudAutorizacion',
        'obtenerHistoricoSolicitudes',
        'reconsiderarSolicitudRechazada',
        //? Contabilidad Liquidaciones
        'listarLiquidacionesPendientesRevision',
        'aprobarLiquidacion',
        'rechazarLiquidacion',
        'devolverLiquidacion',
        'darDeBajaLiquidacion',
        'obtenerHistorialRevisiones',
        'marcarCorreccionRealizada',
        'aprobarLiquidacionesMasivo',
        'listarSolicitudesCorreccionPorLiquidacion',
        //? Asignacion de comprobantes
        'obtenerDetalleLiquidacionesUsuario',
        'asignarComprobanteMasivo',
        //? Mantenimiento liquidaciones
        'actualizarLiquidacionMantenimiento',
        'obtenerDetalleMantenimientoPorPeriodo',

    ];

    // ========================================================================
    // INICIALIZACIÓN
    // ========================================================================
    public function __construct()
    {
        parent::__construct();

        $this->idUsuario = $_SESSION['idUsuario'] ?? '';
        $this->idAgencia = $_SESSION['idAgencia'] ?? 0;
        $this->puesto = $_SESSION['idPuesto'] ?? null;

        // Si tienes lógica de áreas, reemplaza esta función o quítala
        $this->area = $this->obtenerArea($this->puesto);

        // Define qué puestos tienen permiso para usar este módulo
        $this->puestosValidos = [/* ids de puestos permitidos */];

        $this->res = new ApiResponder();
        $this->mailer = new MailerService();
        $this->drive = new DriveService();
    }

    /**
     * Obtiene el área del usuario según su puesto.
     * Ajustar o eliminar según tu lógica.
     */
    private function obtenerArea($puesto): int
    {
        try {
            $q = "SELECT COALESCE((SELECT area 
                                   FROM compras.puesto_areas 
                                   WHERE puesto = ?), 0) AS area";
            $st = $this->connect->prepare($q);
            $st->execute([$puesto]);
            return (int)$st->fetchColumn();
        } catch (Exception $e) {
            error_log("Error obteniendo área: " . $e->getMessage());
            return 0;
        }
    }

    // ========================================================================
    // MÉTODOS DE RUTE0 / PERMISOS
    // ========================================================================

    public function esMetodoGet(string $m): bool
    {
        return in_array($m, $this->metodosGet, true);
    }

    public function esMetodoPost(string $m): bool
    {
        return in_array($m, $this->metodosPost, true);
    }

    // ========================================================================
    // 🛠️ HELPERS GENERALES
    // ========================================================================

    /**
     * Limpia strings del objeto recibido
     */
    protected function limpiarDatos($data): object
    {
        if (is_array($data)) {
            $data = (object)$data;
        }

        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data->$k = trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
            }
        }
        return $data;
    }

    /**
     * Valida permisos según puesto o lógica adicional
     */
    protected function validarPermisos(string $operacion): bool
    {
        // Si solo validas por puesto:
        if (!empty($this->puestosValidos)) {
            return in_array($this->puesto, $this->puestosValidos, true);
        }

        // Aquí puedes agregar lógica por operación, área, etc.
        return true;
    }

    /**
     * Inicializa los helpers del módulo de liquidaciones
     *
     * Se llama una vez al inicio de los métodos que los necesitan.
     * Si ya están inicializados, no hace nada.
     *
     * @return void
     **/
    private function _inicializarHelpersLiquidaciones()
    {
        // Evitar reinicialización
        if ($this->consumoHelper !== null) return;

        $this->consumoHelper = new ConsumoHelper(
            $this->connect,
            $this->idUsuario
        );

        $this->presupuestoAnualHelper = new PresupuestoAnualHelper(
            $this->connect,
            $this->idUsuario,
            $this->idAgencia,
            $this->puesto
        );

        $this->presupuestoMensualHelper = new PresupuestoMensualHelper(
            $this->connect,
            $this->idUsuario,
            $this->consumoHelper
        );

        $this->validacionHelper = new ValidacionHelper(
            $this->connect,
            $this->idUsuario,
            $this->consumoHelper,
            $this->presupuestoAnualHelper,
            $this->presupuestoMensualHelper
        );
    }

    // ========================================================================
// 📋 CRUD - CONFIGURACIÓN GENERAL
// ========================================================================

    /**
     * Lista todas las configuraciones del sistema
     *
     * GET: combustible/listarConfiguraciones
     */
    public function listarConfiguraciones()
    {
        try {
            $registros = $this->_obtenerConfiguraciones();

            return $this->res->ok('Configuraciones obtenidas correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarConfiguraciones: " . $e->getMessage());
            return $this->res->fail('Error al listar configuraciones', $e);
        }
    }

    /**
     * Obtiene la configuración activa del sistema
     *
     * GET: combustible/obtenerConfiguracionActiva
     */
    public function obtenerConfiguracionActiva()
    {
        try {
            $sql = "SELECT
                        conf.idConfiguracionGeneral,
                        conf.dia_corte_mensual,
                        conf.observaciones,
                        conf.activo,
                        conf.created_at,
                        conf.updated_at,
                        dtp.nombres as modificado_por
                    FROM
                        apoyo_combustibles.configuraciongeneral AS conf
                        LEFT JOIN dbintranet.usuarios AS us ON conf.modificado_por = us.idUsuarios
                        LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                    WHERE
                        activo = 1
                    ORDER BY
                        conf.updated_at DESC
                        LIMIT 1";

            $stmt = $this->connect->query($sql);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return $this->res->fail('No hay configuración activa en el sistema');
            }

            return $this->res->ok('Configuración activa obtenida', $config);

        } catch (Exception $e) {
            error_log("Error en obtenerConfiguracionActiva: " . $e->getMessage());
            return $this->res->fail('Error al obtener configuración activa', $e);
        }
    }

    /**
     * Crea una nueva configuración general
     *
     * POST: combustible/crearConfiguracion
     *
     * @param object $datos {
     *   dia_corte_mensual: int,
     *   observaciones?: string
     * }
     */
    public function crearConfiguracion($datos)
    {
        try {
            if (!$this->validarPermisos('crear')) {
                return $this->res->fail('No tiene permisos para crear configuraciones');
            }

            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarConfiguracion($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            $this->connect->beginTransaction();

            // Desactivar configuraciones anteriores
            $sqlDesactivar = "UPDATE apoyo_combustibles.configuraciongeneral 
                          SET activo = 0 
                          WHERE activo = 1";
            $this->connect->exec($sqlDesactivar);

            // Crear nueva configuración
            $sql = "INSERT INTO apoyo_combustibles.configuraciongeneral 
                (dia_corte_mensual, modificado_por, observaciones, activo)
                VALUES (?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->dia_corte_mensual,
                $this->idUsuario,
                $datos->observaciones ?? null
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Configuración creada correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearConfiguracion: " . $e->getMessage());
            return $this->res->fail('Error al crear la configuración', $e);
        }
    }

    /**
     * Edita una configuración existente
     *
     * POST: combustible/editarConfiguracion
     *
     * @param object $datos {
     *   idConfiguracionGeneral: int,
     *   dia_corte_mensual: int,
     *   observaciones?: string
     * }
     */
    public function editarConfiguracion($datos)
    {
        try {
            if (!$this->validarPermisos('editar')) {
                return $this->res->fail('No tiene permisos para editar configuraciones');
            }

            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idConfiguracionGeneral)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Validaciones
            $error = $this->_validarConfiguracion($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeConfiguracion($datos->idConfiguracionGeneral)) {
                return $this->res->fail('La configuración no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.configuraciongeneral 
                SET dia_corte_mensual = ?,
                    modificado_por = ?,
                    observaciones = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idConfiguracionGeneral = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->dia_corte_mensual,
                $this->idUsuario,
                $datos->observaciones ?? null,
                (int)$datos->idConfiguracionGeneral
            ]);

            $this->connect->commit();

            return $this->res->ok('Configuración actualizada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarConfiguracion: " . $e->getMessage());
            return $this->res->fail('Error al editar la configuración', $e);
        }
    }

    /**
     * Desactiva una configuración
     *
     * POST: combustible/desactivarConfiguracion
     *
     * @param object $datos {
     *   idConfiguracionGeneral: int
     * }
     */
    public function desactivarConfiguracion($datos)
    {
        try {
            if (!$this->validarPermisos('eliminar')) {
                return $this->res->fail('No tiene permisos para desactivar configuraciones');
            }

            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idConfiguracionGeneral)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Verificar que existe
            if (!$this->_existeConfiguracion($datos->idConfiguracionGeneral)) {
                return $this->res->fail('La configuración no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.configuraciongeneral 
                SET activo = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idConfiguracionGeneral = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idConfiguracionGeneral]);

            $this->connect->commit();

            return $this->res->ok('Configuración desactivada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarConfiguracion: " . $e->getMessage());
            return $this->res->fail('Error al desactivar la configuración', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todas las configuraciones del sistema
     *
     * @return array Lista de configuraciones
     */
    private function _obtenerConfiguraciones(): array
    {
        $sql = "SELECT
                    conf.idConfiguracionGeneral,
                    conf.dia_corte_mensual,
                    conf.observaciones,
                    conf.activo,
                    conf.created_at,
                    conf.updated_at,
                    dtp.nombres as modificado_por
                FROM
                    apoyo_combustibles.configuraciongeneral AS conf
                    LEFT JOIN dbintranet.usuarios AS us ON conf.modificado_por = us.idUsuarios
                    LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                ORDER BY
                    conf.activo DESC,
                    conf.updated_at DESC,
                    conf.idConfiguracionGeneral DESC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de una configuración
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarConfiguracion($datos): ?string
    {
        // Validar día de corte mensual
        if (empty($datos->dia_corte_mensual)) {
            return 'El día de corte mensual es requerido';
        }

        $diaCorte = (int)$datos->dia_corte_mensual;
        if ($diaCorte < 1 || $diaCorte > 31) {
            return 'El día de corte debe estar entre 1 y 31';
        }

        // Validar observaciones (opcional, pero con límite)
        if (isset($datos->observaciones) && strlen($datos->observaciones) > 500) {
            return 'Las observaciones no pueden exceder 500 caracteres';
        }

        return null;
    }

    /**
     * Verifica si existe una configuración por ID
     *
     * @param int $id ID de la configuración
     * @return bool True si existe
     */
    private function _existeConfiguracion(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.configuraciongeneral 
            WHERE idConfiguracionGeneral = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }


    // ========================================================================
// 📋 CRUD - TIPOS DE APOYO
// ========================================================================

    /**
     * Lista todos los tipos de apoyo del sistema
     *
     * GET: combustible/listarTiposApoyo
     */
    public function listarTiposApoyo()
    {
        try {
            $registros = $this->_obtenerTiposApoyo();

            return $this->res->ok('Tipos de apoyo obtenidos correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarTiposApoyo: " . $e->getMessage());
            return $this->res->fail('Error al listar tipos de apoyo', $e);
        }
    }

    /**
     * Lista los tipos de apoyo activos
     *
     * GET: combustible/listarTiposApoyoActivos
     */
    public function listarTiposApoyoActivos()
    {
        try {
            $sql = "SELECT 
            idTiposApoyo,
            codigo,
            nombre,
            aplica_limite_mensual,
            activo,
            descripcion_predefinida,    
            requiere_detalle,           
            titulo_detalle,             
            placeholder_detalle,        
            created_at,
            updated_at
        FROM apoyo_combustibles.tiposapoyo 
        WHERE activo = 1 
        ORDER BY nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Tipos de apoyo obtenidos', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarTiposApoyoActivos: " . $e->getMessage());
            return $this->res->fail('Error al listar tipos de apoyo', $e);
        }
    }

    /**
     * Crea un nuevo tipo de apoyo
     *
     * POST: combustible/crearTipoApoyo
     *
     * @param object $datos {
     *   codigo: string,
     *   nombre: string,
     *   descripcion?: string,
     *   aplica_limite_mensual?: 0|1
     * }
     */
    public function crearTipoApoyo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarTipoApoyo($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que el código no exista
            if ($this->_existeCodigoTipoApoyo($datos->codigo)) {
                return $this->res->fail('El código ya existe. Por favor use uno diferente');
            }

            $this->connect->beginTransaction();

            $sql = "INSERT INTO apoyo_combustibles.tiposapoyo 
                (codigo, 
                nombre,
                aplica_limite_mensual,
                descripcion_predefinida,
                requiere_detalle,
                titulo_detalle,
                placeholder_detalle, 
                activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->codigo,
                $datos->nombre,
                isset($datos->aplica_limite_mensual) ? (int)$datos->aplica_limite_mensual : 0,
                $datos->descripcion_predefinida ?? '',
                isset($datos->requiere_detalle) ? (int)$datos->requiere_detalle : 0,
                $datos->titulo_detalle ?? '',
                $datos->placeholder_detalle ?? '',
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Tipo de apoyo creado correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearTipoApoyo: " . $e->getMessage());
            return $this->res->fail('Error al crear el tipo de apoyo', $e);
        }
    }

    /**
     * Edita un tipo de apoyo existente
     *
     * POST: combustible/editarTipoApoyo
     *
     * @param object $datos {
     *   idTiposApoyo: int,
     *   codigo: string,
     *   nombre: string,
     *   descripcion?: string,
     *   aplica_limite_mensual?: 0|1
     * }
     */
    public function editarTipoApoyo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idTiposApoyo)) {
                return $this->res->fail('El ID del tipo de apoyo es requerido');
            }

            // Validaciones
            $error = $this->_validarTipoApoyo($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeTipoApoyo($datos->idTiposApoyo)) {
                return $this->res->fail('El tipo de apoyo no existe');
            }

            // Verificar que el código no esté duplicado (excluyendo el actual)
            if ($this->_existeCodigoTipoApoyoExceptoId($datos->codigo, $datos->idTiposApoyo)) {
                return $this->res->fail('El código ya existe. Por favor use uno diferente');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.tiposapoyo 
                SET codigo = ?,
                    nombre = ?,                  
                    aplica_limite_mensual = ?,
                    descripcion_predefinida = ?,
                    requiere_detalle = ?,
                    titulo_detalle = ?,
                    placeholder_detalle = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idTiposApoyo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->codigo,
                $datos->nombre,
                isset($datos->aplica_limite_mensual) ? (int)$datos->aplica_limite_mensual : 0,
                $datos->descripcion_predefinida ?? '',
                isset($datos->requiere_detalle) ? (int)$datos->requiere_detalle : 0,
                $datos->titulo_detalle ?? '',
                $datos->placeholder_detalle ?? '',
                (int)$datos->idTiposApoyo
            ]);

            $this->connect->commit();

            return $this->res->ok('Tipo de apoyo actualizado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarTipoApoyo: " . $e->getMessage());
            return $this->res->fail('Error al editar el tipo de apoyo', $e);
        }
    }

    /**
     * Desactiva un tipo de apoyo
     *
     * POST: combustible/desactivarTipoApoyo
     *
     * @param object $datos {
     *   idTiposApoyo: int
     * }
     */
    public function desactivarTipoApoyo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idTiposApoyo)) {
                return $this->res->fail('El ID del tipo de apoyo es requerido');
            }

            // Verificar que existe
            if (!$this->_existeTipoApoyo($datos->idTiposApoyo)) {
                return $this->res->fail('El tipo de apoyo no existe');
            }

            // Verificar si está en uso (opcional - agregar según lógica de negocio)
            // if ($this->_tipoApoyoEnUso($datos->idTiposApoyo)) {
            //     return $this->res->fail('No se puede desactivar porque está en uso');
            // }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.tiposapoyo 
                SET activo = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idTiposApoyo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idTiposApoyo]);

            $this->connect->commit();

            return $this->res->ok('Tipo de apoyo desactivado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarTipoApoyo: " . $e->getMessage());
            return $this->res->fail('Error al desactivar el tipo de apoyo', $e);
        }
    }

    /**
     * Activa un tipo de apoyo
     *
     * POST: combustible/activarTipoApoyo
     *
     * @param object $datos {
     *   idTiposApoyo: int
     * }
     */
    public function activarTipoApoyo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idTiposApoyo)) {
                return $this->res->fail('El ID del tipo de apoyo es requerido');
            }

            // Verificar que existe
            if (!$this->_existeTipoApoyo($datos->idTiposApoyo)) {
                return $this->res->fail('El tipo de apoyo no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.tiposapoyo 
                SET activo = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idTiposApoyo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idTiposApoyo]);

            $this->connect->commit();

            return $this->res->ok('Tipo de apoyo activado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en activarTipoApoyo: " . $e->getMessage());
            return $this->res->fail('Error al activar el tipo de apoyo', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todos los tipos de apoyo del sistema
     *
     * @return array Lista de tipos de apoyo
     */
    private function _obtenerTiposApoyo(): array
    {
        $sql = "SELECT 
                idTiposApoyo,
                codigo,
                nombre,
                aplica_limite_mensual,
                descripcion_predefinida,
                requiere_detalle,
                titulo_detalle,
                placeholder_detalle,
                activo,
                created_at,
                updated_at                
            FROM apoyo_combustibles.tiposapoyo
            ORDER BY activo DESC, nombre ASC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de un tipo de apoyo
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarTipoApoyo($datos): ?string
    {
        // Validar código
        if (empty($datos->codigo)) {
            return 'El código es requerido';
        }

        if (strlen($datos->codigo) > 20) {
            return 'El código no puede exceder 20 caracteres';
        }

        // Validar nombre
        if (empty($datos->nombre)) {
            return 'El nombre es requerido';
        }

        if (strlen($datos->nombre) > 50) {
            return 'El nombre no puede exceder 50 caracteres';
        }

        // Validar descripción (opcional)
        if (isset($datos->descripcion) && strlen($datos->descripcion) > 200) {
            return 'La descripción no puede exceder 200 caracteres';
        }

        // Validar aplica_limite_mensual (opcional)
        if (isset($datos->aplica_limite_mensual)) {
            $valor = (int)$datos->aplica_limite_mensual;
            if ($valor !== 0 && $valor !== 1) {
                return 'El valor de aplica_limite_mensual debe ser 0 o 1';
            }
        }

        return null;
    }

    /**
     * Verifica si existe un tipo de apoyo por ID
     *
     * @param int $id ID del tipo de apoyo
     * @return bool True si existe
     */
    private function _existeTipoApoyo(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.tiposapoyo 
            WHERE idTiposApoyo = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe un código de tipo de apoyo
     *
     * @param string $codigo Código a verificar
     * @return bool True si existe
     */
    private function _existeCodigoTipoApoyo(string $codigo): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.tiposapoyo 
            WHERE codigo = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$codigo]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe un código excluyendo un ID específico
     *
     * @param string $codigo Código a verificar
     * @param int $idExcluir ID a excluir de la búsqueda
     * @return bool True si existe
     */
    private function _existeCodigoTipoApoyoExceptoId(string $codigo, int $idExcluir): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.tiposapoyo 
            WHERE codigo = ? AND idTiposApoyo != ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$codigo, $idExcluir]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si un tipo de apoyo está siendo usado
     * (Implementar según relaciones con otras tablas)
     *
     * @param int $id ID del tipo de apoyo
     * @return bool True si está en uso
     */
    private function _tipoApoyoEnUso(int $id): bool
    {
        // Ejemplo: verificar si hay solicitudes con este tipo
        // $sql = "SELECT COUNT(*)
        //         FROM apoyo_combustibles.solicitudes
        //         WHERE tipo_apoyo_id = ?";
        // $stmt = $this->connect->prepare($sql);
        // $stmt->execute([$id]);
        // return (int)$stmt->fetchColumn() > 0;

        return false; // Por defecto no está en uso
    }


    //! Tipos de vehiculos

    // ========================================================================
// 📋 CRUD - TIPOS DE VEHÍCULO
// ========================================================================

    /**
     * Lista todos los tipos de vehículo del sistema
     *
     * GET: combustible/listarTiposVehiculo
     */
    public function listarTiposVehiculo()
    {
        try {
            $registros = $this->_obtenerTiposVehiculo();

            return $this->res->ok('Tipos de vehículo obtenidos correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarTiposVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al listar tipos de vehículo', $e);
        }
    }

    /**
     * Obtiene los tipos de vehículo activos del sistema
     *
     * GET: combustible/listarTiposVehiculoActivos
     */
    public function listarTiposVehiculoActivos()
    {
        try {
            $sql = "SELECT 
                    idTiposVehiculo,
                    nombre,
                    activo,
                    created_at
                FROM apoyo_combustibles.tiposvehiculo
                WHERE activo = 1
                ORDER BY nombre ASC";

            $stmt = $this->connect->query($sql);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Tipos de vehículo activos obtenidos', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarTiposVehiculoActivos: " . $e->getMessage());
            return $this->res->fail('Error al obtener tipos de vehículo activos', $e);
        }
    }

    /**
     * Crea un nuevo tipo de vehículo
     *
     * POST: combustible/crearTipoVehiculo
     *
     * @param object $datos {
     *   nombre: string
     * }
     */
    public function crearTipoVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarTipoVehiculo($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que el nombre no exista
            if ($this->_existeNombreTipoVehiculo($datos->nombre)) {
                return $this->res->fail('El nombre ya existe. Por favor use uno diferente');
            }

            $this->connect->beginTransaction();

            $sql = "INSERT INTO apoyo_combustibles.tiposvehiculo 
                (nombre, activo)
                VALUES (?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->nombre]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Tipo de vehículo creado correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearTipoVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al crear el tipo de vehículo', $e);
        }
    }

    /**
     * Edita un tipo de vehículo existente
     *
     * POST: combustible/editarTipoVehiculo
     *
     * @param object $datos {
     *   idTiposVehiculo: int,
     *   nombre: string
     * }
     */
    public function editarTipoVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idTiposVehiculo)) {
                return $this->res->fail('El ID del tipo de vehículo es requerido');
            }

            // Validaciones
            $error = $this->_validarTipoVehiculo($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeTipoVehiculo($datos->idTiposVehiculo)) {
                return $this->res->fail('El tipo de vehículo no existe');
            }

            // Verificar que el nombre no esté duplicado (excluyendo el actual)
            if ($this->_existeNombreTipoVehiculoExceptoId($datos->nombre, $datos->idTiposVehiculo)) {
                return $this->res->fail('El nombre ya existe. Por favor use uno diferente');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.tiposvehiculo 
                SET nombre = ?
                WHERE idTiposVehiculo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->nombre,
                (int)$datos->idTiposVehiculo
            ]);

            $this->connect->commit();

            return $this->res->ok('Tipo de vehículo actualizado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarTipoVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al editar el tipo de vehículo', $e);
        }
    }

    /**
     * Desactiva un tipo de vehículo
     *
     * POST: combustible/desactivarTipoVehiculo
     *
     * @param object $datos {
     *   idTiposVehiculo: int
     * }
     */
    public function desactivarTipoVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idTiposVehiculo)) {
                return $this->res->fail('El ID del tipo de vehículo es requerido');
            }

            // Verificar que existe
            if (!$this->_existeTipoVehiculo($datos->idTiposVehiculo)) {
                return $this->res->fail('El tipo de vehículo no existe');
            }

            // Verificar si está en uso (opcional - agregar según lógica de negocio)
            // if ($this->_tipoVehiculoEnUso($datos->idTiposVehiculo)) {
            //     return $this->res->fail('No se puede desactivar porque está en uso');
            // }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.tiposvehiculo 
                SET activo = 0
                WHERE idTiposVehiculo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idTiposVehiculo]);

            $this->connect->commit();

            return $this->res->ok('Tipo de vehículo desactivado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarTipoVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al desactivar el tipo de vehículo', $e);
        }
    }

    /**
     * Activa un tipo de vehículo
     *
     * POST: combustible/activarTipoVehiculo
     *
     * @param object $datos {
     *   idTiposVehiculo: int
     * }
     */
    public function activarTipoVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idTiposVehiculo)) {
                return $this->res->fail('El ID del tipo de vehículo es requerido');
            }

            // Verificar que existe
            if (!$this->_existeTipoVehiculo($datos->idTiposVehiculo)) {
                return $this->res->fail('El tipo de vehículo no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.tiposvehiculo 
                SET activo = 1
                WHERE idTiposVehiculo = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idTiposVehiculo]);

            $this->connect->commit();

            return $this->res->ok('Tipo de vehículo activado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en activarTipoVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al activar el tipo de vehículo', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todos los tipos de vehículo del sistema
     *
     * @return array Lista de tipos de vehículo
     */
    private function _obtenerTiposVehiculo(): array
    {
        $sql = "SELECT 
                idTiposVehiculo,
                nombre,
                activo,
                created_at
            FROM apoyo_combustibles.tiposvehiculo
            ORDER BY activo DESC, nombre ASC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de un tipo de vehículo
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarTipoVehiculo($datos): ?string
    {
        // Validar nombre
        if (empty($datos->nombre)) {
            return 'El nombre es requerido';
        }

        if (strlen($datos->nombre) > 50) {
            return 'El nombre no puede exceder 50 caracteres';
        }

        return null;
    }

    /**
     * Verifica si existe un tipo de vehículo por ID
     *
     * @param int $id ID del tipo de vehículo
     * @return bool True si existe
     */
    private function _existeTipoVehiculo(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.tiposvehiculo 
            WHERE idTiposVehiculo = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe un nombre de tipo de vehículo
     *
     * @param string $nombre Nombre a verificar
     * @return bool True si existe
     */
    private function _existeNombreTipoVehiculo(string $nombre): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.tiposvehiculo 
            WHERE nombre = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$nombre]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe un nombre excluyendo un ID específico
     *
     * @param string $nombre Nombre a verificar
     * @param int $idExcluir ID a excluir de la búsqueda
     * @return bool True si existe
     */
    private function _existeNombreTipoVehiculoExceptoId(string $nombre, int $idExcluir): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.tiposvehiculo 
            WHERE nombre = ? AND idTiposVehiculo != ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$nombre, $idExcluir]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si un tipo de vehículo está siendo usado
     * (Implementar según relaciones con otras tablas)
     *
     * @param int $id ID del tipo de vehículo
     * @return bool True si está en uso
     */
    private function _tipoVehiculoEnUso(int $id): bool
    {
        // Ejemplo: verificar si hay vehículos con este tipo
        // $sql = "SELECT COUNT(*)
        //         FROM apoyo_combustibles.vehiculos
        //         WHERE tipo_vehiculo_id = ?";
        // $stmt = $this->connect->prepare($sql);
        // $stmt->execute([$id]);
        // return (int)$stmt->fetchColumn() > 0;

        return false; // Por defecto no está en uso
    }

    /*
     *
     */

    // ========================================================================
// 📋 CRUD - CONFIGURACIÓN DÍAS HÁBILES
// ========================================================================

    /**
     * Lista todas las configuraciones de días hábiles del sistema
     *
     * GET: combustible/listarConfiguracionesDiasHabiles
     */
    public function listarConfiguracionesDiasHabiles()
    {
        try {
            $registros = $this->_obtenerConfiguracionesDiasHabiles();

            return $this->res->ok('Configuraciones obtenidas correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarConfiguracionesDiasHabiles: " . $e->getMessage());
            return $this->res->fail('Error al listar configuraciones de días hábiles', $e);
        }
    }

    /**
     * Obtiene la configuración activa de días hábiles con su detalle
     *
     * GET: combustible/obtenerConfiguracionDiasHabilesActiva
     */
    public function obtenerConfiguracionDiasHabilesActiva()
    {
        try {
            // Obtener configuración activa
            $sql = "SELECT
                        dh.idConfiguracionDiasHabiles,
                        dh.cantidad_dias,
                        dh.observaciones,
                        dh.activo,
                        dh.created_at,
                        dtp.nombres AS modificado_por
                    FROM
                        apoyo_combustibles.configuraciondiashabiles AS dh
                        LEFT JOIN dbintranet.usuarios AS us ON dh.modificado_por = us.idUsuarios
                        LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                    WHERE
                        activo = 1
                    ORDER BY
                        dh.created_at DESC
                        LIMIT 1";

            $stmt = $this->connect->query($sql);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return $this->res->fail('No hay configuración activa en el sistema');
            }

            // Obtener detalle de días
            $sqlDetalle = "SELECT 
                        idConfiguracionDiasHabilesDetalle,
                        configuraciondiashabilesid,
                        dia_semana,
                        es_habil,
                        created_at
                    FROM apoyo_combustibles.configuraciondiashabilesdetalle
                    WHERE configuraciondiashabilesid = ?
                    ORDER BY dia_semana ASC";

            $stmtDetalle = $this->connect->prepare($sqlDetalle);
            $stmtDetalle->execute([$config['idConfiguracionDiasHabiles']]);
            $detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            $config['dias_detalle'] = $detalle;

            return $this->res->ok('Configuración activa obtenida', $config);

        } catch (Exception $e) {
            error_log("Error en obtenerConfiguracionDiasHabilesActiva: " . $e->getMessage());
            return $this->res->fail('Error al obtener configuración activa', $e);
        }
    }

    /**
     * Obtiene el detalle completo de una configuración específica
     *
     * POST: combustible/obtenerDetalleConfiguracionDiasHabiles
     *
     * @param object $datos {
     *   idConfiguracionDiasHabiles: int
     * }
     */
    public function obtenerDetalleConfiguracionDiasHabiles($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idConfiguracionDiasHabiles)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Obtener configuración
            $sql = "SELECT
                    cond.idConfiguracionDiasHabiles,
                    cond.cantidad_dias,
                    cond.observaciones,
                    cond.activo,
                    cond.created_at,
                    dtp.nombres AS modificado_por
                FROM
                    apoyo_combustibles.configuraciondiashabiles AS cond
                    LEFT JOIN dbintranet.usuarios AS us ON cond.modificado_por = us.idUsuarios
                    LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                WHERE idConfiguracionDiasHabiles = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idConfiguracionDiasHabiles]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return $this->res->fail('Configuración no encontrada');
            }

            // Obtener detalle de días
            $sqlDetalle = "SELECT 
                        idConfiguracionDiasHabilesDetalle,
                        configuraciondiashabilesid,
                        dia_semana,
                        es_habil,
                        created_at
                    FROM apoyo_combustibles.configuraciondiashabilesdetalle
                    WHERE configuraciondiashabilesid = ?
                    ORDER BY dia_semana ASC";

            $stmtDetalle = $this->connect->prepare($sqlDetalle);
            $stmtDetalle->execute([(int)$datos->idConfiguracionDiasHabiles]);
            $detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            $config['dias_detalle'] = $detalle;

            return $this->res->ok('Detalle obtenido correctamente', $config);

        } catch (Exception $e) {
            error_log("Error en obtenerDetalleConfiguracionDiasHabiles: " . $e->getMessage());
            return $this->res->fail('Error al obtener detalle de configuración', $e);
        }
    }

    /**
     * Crea una nueva configuración de días hábiles
     *
     * POST: combustible/crearConfiguracionDiasHabiles
     *
     * @param object $datos {
     *   cantidad_dias: int,
     *   observaciones?: string,
     *   dias_habiles: array [
     *     { dia_semana: 1, es_habil: 1 },
     *     { dia_semana: 2, es_habil: 1 },
     *     ...
     *   ]
     * }
     */
    public function crearConfiguracionDiasHabiles($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarConfiguracionDiasHabiles($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            $this->connect->beginTransaction();

            // Desactivar configuraciones anteriores
            $sqlDesactivar = "UPDATE apoyo_combustibles.configuraciondiashabiles 
                          SET activo = 0 
                          WHERE activo = 1";
            $this->connect->exec($sqlDesactivar);

            // Crear nueva configuración
            $sql = "INSERT INTO apoyo_combustibles.configuraciondiashabiles 
                (cantidad_dias, modificado_por, observaciones, activo)
                VALUES (?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->cantidad_dias,
                $this->idUsuario,
                $datos->observaciones ?? null
            ]);

            $nuevoId = $this->connect->lastInsertId();

            // Insertar detalle de días (si se proporcionó)
            if (isset($datos->dias_habiles) && is_array($datos->dias_habiles)) {
                $this->_insertarDetalleDias($nuevoId, $datos->dias_habiles);
            } else {
                // Insertar configuración por defecto: Lunes a Viernes hábiles
                $this->_insertarDetalleDiasPorDefecto($nuevoId);
            }

            $this->connect->commit();

            return $this->res->ok('Configuración creada correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearConfiguracionDiasHabiles: " . $e->getMessage());
            return $this->res->fail('Error al crear la configuración', $e);
        }
    }

    /**
     * Edita una configuración de días hábiles existente
     *
     * POST: combustible/editarConfiguracionDiasHabiles
     *
     * @param object $datos {
     *   idConfiguracionDiasHabiles: int,
     *   cantidad_dias: int,
     *   observaciones?: string,
     *   dias_habiles: array [
     *     { dia_semana: 1, es_habil: 1 },
     *     ...
     *   ]
     * }
     */
    public function editarConfiguracionDiasHabiles($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idConfiguracionDiasHabiles)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Validaciones
            $error = $this->_validarConfiguracionDiasHabiles($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeConfiguracionDiasHabiles($datos->idConfiguracionDiasHabiles)) {
                return $this->res->fail('La configuración no existe');
            }

            $this->connect->beginTransaction();

            // Actualizar configuración principal
            $sql = "UPDATE apoyo_combustibles.configuraciondiashabiles 
                SET cantidad_dias = ?,
                    modificado_por = ?,
                    observaciones = ?
                WHERE idConfiguracionDiasHabiles = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->cantidad_dias,
                $this->idUsuario,
                $datos->observaciones ?? null,
                (int)$datos->idConfiguracionDiasHabiles
            ]);

            // Actualizar detalle de días si se proporcionó
            if (isset($datos->dias_habiles) && is_array($datos->dias_habiles)) {
                // Eliminar detalle anterior
                $sqlDeleteDetalle = "DELETE FROM apoyo_combustibles.configuraciondiashabilesdetalle 
                                WHERE configuraciondiashabilesid = ?";
                $stmtDelete = $this->connect->prepare($sqlDeleteDetalle);
                $stmtDelete->execute([(int)$datos->idConfiguracionDiasHabiles]);

                // Insertar nuevo detalle
                $this->_insertarDetalleDias($datos->idConfiguracionDiasHabiles, $datos->dias_habiles);
            }

            $this->connect->commit();

            return $this->res->ok('Configuración actualizada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarConfiguracionDiasHabiles: " . $e->getMessage());
            return $this->res->fail('Error al editar la configuración', $e);
        }
    }

    /**
     * Desactiva una configuración de días hábiles
     *
     * POST: combustible/desactivarConfiguracionDiasHabiles
     *
     * @param object $datos {
     *   idConfiguracionDiasHabiles: int
     * }
     */
    public function desactivarConfiguracionDiasHabiles($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idConfiguracionDiasHabiles)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Verificar que existe
            if (!$this->_existeConfiguracionDiasHabiles($datos->idConfiguracionDiasHabiles)) {
                return $this->res->fail('La configuración no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.configuraciondiashabiles 
                SET activo = 0
                WHERE idConfiguracionDiasHabiles = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idConfiguracionDiasHabiles]);

            $this->connect->commit();

            return $this->res->ok('Configuración desactivada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarConfiguracionDiasHabiles: " . $e->getMessage());
            return $this->res->fail('Error al desactivar la configuración', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todas las configuraciones de días hábiles
     *
     * @return array Lista de configuraciones
     */
    private function _obtenerConfiguracionesDiasHabiles(): array
    {
        $sql = "SELECT
                c.idConfiguracionDiasHabiles,
                c.cantidad_dias,
                c.observaciones,
                c.activo,
                c.created_at,
                COUNT(d.idConfiguracionDiasHabilesDetalle) AS total_dias_configurados,
                SUM(CASE WHEN d.es_habil = 1 THEN 1 ELSE 0 END) AS dias_habiles_count,
                dtp.nombres AS modificado_por
            FROM
                apoyo_combustibles.configuraciondiashabiles AS c
                LEFT JOIN apoyo_combustibles.configuraciondiashabilesdetalle AS d ON c.idConfiguracionDiasHabiles = d.configuraciondiashabilesid
                LEFT JOIN dbintranet.usuarios AS us ON c.modificado_por = us.idUsuarios
                LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            GROUP BY
                c.idConfiguracionDiasHabiles
            ORDER BY
                c.activo DESC,
                c.created_at DESC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de una configuración de días hábiles
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarConfiguracionDiasHabiles($datos): ?string
    {
        // Validar cantidad de días
        if (empty($datos->cantidad_dias)) {
            return 'La cantidad de días es requerida';
        }

        $cantidadDias = (int)$datos->cantidad_dias;
        if ($cantidadDias < 1 || $cantidadDias > 30) {
            return 'La cantidad de días debe estar entre 1 y 30';
        }

        // Validar observaciones (opcional)
        if (isset($datos->observaciones) && strlen($datos->observaciones) > 500) {
            return 'Las observaciones no pueden exceder 500 caracteres';
        }

        // Validar días hábiles si se proporcionan
        if (isset($datos->dias_habiles)) {
            if (!is_array($datos->dias_habiles)) {
                return 'El formato de días hábiles es inválido';
            }

            foreach ($datos->dias_habiles as $dia) {
                if (!isset($dia->dia_semana) || !isset($dia->es_habil)) {
                    return 'Cada día debe tener dia_semana y es_habil';
                }

                $diaSemana = (int)$dia->dia_semana;
                if ($diaSemana < 1 || $diaSemana > 7) {
                    return 'El día de la semana debe estar entre 1 (Lunes) y 7 (Domingo)';
                }

                $esHabil = (int)$dia->es_habil;
                if ($esHabil !== 0 && $esHabil !== 1) {
                    return 'El valor de es_habil debe ser 0 o 1';
                }
            }
        }

        return null;
    }

    /**
     * Verifica si existe una configuración por ID
     *
     * @param int $id ID de la configuración
     * @return bool True si existe
     */
    private function _existeConfiguracionDiasHabiles(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.configuraciondiashabiles 
            WHERE idConfiguracionDiasHabiles = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Inserta el detalle de días hábiles
     *
     * @param int $configId ID de la configuración
     * @param array $diasHabiles Array con la configuración de días
     * @return void
     */
    private function _insertarDetalleDias(int $configId, array $diasHabiles): void
    {
        $sql = "INSERT INTO apoyo_combustibles.configuraciondiashabilesdetalle 
            (configuraciondiashabilesid, dia_semana, es_habil)
            VALUES (?, ?, ?)";

        $stmt = $this->connect->prepare($sql);

        foreach ($diasHabiles as $dia) {
            $stmt->execute([
                $configId,
                (int)$dia->dia_semana,
                (int)$dia->es_habil
            ]);
        }
    }

    /**
     * Inserta configuración por defecto de días (Lunes a Viernes hábiles)
     *
     * @param int $configId ID de la configuración
     * @return void
     */
    private function _insertarDetalleDiasPorDefecto(int $configId): void
    {
        $sql = "INSERT INTO apoyo_combustibles.configuraciondiashabilesdetalle 
            (configuraciondiashabilesid, dia_semana, es_habil)
            VALUES (?, ?, ?)";

        $stmt = $this->connect->prepare($sql);

        // 1=Lunes a 7=Domingo, Lunes-Viernes (1-5) son hábiles
        for ($dia = 1; $dia <= 7; $dia++) {
            $esHabil = ($dia <= 5) ? 1 : 0; // Lunes a Viernes hábiles
            $stmt->execute([$configId, $dia, $esHabil]);
        }
    }


    // ========================================================================
// 📋 CRUD - DÍAS DE DESCANSO
// ========================================================================

    /**
     * Lista todos los días de descanso del sistema
     *
     * GET: combustible/listarDiasDescanso
     */
    public function listarDiasDescanso()
    {
        try {
            $registros = $this->_obtenerDiasDescanso();

            return $this->res->ok('Días de descanso obtenidos correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarDiasDescanso: " . $e->getMessage());
            return $this->res->fail('Error al listar días de descanso', $e);
        }
    }

    /**
     * Obtiene los días de descanso activos del sistema
     *
     * GET: combustible/listarDiasDescansoActivos
     */
    public function listarDiasDescansoActivos()
    {
        try {
            $sql = "SELECT
                    did.idDiasDescanso,
                    did.fecha,
                    did.descripcion,
                    did.activo,
                    did.created_at,
                    did.updated_at,
                    dtp.nombres AS modificado_por
                FROM
                    apoyo_combustibles.diasdescanso AS did
                    LEFT JOIN dbintranet.usuarios AS us ON did.modificado_por = us.idUsuarios
                    LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                WHERE
                    activo = 1
                ORDER BY
                    did.fecha ASC";

            $stmt = $this->connect->query($sql);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Días de descanso activos obtenidos', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarDiasDescansoActivos: " . $e->getMessage());
            return $this->res->fail('Error al obtener días de descanso activos', $e);
        }
    }

    /**
     * Obtiene los días de descanso de un año específico
     *
     * POST: combustible/listarDiasDescansoPorAnio
     *
     * @param object $datos {
     *   anio: int
     * }
     */
    public function listarDiasDescansoPorAnio($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            $anio = isset($datos->anio) ? (int)$datos->anio : date('Y');

            $sql = "SELECT
                        did.idDiasDescanso,
                        did.fecha,
                        did.descripcion,
                        did.activo,
                        did.created_at,
                        did.updated_at,
                        dtp.nombres AS modificado_por
                    FROM
                        apoyo_combustibles.diasdescanso AS did
                        LEFT JOIN dbintranet.usuarios AS us ON did.modificado_por = us.idUsuarios
                        LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                    WHERE YEAR(did.fecha) = ?
                ORDER BY did.fecha ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$anio]);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Días de descanso obtenidos correctamente', [
                'registros' => $registros,
                'total' => count($registros),
                'anio' => $anio
            ]);

        } catch (Exception $e) {
            error_log("Error en listarDiasDescansoPorAnio: " . $e->getMessage());
            return $this->res->fail('Error al listar días de descanso por año', $e);
        }
    }

    /**
     * Crea un nuevo día de descanso
     *
     * POST: combustible/crearDiaDescanso
     *
     * @param object $datos {
     *   fecha: string (YYYY-MM-DD),
     *   descripcion?: string
     * }
     */
    public function crearDiaDescanso($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarDiaDescanso($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que la fecha no exista
            if ($this->_existeFechaDiaDescanso($datos->fecha)) {
                return $this->res->fail('Ya existe un día de descanso registrado para esta fecha');
            }

            $this->connect->beginTransaction();

            $sql = "INSERT INTO apoyo_combustibles.diasdescanso 
                (fecha, descripcion, modificado_por, activo)
                VALUES (?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->fecha,
                $datos->descripcion ?? null,
                $this->idUsuario
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Día de descanso creado correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearDiaDescanso: " . $e->getMessage());
            return $this->res->fail('Error al crear el día de descanso', $e);
        }
    }

    /**
     * Edita un día de descanso existente
     *
     * POST: combustible/editarDiaDescanso
     *
     * @param object $datos {
     *   idDiasDescanso: int,
     *   fecha: string (YYYY-MM-DD),
     *   descripcion?: string
     * }
     */
    public function editarDiaDescanso($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idDiasDescanso)) {
                return $this->res->fail('El ID del día de descanso es requerido');
            }

            // Validaciones
            $error = $this->_validarDiaDescanso($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeDiaDescanso($datos->idDiasDescanso)) {
                return $this->res->fail('El día de descanso no existe');
            }

            // Verificar que la fecha no esté duplicada (excluyendo el actual)
            if ($this->_existeFechaDiaDescansoExceptoId($datos->fecha, $datos->idDiasDescanso)) {
                return $this->res->fail('Ya existe un día de descanso registrado para esta fecha');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.diasdescanso 
                SET fecha = ?,
                    descripcion = ?,
                    modificado_por = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idDiasDescanso = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->fecha,
                $datos->descripcion ?? null,
                $this->idUsuario,
                (int)$datos->idDiasDescanso
            ]);

            $this->connect->commit();

            return $this->res->ok('Día de descanso actualizado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarDiaDescanso: " . $e->getMessage());
            return $this->res->fail('Error al editar el día de descanso', $e);
        }
    }

    /**
     * Desactiva un día de descanso
     *
     * POST: combustible/desactivarDiaDescanso
     *
     * @param object $datos {
     *   idDiasDescanso: int
     * }
     */
    public function desactivarDiaDescanso($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idDiasDescanso)) {
                return $this->res->fail('El ID del día de descanso es requerido');
            }

            // Verificar que existe
            if (!$this->_existeDiaDescanso($datos->idDiasDescanso)) {
                return $this->res->fail('El día de descanso no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.diasdescanso 
                SET activo = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idDiasDescanso = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idDiasDescanso]);

            $this->connect->commit();

            return $this->res->ok('Día de descanso desactivado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarDiaDescanso: " . $e->getMessage());
            return $this->res->fail('Error al desactivar el día de descanso', $e);
        }
    }

    /**
     * Activa un día de descanso
     *
     * POST: combustible/activarDiaDescanso
     *
     * @param object $datos {
     *   idDiasDescanso: int
     * }
     */
    public function activarDiaDescanso($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idDiasDescanso)) {
                return $this->res->fail('El ID del día de descanso es requerido');
            }

            // Verificar que existe
            if (!$this->_existeDiaDescanso($datos->idDiasDescanso)) {
                return $this->res->fail('El día de descanso no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.diasdescanso 
                SET activo = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idDiasDescanso = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idDiasDescanso]);

            $this->connect->commit();

            return $this->res->ok('Día de descanso activado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en activarDiaDescanso: " . $e->getMessage());
            return $this->res->fail('Error al activar el día de descanso', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todos los días de descanso
     *
     * @return array Lista de días de descanso
     */
    private function _obtenerDiasDescanso(): array
    {
        $sql = "SELECT
                    did.idDiasDescanso,
                    did.fecha,
                    did.descripcion,
                    did.activo,
                    did.created_at,
                    did.updated_at,
                    YEAR(fecha) AS anio,
                    MONTH(fecha) AS mes,
                    DAYNAME(fecha) AS nombre_dia,
                    dtp.nombres AS modificado_por
                FROM
                    apoyo_combustibles.diasdescanso AS did
                    INNER JOIN dbintranet.usuarios AS us ON did.modificado_por = us.idUsuarios
                    INNER JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                ORDER BY
                    did.fecha DESC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de un día de descanso
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarDiaDescanso($datos): ?string
    {
        // Validar fecha
        if (empty($datos->fecha)) {
            return 'La fecha es requerida';
        }

        // Validar formato de fecha
        $fecha = \DateTime::createFromFormat('Y-m-d', $datos->fecha);
        if (!$fecha || $fecha->format('Y-m-d') !== $datos->fecha) {
            return 'El formato de fecha es inválido (use YYYY-MM-DD)';
        }

        // Validar descripción (opcional)
        if (isset($datos->descripcion) && strlen($datos->descripcion) > 100) {
            return 'La descripción no puede exceder 100 caracteres';
        }

        return null;
    }

    /**
     * Verifica si existe un día de descanso por ID
     *
     * @param int $id ID del día de descanso
     * @return bool True si existe
     */
    private function _existeDiaDescanso(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.diasdescanso 
            WHERE idDiasDescanso = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe una fecha de día de descanso
     *
     * @param string $fecha Fecha a verificar
     * @return bool True si existe
     */
    private function _existeFechaDiaDescanso(string $fecha): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.diasdescanso 
            WHERE fecha = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$fecha]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe una fecha excluyendo un ID específico
     *
     * @param string $fecha Fecha a verificar
     * @param int $idExcluir ID a excluir de la búsqueda
     * @return bool True si existe
     */
    private function _existeFechaDiaDescansoExceptoId(string $fecha, int $idExcluir): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.diasdescanso 
            WHERE fecha = ? AND idDiasDescanso != ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$fecha, $idExcluir]);
        return (int)$stmt->fetchColumn() > 0;
    }


    //! porcentaje de devolucion

    // ========================================================================
// 📋 CRUD - CONFIGURACIÓN PORCENTAJE ANUAL
// ========================================================================

    /**
     * Lista todas las configuraciones de porcentaje anual del sistema
     *
     * GET: combustible/listarConfiguracionesPorcentajeAnual
     */
    public function listarConfiguracionesPorcentajeAnual()
    {
        try {
            $registros = $this->_obtenerConfiguracionesPorcentajeAnual();

            return $this->res->ok('Configuraciones obtenidas correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarConfiguracionesPorcentajeAnual: " . $e->getMessage());
            return $this->res->fail('Error al listar configuraciones de porcentaje anual', $e);
        }
    }

    /**
     * Obtiene la configuración activa de porcentaje anual
     *
     * GET: combustible/obtenerConfiguracionPorcentajeAnualActiva
     */
    public function obtenerConfiguracionPorcentajeAnualActiva()
    {
        try {
            $sql = "SELECT
                        cnfp.idConfiguracionPorcentajeAnual,
                        cnfp.porcentaje,
                        cnfp.observaciones,
                        cnfp.activo,
                        cnfp.created_at,
                        dtp.nombres AS modificado_por
                    FROM
                        apoyo_combustibles.configuracionporcentajeanual AS cnfp
                        LEFT JOIN dbintranet.usuarios AS us ON cnfp.modificado_por = us.idUsuarios
                        LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                    WHERE
                        activo = 1
                    ORDER BY
                        cnfp.created_at DESC
                        LIMIT 1";

            $stmt = $this->connect->query($sql);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return $this->res->fail('No hay configuración activa en el sistema');
            }

            return $this->res->ok('Configuración activa obtenida', $config);

        } catch (Exception $e) {
            error_log("Error en obtenerConfiguracionPorcentajeAnualActiva: " . $e->getMessage());
            return $this->res->fail('Error al obtener configuración activa', $e);
        }
    }

    /**
     * Crea una nueva configuración de porcentaje anual
     *
     * POST: combustible/crearConfiguracionPorcentajeAnual
     *
     * @param object $datos {
     *   porcentaje: float,
     *   observaciones?: string
     * }
     */
    public function crearConfiguracionPorcentajeAnual($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarConfiguracionPorcentajeAnual($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            $this->connect->beginTransaction();

            // Desactivar configuraciones anteriores
            $sqlDesactivar = "UPDATE apoyo_combustibles.configuracionporcentajeanual 
                          SET activo = 0 
                          WHERE activo = 1";
            $this->connect->exec($sqlDesactivar);

            // Crear nueva configuración
            $sql = "INSERT INTO apoyo_combustibles.configuracionporcentajeanual 
                (porcentaje, observaciones, modificado_por, activo)
                VALUES (?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (float)$datos->porcentaje,
                $datos->observaciones ?? null,
                $this->idUsuario
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Configuración creada correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearConfiguracionPorcentajeAnual: " . $e->getMessage());
            return $this->res->fail('Error al crear la configuración', $e);
        }
    }

    /**
     * Edita una configuración de porcentaje anual existente
     *
     * POST: combustible/editarConfiguracionPorcentajeAnual
     *
     * @param object $datos {
     *   idConfiguracionPorcentajeAnual: int,
     *   porcentaje: float,
     *   observaciones?: string
     * }
     */
    public function editarConfiguracionPorcentajeAnual($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idConfiguracionPorcentajeAnual)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Validaciones
            $error = $this->_validarConfiguracionPorcentajeAnual($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeConfiguracionPorcentajeAnual($datos->idConfiguracionPorcentajeAnual)) {
                return $this->res->fail('La configuración no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.configuracionporcentajeanual 
                SET porcentaje = ?,
                    observaciones = ?,
                    modificado_por = ?
                WHERE idConfiguracionPorcentajeAnual = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (float)$datos->porcentaje,
                $datos->observaciones ?? null,
                $this->idUsuario,
                (int)$datos->idConfiguracionPorcentajeAnual
            ]);

            $this->connect->commit();

            return $this->res->ok('Configuración actualizada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarConfiguracionPorcentajeAnual: " . $e->getMessage());
            return $this->res->fail('Error al editar la configuración', $e);
        }
    }

    /**
     * Desactiva una configuración de porcentaje anual
     *
     * POST: combustible/desactivarConfiguracionPorcentajeAnual
     *
     * @param object $datos {
     *   idConfiguracionPorcentajeAnual: int
     * }
     */
    public function desactivarConfiguracionPorcentajeAnual($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idConfiguracionPorcentajeAnual)) {
                return $this->res->fail('El ID de configuración es requerido');
            }

            // Verificar que existe
            if (!$this->_existeConfiguracionPorcentajeAnual($datos->idConfiguracionPorcentajeAnual)) {
                return $this->res->fail('La configuración no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.configuracionporcentajeanual 
                SET activo = 0
                WHERE idConfiguracionPorcentajeAnual = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idConfiguracionPorcentajeAnual]);

            $this->connect->commit();

            return $this->res->ok('Configuración desactivada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarConfiguracionPorcentajeAnual: " . $e->getMessage());
            return $this->res->fail('Error al desactivar la configuración', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todas las configuraciones de porcentaje anual
     *
     * @return array Lista de configuraciones
     */
    private function _obtenerConfiguracionesPorcentajeAnual(): array
    {
        $sql = "SELECT
                    cpor.idConfiguracionPorcentajeAnual,
                    cpor.porcentaje,
                    cpor.observaciones,
                    cpor.activo,
                    cpor.created_at,
                    dtp.nombres AS modificado_por
                FROM
                    apoyo_combustibles.configuracionporcentajeanual AS cpor
                    LEFT JOIN dbintranet.usuarios AS us ON cpor.modificado_por = us.idUsuarios
                    LEFT JOIN dbintranet.datospersonales AS dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                ORDER BY
                    cpor.activo DESC,
                    cpor.created_at DESC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de una configuración de porcentaje anual
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarConfiguracionPorcentajeAnual($datos): ?string
    {
        // Validar porcentaje
        if (!isset($datos->porcentaje) || $datos->porcentaje === '') {
            return 'El porcentaje es requerido';
        }

        $porcentaje = (float)$datos->porcentaje;

        if ($porcentaje < 0) {
            return 'El porcentaje no puede ser negativo';
        }

        if ($porcentaje > 100) {
            return 'El porcentaje no puede ser mayor a 100';
        }

        // Validar que tenga máximo 2 decimales
        if (round($porcentaje, 2) != $porcentaje) {
            return 'El porcentaje solo puede tener hasta 2 decimales';
        }

        // Validar observaciones (opcional)
        if (isset($datos->observaciones) && strlen($datos->observaciones) > 500) {
            return 'Las observaciones no pueden exceder 500 caracteres';
        }

        return null;
    }

    /**
     * Verifica si existe una configuración por ID
     *
     * @param int $id ID de la configuración
     * @return bool True si existe
     */
    private function _existeConfiguracionPorcentajeAnual(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.configuracionporcentajeanual 
            WHERE idConfiguracionPorcentajeAnual = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    //? control de dias habiles

    // ========================================================================
// 📋 CRUD - USUARIOS CONTROL FECHAS
// ========================================================================

    /**
     * Lista todos los registros de control de fechas
     *
     * GET: combustible/listarUsuariosControlFechas
     */
    public function listarUsuariosControlFechas()
    {
        try {
            $registros = $this->_obtenerUsuariosControlFechas();

            return $this->res->ok('Registros obtenidos correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarUsuariosControlFechas: " . $e->getMessage());
            return $this->res->fail('Error al listar registros de control de fechas', $e);
        }
    }

    /**
     * Lista los registros activos de control de fechas
     *
     * GET: combustible/listarUsuariosControlFechasActivos
     */
    public function listarUsuariosControlFechasActivos()
    {
        try {
            $sql = "SELECT 
                ucf.idUsuariosControlFechas,
                ucf.usuarioid,
                ucf.puestoid,
                ucf.agenciaid,
                ucf.fecha_ingreso,
                ucf.fecha_egreso,
                ucf.activo,
                ucf.es_nuevo,
                ucf.porcentaje_presupuesto,
                ucf.dias_presupuesto,
                ucf.fecha_fin_prueba,
                ucf.created_at,
                ucf.updated_at,
                dp.nombres as nombre_completo,
                a.nombre as agencia,
                p.nombre as puesto
            FROM apoyo_combustibles.usuarioscontrolfechas ucf
            LEFT JOIN dbintranet.usuarios u ON ucf.usuarioid = u.idUsuarios
            LEFT JOIN dbintranet.datospersonales dp ON u.idDatosPersonales = dp.idDatosPersonales
            LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
            LEFT JOIN dbintranet.puesto p ON ucf.puestoid = p.idPuesto
            WHERE ucf.activo = 1
            ORDER BY dp.nombres ASC, ucf.fecha_ingreso DESC";

            $stmt = $this->connect->query($sql);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Registros activos obtenidos', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarUsuariosControlFechasActivos: " . $e->getMessage());
            return $this->res->fail('Error al obtener registros activos', $e);
        }
    }

    /**
     * Lista todos los usuarios disponibles del sistema de seguridad
     *
     * GET: combustible/listarUsuariosDisponibles
     */
    public function listarUsuariosDisponibles()
    {
        try {
            $sql = "SELECT 
                    u.idUsuarios,
                    dp.nombres as nombre_completo,
                    a.nombre as agencia,
                    dc.departamentoCooperativa as departamento
                FROM dbintranet.usuarios u
                INNER JOIN dbintranet.datospersonales dp ON u.idDatosPersonales = dp.idDatosPersonales
                LEFT JOIN dbintranet.agencia a ON u.idAgencia = a.idAgencia
                LEFT JOIN dbintranet.departamentocooperativa dc ON u.idDepartamentoCooperativa = dc.idDepartamentoCooperativa
                WHERE u.idEstados in (1, 5)
                ORDER BY dp.nombres ASC";

            $stmt = $this->connect->query($sql);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Usuarios disponibles obtenidos', [
                'usuarios' => $usuarios,
                'total' => count($usuarios)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarUsuariosDisponibles: " . $e->getMessage());
            return $this->res->fail('Error al obtener usuarios disponibles', $e);
        }
    }

    /**
     * Obtiene el historial de un usuario específico
     *
     * POST: combustible/obtenerHistorialUsuario
     *
     * @param object $datos {
     *   usuarioid: string
     * }
     */
    public function obtenerHistorialUsuario($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->usuarioid)) {
                return $this->res->fail('El ID del usuario es requerido');
            }

            $sql = "SELECT 
                ucf.idUsuariosControlFechas,
                ucf.usuarioid,
                ucf.puestoid,
                ucf.agenciaid,
                ucf.fecha_ingreso,
                ucf.fecha_egreso,
                ucf.activo,
                ucf.es_nuevo,
                ucf.porcentaje_presupuesto,
                ucf.dias_presupuesto,
                ucf.fecha_fin_prueba,
                ucf.created_at,
                ucf.updated_at,
                dp.nombres as nombre_completo,
                a.nombre as agencia,
                p.nombre as puesto
            FROM apoyo_combustibles.usuarioscontrolfechas ucf
            LEFT JOIN dbintranet.usuarios u ON ucf.usuarioid = u.idUsuarios
            LEFT JOIN dbintranet.datospersonales dp ON u.idDatosPersonales = dp.idDatosPersonales
            LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
            LEFT JOIN dbintranet.puesto p ON ucf.puestoid = p.idPuesto
            WHERE ucf.usuarioid = ?
            ORDER BY ucf.fecha_ingreso DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->usuarioid]);
            $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Historial obtenido correctamente', [
                'historial' => $historial,
                'total' => count($historial)
            ]);

        } catch (Exception $e) {
            error_log("Error en obtenerHistorialUsuario: " . $e->getMessage());
            return $this->res->fail('Error al obtener historial del usuario', $e);
        }
    }

    /**
     * Crea un nuevo registro de control de fechas
     *
     * POST: combustible/crearUsuarioControlFechas
     *
     * @param object $datos {
     *   usuarioid: string,
     *   puestoid: int,
     *   agenciaid: int,
     *   fecha_ingreso: string (YYYY-MM-DD),
     *   fecha_egreso?: string (YYYY-MM-DD),
     *   es_nuevo: int,
     *   porcentaje_presupuesto: int,
     *   dias_presupuesto: int
     * }
     */
    public function crearUsuarioControlFechas($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarUsuarioControlFechas($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que el usuario existe en el sistema de seguridad
            if (!$this->_existeUsuarioSistema($datos->usuarioid)) {
                return $this->res->fail('El usuario no existe en el sistema');
            }

            // Validar que no haya solapamiento de fechas para el mismo usuario
            $error = $this->_validarSolapamientoFechas(
                $datos->usuarioid,
                $datos->fecha_ingreso,
                $datos->fecha_egreso ?? null
            );
            if ($error) {
                return $this->res->fail($error);
            }

            $this->connect->beginTransaction();

            $sql = "INSERT INTO apoyo_combustibles.usuarioscontrolfechas 
                (usuarioid, puestoid, agenciaid, fecha_ingreso, fecha_egreso, 
                es_nuevo, porcentaje_presupuesto, dias_presupuesto, fecha_fin_prueba, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->usuarioid,
                $datos->puestoid,
                $datos->agenciaid,
                $datos->fecha_ingreso,
                $datos->fecha_egreso ?? null,
                $datos->es_nuevo ?? 0,
                $datos->porcentaje_presupuesto ?? null,
                $datos->dias_presupuesto ?? null,
                $datos->fecha_fin_prueba ?? null,   // <-- nuevo
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Registro creado correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearUsuarioControlFechas: " . $e->getMessage());
            return $this->res->fail('Error al crear el registro', $e);
        }
    }

    /**
     * Edita un registro de control de fechas existente
     *
     * POST: combustible/editarUsuarioControlFechas
     *
     * @param object $datos {
     *   idUsuariosControlFechas: int,
     *   usuarioid: string,
     *   puestoid: int,
     *   agenciaid: int,
     *   fecha_ingreso: string (YYYY-MM-DD),
     *   fecha_egreso?: string (YYYY-MM-DD),
     *   es_nuevo: int,
     *   porcentaje_presupuesto: int,
     *   dias_presupuesto: int
     * }
     */
    public function editarUsuarioControlFechas($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idUsuariosControlFechas)) {
                return $this->res->fail('El ID del registro es requerido');
            }

            // Validaciones
            $error = $this->_validarUsuarioControlFechas($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existeUsuarioControlFechas($datos->idUsuariosControlFechas)) {
                return $this->res->fail('El registro no existe');
            }

            // Verificar que el usuario existe en el sistema de seguridad
            if (!$this->_existeUsuarioSistema($datos->usuarioid)) {
                return $this->res->fail('El usuario no existe en el sistema');
            }

            // Validar que no haya solapamiento de fechas (excluyendo el registro actual)
            $error = $this->_validarSolapamientoFechas(
                $datos->usuarioid,
                $datos->fecha_ingreso,
                $datos->fecha_egreso ?? null,
                $datos->idUsuariosControlFechas
            );
            if ($error) {
                return $this->res->fail($error);
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.usuarioscontrolfechas 
                SET usuarioid = ?,
                    puestoid = ?,
                    agenciaid = ?,
                    fecha_ingreso = ?,
                    fecha_egreso = ?,
                    es_nuevo = ?,
                    porcentaje_presupuesto = ?,
                    dias_presupuesto = ?,
                    fecha_fin_prueba = ?,           -- <-- nuevo
                    updated_at = CURRENT_TIMESTAMP
                WHERE idUsuariosControlFechas = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->usuarioid,
                $datos->puestoid,
                $datos->agenciaid,
                $datos->fecha_ingreso,
                $datos->fecha_egreso ?? null,
                $datos->es_nuevo ?? 0,
                $datos->porcentaje_presupuesto ?? null,
                $datos->dias_presupuesto ?? null,
                $datos->fecha_fin_prueba ?? null,   // <-- nuevo
                (int)$datos->idUsuariosControlFechas
            ]);

            $this->connect->commit();

            return $this->res->ok('Registro actualizado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarUsuarioControlFechas: " . $e->getMessage());
            return $this->res->fail('Error al editar el registro', $e);
        }
    }

    /**
     * Desactiva un registro de control de fechas
     *
     * POST: combustible/desactivarUsuarioControlFechas
     *
     * @param object $datos {
     *   idUsuariosControlFechas: int
     * }
     */
    public function desactivarUsuarioControlFechas($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idUsuariosControlFechas)) {
                return $this->res->fail('El ID del registro es requerido');
            }

            // Verificar que existe
            if (!$this->_existeUsuarioControlFechas($datos->idUsuariosControlFechas)) {
                return $this->res->fail('El registro no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.usuarioscontrolfechas 
                SET activo = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idUsuariosControlFechas = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idUsuariosControlFechas]);

            $this->connect->commit();

            return $this->res->ok('Registro desactivado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarUsuarioControlFechas: " . $e->getMessage());
            return $this->res->fail('Error al desactivar el registro', $e);
        }
    }

    /**
     * Activa un registro de control de fechas
     *
     * POST: combustible/activarUsuarioControlFechas
     *
     * @param object $datos {
     *   idUsuariosControlFechas: int
     * }
     */
    public function activarUsuarioControlFechas($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idUsuariosControlFechas)) {
                return $this->res->fail('El ID del registro es requerido');
            }

            // Verificar que existe
            if (!$this->_existeUsuarioControlFechas($datos->idUsuariosControlFechas)) {
                return $this->res->fail('El registro no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.usuarioscontrolfechas 
                SET activo = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idUsuariosControlFechas = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idUsuariosControlFechas]);

            $this->connect->commit();

            return $this->res->ok('Registro activado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en activarUsuarioControlFechas: " . $e->getMessage());
            return $this->res->fail('Error al activar el registro', $e);
        }
    }


// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todos los registros de control de fechas con información del usuario
     *
     * @return array Lista de registros
     */
    private function _obtenerUsuariosControlFechas(): array
    {
        $sql = "SELECT 
            ucf.idUsuariosControlFechas,
            ucf.usuarioid,
            ucf.puestoid,
            ucf.agenciaid,
            ucf.fecha_ingreso,
            ucf.fecha_egreso,
            ucf.activo,
            ucf.es_nuevo,
            ucf.porcentaje_presupuesto,
            ucf.dias_presupuesto,
            ucf.fecha_fin_prueba,
            ucf.created_at,
            ucf.updated_at,
            dp.nombres as nombre_completo,
            u.usuario,
            a.nombre as agencia,
            p.nombre as puesto,
            dc.departamentoCooperativa as departamento,
            DATEDIFF(
                COALESCE(ucf.fecha_egreso, CURDATE()), 
                ucf.fecha_ingreso
            ) as dias_laborados
        FROM apoyo_combustibles.usuarioscontrolfechas ucf
        LEFT JOIN dbintranet.usuarios u ON ucf.usuarioid = u.idUsuarios
        LEFT JOIN dbintranet.datospersonales dp ON u.idDatosPersonales = dp.idDatosPersonales
        LEFT JOIN dbintranet.agencia a ON ucf.agenciaid = a.idAgencia
        LEFT JOIN dbintranet.puesto p ON ucf.puestoid = p.idPuesto
        LEFT JOIN dbintranet.departamentocooperativa dc ON u.idDepartamentoCooperativa = dc.idDepartamentoCooperativa
        ORDER BY ucf.activo DESC, dp.nombres ASC, ucf.fecha_ingreso DESC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de un registro de control de fechas
     *
     * @param object $datos Datos a validar
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarUsuarioControlFechas($datos): ?string
    {
        // Validar usuarioid
        if (empty($datos->usuarioid)) {
            return 'El ID del usuario es requerido';
        }

        // Validar puestoid
        if (empty($datos->puestoid)) {
            return 'El puesto es requerido';
        }

        // Validar agenciaid
        if (empty($datos->agenciaid)) {
            return 'La agencia es requerida';
        }

        // Validar fecha_ingreso
        if (empty($datos->fecha_ingreso)) {
            return 'La fecha de ingreso es requerida';
        }

        // Validar formato de fecha_ingreso
        $fechaIngreso = \DateTime::createFromFormat('Y-m-d', $datos->fecha_ingreso);
        if (!$fechaIngreso || $fechaIngreso->format('Y-m-d') !== $datos->fecha_ingreso) {
            return 'El formato de fecha de ingreso es inválido (use YYYY-MM-DD)';
        }

        // Validar fecha_egreso si se proporciona
        if (isset($datos->fecha_egreso) && !empty($datos->fecha_egreso)) {
            $fechaEgreso = \DateTime::createFromFormat('Y-m-d', $datos->fecha_egreso);
            if (!$fechaEgreso || $fechaEgreso->format('Y-m-d') !== $datos->fecha_egreso) {
                return 'El formato de fecha de egreso es inválido (use YYYY-MM-DD)';
            }

            // Validar que fecha_egreso sea mayor o igual a fecha_ingreso
            if ($fechaEgreso < $fechaIngreso) {
                return 'La fecha de egreso no puede ser anterior a la fecha de ingreso';
            }
        }

        // Validar campos de restricción si es_nuevo = 1
        if (isset($datos->es_nuevo) && $datos->es_nuevo == 1) {
            if (!isset($datos->porcentaje_presupuesto) || $datos->porcentaje_presupuesto <= 0 || $datos->porcentaje_presupuesto > 100) {
                return 'El porcentaje de presupuesto debe estar entre 1 y 100';
            }
            if (!isset($datos->dias_presupuesto) || $datos->dias_presupuesto <= 0) {
                return 'Los días de presupuesto deben ser mayor a 0';
            }

            // Validar fecha_fin_prueba
            if (empty($datos->fecha_fin_prueba)) {
                return 'La fecha de fin de prueba es requerida para empleados nuevos';
            }

            $fechaFinPrueba = \DateTime::createFromFormat('Y-m-d', $datos->fecha_fin_prueba);
            if (!$fechaFinPrueba || $fechaFinPrueba->format('Y-m-d') !== $datos->fecha_fin_prueba) {
                return 'El formato de fecha de fin de prueba es inválido (use YYYY-MM-DD)';
            }

            // Debe ser mayor a fecha_ingreso
            $fechaIngresoDT = \DateTime::createFromFormat('Y-m-d', $datos->fecha_ingreso);
            if ($fechaFinPrueba <= $fechaIngresoDT) {
                return 'La fecha de fin de prueba debe ser posterior a la fecha de ingreso';
            }
        }

        return null;
    }

    /**
     * Verifica si existe un registro por ID
     *
     * @param int $id ID del registro
     * @return bool True si existe
     */
    private function _existeUsuarioControlFechas(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.usuarioscontrolfechas 
            WHERE idUsuariosControlFechas = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe un usuario en el sistema de seguridad
     *
     * @param string $usuarioid ID del usuario
     * @return bool True si existe
     */
    private function _existeUsuarioSistema(string $usuarioid): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM dbintranet.usuarios 
            WHERE idUsuarios = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$usuarioid]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Valida que no haya solapamiento de fechas para un usuario
     *
     * @param string $usuarioid ID del usuario
     * @param string $fechaIngreso Fecha de ingreso
     * @param string|null $fechaEgreso Fecha de egreso
     * @param int|null $idExcluir ID a excluir en la validación (para edición)
     * @return string|null Mensaje de error o null si es válido
     */
    private function _validarSolapamientoFechas(
        string  $usuarioid,
        string  $fechaIngreso,
        ?string $fechaEgreso,
        ?int    $idExcluir = null
    ): ?string
    {
        // Si no hay fecha de egreso, consideramos que el período está abierto (actual)
        $fechaEgresoComparar = $fechaEgreso ?? '9999-12-31';

        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.usuarioscontrolfechas 
            WHERE usuarioid = ?
            AND activo = 1";

        // Excluir el registro actual si es edición
        if ($idExcluir !== null) {
            $sql .= " AND idUsuariosControlFechas != ?";
        }

        // Verificar solapamiento:
        // - El nuevo período no debe comenzar dentro de un período existente
        // - El nuevo período no debe terminar dentro de un período existente
        // - El nuevo período no debe contener completamente un período existente
        $sql .= " AND (
        (? BETWEEN fecha_ingreso AND COALESCE(fecha_egreso, '9999-12-31'))
        OR (? BETWEEN fecha_ingreso AND COALESCE(fecha_egreso, '9999-12-31'))
        OR (fecha_ingreso BETWEEN ? AND ?)
    )";

        $stmt = $this->connect->prepare($sql);

        $params = [$usuarioid];
        if ($idExcluir !== null) {
            $params[] = $idExcluir;
        }
        $params[] = $fechaIngreso;
        $params[] = $fechaEgresoComparar;
        $params[] = $fechaIngreso;
        $params[] = $fechaEgresoComparar;

        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) {
            return 'Las fechas se solapan con un período existente para este usuario';
        }

        return null;
    }

    //control de vehiculos
// ========================================================================
// 📋 CRUD - MIS VEHÍCULOS (SOLO USUARIO ACTUAL)
// ========================================================================

    /**
     * Lista los vehículos del usuario actual
     *
     * GET: combustible/listarMisVehiculos
     */
    public function listarMisVehiculos()
    {
        try {
            // Obtener el usuario actual de la sesión
            $usuarioid = $this->idUsuario ?? null;

            if (empty($usuarioid)) {
                return $this->res->fail('Usuario no autenticado');
            }

            $sql = "SELECT 
                    v.idVehiculos,
                    v.usuarioid,
                    v.tipovehiculoid,
                    v.placa,
                    v.marca,
                    v.activo,
                    v.created_at,
                    v.updated_at,
                    tv.nombre as tipo_vehiculo
                FROM apoyo_combustibles.vehiculos v
                LEFT JOIN apoyo_combustibles.tiposvehiculo tv ON v.tipovehiculoid = tv.idTiposVehiculo
                WHERE v.usuarioid = ?
                ORDER BY v.activo DESC, v.placa ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$usuarioid]);
            $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Vehículos obtenidos correctamente', [
                'vehiculos' => $vehiculos,
                'total' => count($vehiculos)
            ]);
            /*return $this->res->info('Vehículos obtenidos correctamente (MODO PRUEBA)', null, [
                'datos' => $vehiculos,
                'total' => count($vehiculos)
            ]);*/

        } catch (Exception $e) {
            error_log("Error en listarMisVehiculos: " . $e->getMessage());
            return $this->res->fail('Error al listar vehículos', $e);
        }
    }

    /**
     * Crea un nuevo vehículo para el usuario actual
     *
     * POST: combustible/crearMiVehiculo
     *
     * @param object $datos {
     *   tipovehiculoid: int,
     *   placa: string,
     *   marca?: string
     * }
     */
    public function crearMiVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Obtener el usuario actual de la sesión
            $usuarioid = $this->idUsuario ?? null;

            if (empty($usuarioid)) {
                return $this->res->fail('Usuario no autenticado');
            }

            // Validaciones
            if (empty($datos->tipovehiculoid)) {
                return $this->res->fail('El tipo de vehículo es requerido');
            }

            if (empty($datos->placa)) {
                return $this->res->fail('La placa es requerida');
            }

            if (strlen($datos->placa) > 20) {
                return $this->res->fail('La placa no puede exceder 20 caracteres');
            }

            if (isset($datos->marca) && strlen($datos->marca) > 50) {
                return $this->res->fail('La marca no puede exceder 50 caracteres');
            }

            // Verificar que el tipo de vehículo existe
            if (!$this->_existeTipoVehiculo($datos->tipovehiculoid)) {
                return $this->res->fail('El tipo de vehículo no existe');
            }

            // Verificar que no exista la combinación usuario-placa para este usuario
            if ($this->_existeVehiculoUsuarioPlaca($usuarioid, $datos->placa)) {
                return $this->res->fail('Ya tienes un vehículo registrado con esta placa');
            }

            $this->connect->beginTransaction();

            $sql = "INSERT INTO apoyo_combustibles.vehiculos 
                (usuarioid, tipovehiculoid, placa, marca, activo)
                VALUES (?, ?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $usuarioid,
                (int)$datos->tipovehiculoid,
                strtoupper($datos->placa),
                $datos->marca ?? null
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Vehículo registrado correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearMiVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al registrar el vehículo', $e);
        }
    }

    /**
     * Edita un vehículo del usuario actual
     *
     * POST: combustible/editarMiVehiculo
     *
     * @param object $datos {
     *   idVehiculos: int,
     *   tipovehiculoid: int,
     *   placa: string,
     *   marca?: string
     * }
     */
    public function editarMiVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Obtener el usuario actual de la sesión
            $usuarioid = $this->idUsuario ?? null;

            if (empty($usuarioid)) {
                return $this->res->fail('Usuario no autenticado');
            }

            // Validar ID
            if (empty($datos->idVehiculos)) {
                return $this->res->fail('El ID del vehículo es requerido');
            }

            // Validaciones
            if (empty($datos->tipovehiculoid)) {
                return $this->res->fail('El tipo de vehículo es requerido');
            }

            if (empty($datos->placa)) {
                return $this->res->fail('La placa es requerida');
            }

            if (strlen($datos->placa) > 20) {
                return $this->res->fail('La placa no puede exceder 20 caracteres');
            }

            if (isset($datos->marca) && strlen($datos->marca) > 50) {
                return $this->res->fail('La marca no puede exceder 50 caracteres');
            }

            // Verificar que el vehículo existe Y pertenece al usuario actual
            if (!$this->_existeVehiculoDeUsuario($datos->idVehiculos, $usuarioid)) {
                return $this->res->fail('El vehículo no existe o no tienes permiso para editarlo');
            }

            // Verificar que el tipo de vehículo existe
            if (!$this->_existeTipoVehiculo($datos->tipovehiculoid)) {
                return $this->res->fail('El tipo de vehículo no existe');
            }

            // Verificar unicidad usuario-placa (excluyendo el vehículo actual)
            if ($this->_existeVehiculoUsuarioPlacaExceptoId(
                $usuarioid,
                $datos->placa,
                $datos->idVehiculos
            )) {
                return $this->res->fail('Ya tienes otro vehículo registrado con esta placa');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.vehiculos 
                SET tipovehiculoid = ?,
                    placa = ?,
                    marca = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idVehiculos = ? AND usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->tipovehiculoid,
                strtoupper($datos->placa),
                $datos->marca ?? null,
                (int)$datos->idVehiculos,
                $usuarioid
            ]);

            $this->connect->commit();

            return $this->res->ok('Vehículo actualizado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarMiVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al editar el vehículo', $e);
        }
    }

    /**
     * Desactiva un vehículo del usuario actual
     *
     * POST: combustible/desactivarMiVehiculo
     *
     * @param object $datos {
     *   idVehiculos: int
     * }
     */
    public function desactivarMiVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Obtener el usuario actual de la sesión
            $usuarioid = $this->idUsuario ?? null;

            if (empty($usuarioid)) {
                return $this->res->fail('Usuario no autenticado');
            }

            if (empty($datos->idVehiculos)) {
                return $this->res->fail('El ID del vehículo es requerido');
            }

            // Verificar que el vehículo existe Y pertenece al usuario actual
            if (!$this->_existeVehiculoDeUsuario($datos->idVehiculos, $usuarioid)) {
                return $this->res->fail('El vehículo no existe o no tienes permiso para desactivarlo');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.vehiculos 
                SET activo = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idVehiculos = ? AND usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idVehiculos, $usuarioid]);

            $this->connect->commit();

            return $this->res->ok('Vehículo desactivado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarMiVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al desactivar el vehículo', $e);
        }
    }

    /**
     * Activa un vehículo del usuario actual
     *
     * POST: combustible/activarMiVehiculo
     *
     * @param object $datos {
     *   idVehiculos: int
     * }
     */
    public function activarMiVehiculo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Obtener el usuario actual de la sesión
            $usuarioid = $this->idUsuario ?? null;

            if (empty($usuarioid)) {
                return $this->res->fail('Usuario no autenticado');
            }

            if (empty($datos->idVehiculos)) {
                return $this->res->fail('El ID del vehículo es requerido');
            }

            // Verificar que el vehículo existe Y pertenece al usuario actual
            if (!$this->_existeVehiculoDeUsuario($datos->idVehiculos, $usuarioid)) {
                return $this->res->fail('El vehículo no existe o no tienes permiso para activarlo');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.vehiculos 
                SET activo = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idVehiculos = ? AND usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idVehiculos, $usuarioid]);

            $this->connect->commit();

            return $this->res->ok('Vehículo activado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en activarMiVehiculo: " . $e->getMessage());
            return $this->res->fail('Error al activar el vehículo', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Verifica si existe la combinación usuario-placa
     *
     * @param string $usuarioid ID del usuario
     * @param string $placa Placa del vehículo
     * @return bool True si existe
     */
    private function _existeVehiculoUsuarioPlaca(string $usuarioid, string $placa): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.vehiculos 
            WHERE usuarioid = ? AND placa = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$usuarioid, strtoupper($placa)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe la combinación usuario-placa excluyendo un ID
     *
     * @param string $usuarioid ID del usuario
     * @param string $placa Placa del vehículo
     * @param int $idExcluir ID a excluir
     * @return bool True si existe
     */
    private function _existeVehiculoUsuarioPlacaExceptoId(
        string $usuarioid,
        string $placa,
        int    $idExcluir
    ): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.vehiculos 
            WHERE usuarioid = ? AND placa = ? AND idVehiculos != ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$usuarioid, strtoupper($placa), $idExcluir]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si un vehículo pertenece a un usuario específico
     *
     * @param int $idVehiculos ID del vehículo
     * @param string $usuarioid ID del usuario
     * @return bool True si el vehículo pertenece al usuario
     */
    private function _existeVehiculoDeUsuario(int $idVehiculos, string $usuarioid): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.vehiculos 
            WHERE idVehiculos = ? AND usuarioid = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$idVehiculos, $usuarioid]);
        return (int)$stmt->fetchColumn() > 0;
    }


    //! inicio de los presupuestos

    // ========================================================================
// 📋 CRUD - PRESUPUESTO GENERAL
// ========================================================================

    /**
     * Lista todos los presupuestos generales
     *
     * GET: combustible/listarPresupuestosGenerales
     */
    public function listarPresupuestosGenerales()
    {
        try {
            $registros = $this->_obtenerPresupuestosGenerales();

            return $this->res->ok('Presupuestos obtenidos correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarPresupuestosGenerales: " . $e->getMessage());
            return $this->res->fail('Error al listar presupuestos', $e);
        }
    }

    /**
     * Lista presupuestos generales activos
     *
     * GET: combustible/listarPresupuestosGeneralesActivos
     */
    public function listarPresupuestosGeneralesActivos()
    {
        try {
            $sql = "SELECT 
                    pg.idPresupuestoGeneral,
                    pg.agenciaid,
                    pg.puestoid,
                    pg.anio,
                    pg.monto_mensual, 
                    pg.monto_anual,
                    pg.monto_diario,
                    pg.observaciones,
                    pg.activo,
                    pg.created_at,
                    pg.updated_at,
                    a.nombre as agencia,
                    p.nombre as puesto
                FROM apoyo_combustibles.presupuestogeneral pg
                LEFT JOIN dbintranet.agencia a ON pg.agenciaid = a.idAgencia
                LEFT JOIN dbintranet.puesto p ON pg.puestoid = p.idPuesto
                WHERE pg.activo = 1
                ORDER BY pg.anio DESC, a.nombre ASC, p.nombre ASC";

            $stmt = $this->connect->query($sql);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Presupuestos activos obtenidos', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarPresupuestosGeneralesActivos: " . $e->getMessage());
            return $this->res->fail('Error al obtener presupuestos activos', $e);
        }
    }

    /**
     * Lista agencias disponibles
     *
     * GET: combustible/listarAgencias
     */
    public function listarAgencias()
    {
        try {
            $sql = "SELECT idAgencia, nombre
                FROM dbintranet.agencia
                ORDER BY nombre ASC";

            $stmt = $this->connect->query($sql);
            $agencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Agencias obtenidas correctamente', [
                'agencias' => $agencias,
                'total' => count($agencias)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarAgencias: " . $e->getMessage());
            return $this->res->fail('Error al obtener agencias', $e);
        }
    }

    /**
     * Lista puestos disponibles
     *
     * GET: combustible/listarPuestos
     */
    public function listarPuestos()
    {
        try {
            $sql = "SELECT idPuesto, nombre
                FROM dbintranet.puesto
                ORDER BY nombre ASC";

            $stmt = $this->connect->query($sql);
            $puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Puestos obtenidos correctamente', [
                'puestos' => $puestos,
                'total' => count($puestos)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarPuestos: " . $e->getMessage());
            return $this->res->fail('Error al obtener puestos', $e);
        }
    }

    /**
     * Crea un nuevo presupuesto general
     *
     * POST: combustible/crearPresupuestoGeneral
     *
     * @param object $datos {
     *   agenciaid: int,
     *   puestoid: int,
     *   anio: int,
     *   monto_anual: float,
     *   observaciones?: string
     * }
     */
    public function crearPresupuestoGeneral($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            $error = $this->_validarPresupuestoGeneral($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que no exista la combinación agencia-puesto-año
            if ($this->_existePresupuestoAgenciaPuestoAnio(
                $datos->agenciaid,
                $datos->puestoid,
                $datos->anio
            )) {
                return $this->res->fail('Ya existe un presupuesto para esta agencia, puesto y año');
            }

            $this->connect->beginTransaction();

            $sql = "INSERT INTO apoyo_combustibles.presupuestogeneral 
            (agenciaid, puestoid, anio, monto_mensual, observaciones, activo)
            VALUES (?, ?, ?, ?, ?, 1)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->agenciaid,
                (int)$datos->puestoid,
                (int)$datos->anio,
                (float)$datos->monto_mensual,   // BD calcula monto_anual automáticamente
                $datos->observaciones ?? null
            ]);

            $nuevoId = $this->connect->lastInsertId();
            $this->connect->commit();

            return $this->res->ok('Presupuesto creado correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearPresupuestoGeneral: " . $e->getMessage());
            return $this->res->fail('Error al crear el presupuesto', $e);
        }
    }

    /**
     * Edita un presupuesto general existente
     *
     * POST: combustible/editarPresupuestoGeneral
     *
     * @param object $datos {
     *   idPresupuestoGeneral: int,
     *   agenciaid: int,
     *   puestoid: int,
     *   anio: int,
     *   monto_anual: float,
     *   observaciones?: string
     * }
     */
    public function editarPresupuestoGeneral($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar ID
            if (empty($datos->idPresupuestoGeneral)) {
                return $this->res->fail('El ID del presupuesto es requerido');
            }

            // Validaciones
            $error = $this->_validarPresupuestoGeneral($datos);
            if ($error) {
                return $this->res->fail($error);
            }

            // Verificar que existe
            if (!$this->_existePresupuestoGeneral($datos->idPresupuestoGeneral)) {
                return $this->res->fail('El presupuesto no existe');
            }

            // Verificar unicidad (excluyendo el presupuesto actual)
            if ($this->_existePresupuestoAgenciaPuestoAnioExceptoId(
                $datos->agenciaid,
                $datos->puestoid,
                $datos->anio,
                $datos->idPresupuestoGeneral
            )) {
                return $this->res->fail('Ya existe otro presupuesto para esta agencia, puesto y año');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.presupuestogeneral 
            SET agenciaid = ?,
                puestoid = ?,
                anio = ?,
                monto_mensual = ?,
                observaciones = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE idPresupuestoGeneral = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                (int)$datos->agenciaid,
                (int)$datos->puestoid,
                (int)$datos->anio,
                (float)$datos->monto_mensual,   // BD calcula monto_anual automáticamente
                $datos->observaciones ?? null,
                (int)$datos->idPresupuestoGeneral
            ]);

            $this->connect->commit();

            return $this->res->ok('Presupuesto actualizado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarPresupuestoGeneral: " . $e->getMessage());
            return $this->res->fail('Error al editar el presupuesto', $e);
        }
    }

    /**
     * Desactiva un presupuesto general
     *
     * POST: combustible/desactivarPresupuestoGeneral
     *
     * @param object $datos {
     *   idPresupuestoGeneral: int
     * }
     */
    public function desactivarPresupuestoGeneral($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idPresupuestoGeneral)) {
                return $this->res->fail('El ID del presupuesto es requerido');
            }

            if (!$this->_existePresupuestoGeneral($datos->idPresupuestoGeneral)) {
                return $this->res->fail('El presupuesto no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.presupuestogeneral 
                SET activo = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idPresupuestoGeneral = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idPresupuestoGeneral]);

            $this->connect->commit();

            return $this->res->ok('Presupuesto desactivado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en desactivarPresupuestoGeneral: " . $e->getMessage());
            return $this->res->fail('Error al desactivar el presupuesto', $e);
        }
    }

    /**
     * Activa un presupuesto general
     *
     * POST: combustible/activarPresupuestoGeneral
     *
     * @param object $datos {
     *   idPresupuestoGeneral: int
     * }
     */
    public function activarPresupuestoGeneral($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idPresupuestoGeneral)) {
                return $this->res->fail('El ID del presupuesto es requerido');
            }

            if (!$this->_existePresupuestoGeneral($datos->idPresupuestoGeneral)) {
                return $this->res->fail('El presupuesto no existe');
            }

            $this->connect->beginTransaction();

            $sql = "UPDATE apoyo_combustibles.presupuestogeneral 
                SET activo = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE idPresupuestoGeneral = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([(int)$datos->idPresupuestoGeneral]);

            $this->connect->commit();

            return $this->res->ok('Presupuesto activado correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en activarPresupuestoGeneral: " . $e->getMessage());
            return $this->res->fail('Error al activar el presupuesto', $e);
        }
    }

    /**
     * Carga masiva de presupuestos desde archivo Excel
     *
     * POST: combustible/cargarPresupuestosMasivo
     *
     * @param object $datos {
     *   archivo_base64: string (Excel en base64),
     *   nombre_archivo: string,
     *   anio: int (opcional, para validar que coincida con el archivo)
     * }
     */
    public function cargarPresupuestosMasivo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->archivo_base64)) {
                return $this->res->fail('El archivo es requerido');
            }

            if (empty($datos->nombre_archivo)) {
                return $this->res->fail('El nombre del archivo es requerido');
            }

            // Validar extensión
            $extension = strtolower(pathinfo($datos->nombre_archivo, PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'])) {
                return $this->res->fail('El archivo debe ser formato Excel (.xlsx o .xls)');
            }

            // Decodificar base64
            $archivoContenido = base64_decode($datos->archivo_base64);
            if ($archivoContenido === false) {
                return $this->res->fail('Error al decodificar el archivo');
            }

            // Crear archivo temporal
            $archivoTemporal = tempnam(sys_get_temp_dir(), 'presupuesto_') . '.' . $extension;
            file_put_contents($archivoTemporal, $archivoContenido);


            $spreadsheet = IOFactory::load($archivoTemporal);
            $hoja = $spreadsheet->getActiveSheet();
            $filas = $hoja->toArray();

            // Eliminar archivo temporal
            unlink($archivoTemporal);

            // Validar estructura del archivo
            // Esperamos: ID Agencia | ID Puesto | Año | Monto Anual | Observaciones
            if (count($filas) < 2) {
                return $this->res->fail('El archivo debe contener al menos una fila de encabezados y una fila de datos');
            }

            $encabezados = $filas[0];

            // Resultados
            $insertados = 0;
            $actualizados = 0;
            $errores = [];
            $advertencias = [];

            $this->connect->beginTransaction();

            try {
                // Procesar cada fila (comenzando desde la segunda fila)
                for ($i = 1; $i < count($filas); $i++) {
                    $fila = $filas[$i];

                    // Saltar filas vacías
                    if (empty($fila[0]) && empty($fila[1]) && empty($fila[2])) {
                        continue;
                    }

                    $numeroFila = $i + 1;

                    // Extraer datos (ahora son IDs numéricos)
                    $agenciaid = (int)($fila[0] ?? 0);
                    $puestoid = (int)($fila[1] ?? 0);
                    $anio = (int)($fila[2] ?? 0);
                    $montoMensual = (float)($fila[3] ?? 0);
                    $observaciones = trim($fila[4] ?? '');

                    // Validaciones básicas
                    if ($agenciaid <= 0) {
                        $errores[] = "Fila {$numeroFila}: ID de Agencia inválido o faltante";
                        continue;
                    }

                    if ($puestoid <= 0) {
                        $errores[] = "Fila {$numeroFila}: ID de Puesto inválido o faltante";
                        continue;
                    }

                    if ($anio < 2000 || $anio > 2100) {
                        $errores[] = "Fila {$numeroFila}: Año inválido ({$anio})";
                        continue;
                    }

                    if ($montoMensual <= 0) {
                        $errores[] = "Fila {$numeroFila}: Monto mensual debe ser mayor a 0";
                        continue;
                    }

                    // Verificar que la agencia existe
                    $sqlAgencia = "SELECT idAgencia, nombre FROM dbintranet.agencia WHERE idAgencia = ?";
                    $stmtAgencia = $this->connect->prepare($sqlAgencia);
                    $stmtAgencia->execute([$agenciaid]);
                    $agencia = $stmtAgencia->fetch(PDO::FETCH_ASSOC);

                    if (!$agencia) {
                        $errores[] = "Fila {$numeroFila}: Agencia con ID {$agenciaid} no existe";
                        continue;
                    }

                    // Verificar que el puesto existe
                    $sqlPuesto = "SELECT idPuesto, nombre FROM dbintranet.puesto WHERE idPuesto = ?";
                    $stmtPuesto = $this->connect->prepare($sqlPuesto);
                    $stmtPuesto->execute([$puestoid]);
                    $puesto = $stmtPuesto->fetch(PDO::FETCH_ASSOC);

                    if (!$puesto) {
                        $errores[] = "Fila {$numeroFila}: Puesto con ID {$puestoid} no existe";
                        continue;
                    }

                    // Verificar si ya existe
                    $sqlExiste = "SELECT idPresupuestoGeneral, activo
                              FROM apoyo_combustibles.presupuestogeneral
                              WHERE agenciaid = ? AND puestoid = ? AND anio = ?";
                    $stmtExiste = $this->connect->prepare($sqlExiste);
                    $stmtExiste->execute([$agenciaid, $puestoid, $anio]);
                    $existe = $stmtExiste->fetch(PDO::FETCH_ASSOC);

                    if ($existe) {
                        // Actualizar
                        $sqlUpdate = "UPDATE apoyo_combustibles.presupuestogeneral 
                                  SET monto_mensual = ?,
                                      observaciones = ?,
                                      activo = 1,
                                      updated_at = CURRENT_TIMESTAMP
                                  WHERE idPresupuestoGeneral = ?";
                        $stmtUpdate = $this->connect->prepare($sqlUpdate);
                        $stmtUpdate->execute([
                            $montoMensual,
                            $observaciones ?: null,
                            (int)$existe['idPresupuestoGeneral']
                        ]);
                        $actualizados++;

                        // Advertencia si estaba inactivo
                        if ($existe['activo'] == 0) {
                            $advertencias[] = "Fila {$numeroFila}: Presupuesto reactivado ({$agencia['nombre']} - {$puesto['nombre']})";
                        }
                    } else {
                        // Insertar
                        $sqlInsert = "INSERT INTO apoyo_combustibles.presupuestogeneral 
                                  (agenciaid, puestoid, anio, monto_mensual, observaciones, activo)
                                  VALUES (?, ?, ?, ?, ?, 1)";
                        $stmtInsert = $this->connect->prepare($sqlInsert);
                        $stmtInsert->execute([
                            $agenciaid,
                            $puestoid,
                            $anio,
                            $montoMensual,
                            $observaciones ?: null
                        ]);
                        $insertados++;
                    }
                }

                $this->connect->commit();

                $mensaje = "Carga masiva completada: {$insertados} insertados, {$actualizados} actualizados";

                if (count($errores) > 0) {
                    $mensaje .= ". " . count($errores) . " errores encontrados";
                }

                if (count($advertencias) > 0) {
                    $mensaje .= ". " . count($advertencias) . " advertencias";
                }

                return $this->res->ok($mensaje, [
                    'insertados' => $insertados,
                    'actualizados' => $actualizados,
                    'total_procesados' => $insertados + $actualizados,
                    'total_errores' => count($errores),
                    'total_advertencias' => count($advertencias),
                    'errores' => $errores,
                    'advertencias' => $advertencias
                ]);

            } catch (Exception $e) {
                $this->connect->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en cargarPresupuestosMasivo: " . $e->getMessage());
            return $this->res->fail('Error al procesar el archivo: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Descarga plantilla Excel para carga masiva de presupuestos
     *
     * GET: combustible/descargarPlantillaPresupuestos
     */
    public function descargarPlantillaPresupuestos()
    {
        try {

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Configurar encabezados
            $sheet->setCellValue('A1', 'ID Agencia');
            $sheet->setCellValue('B1', 'ID Puesto');
            $sheet->setCellValue('C1', 'Año');
            $sheet->setCellValue('D1', 'Monto Mensual');
            $sheet->setCellValue('E1', 'Observaciones');

            // Estilo de encabezados
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F97316'], // Color naranja
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];

            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            // Agregar filas de ejemplo
            $sheet->setCellValue('A2', '1');
            $sheet->setCellValue('B2', '5');
            $sheet->setCellValue('C2', '2025');
            $sheet->setCellValue('D2', '50000.00');
            $sheet->setCellValue('E2', 'Presupuesto aprobado');

            $sheet->setCellValue('A3', '1');
            $sheet->setCellValue('B3', '10');
            $sheet->setCellValue('C3', '2025');
            $sheet->setCellValue('D3', '36500.00');
            $sheet->setCellValue('E3', '');

            $sheet->setCellValue('A4', '2');
            $sheet->setCellValue('B4', '8');
            $sheet->setCellValue('C4', '2025');
            $sheet->setCellValue('D4', '40000.00');
            $sheet->setCellValue('E4', 'Incluye bonificación');

            // Ajustar anchos de columna
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(10);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(30);

            // Agregar una hoja de ayuda con catálogos
            $sheetAyuda = $spreadsheet->createSheet();
            $sheetAyuda->setTitle('Catálogos');

            // Obtener agencias
            $sqlAgencias = "SELECT idAgencia, nombre FROM dbintranet.agencia ORDER BY nombre";
            $stmtAgencias = $this->connect->query($sqlAgencias);
            $agencias = $stmtAgencias->fetchAll(PDO::FETCH_ASSOC);

            $sheetAyuda->setCellValue('A1', 'ID Agencia');
            $sheetAyuda->setCellValue('B1', 'Nombre Agencia');
            $sheetAyuda->getStyle('A1:B1')->applyFromArray($headerStyle);

            $fila = 2;
            foreach ($agencias as $agencia) {
                $sheetAyuda->setCellValue("A{$fila}", $agencia['idAgencia']);
                $sheetAyuda->setCellValue("B{$fila}", $agencia['nombre']);
                $fila++;
            }

            // Obtener puestos
            $sqlPuestos = "SELECT idPuesto, nombre FROM dbintranet.puesto ORDER BY nombre";
            $stmtPuestos = $this->connect->query($sqlPuestos);
            $puestos = $stmtPuestos->fetchAll(PDO::FETCH_ASSOC);

            $sheetAyuda->setCellValue('D1', 'ID Puesto');
            $sheetAyuda->setCellValue('E1', 'Nombre Puesto');
            $sheetAyuda->getStyle('D1:E1')->applyFromArray($headerStyle);

            $fila = 2;
            foreach ($puestos as $puesto) {
                $sheetAyuda->setCellValue("D{$fila}", $puesto['idPuesto']);
                $sheetAyuda->setCellValue("E{$fila}", $puesto['nombre']);
                $fila++;
            }

            // Ajustar anchos
            $sheetAyuda->getColumnDimension('A')->setWidth(12);
            $sheetAyuda->getColumnDimension('B')->setWidth(30);
            $sheetAyuda->getColumnDimension('D')->setWidth(12);
            $sheetAyuda->getColumnDimension('E')->setWidth(30);

            // Volver a la primera hoja
            $spreadsheet->setActiveSheetIndex(0);

            // Generar archivo
            $writer = new Xlsx($spreadsheet);

            // Guardar en temporal
            $archivoTemporal = tempnam(sys_get_temp_dir(), 'plantilla_presupuestos_') . '.xlsx';
            $writer->save($archivoTemporal);

            // Leer contenido
            $contenido = file_get_contents($archivoTemporal);
            $base64 = base64_encode($contenido);

            // Eliminar temporal
            unlink($archivoTemporal);

            return $this->res->ok('Plantilla generada correctamente', [
                'archivo_base64' => $base64,
                'nombre_archivo' => 'plantilla_presupuestos.xlsx'
            ]);

        } catch (Exception $e) {
            error_log("Error en descargarPlantillaPresupuestos: " . $e->getMessage());
            return $this->res->fail('Error al generar la plantilla', $e);
        }
    }

// ========================================================================
// 🔧 MÉTODOS PRIVADOS DE SOPORTE
// ========================================================================

    /**
     * Obtiene todos los presupuestos generales con información relacionada
     */
    private function _obtenerPresupuestosGenerales(): array
    {
        $sql = "SELECT 
                pg.idPresupuestoGeneral,
                pg.agenciaid,
                pg.puestoid,
                pg.anio,                
                pg.monto_mensual,
                pg.monto_anual,
                pg.monto_diario,
                pg.observaciones,
                pg.activo,
                pg.created_at,
                pg.updated_at,
                a.nombre as agencia,
                p.nombre as puesto
            FROM apoyo_combustibles.presupuestogeneral pg
            LEFT JOIN dbintranet.agencia a ON pg.agenciaid = a.idAgencia
            LEFT JOIN dbintranet.puesto p ON pg.puestoid = p.idPuesto
            ORDER BY pg.anio DESC, pg.activo DESC, a.nombre ASC, p.nombre ASC";

        $stmt = $this->connect->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida los datos de un presupuesto general
     */
    private function _validarPresupuestoGeneral($datos): ?string
    {
        if (empty($datos->agenciaid)) {
            return 'La agencia es requerida';
        }

        if (empty($datos->puestoid)) {
            return 'El puesto es requerido';
        }

        if (empty($datos->anio)) {
            return 'El año es requerido';
        }

        if ($datos->anio < 2000 || $datos->anio > 2100) {
            return 'El año debe estar entre 2000 y 2100';
        }

        if (empty($datos->monto_mensual) || $datos->monto_mensual <= 0) {
            return 'El monto mensual debe ser mayor a 0';
        }

        if (isset($datos->observaciones) && strlen($datos->observaciones) > 1000) {
            return 'Las observaciones no pueden exceder 1000 caracteres';
        }

        return null;
    }

    /**
     * Verifica si existe un presupuesto general por ID
     */
    private function _existePresupuestoGeneral(int $id): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.presupuestogeneral 
            WHERE idPresupuestoGeneral = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe la combinación agencia-puesto-año
     */
    private function _existePresupuestoAgenciaPuestoAnio(
        int $agenciaid,
        int $puestoid,
        int $anio
    ): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.presupuestogeneral 
            WHERE agenciaid = ? AND puestoid = ? AND anio = ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$agenciaid, $puestoid, $anio]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe la combinación agencia-puesto-año excluyendo un ID
     */
    private function _existePresupuestoAgenciaPuestoAnioExceptoId(
        int $agenciaid,
        int $puestoid,
        int $anio,
        int $idExcluir
    ): bool
    {
        $sql = "SELECT COUNT(*) 
            FROM apoyo_combustibles.presupuestogeneral 
            WHERE agenciaid = ? AND puestoid = ? AND anio = ? 
            AND idPresupuestoGeneral != ?";
        $stmt = $this->connect->prepare($sql);
        $stmt->execute([$agenciaid, $puestoid, $anio, $idExcluir]);
        return (int)$stmt->fetchColumn() > 0;
    }

    //? fin de los presupuestos generales


    //! inicio de liquidaciones

    // ========================================================================
// 🛠️ HELPERS PRIVADOS - LIQUIDACIONES
// ========================================================================

    //? Presupuestos anuales y mensuales, segun sea el caso:


    /**
     * Obtiene el presupuesto disponible del usuario actual
     *
     * GET: combustible/obtenerPresupuestoDisponible
     */
    public function obtenerPresupuestoDisponible()
    {
        try {
            $this->_inicializarHelpersLiquidaciones();
            $anio = date('Y');

            $presupuesto = $this->presupuestoAnualHelper->obtener($anio);

            if (!$presupuesto) {
                return $this->res->fail('No existe presupuesto configurado para su agencia y puesto');
            }

            $detalle    = $presupuesto['detalle_periodo_actual'] ?? [];
            $fechaDesde = $detalle['fecha_consumo_desde'] ?? null;
            $fechaHasta = $detalle['fecha_consumo_hasta'] ?? null;

            $consumidoAnual  = $this->consumoHelper->obtenerConsumoAnual($anio, $fechaDesde, $fechaHasta);
            $disponibleAnual = $presupuesto['monto_anual'] - $consumidoAnual;

            $presupuestoMensual = $this->presupuestoMensualHelper->calcular($presupuesto);

            $mensajeContexto = MensajeContextualHelper::obtener($presupuesto);

            // Monto diario calculado dinámicamente (nunca del DB — varía según el mes)
            $diasMesActual        = (int) date('t');
            $montoMensual         = (float) ($presupuesto['monto_mensual'] ?? 0);
            $monto_diario_sistema = round($montoMensual / $diasMesActual, 2);

            if ($detalle && ($detalle['en_periodo_prueba'] ?? false)) {
                $porcentaje           = ($detalle['porcentaje_restriccion'] ?? 100) / 100;
                $monto_diario_sistema = round($monto_diario_sistema * $porcentaje, 2);
            }

            $response = [
                'agencia'  => $presupuesto['agencia'],
                'puesto'   => $presupuesto['puesto'],
                'anio'     => $presupuesto['anio'],
                'contexto' => $mensajeContexto,

                'presupuesto_anual' => [
                    'total'            => round($presupuesto['monto_anual'], 2),
                    'consumido'        => round($consumidoAnual, 2),
                    'disponible'       => round($disponibleAnual, 2),
                    'porcentaje_usado' => $presupuesto['monto_anual'] > 0
                        ? round(($consumidoAnual / $presupuesto['monto_anual']) * 100, 2)
                        : 0,
                ],

                'presupuesto_mensual' => [
                    'dias_transcurridos' => $presupuestoMensual['dias_transcurridos'],
                    'calculado'          => $presupuestoMensual['presupuesto_calculado'],
                    'consumido'          => $presupuestoMensual['consumido'],
                    'disponible'         => $presupuestoMensual['disponible'],
                    'porcentaje_usado'   => $presupuestoMensual['presupuesto_calculado'] > 0
                        ? round(($presupuestoMensual['consumido'] / $presupuestoMensual['presupuesto_calculado']) * 100, 2)
                        : 0,
                ],

                'monto_diario'  => $monto_diario_sistema,
                'monto_mensual' => round($montoMensual, 2),
            ];

            if (isset($presupuesto['detalle_periodo_actual'])) {
                $response['detalle_periodo'] = $presupuesto['detalle_periodo_actual'];
            }

            if (isset($presupuesto['detalle_periodos'])) {
                $response['detalle_periodos_historicos'] = $presupuesto['detalle_periodos'];
            }

            return $this->res->ok('Presupuesto disponible obtenido', $response);

        } catch (Exception $e) {
            error_log("Error en obtenerPresupuestoDisponible: " . $e->getMessage());
            return $this->res->fail('Error al obtener presupuesto disponible', $e);
        }
    }


    //? fin calculos de presupuestos.

    /**
     * Obtiene la configuración activa de días hábiles
     *
     * @return array|null Configuración con detalle de días o null
     */
    private function _obtenerConfiguracionDiasHabiles()
    {
        try {
            $sql = "SELECT 
                    dh.idConfiguracionDiasHabiles,
                    dh.cantidad_dias,
                    dh.observaciones
                FROM apoyo_combustibles.configuraciondiashabiles dh
                WHERE dh.activo = 1
                ORDER BY dh.created_at DESC
                LIMIT 1";

            $stmt = $this->connect->query($sql);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return null;
            }

            // Obtener detalle de días hábiles
            $sqlDetalle = "SELECT 
                        dia_semana,
                        es_habil
                    FROM apoyo_combustibles.configuraciondiashabilesdetalle
                    WHERE configuraciondiashabilesid = ?
                    ORDER BY dia_semana ASC";

            $stmtDetalle = $this->connect->prepare($sqlDetalle);
            $stmtDetalle->execute([$config['idConfiguracionDiasHabiles']]);
            $detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            // Crear mapa de días hábiles (1=Lunes, 7=Domingo)
            $diasHabiles = [];
            foreach ($detalle as $dia) {
                $diasHabiles[$dia['dia_semana']] = (bool)$dia['es_habil'];
            }

            $config['dias_habiles'] = $diasHabiles;

            $config['dias_descanso'] = $this->_obtenerDiasDescansoFactura();

            return $config;

        } catch (Exception $e) {
            error_log("Error en _obtenerConfiguracionDiasHabiles: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todos los días de descanso activos
     *
     * @return array Array asociativo [fecha => descripcion]
     */
    private function _obtenerDiasDescansoFactura()
    {
        try {
            $sql = "SELECT 
                fecha,
                descripcion
            FROM apoyo_combustibles.diasdescanso
            WHERE activo = 1
            ORDER BY fecha ASC";

            $stmt = $this->connect->query($sql);
            $diasDescanso = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convertir a array asociativo para fácil búsqueda
            $fechas = [];
            foreach ($diasDescanso as $dia) {
                $fechas[$dia['fecha']] = $dia['descripcion'];
            }

            return $fechas;

        } catch (Exception $e) {
            error_log("Error en _obtenerDiasDescanso: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta cuántos días de descanso caen dentro de un rango de fechas
     *
     * @param string $fechaInicio Fecha inicial (YYYY-MM-DD)
     * @param string $fechaFin Fecha final (YYYY-MM-DD)
     * @param array $diasDescanso Array de días de descanso [fecha => descripcion]
     * @return int Cantidad de días de descanso en el rango
     */
    private function _contarDiasDescansoEnRango($fechaInicio, $fechaFin, $diasDescanso)
    {
        if (empty($diasDescanso)) {
            return 0;
        }

        $inicio = new \DateTime($fechaInicio);
        $fin = new \DateTime($fechaFin);
        $contador = 0;

        foreach ($diasDescanso as $fecha => $descripcion) {
            $diaDescanso = new \DateTime($fecha);

            // Verificar si el día de descanso está dentro del rango
            if ($diaDescanso >= $inicio && $diaDescanso <= $fin) {
                $contador++;
            }
        }

        return $contador;
    }

    /**
     * Calcula días hábiles transcurridos entre dos fechas
     *
     * @param string $fechaInicio Fecha inicial (YYYY-MM-DD)
     * @param string $fechaFin Fecha final (YYYY-MM-DD)
     * @param array $diasHabiles Mapa de días hábiles [1=>true, 2=>false, ...]
     * @return int Cantidad de días hábiles
     */
    private function _calcularDiasHabiles($fechaInicio, $fechaFin, $diasHabiles)
    {
        $inicio = new \DateTime($fechaInicio);
        $fin = new \DateTime($fechaFin);

        // Si la fecha fin es anterior a la fecha inicio, retornar 0
        if ($fin < $inicio) {
            return 0;
        }

        $diasHabilesContados = 0;
        $current = clone $inicio;

        while ($current <= $fin) {
            // Obtener día de la semana (1=Lunes, 7=Domingo)
            $diaSemana = (int)$current->format('N');

            // Si el día está marcado como hábil, contar
            if (isset($diasHabiles[$diaSemana]) && $diasHabiles[$diaSemana]) {
                $diasHabilesContados++;
            }

            // Avanzar al siguiente día
            $current->modify('+1 day');
        }

        return $diasHabilesContados;
    }


    /**
     * Verifica si una factura ya fue liquidada
     *
     * @param string $numeroFactura Número de factura
     * @return bool True si ya está liquidada
     */
    private function _facturaYaLiquidada($numeroFactura)
    {
        $this->_inicializarHelpersLiquidaciones();
        return $this->validacionHelper->facturaYaLiquidada($numeroFactura);
    }

    /**
     * Obtiene solicitud de autorización para una factura
     *
     * @param string $numeroFactura Número de factura
     * @return array|null Datos de la solicitud o null
     */
    private function _obtenerSolicitudAutorizacion($numeroFactura, $usuarioid)
    {
        $this->_inicializarHelpersLiquidaciones();
        return $this->validacionHelper->obtenerSolicitudAutorizacion($numeroFactura, $usuarioid);
    }

    /**
     * Valida si hay presupuesto disponible para una liquidación
     *
     * @param float $monto Monto de la liquidación
     * @param int $tipoapoyoid ID del tipo de apoyo
     * @return array ['valido' => bool, 'mensaje' => string]
     */
    private function _validarPresupuestoDisponible($monto, $tipoapoyoid)
    {
        $this->_inicializarHelpersLiquidaciones();
        return $this->validacionHelper->validarPresupuestoDisponible($monto, $tipoapoyoid);
    }

// ========================================================================
// 📋 ENDPOINTS PÚBLICOS - LIQUIDACIONES
// ========================================================================

    /**
     * Calcula días hábiles transcurridos desde la emisión de una factura
     *
     * GET: combustible/calcularDiasHabilesTranscurridos?numero_factura=XXX
     */
    public function calcularDiasHabilesTranscurridos()
    {
        try {
            $numeroFactura = $_GET['numero_factura'] ?? null;

            if (empty($numeroFactura)) {
                return $this->res->fail('El número de factura es requerido');
            }

            // Obtener configuración de días hábiles
            $config = $this->_obtenerConfiguracionDiasHabiles();
            if (!$config) {
                return $this->res->fail('No hay configuración de días hábiles activa');
            }

            // Obtener factura
            $sql = "SELECT fecha_emision FROM compras.facturas_sat WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$numeroFactura]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$factura) {
                return $this->res->fail('Factura no encontrada');
            }

            // Calcular días hábiles desde el día siguiente a la emisión
            $fechaEmision = new \DateTime($factura['fecha_emision']);
            $fechaEmision->modify('+1 day'); // Empezar a contar desde el día siguiente
            $hoy = new \DateTime();

            $diasHabiles = $this->_calcularDiasHabiles(
                $fechaEmision->format('Y-m-d'),
                $hoy->format('Y-m-d'),
                $config['dias_habiles']
            );

            return $this->res->ok('Días hábiles calculados', [
                'dias_habiles_transcurridos' => $diasHabiles,
                'dias_habiles_permitidos' => $config['cantidad_dias'],
                'fecha_emision' => $factura['fecha_emision'],
                'fecha_calculo' => $hoy->format('Y-m-d')
            ]);

        } catch (Exception $e) {
            error_log("Error en calcularDiasHabilesTranscurridos: " . $e->getMessage());
            return $this->res->fail('Error al calcular días hábiles', $e);
        }
    }


    /**
     * Busca una factura y valida si puede ser liquidada
     *
     * POST: combustible/buscarFacturaParaLiquidacion
     *
     * @param object $datos { numero_factura: string }
     */
    public function buscarFacturaParaLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->numero_factura)) {
                return $this->res->fail('El número de factura es requerido');
            }

            // Buscar factura
            $sql = "SELECT 
                id,
                numero_dte,
                fecha_emision,
                numero_autorizacion,
                tipo_dte,
                nombre_emisor,
                monto_total,
                estado,
                estado_liquidacion,
                tiene_autorizacion_tardanza
            FROM compras.facturas_sat 
            WHERE numero_dte = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->numero_factura]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$factura) {
                return $this->res->fail('Factura no encontrada en el sistema');
            }

            // Verificar si ya está liquidada
            $yaLiquidada = $this->_facturaYaLiquidada($datos->numero_factura);

            if ($yaLiquidada) {
                return $this->res->info('Esta factura ya fue liquidada anteriormente');
            }

            // Obtener configuración de días hábiles
            $config = $this->_obtenerConfiguracionDiasHabiles();
            if (!$config) {
                return $this->res->fail('No hay configuración de días hábiles activa en el sistema');
            }

            // Calcular días hábiles transcurridos
            $fechaEmision = new \DateTime($factura['fecha_emision']);
            $fechaEmision->modify('+1 day');
            $hoy = new \DateTime();

            $fechaInicioStr = $fechaEmision->format('Y-m-d');
            $fechaFinStr = $hoy->format('Y-m-d');

            $diasHabilesTranscurridos = $this->_calcularDiasHabiles(
                $fechaInicioStr,
                $fechaFinStr,
                $config['dias_habiles']
            );

            // Contar días de descanso en el rango
            $diasDescansoEnRango = $this->_contarDiasDescansoEnRango(
                $fechaInicioStr,
                $fechaFinStr,
                $config['dias_descanso']
            );

            // Sumar días de descanso a los días permitidos
            $diasHabilesPermitidos = $config['cantidad_dias'] + $diasDescansoEnRango;

            $requiereAutorizacion = $diasHabilesTranscurridos > $diasHabilesPermitidos;

            // Obtener solicitud de autorización si existe
            $solicitudAutorizacion = null;
            $tieneAutorizacionAprobada = false;

            if ($requiereAutorizacion || $factura['tiene_autorizacion_tardanza']) {
                $solicitudAutorizacion = $this->_obtenerSolicitudAutorizacion(
                    $datos->numero_factura,
                    $this->idUsuario
                );

                if ($solicitudAutorizacion) {
                    $tieneAutorizacionAprobada = ($solicitudAutorizacion['estado'] === 'aprobada');
                }
            }

            // Determinar si puede liquidar
            $puedeLiquidar = !$requiereAutorizacion || $tieneAutorizacionAprobada;

            return $this->res->ok('Factura encontrada', [
                'factura' => $factura,
                'dias_habiles_transcurridos' => $diasHabilesTranscurridos,
                'dias_habiles_permitidos' => $diasHabilesPermitidos,
                'requiere_autorizacion' => $requiereAutorizacion,
                'tiene_autorizacion_aprobada' => $tieneAutorizacionAprobada,
                'puede_liquidar' => $puedeLiquidar,
                'solicitud_autorizacion' => $solicitudAutorizacion,
                'ya_liquidada' => false
            ]);

        } catch (Exception $e) {
            error_log("Error en buscarFacturaParaLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al buscar la factura', $e);
        }
    }


    /**
     * Lista las liquidaciones del usuario actual
     *
     * GET: combustible/listarMisLiquidaciones
     */
    public function listarMisLiquidaciones()
    {
        try {
            $sql = "SELECT
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.vehiculoid,
                l.tipoapoyoid,
                l.descripcion,
                l.detalle,
                l.monto AS monto_liquidar,
                l.monto_factura,
                l.estado,
                l.fecha_liquidacion,
                l.solicitudautorizacionid,
                l.created_at,
                l.updated_at,
                v.placa AS vehiculo_placa,
                v.marca AS vehiculo_marca,
                tv.nombre AS tipo_vehiculo,
                ta.nombre AS tipo_apoyo,
                ta.codigo AS tipo_apoyo_codigo,
                ta.requiere_detalle,
                ta.descripcion_predefinida,
                ta.titulo_detalle,
                ta.placeholder_detalle,
                sa.estado AS autorizacion_estado,
                apc.fecha_comprobante as fecha_pago,
                sa.fecha_solicitud AS autorizacion_fecha,
                (SELECT COUNT(*) FROM apoyo_combustibles.solicitudescorreccion sc WHERE sc.liquidacionid = l.idLiquidaciones AND sc.estado = 'pendiente') AS tiene_correccion_pendiente
                FROM
                    apoyo_combustibles.liquidaciones AS l
                    LEFT JOIN apoyo_combustibles.vehiculos AS v ON l.vehiculoid = v.idVehiculos
                    LEFT JOIN apoyo_combustibles.tiposvehiculo AS tv ON v.tipovehiculoid = tv.idTiposVehiculo
                    LEFT JOIN apoyo_combustibles.tiposapoyo AS ta ON l.tipoapoyoid = ta.idTiposApoyo
                    LEFT JOIN apoyo_combustibles.solicitudesautorizacion AS sa ON l.solicitudautorizacionid = sa.idSolicitudesAutorizacion
                    LEFT JOIN apoyo_combustibles.comprobantescontables AS apc ON apc.idComprobantesContables = l.comprobantecontableid
                WHERE l.usuarioid = ?
                AND l.estado != 'eliminada'
            ORDER BY l.fecha_liquidacion DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$this->idUsuario]);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Liquidaciones obtenidas', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarMisLiquidaciones: " . $e->getMessage());
            return $this->res->fail('Error al listar liquidaciones', $e);
        }
    }

    /**
     * Crea una nueva liquidación
     *
     * POST: combustible/crearLiquidacion
     *
     * @param object $datos {
     *   numero_factura: string,
     *   vehiculoid: int,
     *   tipoapoyoid: int,
     *   descripcion: string,
     *   detalle?: string,
     *   monto_liquidar: float
     * }
     */
    public function crearLiquidacion($datos)
    {
        try {
            return $this->res->info('Sistema Bloqueado no se permite nuevas liquidaciones ');
            $datos = $this->limpiarDatos($datos);

            // Validaciones básicas
            if (empty($datos->numero_factura)) {
                return $this->res->fail('El número de factura es requerido');
            }
            if (empty($datos->tipoapoyoid)) {
                return $this->res->fail('El tipo de apoyo es requerido');
            }
            if (empty($datos->descripcion)) {
                return $this->res->fail('La descripción es requerida');
            }
            if (!isset($datos->monto_liquidar) || $datos->monto_liquidar <= 0) {
                return $this->res->fail('El monto a liquidar es requerido y debe ser mayor a 0');
            }

            // Verificar que la factura existe
            $sqlFactura = "SELECT 
                    numero_dte,
                    fecha_emision,
                    monto_total,
                    estado_liquidacion
                  FROM compras.facturas_sat 
                  WHERE numero_dte = ?";

            $stmt = $this->connect->prepare($sqlFactura);
            $stmt->execute([$datos->numero_factura]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$factura) {
                return $this->res->fail('La factura no existe en el sistema');
            }

            // Validar que monto_liquidar no exceda monto_total
            if ($datos->monto_liquidar > $factura['monto_total']) {
                return $this->res->fail('El monto a liquidar no puede exceder el monto total de la factura');
            }

            // Verificar que no esté liquidada
            if ($this->_facturaYaLiquidada($datos->numero_factura)) {
                return $this->res->info('Esta factura ya fue liquidada anteriormente');
            }

            // Verificar que el vehículo pertenece al usuario (si se proporcionó)
            if (!empty($datos->vehiculoid)) {
                $sqlVehiculo = "SELECT COUNT(*) 
                       FROM apoyo_combustibles.vehiculos 
                       WHERE idVehiculos = ? AND usuarioid = ? AND activo = 1";
                $stmt = $this->connect->prepare($sqlVehiculo);
                $stmt->execute([$datos->vehiculoid, $this->idUsuario]);

                if ($stmt->fetchColumn() == 0) {
                    return $this->res->fail('El vehículo seleccionado no es válido');
                }
            }

            // Validar días hábiles y autorización
            $config = $this->_obtenerConfiguracionDiasHabiles();
            if (!$config) {
                return $this->res->fail('No hay configuración de días hábiles activa');
            }

            $fechaEmision = new \DateTime($factura['fecha_emision']);
            $fechaEmision->modify('+1 day');
            $hoy = new \DateTime();

            $diasHabilesTranscurridos = $this->_calcularDiasHabiles(
                $fechaEmision->format('Y-m-d'),
                $hoy->format('Y-m-d'),
                $config['dias_habiles']
            );

            // Contar días de descanso en el rango
            $diasDescansoEnRango = $this->_contarDiasDescansoEnRango(
                $fechaEmision->format('Y-m-d'),
                $hoy->format('Y-m-d'),
                $config['dias_descanso']
            );

            // Sumar días de descanso a los días permitidos
            $diasHabilesPermitidos = $config['cantidad_dias'] + $diasDescansoEnRango;

            $requiereAutorizacion = $diasHabilesTranscurridos > $diasHabilesPermitidos;

            if ($requiereAutorizacion) {
                // Verificar que existe autorización aprobada
                $solicitud = $this->_obtenerSolicitudAutorizacion(
                    $datos->numero_factura,
                    $this->idUsuario
                );

                if (!$solicitud || $solicitud['estado'] !== 'aprobada') {
                    return $this->res->fail('Esta factura requiere autorización aprobada para poder liquidarse');
                }

                $solicitudautorizacionid = $solicitud['idSolicitudesAutorizacion'];
            } else {
                $solicitudautorizacionid = null;
            }

            // Validar presupuesto con monto_liquidar
            $validacionPresupuesto = $this->_validarPresupuestoDisponible(
                $datos->monto_liquidar,
                $datos->tipoapoyoid
            );

            if (!$validacionPresupuesto['valido']) {
                return $this->res->info($validacionPresupuesto['mensaje']);
            }

            $this->connect->beginTransaction();

            // Insertar: monto = monto_liquidar, monto_factura = monto_total
            $sql = "INSERT INTO apoyo_combustibles.liquidaciones 
                (usuarioid, numero_factura, vehiculoid, tipoapoyoid, descripcion, 
                 detalle, monto, monto_factura, estado, solicitudautorizacionid)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'enviada', ?)";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->numero_factura,
                $datos->vehiculoid ?? null,
                $datos->tipoapoyoid,
                $datos->descripcion,
                $datos->detalle ?? null,
                $datos->monto_liquidar,              // monto = monto a liquidar
                $factura['monto_total'],             // monto_factura = monto total
                $solicitudautorizacionid
            ]);

            $nuevoId = $this->connect->lastInsertId();

            // Actualizar estado de la factura
            $sqlUpdate = "UPDATE compras.facturas_sat 
                 SET estado_liquidacion = 'Liquidado' 
                 WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sqlUpdate);
            $stmt->execute([$datos->numero_factura]);

            $this->connect->commit();

            return $this->res->ok('Liquidación creada correctamente', null, [
                'id' => $nuevoId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al crear la liquidación', $e);
        }
    }

    /**
     * Edita una liquidación existente
     *
     * POST: combustible/editarLiquidacion
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   vehiculoid: int,
     *   tipoapoyoid: int,
     *   descripcion: string,
     *   detalle?: string,
     *   monto_liquidar: float
     * }
     */
    public function editarLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            if (!isset($datos->monto_liquidar) || $datos->monto_liquidar <= 0) {
                return $this->res->fail('El monto a liquidar es requerido y debe ser mayor a 0');
            }

            $sql = "SELECT
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.estado,
                l.monto   AS monto_liquidar_actual,
                l.monto_factura
            FROM apoyo_combustibles.liquidaciones l
            WHERE l.idLiquidaciones = ? AND l.usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones, $this->idUsuario]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe o no tiene permisos para editarla');
            }

            if (!in_array($liquidacion['estado'], ['enviada', 'devuelta', 'corregida'])) {
                return $this->res->fail('Solo se pueden editar liquidaciones en estado "enviada", "devuelta" o "corregida"');
            }

            if ($datos->monto_liquidar > $liquidacion['monto_factura']) {
                return $this->res->fail('El monto a liquidar no puede exceder el monto total de la factura');
            }

            if (!empty($datos->vehiculoid)) {
                $sqlVehiculo = "SELECT COUNT(*)
                       FROM apoyo_combustibles.vehiculos
                       WHERE idVehiculos = ? AND usuarioid = ? AND activo = 1";
                $stmt = $this->connect->prepare($sqlVehiculo);
                $stmt->execute([$datos->vehiculoid, $this->idUsuario]);

                if ($stmt->fetchColumn() == 0) {
                    return $this->res->fail('El vehículo seleccionado no es válido');
                }
            }

            // Solo validar la diferencia: el monto actual ya está comprometido en el presupuesto.
            // Si el nuevo monto es menor o igual, no se consume presupuesto adicional.
            $montoActual = (float) $liquidacion['monto_liquidar_actual'];
            $diferencia  = $datos->monto_liquidar - $montoActual;

            if ($diferencia > 0) {
                $validacionPresupuesto = $this->_validarPresupuestoDisponible(
                    $diferencia,
                    $datos->tipoapoyoid
                );
                if (!$validacionPresupuesto['valido']) {
                    return $this->res->fail($validacionPresupuesto['mensaje']);
                }
            }

            $this->connect->beginTransaction();

            $estado_nuevo = ($liquidacion['estado'] === 'devuelta') ? 'corregida' : $liquidacion['estado'];

            $sql = "UPDATE apoyo_combustibles.liquidaciones
                SET vehiculoid  = ?,
                    tipoapoyoid = ?,
                    descripcion = ?,
                    detalle     = ?,
                    monto       = ?,
                    estado      = ?,
                    updated_at  = CURRENT_TIMESTAMP
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->vehiculoid ?? null,
                $datos->tipoapoyoid,
                $datos->descripcion,
                $datos->detalle ?? null,
                $datos->monto_liquidar,
                $estado_nuevo,
                $datos->idLiquidaciones,
            ]);

            $this->connect->commit();

            return $this->res->ok('Liquidación actualizada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en editarLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al editar la liquidación', $e);
        }
    }

    /**
     * Elimina (da de baja) una liquidación
     *
     * POST: combustible/eliminarLiquidacion
     *
     * @param object $datos { idLiquidaciones: int }
     */
    public function eliminarLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            // Verificar que la liquidación existe y pertenece al usuario
            $sql = "SELECT 
                    l.idLiquidaciones,
                    l.usuarioid,
                    l.numero_factura,
                    l.estado
                FROM apoyo_combustibles.liquidaciones l
                WHERE l.idLiquidaciones = ? AND l.usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones, $this->idUsuario]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe o no tiene permisos para eliminarla');
            }

            // Solo se puede eliminar si está en estado 'enviada'
            if ($liquidacion['estado'] !== 'enviada') {
                return $this->res->fail('Solo se pueden eliminar liquidaciones en estado "enviada"');
            }

            $this->connect->beginTransaction();

            // Cambiar estado a 'eliminada'
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'eliminada',
                    updated_at = CURRENT_TIMESTAMP
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);

            // Liberar la factura
            $sqlFactura = "UPDATE compras.facturas_sat 
                      SET estado_liquidacion = 'Pendiente' 
                      WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sqlFactura);
            $stmt->execute([$liquidacion['numero_factura']]);

            $this->connect->commit();

            return $this->res->ok('Liquidación eliminada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en eliminarLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al eliminar la liquidación', $e);
        }
    }

    /**
     * Crea una solicitud de autorización para factura fuera de plazo
     *
     * POST: combustible/crearSolicitudAutorizacion
     *
     * @param object $datos {
     *   numero_factura: string,
     *   justificacion: string,
     *   archivo?: file (opcional)
     * }
     */
    public function crearSolicitudAutorizacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->numero_factura)) {
                return $this->res->fail('El número de factura es requerido');
            }
            if (empty($datos->justificacion)) {
                return $this->res->fail('La justificación es requerida');
            }

            // Verificar que la factura existe
            $sqlFactura = "SELECT fecha_emision 
                  FROM compras.facturas_sat 
                  WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sqlFactura);
            $stmt->execute([$datos->numero_factura]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$factura) {
                return $this->res->fail('La factura no existe en el sistema');
            }

            // Verificar que no existe solicitud pendiente o aprobada
            $solicitudExistente = $this->_obtenerSolicitudAutorizacion(
                $datos->numero_factura,
                $this->idUsuario
            );

            if ($solicitudExistente && in_array($solicitudExistente['estado'], ['pendiente', 'aprobada'])) {
                return $this->res->fail('Ya existe una solicitud ' . $solicitudExistente['estado'] . ' para esta factura');
            }

            // Calcular días hábiles excedidos
            $config = $this->_obtenerConfiguracionDiasHabiles();
            if (!$config) {
                return $this->res->fail('No hay configuración de días hábiles activa');
            }

            $fechaEmision = new \DateTime($factura['fecha_emision']);
            $fechaEmision->modify('+1 day');
            $hoy = new \DateTime();

            $diasHabilesTranscurridos = $this->_calcularDiasHabiles(
                $fechaEmision->format('Y-m-d'),
                $hoy->format('Y-m-d'),
                $config['dias_habiles']
            );

            $diasExcedidos = max(0, $diasHabilesTranscurridos - $config['cantidad_dias']);

            $this->connect->beginTransaction();

            // Crear solicitud de autorización
            $sql = "INSERT INTO apoyo_combustibles.solicitudesautorizacion 
            (usuarioid, numero_factura, dias_habiles_excedidos, justificacion, estado)
            VALUES (?, ?, ?, ?, 'pendiente')";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->numero_factura,
                $diasExcedidos,
                $datos->justificacion
            ]);

            $solicitudId = $this->connect->lastInsertId();

            // Si hay archivo, subirlo usando DriveService
            if (!empty($_FILES)) {
                try {
                    // Instanciar DriveService
                    $driveService = new \App\Core\DriveService();

                    // Subir archivo con estructura de carpetas
                    $resultado = $driveService->subirArchivo(
                        'combustibles',                          // Carpeta base
                        ['autorizaciones', date('Y'), date('m')], // Subcarpetas
                        'autorizacion_' . $solicitudId . '_' . time(), // Nombre personalizado (sin extensión)
                        ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'], // Tipos permitidos
                        5 * 1024 * 1024                          // 5MB máximo
                    );

                    if ($resultado['exito']) {
                        // Guardar registro del archivo
                        $sqlArchivo = "INSERT INTO apoyo_combustibles.archivosautorizacion 
                              (solicitudautorizacionid, drive_id, nombre_original, 
                               nombre_en_drive, tipo_mime, tamano_bytes, subido_por)
                              VALUES (?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $this->connect->prepare($sqlArchivo);
                        $stmt->execute([
                            $solicitudId,
                            $resultado['drive_id'],
                            $resultado['metadata']['nombre_original'],
                            $resultado['nombre_archivo'],
                            $resultado['metadata']['tipo_mime'],
                            $resultado['metadata']['tamaño_bytes'],
                            $this->idUsuario
                        ]);
                    } else {
                        // Log del error pero continuar sin archivo
                        error_log("No se pudo subir archivo: " . $resultado['mensaje']);
                    }
                } catch (Exception $e) {
                    error_log("Error al subir archivo: " . $e->getMessage());
                    // Continuar sin archivo
                }
            }

            // Actualizar factura
            $sqlFactura = "UPDATE compras.facturas_sat 
                  SET tiene_autorizacion_tardanza = 1 
                  WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sqlFactura);
            $stmt->execute([$datos->numero_factura]);

            $this->connect->commit();

            return $this->res->ok('Solicitud de autorización creada correctamente', null, [
                'id' => $solicitudId
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en crearSolicitudAutorizacion: " . $e->getMessage());
            return $this->res->fail('Error al crear la solicitud de autorización', $e);
        }
    }

    /**
     * Obtiene el detalle de una solicitud de autorización
     *
     * POST: combustible/obtenerDetalleSolicitudAutorizacion
     *
     * @param object $datos { idSolicitudesAutorizacion: int }
     */
    public function obtenerDetalleSolicitudAutorizacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idSolicitudesAutorizacion)) {
                return $this->res->fail('El ID de la solicitud es requerido');
            }

            // Obtener solicitud
            $sql = "SELECT
                        sa.idSolicitudesAutorizacion,
                        sa.usuarioid,
                        sa.numero_factura,
                        sa.dias_habiles_excedidos,
                        sa.justificacion,
                        sa.estado,
                        sa.fecha_solicitud,
                        sa.fecha_respuesta,
                        sa.autorizado_por,
                        sa.motivo_rechazo,
                        -- Datos del autorizador
                        dtp.nombres AS nombre_autorizador,
                        -- Datos del solicitante
                        dtp_solicitante.nombres AS nombre_solicitante,
                        ag_solicitante.nombre AS agencia_solicitante,
                        pu_solicitante.nombre AS puesto_solicitante,
                        -- Datos de la factura SAT
                        sat.fecha_emision,
                        sat.monto_total,
                        sat.nombre_emisor
                    FROM
                        apoyo_combustibles.solicitudesautorizacion AS sa
                        -- Join para obtener datos del AUTORIZADOR
                        LEFT JOIN dbintranet.usuarios AS us_autorizador ON BINARY sa.autorizado_por = BINARY us_autorizador.idUsuarios
                        LEFT JOIN dbintranet.datospersonales AS dtp ON us_autorizador.idDatosPersonales = dtp.idDatosPersonales
                        -- Join para obtener datos del SOLICITANTE
                        LEFT JOIN dbintranet.usuarios AS us_solicitante ON BINARY sa.usuarioid = BINARY us_solicitante.idUsuarios
                        LEFT JOIN dbintranet.datospersonales AS dtp_solicitante ON us_solicitante.idDatosPersonales = dtp_solicitante.idDatosPersonales
                        LEFT JOIN dbintranet.agencia AS ag_solicitante ON ag_solicitante.idAgencia = us_solicitante.idAgencia
                        LEFT JOIN dbintranet.puesto AS pu_solicitante ON pu_solicitante.idPuesto = us_solicitante.idPuesto
                        -- Join para obtener datos de la FACTURA SAT
                        LEFT JOIN compras.facturas_sat AS sat ON BINARY sa.numero_factura = BINARY sat.numero_dte
                    WHERE
                        sa.idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idSolicitudesAutorizacion]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                return $this->res->fail('La solicitud no existe');
            }

            // Obtener archivos adjuntos
            $sqlArchivos = "SELECT 
                            idArchivosAutorizacion,
                            drive_id,
                            nombre_original,
                            tipo_mime,
                            tamano_bytes,
                            fecha_subida
                        FROM apoyo_combustibles.archivosautorizacion
                        WHERE solicitudautorizacionid = ? AND estado = 'activo'";

            $stmt = $this->connect->prepare($sqlArchivos);
            $stmt->execute([$datos->idSolicitudesAutorizacion]);
            $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $solicitud['archivos'] = $archivos;

            return $this->res->ok('Detalle de solicitud obtenido', $solicitud);

        } catch (Exception $e) {
            error_log("Error en obtenerDetalleSolicitudAutorizacion: " . $e->getMessage());
            return $this->res->fail('Error al obtener detalle de la solicitud', $e);
        }
    }


    // ========================================================================
// 🔐 HELPERS PRIVADOS - AUTORIZACIONES
// ========================================================================

    /**
     * Verifica si existe una liquidación asociada a una solicitud
     *
     * @param int $solicitudId ID de la solicitud
     * @return bool True si existe liquidación
     */
    private function _existeLiquidacionDeSolicitud($solicitudId)
    {
        try {
            $sql = "SELECT COUNT(*) 
                FROM apoyo_combustibles.liquidaciones 
                WHERE solicitudautorizacionid = ? 
                    AND estado NOT IN ('eliminada')";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$solicitudId]);

            return $stmt->fetchColumn() > 0;

        } catch (Exception $e) {
            error_log("Error en _existeLiquidacionDeSolicitud: " . $e->getMessage());
            return true; // Por seguridad, asumir que existe
        }
    }

    /**
     * Verifica si existe una solicitud posterior para la misma factura
     *
     * @param string $numeroFactura Número de factura
     * @param int $solicitudIdActual ID de la solicitud actual
     * @return bool True si existe solicitud posterior
     */
    private function _existeSolicitudPosterior($numeroFactura, $solicitudIdActual)
    {
        try {
            $sql = "SELECT COUNT(*) 
                FROM apoyo_combustibles.solicitudesautorizacion 
                WHERE numero_factura = ? 
                    AND idSolicitudesAutorizacion > ? 
                    AND estado IN ('pendiente', 'aprobada')";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$numeroFactura, $solicitudIdActual]);

            return $stmt->fetchColumn() > 0;

        } catch (Exception $e) {
            error_log("Error en _existeSolicitudPosterior: " . $e->getMessage());
            return true; // Por seguridad, asumir que existe
        }
    }

// ========================================================================
// 📋 ENDPOINTS PÚBLICOS - AUTORIZACIONES
// ========================================================================

    /**
     * Obtiene todas las solicitudes pendientes de autorización
     *
     * GET: combustible/obtenerSolicitudesPendientes
     */
    public function obtenerSolicitudesPendientes()
    {
        try {
            $sql = "SELECT 
                sa.idSolicitudesAutorizacion,
                sa.usuarioid,
                sa.numero_factura,
                sa.dias_habiles_excedidos,
                sa.justificacion,
                sa.estado,
                sa.fecha_solicitud,
                dtp.nombres as nombre_solicitante,
                a.nombre as agencia_solicitante,
                p.nombre as puesto_solicitante,
                fs.fecha_emision,
                fs.monto_total,
                fs.nombre_emisor,
                (SELECT COUNT(*) 
                FROM apoyo_combustibles.archivosautorizacion aa 
                WHERE aa.solicitudautorizacionid = sa.idSolicitudesAutorizacion 
                    -- Agregamos COLLATE aquí si 'estado' genera conflicto
                AND aa.estado COLLATE utf8mb4_general_ci = 'activo' COLLATE utf8mb4_general_ci) as cantidad_archivos
            FROM apoyo_combustibles.solicitudesautorizacion sa
            INNER JOIN dbintranet.usuarios us ON sa.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            LEFT JOIN dbintranet.agencia a ON us.idAgencia = a.idAgencia
            LEFT JOIN dbintranet.puesto p ON us.idPuesto = p.idPuesto
            -- PUNTO CLAVE: El join de factura suele ser el culpable por ser campos de texto (DTE)
            LEFT JOIN compras.facturas_sat fs ON sa.numero_factura COLLATE utf8mb4_general_ci = fs.numero_dte COLLATE utf8mb4_general_ci
            WHERE sa.estado COLLATE utf8mb4_general_ci = 'pendiente' COLLATE utf8mb4_general_ci
            ORDER BY sa.fecha_solicitud ASC";

            $stmt = $this->connect->query($sql);
            $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Solicitudes pendientes obtenidas', $solicitudes);

        } catch (Exception $e) {
            error_log("Error en obtenerSolicitudesPendientes: " . $e->getMessage());
            return $this->res->fail('Error al obtener solicitudes pendientes', $e);
        }
    }

    /**
     * Aprueba una solicitud de autorización
     *
     * POST: combustible/aprobarSolicitudAutorizacion
     *
     * @param object $datos {
     *   idSolicitudesAutorizacion: int,
     *   observaciones?: string (opcional)
     * }
     */
    public function aprobarSolicitudAutorizacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idSolicitudesAutorizacion)) {
                return $this->res->fail('El ID de la solicitud es requerido');
            }

            // Verificar que la solicitud existe y está pendiente
            $sql = "SELECT 
                sa.idSolicitudesAutorizacion,
                sa.usuarioid,
                sa.numero_factura,
                sa.estado,
                dtp.nombres as nombre_solicitante,
                dtp.correoElectronico as email_solicitante
            FROM apoyo_combustibles.solicitudesautorizacion sa
            INNER JOIN dbintranet.usuarios us ON sa.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE sa.idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idSolicitudesAutorizacion]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                return $this->res->fail('La solicitud no existe');
            }

            if ($solicitud['estado'] !== 'pendiente') {
                return $this->res->fail('Solo se pueden aprobar solicitudes en estado "pendiente"');
            }

            $this->connect->beginTransaction();

            // Actualizar solicitud
            $sql = "UPDATE apoyo_combustibles.solicitudesautorizacion 
                SET estado = 'aprobada',
                    fecha_respuesta = CURRENT_TIMESTAMP,
                    autorizado_por = ?,
                    motivo_rechazo = NULL
                WHERE idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->idSolicitudesAutorizacion
            ]);

            $this->connect->commit();

            // Enviar notificación por correo (opcional)
            try {
                $asunto = "Solicitud de Autorización Aprobada - Factura {$solicitud['numero_factura']}";
                $mensaje = "
                <h2>Solicitud Aprobada</h2>
                <p>Estimado/a {$solicitud['nombre_solicitante']},</p>
                <p>Su solicitud de autorización para la factura <strong>{$solicitud['numero_factura']}</strong> ha sido <strong>APROBADA</strong>.</p>
                <p>Ya puede proceder a crear la liquidación correspondiente.</p>
                <p><em>Sistema de Apoyo de Combustibles</em></p>
            ";

                if (!empty($solicitud['email_solicitante'])) {
                    $this->mailer->enviar(
                        $solicitud['email_solicitante'],
                        $asunto,
                        $mensaje
                    );
                }
            } catch (Exception $e) {
                error_log("Error al enviar notificación de aprobación: " . $e->getMessage());
                // No afectar la transacción si falla el correo
            }

            return $this->res->ok('Solicitud aprobada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en aprobarSolicitudAutorizacion: " . $e->getMessage());
            return $this->res->fail('Error al aprobar la solicitud', $e);
        }
    }

    /**
     * Rechaza una solicitud de autorización
     *
     * POST: combustible/rechazarSolicitudAutorizacion
     *
     * @param object $datos {
     *   idSolicitudesAutorizacion: int,
     *   motivo_rechazo: string
     * }
     */
    public function rechazarSolicitudAutorizacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idSolicitudesAutorizacion)) {
                return $this->res->fail('El ID de la solicitud es requerido');
            }

            if (empty($datos->motivo_rechazo)) {
                return $this->res->fail('El motivo del rechazo es requerido');
            }

            // Verificar que la solicitud existe y está pendiente
            $sql = "SELECT 
                sa.idSolicitudesAutorizacion,
                sa.usuarioid,
                sa.numero_factura,
                sa.estado,
                dtp.nombres as nombre_solicitante,
                dtp.correoElectronico as email_solicitante
            FROM apoyo_combustibles.solicitudesautorizacion sa
            INNER JOIN dbintranet.usuarios us ON sa.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE sa.idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idSolicitudesAutorizacion]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                return $this->res->fail('La solicitud no existe');
            }

            if ($solicitud['estado'] !== 'pendiente') {
                return $this->res->fail('Solo se pueden rechazar solicitudes en estado "pendiente"');
            }

            $this->connect->beginTransaction();

            // Actualizar solicitud
            $sql = "UPDATE apoyo_combustibles.solicitudesautorizacion 
                SET estado = 'rechazada',
                    fecha_respuesta = CURRENT_TIMESTAMP,
                    autorizado_por = ?,
                    motivo_rechazo = ?
                WHERE idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->motivo_rechazo,
                $datos->idSolicitudesAutorizacion
            ]);

            // Actualizar factura (quitar bandera de autorización)
            $sqlFactura = "UPDATE compras.facturas_sat 
                      SET tiene_autorizacion_tardanza = 0 
                      WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sqlFactura);
            $stmt->execute([$solicitud['numero_factura']]);

            $this->connect->commit();

            // Enviar notificación por correo (opcional)
            try {
                $asunto = "Solicitud de Autorización Rechazada - Factura {$solicitud['numero_factura']}";
                $mensaje = "
                <h2>Solicitud Rechazada</h2>
                <p>Estimado/a {$solicitud['nombre_solicitante']},</p>
                <p>Su solicitud de autorización para la factura <strong>{$solicitud['numero_factura']}</strong> ha sido <strong>RECHAZADA</strong>.</p>
                <p><strong>Motivo:</strong> {$datos->motivo_rechazo}</p>
                <p>Si considera que debe reconsiderarse, puede solicitarlo desde el histórico de autorizaciones.</p>
                <p><em>Sistema de Apoyo de Combustibles</em></p>
            ";

                if (!empty($solicitud['email_solicitante'])) {
                    $this->mailer->enviar(
                        $solicitud['email_solicitante'],
                        $asunto,
                        $mensaje
                    );
                }
            } catch (Exception $e) {
                error_log("Error al enviar notificación de rechazo: " . $e->getMessage());
                // No afectar la transacción si falla el correo
            }

            return $this->res->ok('Solicitud rechazada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en rechazarSolicitudAutorizacion: " . $e->getMessage());
            return $this->res->fail('Error al rechazar la solicitud', $e);
        }
    }

    /**
     * Obtiene el histórico de solicitudes con filtros
     *
     * POST: combustible/obtenerHistoricoSolicitudes
     *
     * @param object $datos {
     *   fecha_inicio?: string (YYYY-MM-DD),
     *   fecha_fin?: string (YYYY-MM-DD),
     *   estado?: string ('pendiente'|'aprobada'|'rechazada'),
     *   numero_factura?: string,
     *   usuarioid?: string
     * }
     */
    public function obtenerHistoricoSolicitudes($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Construir query dinámica
            $sql = "SELECT 
                        sa.idSolicitudesAutorizacion,
                        sa.usuarioid,
                        sa.numero_factura,
                        sa.dias_habiles_excedidos,
                        sa.justificacion,
                        sa.estado,
                        sa.fecha_solicitud,
                        sa.fecha_respuesta,
                        sa.motivo_rechazo,
                        dtp.nombres as nombre_solicitante,
                        a.nombre as agencia_solicitante,
                        p.nombre as puesto_solicitante,
                        dtp_aut.nombres as nombre_autorizador,
                        fs.fecha_emision,
                        fs.monto_total,
                        fs.nombre_emisor,
                        (SELECT COUNT(*) 
                            FROM apoyo_combustibles.archivosautorizacion aa 
                            WHERE aa.solicitudautorizacionid = sa.idSolicitudesAutorizacion 
                            AND aa.estado COLLATE utf8mb4_general_ci = 'activo' COLLATE utf8mb4_general_ci) as cantidad_archivos,
                            (SELECT COUNT(*) 
                            FROM apoyo_combustibles.liquidaciones liq 
                            WHERE liq.solicitudautorizacionid = sa.idSolicitudesAutorizacion 
                            AND liq.estado COLLATE utf8mb4_general_ci NOT IN ('eliminada' COLLATE utf8mb4_general_ci)) as tiene_liquidacion
                    FROM apoyo_combustibles.solicitudesautorizacion sa
                    INNER JOIN dbintranet.usuarios us ON sa.usuarioid = us.idUsuarios
                    INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
                    LEFT JOIN dbintranet.agencia a ON us.idAgencia = a.idAgencia
                    LEFT JOIN dbintranet.puesto p ON us.idPuesto = p.idPuesto
                    LEFT JOIN dbintranet.usuarios us_aut ON sa.autorizado_por = us_aut.idUsuarios
                    LEFT JOIN dbintranet.datospersonales dtp_aut ON us_aut.idDatosPersonales = dtp_aut.idDatosPersonales
                    -- El cruce entre sa.numero_factura y fs.numero_dte suele ser el origen del error
                    LEFT JOIN compras.facturas_sat fs ON sa.numero_factura COLLATE utf8mb4_general_ci = fs.numero_dte COLLATE utf8mb4_general_ci
                    WHERE 1=1";

            $params = [];

            // Filtro por rango de fechas
            if (!empty($datos->fecha_inicio)) {
                $sql .= " AND DATE(sa.fecha_solicitud) >= ?";
                $params[] = $datos->fecha_inicio;
            }

            if (!empty($datos->fecha_fin)) {
                $sql .= " AND DATE(sa.fecha_solicitud) <= ?";
                $params[] = $datos->fecha_fin;
            }

            // Filtro por estado
            if (!empty($datos->estado)) {
                $sql .= " AND sa.estado = ?";
                $params[] = $datos->estado;
            }

            // Filtro por número de factura
            if (!empty($datos->numero_factura)) {
                $sql .= " AND sa.numero_factura LIKE ?";
                $params[] = "%{$datos->numero_factura}%";
            }

            // Filtro por usuario solicitante
            if (!empty($datos->usuarioid)) {
                $sql .= " AND sa.usuarioid = ?";
                $params[] = $datos->usuarioid;
            }

            $sql .= " ORDER BY sa.fecha_solicitud DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);
            $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agregar información adicional para cada solicitud
            foreach ($solicitudes as &$solicitud) {
                // Verificar si puede ser reconsiderada
                $puedeReconsiderar = false;
                if ($solicitud['estado'] === 'rechazada') {
                    $tieneLiquidacion = $solicitud['tiene_liquidacion'] > 0;
                    $tienePosterior = $this->_existeSolicitudPosterior(
                        $solicitud['numero_factura'],
                        $solicitud['idSolicitudesAutorizacion']
                    );
                    $puedeReconsiderar = !$tieneLiquidacion && !$tienePosterior;
                }
                $solicitud['puede_reconsiderar'] = $puedeReconsiderar;
            }

            return $this->res->ok('Histórico de solicitudes obtenido', $solicitudes);

        } catch (Exception $e) {
            error_log("Error en obtenerHistoricoSolicitudes: " . $e->getMessage());
            return $this->res->fail('Error al obtener histórico de solicitudes', $e);
        }
    }

    /**
     * Reconsidera una solicitud rechazada (la vuelve a pendiente)
     *
     * POST: combustible/reconsiderarSolicitudRechazada
     *
     * @param object $datos {
     *   idSolicitudesAutorizacion: int,
     *   justificacion_reconsideracion?: string (opcional)
     * }
     */
    public function reconsiderarSolicitudRechazada($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idSolicitudesAutorizacion)) {
                return $this->res->fail('El ID de la solicitud es requerido');
            }

            // Verificar que la solicitud existe y está rechazada
            $sql = "SELECT 
                sa.idSolicitudesAutorizacion,
                sa.usuarioid,
                sa.numero_factura,
                sa.estado,
                dtp.nombres as nombre_solicitante
            FROM apoyo_combustibles.solicitudesautorizacion sa
            INNER JOIN dbintranet.usuarios us ON sa.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE sa.idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idSolicitudesAutorizacion]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                return $this->res->fail('La solicitud no existe');
            }

            if ($solicitud['estado'] !== 'rechazada') {
                return $this->res->fail('Solo se pueden reconsiderar solicitudes rechazadas');
            }

            // Verificar que no existe liquidación asociada
            if ($this->_existeLiquidacionDeSolicitud($datos->idSolicitudesAutorizacion)) {
                return $this->res->fail('No se puede reconsiderar: ya existe una liquidación asociada a esta solicitud');
            }

            // Verificar que no existe solicitud posterior
            if ($this->_existeSolicitudPosterior($solicitud['numero_factura'], $datos->idSolicitudesAutorizacion)) {
                return $this->res->fail('No se puede reconsiderar: existe una solicitud más reciente para esta factura');
            }

            $this->connect->beginTransaction();

            // Actualizar solicitud a pendiente
            $sql = "UPDATE apoyo_combustibles.solicitudesautorizacion 
                SET estado = 'pendiente',
                    fecha_respuesta = NULL,
                    autorizado_por = NULL,
                    motivo_rechazo = NULL
                WHERE idSolicitudesAutorizacion = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idSolicitudesAutorizacion]);

            // Restaurar bandera en factura
            $sqlFactura = "UPDATE compras.facturas_sat 
                      SET tiene_autorizacion_tardanza = 1 
                      WHERE numero_dte = ?";
            $stmt = $this->connect->prepare($sqlFactura);
            $stmt->execute([$solicitud['numero_factura']]);

            $this->connect->commit();

            return $this->res->ok('Solicitud reconsiderada correctamente. Ahora está pendiente de autorización.');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en reconsiderarSolicitudRechazada: " . $e->getMessage());
            return $this->res->fail('Error al reconsiderar la solicitud', $e);
        }
    }


    //? Liquidaciones inicio del apartado:
    // ========================================================================
// 🛠️ HELPERS PRIVADOS - CONTABILIDAD
// ========================================================================

    /**
     * Método privado para obtener liquidaciones con filtros
     */
    private function _obtenerLiquidacionesPendientesRevision2($filtros = [])
    {
        // Base de la consulta
        $sql = "SELECT 
        l.idLiquidaciones,
        l.usuarioid,
        l.numero_factura,
        l.vehiculoid,
        l.tipoapoyoid,
        l.descripcion,
        l.detalle,
        l.monto,
        l.monto_factura,
        l.estado,
        l.fecha_liquidacion,
        l.solicitudautorizacionid,
        l.revisado_por,
        l.fecha_revision,
        l.motivo_rechazo,
        l.created_at,
        l.updated_at,
        -- Usuario
        CONCAT(dtp.nombres) as nombre_usuario,
        ag.nombre as agencia_usuario,
        p.nombre as puesto_usuario,
        -- Revisor
        CONCAT(dtp_rev.nombres) as nombre_revisor,
        -- Vehículo
        v.placa as vehiculo_placa,
        v.marca as vehiculo_marca,
        tv.nombre as tipo_vehiculo,
        -- Tipo Apoyo
        ta.codigo as tipo_apoyo_codigo,
        ta.nombre as tipo_apoyo,
        -- Autorización
        sa.estado as autorizacion_estado,
        sa.fecha_respuesta as autorizacion_fecha,
        -- Factura
        f.fecha_emision,
        f.nombre_emisor,
        -- Corrección pendiente
        (SELECT COUNT(*) 
         FROM apoyo_combustibles.solicitudescorreccion sc 
         WHERE sc.liquidacionid = l.idLiquidaciones 
         AND sc.estado = 'pendiente') as tiene_correccion_pendiente
    FROM apoyo_combustibles.liquidaciones l
    INNER JOIN dbintranet.usuarios us ON CONVERT(l.usuarioid USING utf8mb4) = CONVERT(us.idUsuarios USING utf8mb4)
    INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
    INNER JOIN dbintranet.agencia ag ON us.idAgencia = ag.idAgencia
    INNER JOIN dbintranet.puesto p ON us.idPuesto = p.idPuesto
    LEFT JOIN dbintranet.usuarios us_rev ON CONVERT(l.revisado_por USING utf8mb4) = CONVERT(us_rev.idUsuarios USING utf8mb4)
    LEFT JOIN dbintranet.datospersonales dtp_rev ON us_rev.idDatosPersonales = dtp_rev.idDatosPersonales
    LEFT JOIN apoyo_combustibles.vehiculos v ON l.vehiculoid = v.idVehiculos
    LEFT JOIN apoyo_combustibles.tiposvehiculo tv ON v.tipovehiculoid = tv.idTiposVehiculo
    INNER JOIN apoyo_combustibles.tiposapoyo ta ON l.tipoapoyoid = ta.idTiposApoyo
    LEFT JOIN apoyo_combustibles.solicitudesautorizacion sa ON l.solicitudautorizacionid = sa.idSolicitudesAutorizacion
    INNER JOIN compras.facturas_sat f ON CONVERT(l.numero_factura USING utf8mb4) = CONVERT(f.numero_dte USING utf8mb4)
    WHERE 1=1";

        $params = [];

        // =====================================================================
        // FILTRO DE FECHAS (Conjuntas: created_at O updated_at)
        // =====================================================================
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            // Busca liquidaciones donde CUALQUIERA de las dos fechas esté en el rango
            $sql .= " AND (
            (DATE(l.created_at) BETWEEN ? AND ?) 
            OR 
            (DATE(l.updated_at) BETWEEN ? AND ?)
        )";
            $params[] = $filtros['fecha_inicio'];
            $params[] = $filtros['fecha_fin'];
            $params[] = $filtros['fecha_inicio'];
            $params[] = $filtros['fecha_fin'];
        } elseif (!empty($filtros['fecha_inicio'])) {
            // Solo fecha inicio
            $sql .= " AND (DATE(l.created_at) >= ? OR DATE(l.updated_at) >= ?)";
            $params[] = $filtros['fecha_inicio'];
            $params[] = $filtros['fecha_inicio'];
        } elseif (!empty($filtros['fecha_fin'])) {
            // Solo fecha fin
            $sql .= " AND (DATE(l.created_at) <= ? OR DATE(l.updated_at) <= ?)";
            $params[] = $filtros['fecha_fin'];
            $params[] = $filtros['fecha_fin'];
        }

        // =====================================================================
        // FILTRO DE AGENCIA
        // =====================================================================
        if (!empty($filtros['agenciaid'])) {
            $sql .= " AND us.idAgencia = ?";
            $params[] = $filtros['agenciaid'];
        }

        // =====================================================================
        // FILTRO DE PUESTO
        // =====================================================================
        if (!empty($filtros['puestoid'])) {
            $sql .= " AND us.idPuesto = ?";
            $params[] = $filtros['puestoid'];
        }

        // =====================================================================
        // FILTRO DE ESTADO
        // =====================================================================
        if (!empty($filtros['estado'])) {
            $sql .= " AND l.estado = ?";
            $params[] = $filtros['estado'];
        } else {
            // Si no se especifica estado, mostrar solo: enviada, devuelta, corregida
            $sql .= " AND l.estado IN ('enviada', 'devuelta', 'corregida')";
        }

        // =====================================================================
        // FILTRO DE NÚMERO DE FACTURA
        // =====================================================================
        if (!empty($filtros['numero_factura'])) {
            $sql .= " AND CONVERT(l.numero_factura USING utf8mb4) LIKE ?";
            $params[] = '%' . $filtros['numero_factura'] . '%';
        }

        // Ordenar por fecha de actualización (más recientes primero)
        $sql .= " ORDER BY l.updated_at DESC, l.created_at DESC";

        try {
            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);
            $liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $liquidaciones;
        } catch (Exception $e) {
            error_log("Error en _obtenerLiquidacionesPendientesRevision: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            throw $e;
        }
    }

    /**
     * Esta versión simplifica la lógica ya que ahora solo manejamos fecha_fin
     */
    private function _obtenerLiquidacionesPendientesRevision($filtros = [])
    {
        // Base de la consulta
        $sql = "SELECT 
        l.idLiquidaciones,
        l.usuarioid,
        l.numero_factura,
        l.vehiculoid,
        l.tipoapoyoid,
        l.descripcion,
        l.detalle,
        l.monto,
        l.monto_factura,
        l.estado,
        l.fecha_liquidacion,
        l.solicitudautorizacionid,
        l.revisado_por,
        l.fecha_revision,
        l.motivo_rechazo,
        l.created_at,
        l.updated_at,
        -- Usuario
        CONCAT(dtp.nombres) as nombre_usuario,
        ag.nombre as agencia_usuario,
        p.nombre as puesto_usuario,
        -- Revisor
        CONCAT(dtp_rev.nombres) as nombre_revisor,
        -- Vehículo
        v.placa as vehiculo_placa,
        v.marca as vehiculo_marca,
        tv.nombre as tipo_vehiculo,
        -- Tipo Apoyo
        ta.codigo as tipo_apoyo_codigo,
        ta.nombre as tipo_apoyo,
        -- Autorización
        sa.estado as autorizacion_estado,
        sa.fecha_respuesta as autorizacion_fecha,
        -- Factura
        f.fecha_emision,
        f.nombre_establecimiento as nombre_emisor,
        -- Corrección pendiente
        (SELECT COUNT(*) 
         FROM apoyo_combustibles.solicitudescorreccion sc 
         WHERE sc.liquidacionid = l.idLiquidaciones 
         AND sc.estado = 'pendiente') as tiene_correccion_pendiente
    FROM apoyo_combustibles.liquidaciones l
    INNER JOIN dbintranet.usuarios us ON CONVERT(l.usuarioid USING utf8mb4) = CONVERT(us.idUsuarios USING utf8mb4)
    INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
    INNER JOIN dbintranet.agencia ag ON us.idAgencia = ag.idAgencia
    INNER JOIN dbintranet.puesto p ON us.idPuesto = p.idPuesto
    LEFT JOIN dbintranet.usuarios us_rev ON CONVERT(l.revisado_por USING utf8mb4) = CONVERT(us_rev.idUsuarios USING utf8mb4)
    LEFT JOIN dbintranet.datospersonales dtp_rev ON us_rev.idDatosPersonales = dtp_rev.idDatosPersonales
    LEFT JOIN apoyo_combustibles.vehiculos v ON l.vehiculoid = v.idVehiculos
    LEFT JOIN apoyo_combustibles.tiposvehiculo tv ON v.tipovehiculoid = tv.idTiposVehiculo
    INNER JOIN apoyo_combustibles.tiposapoyo ta ON l.tipoapoyoid = ta.idTiposApoyo
    LEFT JOIN apoyo_combustibles.solicitudesautorizacion sa ON l.solicitudautorizacionid = sa.idSolicitudesAutorizacion
    INNER JOIN compras.facturas_sat f ON CONVERT(l.numero_factura USING utf8mb4) = CONVERT(f.numero_dte USING utf8mb4)
    WHERE 1=1";

        $params = [];

        // =====================================================================
        // FILTRO DE FECHA (CREACIÓN Y MODIFICACIÓN DENTRO DEL LÍMITE)
        // =====================================================================
        if (!empty($filtros['fecha_fin'])) {
            // Trae liquidaciones creadas antes o igual a la fecha fin
            // SIEMPRE QUE su última modificación tampoco supere esa fecha.
            $sql .= " AND DATE(l.created_at) <= ? 
              AND DATE(l.updated_at) <= ?";

            $params[] = $filtros['fecha_fin'];
            $params[] = $filtros['fecha_fin'];
        }

        // =====================================================================
        // FILTRO DE AGENCIA
        // =====================================================================
        if (!empty($filtros['agenciaid'])) {
            $sql .= " AND us.idAgencia = ?";
            $params[] = $filtros['agenciaid'];
        }

        // =====================================================================
        // FILTRO DE PUESTO
        // =====================================================================
        if (!empty($filtros['puestoid'])) {
            $sql .= " AND us.idPuesto = ?";
            $params[] = $filtros['puestoid'];
        }

        // =====================================================================
        // FILTRO DE ESTADO
        // =====================================================================
        if (!empty($filtros['estado'])) {
            $sql .= " AND l.estado = ?";
            $params[] = $filtros['estado'];
        } else {
            // Si no se especifica estado, mostrar solo: enviada, devuelta, corregida
            $sql .= " AND l.estado IN ('enviada', 'devuelta', 'corregida')";
        }

        // =====================================================================
        // FILTRO DE NÚMERO DE FACTURA
        // =====================================================================
        if (!empty($filtros['numero_factura'])) {
            $sql .= " AND CONVERT(l.numero_factura USING utf8mb4) LIKE ?";
            $params[] = '%' . $filtros['numero_factura'] . '%';
        }

        // Ordenar por fecha de actualización (más recientes primero)
        $sql .= " ORDER BY l.updated_at ASC, l.created_at ASC";

        try {
            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);
            $liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $liquidaciones;
        } catch (Exception $e) {
            error_log("Error en _obtenerLiquidacionesPendientesRevision: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            throw $e;
        }
    }

    /**
     * Obtiene el historial de revisiones de liquidaciones
     *
     * @param array $filtros Filtros opcionales
     * @return array Lista de liquidaciones revisadas
     */
    private function _obtenerHistorialRevisiones($filtros = [])
    {
        try {
            $sql = "SELECT 
    l.idLiquidaciones,
    l.usuarioid,
    l.numero_factura,
    l.vehiculoid,
    l.tipoapoyoid,
    l.descripcion,
    l.monto,
    l.estado,
    l.fecha_liquidacion,
    l.revisado_por,
    l.fecha_revision,
    l.motivo_rechazo,
    l.created_at,
    l.updated_at,
    -- Datos del usuario
    dtp.nombres as nombre_usuario,
    a.nombre as agencia_usuario,
    p.nombre as puesto_usuario,
    -- Datos del revisor
    dtp_rev.nombres as nombre_revisor,
    -- Datos del vehículo
    v.placa as vehiculo_placa,
    v.marca as vehiculo_marca,
    tv.nombre as tipo_vehiculo,
    -- Datos del tipo de apoyo
    ta.codigo as tipo_apoyo_codigo,
    ta.nombre as tipo_apoyo,
    -- Datos de la factura
    fs.fecha_emision,
    fs.nombre_emisor
FROM apoyo_combustibles.liquidaciones l
INNER JOIN dbintranet.usuarios us ON l.usuarioid = us.idUsuarios
INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
LEFT JOIN dbintranet.agencia a ON us.idAgencia = a.idAgencia
LEFT JOIN dbintranet.puesto p ON us.idPuesto = p.idPuesto
LEFT JOIN dbintranet.usuarios us_rev ON l.revisado_por = us_rev.idUsuarios
LEFT JOIN dbintranet.datospersonales dtp_rev ON us_rev.idDatosPersonales = dtp_rev.idDatosPersonales
LEFT JOIN apoyo_combustibles.vehiculos v ON l.vehiculoid = v.idVehiculos
LEFT JOIN apoyo_combustibles.tiposvehiculo tv ON v.tipovehiculoid = tv.idTiposVehiculo
LEFT JOIN apoyo_combustibles.tiposapoyo ta ON l.tipoapoyoid = ta.idTiposApoyo
-- CORRECCIÓN 1: El JOIN con compras.facturas_sat
LEFT JOIN compras.facturas_sat fs 
    ON l.numero_factura COLLATE utf8mb4_general_ci = fs.numero_dte COLLATE utf8mb4_general_ci
-- CORRECCIÓN 2: El filtrado de estados
WHERE l.estado COLLATE utf8mb4_general_ci IN ('aprobada', 'rechazada', 'devuelta', 'corregida', 'de_baja', 'en_lote', 'pagada')
    AND l.revisado_por IS NOT NULL;";

            $params = [];

            // Aplicar filtros opcionales
            if (!empty($filtros['fecha_inicio'])) {
                $sql .= " AND DATE(l.fecha_revision) >= ?";
                $params[] = $filtros['fecha_inicio'];
            }

            if (!empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(l.fecha_revision) <= ?";
                $params[] = $filtros['fecha_fin'];
            }

            if (!empty($filtros['estado'])) {
                $sql .= " AND l.estado = ?";
                $params[] = $filtros['estado'];
            }

            if (!empty($filtros['agenciaid'])) {
                $sql .= " AND us.idAgencia = ?";
                $params[] = $filtros['agenciaid'];
            }

            if (!empty($filtros['numero_factura'])) {
                $sql .= " AND l.numero_factura LIKE ?";
                $params[] = "%{$filtros['numero_factura']}%";
            }

            $sql .= " ORDER BY l.fecha_revision DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error en _obtenerHistorialRevisiones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene solicitudes de corrección de un usuario
     *
     * @param string $usuarioid ID del usuario
     * @return array Lista de solicitudes de corrección
     */
    private function _obtenerSolicitudesCorreccionUsuario($usuarioid)
    {
        try {
            $sql = "SELECT 
                sc.idSolicitudesCorreccion,
                sc.liquidacionid,
                sc.solicitado_por,
                sc.motivo,
                sc.estado,
                sc.fecha_solicitud,
                sc.fecha_correccion,
                sc.observaciones_correccion,
                -- Datos del solicitante (contabilidad)
                dtp_sol.nombres as nombre_solicitante,
                -- Datos de la liquidación
                l.numero_factura,
                l.monto,
                l.descripcion,
                l.estado as estado_liquidacion,
                ta.nombre as tipo_apoyo
            FROM apoyo_combustibles.solicitudescorreccion sc
            INNER JOIN apoyo_combustibles.liquidaciones l ON sc.liquidacionid = l.idLiquidaciones
            LEFT JOIN apoyo_combustibles.tiposapoyo ta ON l.tipoapoyoid = ta.idTiposApoyo
            LEFT JOIN dbintranet.usuarios us_sol ON sc.solicitado_por = us_sol.idUsuarios
            LEFT JOIN dbintranet.datospersonales dtp_sol ON us_sol.idDatosPersonales = dtp_sol.idDatosPersonales
            WHERE l.usuarioid = ?
            ORDER BY sc.fecha_solicitud DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$usuarioid]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error en _obtenerSolicitudesCorreccionUsuario: " . $e->getMessage());
            return [];
        }
    }


    /**
     * Verifica si una liquidación existe en la base de datos
     *
     * @param int $liquidacionid ID de la liquidación a verificar
     * @return array|false Datos de la liquidación si existe, false si no existe
     */
    private function _verificarExistenciaLiquidacion($liquidacionid)
    {
        try {
            $sql = "SELECT 
            l.idLiquidaciones,
            l.usuarioid,
            l.numero_factura,
            l.estado
        FROM apoyo_combustibles.liquidaciones l
        WHERE l.idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$liquidacionid]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            return $liquidacion ?: false;

        } catch (Exception $e) {
            error_log("Error en _verificarExistenciaLiquidacion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todas las solicitudes de corrección de una liquidación específica
     * Este método retorna el historial completo para que Contabilidad pueda revisarlo
     *
     * @param int $liquidacionid ID de la liquidación
     * @return array Lista de solicitudes de corrección con toda la información
     */
    private function _obtenerCorreccionesPorLiquidacion($liquidacionid)
    {
        try {
            $sql = "SELECT 
            -- Datos de la solicitud de corrección
            sc.idSolicitudesCorreccion,
            sc.liquidacionid,
            sc.solicitado_por,
            sc.motivo,
            sc.estado,
            sc.fecha_solicitud,
            sc.fecha_correccion,
            sc.observaciones_correccion,
            -- Datos del solicitante (persona de contabilidad que pidió la corrección)
            dtp_sol.nombres as nombre_solicitante,
            -- Datos de la liquidación
            l.numero_factura,
            l.monto,
            l.descripcion,
            l.detalle,
            l.estado as estado_liquidacion,
            ta.nombre as tipo_apoyo,
            -- Datos del usuario dueño de la liquidación
            dtp_usuario.nombres as nombre_usuario
        FROM apoyo_combustibles.solicitudescorreccion sc
        INNER JOIN apoyo_combustibles.liquidaciones l 
            ON sc.liquidacionid = l.idLiquidaciones
        LEFT JOIN apoyo_combustibles.tiposapoyo ta 
            ON l.tipoapoyoid = ta.idTiposApoyo
        -- Join para datos del solicitante (quien pidió la corrección)
        LEFT JOIN dbintranet.usuarios us_sol 
            ON sc.solicitado_por = us_sol.idUsuarios
        LEFT JOIN dbintranet.datospersonales dtp_sol 
            ON us_sol.idDatosPersonales = dtp_sol.idDatosPersonales
        -- Join para datos del usuario de la liquidación
        LEFT JOIN dbintranet.usuarios us_usuario 
            ON l.usuarioid = us_usuario.idUsuarios
        LEFT JOIN dbintranet.datospersonales dtp_usuario 
            ON us_usuario.idDatosPersonales = dtp_usuario.idDatosPersonales
        WHERE sc.liquidacionid = ?
        ORDER BY sc.fecha_solicitud DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$liquidacionid]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error en _obtenerCorreccionesPorLiquidacion: " . $e->getMessage());
            return [];
        }
    }
    // ========================================================================
// 📋 ENDPOINTS PÚBLICOS - CONTABILIDAD
// ========================================================================

    /**
     * Lista liquidaciones pendientes de revisión con filtros extendidos
     *
     * POST: combustible/listarLiquidacionesPendientesRevision
     *
     * @param object $datos {
     *   fecha_inicio?: string,      // Filtra por created_at >= fecha_inicio O updated_at >= fecha_inicio
     *   fecha_fin?: string,          // Filtra por created_at <= fecha_fin O updated_at <= fecha_fin
     *   agenciaid?: int,             // Filtra por agencia del usuario
     *   puestoid?: int,              // Filtra por puesto del usuario
     *   estado?: string,             // Filtra por estado de liquidación
     *   numero_factura?: string      // Busca por número de factura (LIKE)
     * }
     */
    public function listarLiquidacionesPendientesRevision($datos = null)
    {
        try {
            $filtros = [];

            if ($datos) {
                $datos = $this->limpiarDatos($datos);
                if (!empty($datos->fecha_fin)) $filtros['fecha_fin'] = $datos->fecha_fin;
                if (!empty($datos->agenciaid)) $filtros['agenciaid'] = $datos->agenciaid;
                if (!empty($datos->puestoid)) $filtros['puestoid'] = $datos->puestoid;
                if (!empty($datos->estado)) $filtros['estado'] = $datos->estado;
                if (!empty($datos->numero_factura)) $filtros['numero_factura'] = $datos->numero_factura;
            }

            $liquidaciones = $this->_obtenerLiquidacionesPendientesRevision($filtros);

            return $this->res->ok('Liquidaciones pendientes obtenidas', [
                'registros' => $liquidaciones,
                'total' => count($liquidaciones)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarLiquidacionesPendientesRevision: " . $e->getMessage());
            return $this->res->fail('Error al obtener liquidaciones pendientes', $e);
        }
    }

    /**
     * Lista solicitudes de corrección de una liquidación específica
     * Este endpoint es para que Contabilidad pueda ver el historial completo de correcciones
     *
     * POST: combustible/listarSolicitudesCorreccionPorLiquidacion
     *
     * @param object $datos {
     *   liquidacionid: int
     * }
     */
    public function listarSolicitudesCorreccionPorLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validar que se envió el liquidacionid
            if (empty($datos->liquidacionid)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            // Verificar que la liquidación existe
            $liquidacion = $this->_verificarExistenciaLiquidacion($datos->liquidacionid);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe');
            }

            // Obtener las correcciones de la liquidación
            $correcciones = $this->_obtenerCorreccionesPorLiquidacion($datos->liquidacionid);

            return $this->res->ok('Correcciones de la liquidación obtenidas', [
                'registros' => $correcciones,
                'total' => count($correcciones)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarSolicitudesCorreccionPorLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al obtener correcciones de la liquidación', $e);
        }
    }

    /**
     * Aprueba una liquidación
     *
     * POST: combustible/aprobarLiquidacion
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   observaciones?: string
     * }
     */
    public function aprobarLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            // Verificar que la liquidación existe y está en estado válido
            $sql = "SELECT 
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.estado,
                l.monto,
                dtp.nombres,
                dtp.correoElectronico as email
            FROM apoyo_combustibles.liquidaciones l
            INNER JOIN dbintranet.usuarios us ON l.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE l.idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe');
            }

            if (!in_array($liquidacion['estado'], ['enviada', 'corregida', 'devuelta'])) {
                return $this->res->fail('Solo se pueden aprobar liquidaciones en estado "enviada" o "corregida"');
            }

            $this->connect->beginTransaction();

            // Actualizar liquidación
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'aprobada',
                    revisado_por = ?,
                    fecha_revision = CURRENT_TIMESTAMP
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->idLiquidaciones
            ]);

            $this->connect->commit();

            // Enviar notificación (opcional)
            /*try {
                $asunto = "Liquidación Aprobada - Factura {$liquidacion['numero_factura']}";
                $mensaje = "
                <h2>Liquidación Aprobada</h2>
                <p>Estimado/a {$liquidacion['nombres']},</p>
                <p>Su liquidación de la factura <strong>{$liquidacion['numero_factura']}</strong> ha sido <strong>APROBADA</strong> por Contabilidad.</p>
                <p>Monto: Q" . number_format($liquidacion['monto'], 2) . "</p>
                <p><em>Sistema de Apoyo de Combustibles</em></p>
            ";

                if (!empty($liquidacion['email'])) {
                    $this->mailer->enviar($liquidacion['email'], $asunto, $mensaje);
                }
            } catch (Exception $e) {
                error_log("Error al enviar notificación de aprobación: " . $e->getMessage());
            }*/

            return $this->res->ok('Liquidación aprobada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en aprobarLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al aprobar la liquidación', $e);
        }
    }

    /**
     * Rechaza una liquidación
     *
     * POST: combustible/rechazarLiquidacion
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   motivo_rechazo: string
     * }
     */
    public function rechazarLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            if (empty($datos->motivo_rechazo)) {
                return $this->res->fail('El motivo del rechazo es requerido');
            }

            // Verificar que la liquidación existe y está en estado válido
            $sql = "SELECT 
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.estado,
                l.monto,
                dtp.nombres,
                dtp.correoElectronico as email
            FROM apoyo_combustibles.liquidaciones l
            INNER JOIN dbintranet.usuarios us ON l.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE l.idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe');
            }

            if (!in_array($liquidacion['estado'], ['enviada', 'corregida', 'devuelta'])) {
                return $this->res->fail('Solo se pueden rechazar liquidaciones en estado "enviada" o "corregida"');
            }

            $this->connect->beginTransaction();

            // Actualizar liquidación
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'rechazada',
                    revisado_por = ?,
                    fecha_revision = CURRENT_TIMESTAMP,
                    motivo_rechazo = ?
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->motivo_rechazo,
                $datos->idLiquidaciones
            ]);

            $this->connect->commit();

            // Enviar notificación (opcional)
            try {
                $asunto = "Liquidación Rechazada - Factura {$liquidacion['numero_factura']}";
                $mensaje = "
                <h2>Liquidación Rechazada</h2>
                <p>Estimado/a {$liquidacion['nombres']},</p>
                <p>Su liquidación de la factura <strong>{$liquidacion['numero_factura']}</strong> ha sido <strong>RECHAZADA</strong> por Contabilidad.</p>
                <p><strong>Motivo:</strong> {$datos->motivo_rechazo}</p>
                <p><em>Sistema de Apoyo de Combustibles</em></p>
            ";

                if (!empty($liquidacion['email'])) {
                    $this->mailer->enviar($liquidacion['email'], $asunto, $mensaje);
                }
            } catch (Exception $e) {
                error_log("Error al enviar notificación de rechazo: " . $e->getMessage());
            }

            return $this->res->ok('Liquidación rechazada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en rechazarLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al rechazar la liquidación', $e);
        }
    }

    /**
     * Devuelve una liquidación solicitando corrección
     *
     * POST: combustible/devolverLiquidacion
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   motivo: string
     * }
     */
    public function devolverLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            if (empty($datos->motivo)) {
                return $this->res->fail('El motivo de la devolución es requerido');
            }

            // Verificar que la liquidación existe y está en estado válido
            $sql = "SELECT 
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.estado,
                l.monto,
                dtp.nombres,
                dtp.correoElectronico as email
            FROM apoyo_combustibles.liquidaciones l
            INNER JOIN dbintranet.usuarios us ON l.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE l.idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe');
            }

            if (!in_array($liquidacion['estado'], ['enviada', 'corregida', 'devuelta'])) {
                return $this->res->fail('Solo se pueden devolver liquidaciones en estado "enviada", "corregida" o "devuelta"');
            }

            $this->connect->beginTransaction();

            // Actualizar liquidación a estado "devuelta"
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'devuelta',
                    revisado_por = ?,
                    fecha_revision = CURRENT_TIMESTAMP
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->idLiquidaciones
            ]);

            // Crear solicitud de corrección
            $sql = "INSERT INTO apoyo_combustibles.solicitudescorreccion 
                (liquidacionid, solicitado_por, motivo, estado)
                VALUES (?, ?, ?, 'pendiente')";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->idLiquidaciones,
                $this->idUsuario,
                $datos->motivo
            ]);

            $this->connect->commit();

            // Enviar notificación (opcional)
            try {
                $asunto = "Liquidación Devuelta para Corrección - Factura {$liquidacion['numero_factura']}";
                $mensaje = "
                <h2>Solicitud de Corrección</h2>
                <p>Estimado/a {$liquidacion['nombres']},</p>
                <p>Su liquidación de la factura <strong>{$liquidacion['numero_factura']}</strong> ha sido <strong>DEVUELTA</strong> por Contabilidad.</p>
                <p><strong>Se requiere corrección:</strong> {$datos->motivo}</p>
                <p>Por favor, ingrese al sistema para realizar las correcciones solicitadas.</p>
                <p><em>Sistema de Apoyo de Combustibles</em></p>
            ";

                if (!empty($liquidacion['email'])) {
                    $this->mailer->enviar($liquidacion['email'], $asunto, $mensaje);
                }
            } catch (Exception $e) {
                error_log("Error al enviar notificación de devolución: " . $e->getMessage());
            }

            return $this->res->ok('Liquidación devuelta correctamente. Se ha notificado al usuario.');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en devolverLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al devolver la liquidación', $e);
        }
    }

    /**
     * Da de baja una liquidación (aprobada pero con gestión especial)
     *
     * POST: combustible/darDeBajaLiquidacion
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   observaciones?: string
     * }
     */
    public function darDeBajaLiquidacion($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            // Verificar que la liquidación existe y está en estado válido
            $sql = "SELECT 
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.estado,
                l.monto,
                dtp.nombres,
                dtp.correoElectronico as email
            FROM apoyo_combustibles.liquidaciones l
            INNER JOIN dbintranet.usuarios us ON l.usuarioid = us.idUsuarios
            INNER JOIN dbintranet.datospersonales dtp ON us.idDatosPersonales = dtp.idDatosPersonales
            WHERE l.idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe');
            }

            if (!in_array($liquidacion['estado'], ['enviada', 'corregida', 'correcion', 'devuelta'])) {
                return $this->res->fail('Solo se pueden dar de baja liquidaciones en estado "enviada" o "corregida"');
            }

            $this->connect->beginTransaction();

            // Actualizar liquidación a estado "de_baja"
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'de_baja',
                    revisado_por = ?,
                    fecha_revision = CURRENT_TIMESTAMP
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $this->idUsuario,
                $datos->idLiquidaciones
            ]);

            $this->connect->commit();

            // Enviar notificación (opcional)
            try {
                $asunto = "Liquidación Dada de Baja - Factura {$liquidacion['numero_factura']}";
                $mensaje = "
                <h2>Liquidación Dada de Baja</h2>
                <p>Estimado/a {$liquidacion['nombres']}</p>
                <p>Su liquidación de la factura <strong>{$liquidacion['numero_factura']}</strong> ha sido dada de baja por Contabilidad.</p>
                <p><em>Sistema de Apoyo de Combustibles</em></p>
            ";

                //if (!empty($liquidacion['email'])) {
                //    $this->mailer->enviarCorreo($liquidacion['email'], $asunto, $mensaje);
                //}
            } catch (Exception $e) {
                error_log("Error al enviar notificación de baja: " . $e->getMessage());
            }

            return $this->res->ok('Liquidación dada de baja correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en darDeBajaLiquidacion: " . $e->getMessage());
            return $this->res->fail('Error al dar de baja la liquidación', $e);
        }
    }

    /**
     * Obtiene el historial de revisiones con filtros
     *
     * POST: combustible/obtenerHistorialRevisiones
     *
     * @param object $datos {
     *   fecha_inicio?: string,
     *   fecha_fin?: string,
     *   estado?: string,
     *   agenciaid?: int,
     *   numero_factura?: string
     * }
     */
    public function obtenerHistorialRevisiones($datos = null)
    {
        try {
            $filtros = [];

            if ($datos) {
                $datos = $this->limpiarDatos($datos);

                if (!empty($datos->fecha_inicio)) $filtros['fecha_inicio'] = $datos->fecha_inicio;
                if (!empty($datos->fecha_fin)) $filtros['fecha_fin'] = $datos->fecha_fin;
                if (!empty($datos->estado)) $filtros['estado'] = $datos->estado;
                if (!empty($datos->agenciaid)) $filtros['agenciaid'] = $datos->agenciaid;
                if (!empty($datos->numero_factura)) $filtros['numero_factura'] = $datos->numero_factura;
            }

            $liquidaciones = $this->_obtenerHistorialRevisiones($filtros);

            return $this->res->ok('Historial de revisiones obtenido', [
                'registros' => $liquidaciones,
                'total' => count($liquidaciones)
            ]);

        } catch (Exception $e) {
            error_log("Error en obtenerHistorialRevisiones: " . $e->getMessage());
            return $this->res->fail('Error al obtener historial de revisiones', $e);
        }
    }

// ========================================================================
// 📋 ENDPOINTS PARA USUARIO - CORRECCIONES
// ========================================================================

    /**
     * Lista solicitudes de corrección del usuario actual
     *
     * GET: combustible/listarMisSolicitudesCorreccion
     */
    public function listarMisSolicitudesCorreccion()
    {
        try {
            $solicitudes = $this->_obtenerSolicitudesCorreccionUsuario($this->idUsuario);

            return $this->res->ok('Solicitudes de corrección obtenidas', [
                'registros' => $solicitudes,
                'total' => count($solicitudes)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarMisSolicitudesCorreccion: " . $e->getMessage());
            return $this->res->fail('Error al obtener solicitudes de corrección', $e);
        }
    }

    /**
     * Marca una liquidación como corregida por el usuario
     *
     * POST: combustible/marcarCorreccionRealizada
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   observaciones_correccion?: string
     * }
     */
    public function marcarCorreccionRealizada($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            // Verificar que la liquidación existe, pertenece al usuario y está devuelta
            $sql = "SELECT 
                l.idLiquidaciones,
                l.usuarioid,
                l.numero_factura,
                l.estado
            FROM apoyo_combustibles.liquidaciones l
            WHERE l.idLiquidaciones = ? AND l.usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones, $this->idUsuario]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe o no tiene permisos para modificarla');
            }

            $estadosPermitidos = ['devuelta', 'corregida'];

            if (!in_array($liquidacion['estado'], $estadosPermitidos)) {
                return $this->res->fail('Estado no válido para realizar esta acción.');
            }

            $this->connect->beginTransaction();

            // Actualizar liquidación a estado "corregida"
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'corregida'
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);

            // El trigger automáticamente actualizará la solicitud de corrección

            // Si hay observaciones, actualizar la solicitud de corrección
            $sql = "UPDATE apoyo_combustibles.solicitudescorreccion 
                    SET estado = 'realizada'
                    WHERE liquidacionid = ? 
                    ORDER BY fecha_solicitud DESC
                    LIMIT 3";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $datos->idLiquidaciones
            ]);

            $this->connect->commit();

            return $this->res->ok('Corrección marcada como realizada. La liquidación volverá a revisión de Contabilidad.');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en marcarCorreccionRealizada: " . $e->getMessage());
            return $this->res->fail('Error al marcar la corrección como realizada', $e);
        }
    }

    public function marcarCorreccionRealizada2($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // DEBUG 1: Datos recibidos
            echo "1. Datos recibidos: ";
            var_dump($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de la liquidación es requerido');
            }

            $sql = "SELECT l.idLiquidaciones, l.usuarioid, l.numero_factura, l.estado
                FROM apoyo_combustibles.liquidaciones l
                WHERE l.idLiquidaciones = ? AND l.usuarioid = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones, $this->idUsuario]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            // DEBUG 2: Resultado de la consulta inicial
            echo "2. Liquidación encontrada: ";
            var_dump($liquidacion);

            if (!$liquidacion) {
                return $this->res->fail('La liquidación no existe o no tiene permisos');
            }

            $estadosPermitidos = ['devuelta', 'corregida'];
            if (!in_array($liquidacion['estado'], $estadosPermitidos)) {
                echo "3. Falló validación de estado. Estado actual: " . $liquidacion['estado'];
                return $this->res->fail('Estado no válido: ' . $liquidacion['estado']);
            }

            $this->connect->beginTransaction();
            echo "4. Transacción iniciada<br>";

            // Actualizar liquidación
            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET estado = 'corregida'
                WHERE idLiquidaciones = ? 
                AND estado != 'corregida'";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$datos->idLiquidaciones]);

            $filasLiq = $stmt->rowCount();
            // DEBUG 5: ¿Se afectó la fila? (Si da 0 y el estado ya era 'corregida', MySQL no cuenta la fila como afectada)
            echo "5. Filas actualizadas en liquidaciones: " . $filasLiq;

            if (!empty($datos->observaciones_correccion)) {
                // OJO: Aquí 'estado = realizada' puede ser el problema si aún no se ha marcado así
                $sql = "UPDATE apoyo_combustibles.solicitudescorreccion 
                SET observaciones_correccion = ?
                WHERE liquidacionid = ? 
                    AND estado = 'realizada'
                ORDER BY fecha_solicitud DESC
                LIMIT 1";

                $stmt = $this->connect->prepare($sql);
                $stmt->execute([
                    $datos->observaciones_correccion,
                    $datos->idLiquidaciones
                ]);

                $filasObs = $stmt->rowCount();
                // DEBUG 6: ¿Se actualizaron las observaciones?
                echo "6. Filas actualizadas en observaciones: " . $filasObs . " para ID: " . $datos->idLiquidaciones;
            }

            $this->connect->commit();
            echo "7. ¡COMMIT realizado con éxito!";

            return $this->res->ok('Corrección marcada como realizada.');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            // DEBUG FINAL: Error de sistema
            echo "ERROR CAPTURADO: " . $e->getMessage();
            return $this->res->fail('Error al marcar la corrección', $e);
        }
    }

    /**
     * Aprueba múltiples liquidaciones
     *
     * POST: combustible/aprobarLiquidacionesMasivo
     *
     * @param object $datos {
     *   liquidaciones: array<int>  // Array de IDs de liquidaciones
     * }
     */
    public function aprobarLiquidacionesMasivo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->liquidaciones) || !is_array($datos->liquidaciones)) {
                return $this->res->fail('Debe proporcionar un array de IDs de liquidaciones');
            }

            if (count($datos->liquidaciones) === 0) {
                return $this->res->fail('Debe seleccionar al menos una liquidación');
            }

            // Limitar a 100 liquidaciones por petición para evitar timeouts
            if (count($datos->liquidaciones) > 100) {
                return $this->res->fail('No se pueden aprobar más de 100 liquidaciones a la vez');
            }

            $exitosas = [];
            $fallidas = [];

            $this->connect->beginTransaction();

            foreach ($datos->liquidaciones as $idLiquidacion) {
                try {
                    // Verificar que la liquidación existe y está en estado válido
                    $sql = "SELECT idLiquidaciones, estado, usuarioid, numero_factura
                        FROM apoyo_combustibles.liquidaciones 
                        WHERE idLiquidaciones = ?";

                    $stmt = $this->connect->prepare($sql);
                    $stmt->execute([$idLiquidacion]);
                    $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$liquidacion) {
                        $fallidas[] = $idLiquidacion;
                        continue;
                    }

                    if (!in_array($liquidacion['estado'], ['enviada', 'corregida', 'devuelta'])) {
                        $fallidas[] = $idLiquidacion;
                        continue;
                    }

                    // Actualizar liquidación
                    $sql = "UPDATE apoyo_combustibles.liquidaciones 
                        SET estado = 'aprobada',
                            revisado_por = ?,
                            fecha_revision = CURRENT_TIMESTAMP
                        WHERE idLiquidaciones = ?";

                    $stmt = $this->connect->prepare($sql);
                    $stmt->execute([
                        $this->idUsuario,
                        $idLiquidacion
                    ]);

                    $exitosas[] = $idLiquidacion;

                } catch (Exception $e) {
                    error_log("Error al aprobar liquidación {$idLiquidacion}: " . $e->getMessage());
                    $fallidas[] = $idLiquidacion;
                }
            }

            $this->connect->commit();

            $mensaje = sprintf(
                'Aprobación masiva completada: %d exitosas, %d fallidas',
                count($exitosas),
                count($fallidas)
            );

            return $this->res->ok($mensaje, [
                'exitosas' => $exitosas,
                'fallidas' => $fallidas
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en aprobarLiquidacionesMasivo: " . $e->getMessage());
            return $this->res->fail('Error al aprobar liquidaciones masivamente', $e);
        }
    }

// ============================================================================
// ENDPOINTS - COMPROBANTES Y LIQUIDACIONES
// ============================================================================

    /**
     * Lista todas las liquidaciones aprobadas sin comprobante agrupadas por usuario
     *
     * GET: combustible/listarLiquidacionesSinComprobante
     */
    public function listarLiquidacionesSinComprobante()
    {
        try {
            $registros = $this->_obtenerLiquidacionesSinComprobante();

            return $this->res->ok('Liquidaciones obtenidas correctamente', [
                'registros' => $registros,
                'total' => count($registros)
            ]);

        } catch (Exception $e) {
            error_log("Error en listarLiquidacionesSinComprobante: " . $e->getMessage());
            return $this->res->fail('Error al obtener liquidaciones', $e);
        }
    }

    /**
     * Obtiene el detalle completo de liquidaciones de un usuario específico
     *
     * POST: combustible/obtenerDetalleLiquidacionesUsuario
     *
     * @param object $datos {
     *   usuarioid: string,
     *   codigoCliente?: string
     * }
     */
    public function obtenerDetalleLiquidacionesUsuario($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->usuarioid)) {
                return $this->res->fail('El ID de usuario es requerido');
            }

            // Obtener información del usuario
            $sqlUsuario = "SELECT 
            cli.CodigoCliente,
            dtp.nombres AS NombreUsuario,
            ag.nombre AS Agencia,
            pu.nombre AS Puesto,
            (
                SELECT cap2.NumeroCuenta
                FROM asociado_t24.ccomndatoscaptaciones AS cap2
                WHERE cap2.Cliente = cli.CodigoCliente
                    AND cap2.Producto = '114A.AHORRO.DISPONIBLE'
                ORDER BY cap2.NumeroCuenta
                LIMIT 1
            ) AS PrimeraCuenta,
            SUM(l.monto) AS TotalLiquidaciones,
            COUNT(l.idLiquidaciones) AS CantidadLiquidaciones
        FROM
            apoyo_combustibles.liquidaciones AS l
            LEFT JOIN dbintranet.usuarios AS us ON l.usuarioid = us.idUsuarios
            LEFT JOIN dbintranet.datospersonales AS dtp ON dtp.idDatosPersonales = us.idDatosPersonales
            LEFT JOIN asociado_t24.comndatosclientes AS cli ON dtp.dpi = cli.Dpi
            INNER JOIN dbintranet.agencia AS ag ON ag.idAgencia = us.idAgencia
            INNER JOIN dbintranet.puesto AS pu ON pu.idPuesto = us.idPuesto
        WHERE
            l.usuarioid = ?
            AND l.estado = 'aprobada'
            AND l.comprobantecontableid IS NULL
        GROUP BY
            cli.CodigoCliente,
            dtp.nombres,
            ag.nombre,
            pu.nombre";

            $stmt = $this->connect->prepare($sqlUsuario);
            $stmt->execute([$datos->usuarioid]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return $this->res->fail('No se encontró información del usuario o no tiene liquidaciones pendientes');
            }

            // Obtener detalle de liquidaciones
            $sqlLiquidaciones = "SELECT 
            l.idLiquidaciones,
            l.numero_factura,
            l.monto,
            l.monto_factura,
            l.descripcion,
            l.detalle,
            l.fecha_liquidacion,
            l.estado,
            ta.nombre AS tipo_apoyo,
            v.marca AS vehiculo,
            v.placa
        FROM apoyo_combustibles.liquidaciones l
        LEFT JOIN apoyo_combustibles.tiposapoyo ta ON l.tipoapoyoid = ta.idTiposApoyo
        LEFT JOIN apoyo_combustibles.vehiculos v ON l.vehiculoid = v.idVehiculos
        WHERE l.usuarioid = ?
            AND l.estado = 'aprobada'
            AND l.comprobantecontableid IS NULL
        ORDER BY l.fecha_liquidacion DESC";

            $stmt = $this->connect->prepare($sqlLiquidaciones);
            $stmt->execute([$datos->usuarioid]);
            $liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Construir respuesta completa
            $resultado = [
                'CodigoCliente' => $usuario['CodigoCliente'],
                'NombreUsuario' => $usuario['NombreUsuario'],
                'Agencia' => $usuario['Agencia'],
                'Puesto' => $usuario['Puesto'],
                'PrimeraCuenta' => $usuario['PrimeraCuenta'],
                'TotalLiquidaciones' => (float)$usuario['TotalLiquidaciones'],
                'CantidadLiquidaciones' => (int)$usuario['CantidadLiquidaciones'],
                'liquidaciones' => $liquidaciones
            ];

            return $this->res->ok('Detalle obtenido correctamente', $resultado);

        } catch (Exception $e) {
            error_log("Error en obtenerDetalleLiquidacionesUsuario: " . $e->getMessage());
            return $this->res->fail('Error al obtener detalle de liquidaciones', $e);
        }
    }

    /**
     * Asigna un comprobante contable a todas las liquidaciones aprobadas sin comprobante
     *
     * POST: combustible/asignarComprobanteMasivo
     *
     * @param object $datos {
     *   numero_comprobante: string,
     *   fecha_comprobante: string,
     *   observaciones?: string
     * }
     */
    public function asignarComprobanteMasivo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // Validaciones
            if (empty($datos->numero_comprobante)) {
                return $this->res->fail('El número de comprobante es requerido');
            }

            if (empty($datos->fecha_comprobante)) {
                return $this->res->fail('La fecha del comprobante es requerida');
            }

            // Validación
            if (empty($datos->mes_anio_comprobante)) {
                return $this->res->fail('El mes y año del comprobante son requeridos');
            }

            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $datos->mes_anio_comprobante)) {
                return $this->res->fail('El formato de mes/año es inválido (esperado: YYYY-MM)');
            }

// Convertir "YYYY-MM" → "YYYY-MM-01"
            $mesAnioDate = $datos->mes_anio_comprobante . '-01';

            // Validar que la fecha no sea futura
            $fechaComprobante = new \DateTime($datos->fecha_comprobante);
            $hoy = new \DateTime();
            $hoy->setTime(0, 0, 0);

            if ($fechaComprobante > $hoy) {
                return $this->res->fail('La fecha del comprobante no puede ser futura');
            }

            // Verificar que el número de comprobante no exista
            $sqlVerificar = "SELECT idComprobantesContables 
            FROM apoyo_combustibles.comprobantescontables 
            WHERE numero_comprobante = ?";

            $stmt = $this->connect->prepare($sqlVerificar);
            $stmt->execute([$datos->numero_comprobante]);

            if ($stmt->fetch()) {
                return $this->res->fail('El número de comprobante ya existe en el sistema');
            }

            // Obtener todas las liquidaciones aprobadas sin comprobante
            $sqlLiquidaciones = "SELECT 
            idLiquidaciones,
            monto
        FROM apoyo_combustibles.liquidaciones
        WHERE estado = 'aprobada'
            AND comprobantecontableid IS NULL";

            $stmt = $this->connect->prepare($sqlLiquidaciones);
            $stmt->execute();
            $liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($liquidaciones)) {
                return $this->res->fail('No hay liquidaciones aprobadas sin comprobante para asignar');
            }

            // Calcular monto total
            $montoTotal = array_sum(array_column($liquidaciones, 'monto'));

            $this->connect->beginTransaction();

            // Crear el comprobante contable
            $sqlComprobante = "INSERT INTO apoyo_combustibles.comprobantescontables (
            numero_comprobante,
            tipo,
            fecha_comprobante,
            mes_anio_comprobante,
            monto_total,
            observaciones,
            registrado_por
        ) VALUES (?, 'liquidacion', ?, ?, ?, ?, ?)";

            $stmt = $this->connect->prepare($sqlComprobante);
            $stmt->execute([
                $datos->numero_comprobante,
                $datos->fecha_comprobante,
                $mesAnioDate,
                $montoTotal,
                $datos->observaciones ?? null,
                $this->idUsuario
            ]);

            $idComprobante = $this->connect->lastInsertId();

            // Actualizar todas las liquidaciones con el comprobante
            $sqlActualizar = "UPDATE apoyo_combustibles.liquidaciones 
            SET comprobantecontableid = ?
            WHERE estado = 'aprobada'
                AND comprobantecontableid IS NULL";

            $stmt = $this->connect->prepare($sqlActualizar);
            $stmt->execute([$idComprobante]);

            $liquidacionesActualizadas = $stmt->rowCount();

            $this->connect->commit();

            // Registrar en log (opcional)
            error_log(sprintf(
                "Comprobante masivo asignado - ID: %d, Número: %s, Liquidaciones: %d, Monto: %.2f, Usuario: %s",
                $idComprobante,
                $datos->numero_comprobante,
                $liquidacionesActualizadas,
                $montoTotal,
                $this->idUsuario
            ));

            return $this->res->ok('Comprobante asignado correctamente', [
                'idComprobante' => $idComprobante,
                'numero_comprobante' => $datos->numero_comprobante,
                'liquidacionesActualizadas' => $liquidacionesActualizadas,
                'montoTotal' => $montoTotal
            ]);

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log("Error en asignarComprobanteMasivo: " . $e->getMessage());
            return $this->res->fail('Error al asignar el comprobante', $e);
        }
    }

// ============================================================================
// MÉTODOS AUXILIARES PRIVADOS
// ============================================================================

    /**
     * Obtiene liquidaciones aprobadas sin comprobante agrupadas por usuario
     *
     * @return array
     */
    private function _obtenerLiquidacionesSinComprobante()
    {
        $sql = "SELECT
            cli.CodigoCliente,
            ag.nombre AS Agencia,
            pu.nombre AS Puesto,
            (
                SELECT cap2.NumeroCuenta
                FROM asociado_t24.ccomndatoscaptaciones AS cap2
                WHERE cap2.Cliente = cli.CodigoCliente
                    AND cap2.Producto = '114A.AHORRO.DISPONIBLE'
                ORDER BY cap2.NumeroCuenta
                LIMIT 1
            ) AS PrimeraCuenta,    
            dtp.nombres AS NombreUsuario,
            us.idUsuarios AS usuarioid,
            SUM(l.monto) AS TotalLiquidaciones,
            COUNT(l.idLiquidaciones) AS CantidadLiquidaciones,
            GROUP_CONCAT(l.numero_factura ORDER BY l.numero_factura SEPARATOR ', ') AS FacturasIncluidas,
            pg.monto_anual AS PresupuestoAnual,
            pg.anio AS AnioPresupuesto
        FROM
            apoyo_combustibles.liquidaciones AS l
            LEFT JOIN dbintranet.usuarios AS us ON l.usuarioid = us.idUsuarios
            LEFT JOIN dbintranet.datospersonales AS dtp ON dtp.idDatosPersonales = us.idDatosPersonales
            LEFT JOIN asociado_t24.comndatosclientes AS cli ON dtp.dpi = cli.Dpi
            INNER JOIN dbintranet.agencia AS ag ON ag.idAgencia = us.idAgencia
            INNER JOIN dbintranet.puesto AS pu ON pu.idPuesto = us.idPuesto
            LEFT JOIN apoyo_combustibles.presupuestogeneral AS pg 
                ON pg.agenciaid = us.idAgencia 
                AND pg.puestoid = us.idPuesto
                AND pg.anio = YEAR(CURDATE())
                AND pg.activo = 1
        WHERE
            l.estado = 'aprobada'
            AND l.comprobantecontableid IS NULL
        GROUP BY
            cli.CodigoCliente,
            dtp.nombres,
            ag.nombre,
            pu.nombre,
            us.idUsuarios,
            pg.monto_anual,
            pg.monto_diario,
            pg.anio
        HAVING
            PrimeraCuenta IS NOT NULL
        ORDER BY
            ag.nombre,
            dtp.nombres";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convertir valores numéricos
        return array_map(function ($row) {
            return [
                'CodigoCliente' => $row['CodigoCliente'],
                'Agencia' => $row['Agencia'],
                'Puesto' => $row['Puesto'],
                'PrimeraCuenta' => $row['PrimeraCuenta'],
                'NombreUsuario' => $row['NombreUsuario'],
                'usuarioid' => $row['usuarioid'],
                'TotalLiquidaciones' => (float)$row['TotalLiquidaciones'],
                'CantidadLiquidaciones' => (int)$row['CantidadLiquidaciones'],
                'FacturasIncluidas' => $row['FacturasIncluidas'],
                'MontoAnual' => $row['PresupuestoAnual'],
                'AnioPresupuesto' => $row['AnioPresupuesto'],
            ];
        }, $resultados);
    }

    //! MANTENIMIENTO LIQUIDACIONES
    // ============================================================================
// ENDPOINTS - MANTENIMIENTO LIQUIDACIONES
// ============================================================================

    /**
     * Inicializa el helper de mantenimiento de liquidaciones.
     * Llamar al inicio de cada endpoint de mantenimiento.
     */
    private function _inicializarHelperMantenimiento(): void
    {

        if (!isset($this->mantHelper)) {
            $this->mantHelper = new MantenimientoLiquidacionesHelper(
                $this->connect
            );
        }
    }

    /**
     * Lista todos los períodos laborales de todos los usuarios en los últimos
     * 2 meses. Cada sub-período (prueba / post_prueba / normal / en_curso) es
     * una fila independiente en la tabla del frontend.
     *
     * GET: combustible/listarMantenimientoPorPeriodos
     */
    public function listarMantenimientoPorPeriodos()
    {
        try {
            $this->_inicializarHelperMantenimiento();
            $h = $this->mantHelper;

            // ── Rango de análisis ─────────────────────────────────────────────
            [$fechaDesde, $fechaHasta, $anio] = $h->rangoAnalisis();

            // ── 1. Todos los UCF que se cruzan con el rango ───────────────────
            $periodos = $h->obtenerTodosLosPeriodosEnRango($fechaDesde, $fechaHasta, $anio);

            // ── 2. Datos de usuario (una sola query con IN) ───────────────────
            $usuariosIds   = array_values(array_unique(array_column($periodos, 'usuarioid')));
            $datosUsuarios = $h->obtenerDatosMultiplesUsuarios($usuariosIds);

            // ── 3. Liquidaciones de todos los usuarios en el rango (una query) ─
            $liquidacionesAgrupadas = $h->obtenerLiquidacionesRangoGlobal(
                $usuariosIds, $fechaDesde, $fechaHasta
            );

            // ── 3.1 Filtrar solo usuarios con al menos una liquidación ────────
            $periodos = array_values(array_filter(
                $periodos,
                fn($p) => isset($liquidacionesAgrupadas[$p['usuarioid']])
            ));

            // Recalcular IDs tras el filtro
            $usuariosIds   = array_values(array_unique(array_column($periodos, 'usuarioid')));
            $datosUsuarios = array_intersect_key($datosUsuarios, array_flip($usuariosIds));

            // ── 4. Construir filas por sub-período ────────────────────────────
            $registros = [];

            foreach ($periodos as $periodo) {
                $uid     = $periodo['usuarioid'];
                $usuario = $datosUsuarios[$uid] ?? null;
                if (!$usuario) continue;

                $subPeriodos = $h->dividirEnSubPeriodos($periodo, $fechaDesde, $fechaHasta);

                foreach ($subPeriodos as $sub) {
                    $registros[] = $h->construirFilaPeriodo(
                        $periodo, $sub, $usuario,
                        $liquidacionesAgrupadas[$uid] ?? []
                    );
                }
            }

            // ── 5. Agregar períodos en curso ──────────────────────────────────
            $enCurso   = $h->calcularPeriodosEnCurso(
                $periodos, $datosUsuarios, $liquidacionesAgrupadas,
                $fechaDesde, $fechaHasta, $anio
            );
            $registros = array_merge($registros, $enCurso);

            // ── 6. Ordenar agrupando por usuario ──────────────────────────────
            $requiereAjustePorUsuario = [];
            foreach ($registros as $r) {
                $uid = $r['usuarioid'];
                if (!isset($requiereAjustePorUsuario[$uid])) {
                    $requiereAjustePorUsuario[$uid] = false;
                }
                if ($r['requiere_ajuste']) {
                    $requiereAjustePorUsuario[$uid] = true;
                }
            }

            usort($registros, function ($a, $b) use ($requiereAjustePorUsuario) {
                $ajusteA = $requiereAjustePorUsuario[$a['usuarioid']];
                $ajusteB = $requiereAjustePorUsuario[$b['usuarioid']];
                if ($ajusteA !== $ajusteB) return $ajusteB ? 1 : -1;

                $cmp = strcmp($a['agencia'], $b['agencia']);
                if ($cmp !== 0) return $cmp;

                $cmp = strcmp($a['NombreUsuario'], $b['NombreUsuario']);
                if ($cmp !== 0) return $cmp;

                return strcmp($a['fecha_inicio_rango'], $b['fecha_inicio_rango']);
            });

            return $this->res->ok('Períodos obtenidos correctamente', [
                'registros'      => $registros,
                'total'          => count($registros),
                'rango_analisis' => ['desde' => $fechaDesde, 'hasta' => $fechaHasta],
            ]);

        } catch (Exception $e) {
            error_log("Error en listarMantenimientoPorPeriodos: " . $e->getMessage());
            return $this->res->fail('Error al obtener períodos', $e);
        }
    }

    /**
     * Obtiene el detalle completo de un usuario: todos sus sub-períodos con sus
     * liquidaciones incluidas.
     *
     * POST: combustible/obtenerDetalleMantenimientoPorPeriodo
     *
     * @param object $datos { usuarioid: string }
     */
    public function obtenerDetalleMantenimientoPorPeriodo($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);
            $this->_inicializarHelperMantenimiento();
            $h = $this->mantHelper;

            if (empty($datos->usuarioid)) {
                return $this->res->fail('El ID de usuario es requerido');
            }

            // ── Rango de análisis ─────────────────────────────────────────────
            [$fechaDesde, $fechaHasta, $anio] = $h->rangoAnalisis();

            // ── Datos generales del usuario (encabezado del modal) ────────────
            $usuario = $h->obtenerDatosUsuario($datos->usuarioid);
            if (!$usuario) {
                return $this->res->fail('No se encontró información del usuario');
            }

            // ── UCF del usuario que se cruzan con el rango ────────────────────
            $periodos = $h->obtenerPeriodosConPresupuesto(
                $datos->usuarioid, $fechaDesde, $fechaHasta
            );

            // Sin registros UCF → período genérico
            if (empty($periodos)) {
                $periodos = $h->generarPeriodoGenerico($usuario, $fechaDesde, $fechaHasta);
            }

            // ── Liquidaciones del usuario en el rango completo ────────────────
            $todasLiquidaciones = $h->obtenerLiquidacionesEnRango(
                $datos->usuarioid, $fechaDesde, $fechaHasta
            );

            // ── Construir períodos detalle con división de sub-períodos ────────
            $periodosDetalle = [];

            foreach ($periodos as $periodo) {
                $subPeriodos = $h->dividirEnSubPeriodos($periodo, $fechaDesde, $fechaHasta);

                foreach ($subPeriodos as $sub) {
                    $fila = $h->construirFilaPeriodo(
                        $periodo, $sub, [], $todasLiquidaciones
                    );

                    // Agregar el detalle de liquidaciones al período
                    $fila['liquidaciones'] = $h->filtrarLiquidacionesPorRango(
                        $todasLiquidaciones, $sub['inicio'], $sub['fin']
                    );

                    // Quitar campos de usuario (van en el encabezado del modal)
                    unset($fila['usuarioid'], $fila['CodigoCliente'],
                        $fila['NombreUsuario'], $fila['PrimeraCuenta']);

                    $periodosDetalle[] = $fila;
                }
            }

            // ── Período en curso (si el último UCF ya venció) ─────────────────
            $periodoCurso = $h->calcularPeriodoEnCursoDetalle(
                $periodos, $todasLiquidaciones, $fechaDesde, $fechaHasta, $anio
            );
            if ($periodoCurso) {
                $periodosDetalle[] = $periodoCurso;
            }

            return $this->res->ok('Detalle por período obtenido correctamente', [
                'CodigoCliente'  => $usuario['CodigoCliente'],
                'NombreUsuario'  => $usuario['NombreUsuario'],
                'Agencia'        => $usuario['Agencia'],
                'Puesto'         => $usuario['Puesto'],
                'PrimeraCuenta'  => $usuario['PrimeraCuenta'],
                'rango_analisis' => ['desde' => $fechaDesde, 'hasta' => $fechaHasta],
                'periodos'       => $periodosDetalle,
                'total_periodos' => count($periodosDetalle),
            ]);

        } catch (Exception $e) {
            error_log("Error en obtenerDetalleMantenimientoPorPeriodo: " . $e->getMessage());
            return $this->res->fail('Error al obtener detalle por período', $e);
        }
    }

    /**
     * Actualiza monto y/o estado de una liquidación.
     * Solo permite si no tiene comprobante y no tiene aprobación gerencia.
     *
     * POST: combustible/actualizarLiquidacionMantenimiento
     *
     * @param object $datos {
     *   idLiquidaciones: int,
     *   monto?: float,
     *   estado?: 'aprobada'|'de_baja'|'rechazada',
     *   motivo_rechazo?: string
     * }
     */
    public function actualizarLiquidacionMantenimiento($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->idLiquidaciones)) {
                return $this->res->fail('El ID de liquidación es requerido');
            }

            // Verificar que la liquidación existe y es editable
            $sqlVerificar = "SELECT 
                idLiquidaciones, estado, monto,
                comprobantecontableid, aprobaciongerenciaid
            FROM apoyo_combustibles.liquidaciones
            WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sqlVerificar);
            $stmt->execute([$datos->idLiquidaciones]);
            $liquidacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liquidacion) {
                return $this->res->fail('Liquidación no encontrada');
            }

            if (!is_null($liquidacion['comprobantecontableid'])) {
                return $this->res->fail('No se puede editar: la liquidación ya tiene comprobante contable asignado');
            }

            if (!is_null($liquidacion['aprobaciongerenciaid'])) {
                return $this->res->fail('No se puede editar: la liquidación tiene una aprobación de gerencia asociada');
            }

            $estadosEditables = ['aprobada', 'de_baja'];
            if (!in_array($liquidacion['estado'], $estadosEditables)) {
                return $this->res->fail('Solo se pueden editar liquidaciones en estado aprobada o de_baja');
            }

            $estadosPermitidos = ['aprobada', 'de_baja', 'rechazada'];
            if (!empty($datos->estado) && !in_array($datos->estado, $estadosPermitidos)) {
                return $this->res->fail('Estado no permitido. Use: aprobada, de_baja o rechazada');
            }

            if (isset($datos->monto)) {
                if (!is_numeric($datos->monto) || (float) $datos->monto <= 0) {
                    return $this->res->fail('El monto debe ser un valor numérico mayor a 0');
                }
            }

            if (!empty($datos->estado) && $datos->estado === 'rechazada' && empty($datos->motivo_rechazo)) {
                return $this->res->fail('El motivo de rechazo es requerido al cambiar a estado rechazado');
            }

            // Construir UPDATE dinámico
            $campos  = [];
            $valores = [];

            if (isset($datos->monto)) {
                $campos[]  = 'monto = ?';
                $valores[] = (float) $datos->monto;
            }

            if (!empty($datos->estado)) {
                $campos[]  = 'estado = ?';
                $valores[] = $datos->estado;

                if ($datos->estado === 'rechazada') {
                    $campos[]  = 'motivo_rechazo = ?';
                    $valores[] = $datos->motivo_rechazo;
                    $campos[]  = 'revisado_por = ?';
                    $valores[] = $this->idUsuario;
                    $campos[]  = 'fecha_revision = NOW()';
                }
            }

            if (empty($campos)) {
                return $this->res->fail('No se proporcionaron campos para actualizar');
            }

            $valores[] = $datos->idLiquidaciones;

            $sql = "UPDATE apoyo_combustibles.liquidaciones 
                SET " . implode(', ', $campos) . ", updated_at = NOW()
                WHERE idLiquidaciones = ?";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($valores);

            return $this->res->ok('Liquidación actualizada correctamente', [
                'idLiquidaciones' => (int) $datos->idLiquidaciones,
                'estadoAnterior'  => $liquidacion['estado'],
                'montoAnterior'   => (float) $liquidacion['monto'],
            ]);

        } catch (Exception $e) {
            error_log("Error en actualizarLiquidacionMantenimiento: " . $e->getMessage());
            return $this->res->fail('Error al actualizar la liquidación', $e);
        }
    }

// ============================================================================
// ENDPOINTS - ENVÍO Y APROBACIÓN DE PAGO DE LIQUIDACIONES
// ============================================================================
// Agregar estas propiedades y métodos al controller existente
// ============================================================================

// ── Propiedad a agregar en la clase del controller ───────────────────────────
// private ?EnvioPagoLiquidacionesHelper $envioPagoHelper = null;

    /**
     * Inicializa el helper de envío y pago de liquidaciones.
     * Llamar al inicio de cada endpoint relacionado.
     */
    private function _inicializarHelperEnvioPago(): void
    {
        if (!isset($this->envioPagoHelper)) {
            $this->envioPagoHelper = new EnvioPagoLiquidacionesHelper(
                $this->connect
            );
        }
    }

    /**
     * Resuelve el año de consulta por defecto para la vista de liquidaciones.
     *
     * Regla de negocio:
     *   - Si hoy es 1-ene al 14-ene (antes del 15 de enero) → año ANTERIOR
     *   - Cualquier otra fecha → año EN CURSO
     *
     * Si el frontend envía un año explícito se respeta siempre.
     *
     * @param int|null $anioRecibido Año enviado por el frontend (puede ser null)
     * @return int
     */
    private function _resolverAnioLiquidaciones(?int $anioRecibido): int
    {
        if ($anioRecibido !== null) {
            return $anioRecibido;
        }

        $hoy  = new \DateTime();
        $mes  = (int) $hoy->format('n');
        $dia  = (int) $hoy->format('j');
        $anio = (int) $hoy->format('Y');

        // Antes del 15 de enero → mostrar el año anterior
        if ($mes === 1 && $dia < 15) {
            return $anio - 1;
        }

        return $anio;
    }

    // -------------------------------------------------------------------------

    /**
     * Lista todos los usuarios con sus liquidaciones organizadas por mes/
     * comprobante para la vista de envío y aprobación de pago.
     *
     * Retorna:
     *   - comprobantes: columnas dinámicas del año (id, número, mes)
     *   - registros   : una fila por usuario con montos por comprobante
     *
     * GET: combustible/listarEnvioPagoLiquidaciones?anio=2026
     */
    public function listarEnvioPagoLiquidaciones()
    {
        try {
            $this->_inicializarHelperEnvioPago();
            $h = $this->envioPagoHelper;

            // ── Año de consulta: regla antes-del-15-de-enero ─────────────────
            $anioGet = isset($_GET['anio']) && is_numeric($_GET['anio'])
                ? (int) $_GET['anio']
                : null;
            $anio = $this->_resolverAnioLiquidaciones($anioGet);

            // ── 1. Comprobantes del año → definen las columnas dinámicas ──────
            $comprobantes = $h->obtenerComprobantesDelAnio($anio);

            // ── 2. Usuarios con liquidaciones en el año ───────────────────────
            $datosUsuarios = $h->obtenerUsuariosConLiquidaciones($anio);

            if (empty($datosUsuarios)) {
                return $this->res->ok('No hay liquidaciones registradas para el año indicado', [
                    'anio'         => $anio,
                    'comprobantes' => $comprobantes,
                    'registros'    => [],
                    'total'        => 0,
                ]);
            }

            $usuariosIds = array_keys($datosUsuarios);

            // ── 3. Periodos UCF de todos los usuarios ─────────────────────────
            $periodosPorUsuario = $h->obtenerPeriodosResumenPorUsuarios($usuariosIds, $anio);

            // ── 4. Liquidaciones de todos los usuarios en el año ──────────────
            $liquidacionesPorUsuario = $h->obtenerLiquidacionesAgrupadas($usuariosIds, $anio);

            // ── 5. Construir una fila por usuario ─────────────────────────────
            $registros = [];

            foreach ($datosUsuarios as $uid => $datosUsuario) {
                $registros[] = $h->construirFilaUsuario(
                    $uid,
                    $datosUsuario,
                    $periodosPorUsuario[$uid]      ?? [],
                    $liquidacionesPorUsuario[$uid] ?? [],
                    $comprobantes,
                    $anio
                );
            }

            // ── 6. Ordenar: sobregiro primero, luego agencia y nombre ─────────
            usort($registros, function ($a, $b) {
                if ($a['tiene_sobregiro'] !== $b['tiene_sobregiro']) {
                    return $a['tiene_sobregiro'] ? -1 : 1;
                }
                $cmp = strcmp($a['agencia'], $b['agencia']);
                if ($cmp !== 0) return $cmp;
                return strcmp($a['NombreUsuario'], $b['NombreUsuario']);
            });

            return $this->res->ok('Registros de envío y pago obtenidos correctamente', [
                'anio'         => $anio,
                'comprobantes' => $comprobantes,
                'registros'    => $registros,
                'total'        => count($registros),
            ]);

        } catch (\Exception $e) {
            error_log("Error en listarEnvioPagoLiquidaciones: " . $e->getMessage());
            return $this->res->fail('Error al obtener registros de pago', $e);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Obtiene el detalle completo de un usuario para el modal:
     * sus periodos UCF con las liquidaciones de cada uno, agrupadas
     * por comprobante/mes.
     *
     * POST: combustible/obtenerDetalleEnvioPago
     *
     * @param object $datos {
     *   usuarioid : string  (requerido)
     *   anio      : int     (opcional, default año actual)
     * }
     */
    public function obtenerDetalleEnvioPago($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);
            $this->_inicializarHelperEnvioPago();
            $h = $this->envioPagoHelper;

            if (empty($datos->usuarioid)) {
                return $this->res->fail('El ID de usuario es requerido');
            }

            // ── Año de consulta: regla antes-del-15-de-enero ─────────────────
            $anioPost = isset($datos->anio) && is_numeric($datos->anio)
                ? (int) $datos->anio
                : null;
            $anio = $this->_resolverAnioLiquidaciones($anioPost);

            // ── Datos generales del usuario ───────────────────────────────────
            $datosUsuarios = $h->obtenerUsuariosConLiquidaciones($anio);
            $datosUsuario  = $datosUsuarios[$datos->usuarioid] ?? null;

            if (!$datosUsuario) {
                return $this->res->fail(
                    'No se encontró información del usuario o no tiene liquidaciones en el año indicado'
                );
            }

            // ── Comprobantes del año (columnas del modal) ─────────────────────
            $comprobantes = $h->obtenerComprobantesDelAnio($anio);

            // ── Periodos UCF del usuario ──────────────────────────────────────
            $periodosPorUsuario = $h->obtenerPeriodosResumenPorUsuarios(
                [$datos->usuarioid], $anio
            );
            $periodosUsuario = $periodosPorUsuario[$datos->usuarioid] ?? [];

            // ── Todas las liquidaciones del usuario en el año ─────────────────
            $liquidaciones = $h->obtenerLiquidacionesPorUsuario($datos->usuarioid, $anio);

            // ── Construir el detalle por sub-periodo ──────────────────────────
            $periodosDetalle = $h->construirDetalleUsuario(
                $periodosUsuario,
                $liquidaciones,
                $comprobantes,
                $anio
            );

            // ── Totales globales del usuario ──────────────────────────────────
            $totalEjecutado  = array_sum(array_column($periodosDetalle, 'subtotal_liquidado'));
            $totalPresupuesto = array_sum(array_column($periodosDetalle, 'presupuesto_asignado'));

            return $this->res->ok('Detalle de envío y pago obtenido correctamente', [
                // Encabezado del modal
                'usuarioid'      => $datos->usuarioid,
                'CodigoCliente'  => $datosUsuario['CodigoCliente'],
                'NombreUsuario'  => $datosUsuario['NombreUsuario'],
                'PrimeraCuenta'  => $datosUsuario['PrimeraCuenta'],
                'agencia'        => $datosUsuario['agencia'],
                'puesto_actual'  => $datosUsuario['puesto_actual'],
                'estado_usuario' => $datosUsuario['estado_usuario'],

                // Columnas dinámicas disponibles
                'comprobantes'   => $comprobantes,
                'anio'           => $anio,

                // Periodos con sus liquidaciones
                'periodos'       => $periodosDetalle,
                'total_periodos' => count($periodosDetalle),

                // Resumen financiero global
                'resumen' => [
                    'presupuesto_asignado'  => round($totalPresupuesto, 2),
                    'presupuesto_ejecutado' => round($totalEjecutado, 2),
                    'sobregiro'             => round(max(0, $totalEjecutado - $totalPresupuesto), 2),
                    'tiene_sobregiro'       => $totalEjecutado > $totalPresupuesto,
                ],
            ]);

        } catch (\Exception $e) {
            error_log("Error en obtenerDetalleEnvioPago: " . $e->getMessage());
            return $this->res->fail('Error al obtener detalle de pago', $e);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Obtiene los años disponibles con comprobantes de tipo 'liquidacion'.
     * Útil para el selector de año en el frontend.
     *
     * GET: combustible/obtenerAniosDisponiblesLiquidaciones
     */
    public function obtenerAniosDisponiblesLiquidaciones()
    {
        try {
            $sql = "SELECT DISTINCT
                        YEAR(mes_anio_comprobante) AS anio
                    FROM apoyo_combustibles.comprobantescontables
                    WHERE tipo = 'liquidacion'
                    ORDER BY anio DESC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute();
            $anios = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'anio');

            // Si no hay ninguno, devolver al menos el año actual
            if (empty($anios)) {
                $anios = [(int) date('Y')];
            }

            return $this->res->ok('Años disponibles obtenidos', [
                'anios'         => array_map('intval', $anios),
                'anio_default'  => $this->_resolverAnioLiquidaciones(null),
            ]);

        } catch (\Exception $e) {
            error_log("Error en obtenerAniosDisponiblesLiquidaciones: " . $e->getMessage());
            return $this->res->fail('Error al obtener años disponibles', $e);
        }
    }


}