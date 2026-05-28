<?php

namespace App\inventarioApi;

use App\Core\ApiResponder;
use App\Core\MailerService;
use App\Core\DriveService;
use ConexionBD;
use Exception;
use PDO;

/**
 * ============================================================================
 * PLANTILLA DEFINITIVA PARA MÓDULOS
 * ============================================================================
 *
 * ✅ Incluye:
 * - ApiResponder (ok, fail, info)
 * - MailerService::enviar()
 * - DriveService::subirArchivo()
 * - Subida desde base64 y desde $_FILES
 * - Construcción de carpetas en Drive
 * - Helpers externos con lazy loading
 * - Transacciones completas
 * - Validación de permisos por puesto
 * - Arrays de métodos GET/POST permitidos
 * - Limpieza de datos y manejo de excepciones
 *
 * @author Tu Nombre
 * @version 3.0 - Totalmente compatible
 */
final class inventarioApiClass extends ConexionBD
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

    // Helpers externos (opcionales - inicialización perezosa)
    private ?TuHelper $miHelper = null;

    /** Métodos GET permitidos */
    private array $metodosGet = [
        'listarItems',
        'obtenerItem',
        'descargarPlantilla',
        'obtenerMiArea',
        // Agrega aquí tus GETs
        //? Tipos de producto
        'listarTipos',
        'obtenerTipo',
        //? Categorias de productos
        'listarCategorias',
        'obtenerCategoria',
        //? Unidades de medida
        'listarUnidades',
        'obtenerUnidad',
        //? Productos
        'listarProductos',
        'obtenerProducto',
        //? Bodegas
        'listarBodegas',
        'obtenerBodega',
        'listarAgenciasBodega',
        'listarDepartamentosBodega',
        //? Encargados Bodega
        'listarEncargados',
        'buscarUsuarios',
        //? Mi bodega
        'obtenerMiBodega',
        //? MAtris area
        'obtenerMiMatriz',
        'obtenerProductosCelda',
        //?Matris producto
        'obtenerMatrizProductos',
        //? Modulo de solicitudes
        'obtenerBodegaAgencia',
        'listarBodegasArea',
        'listarProductosDisponibles',
        'listarMisSolicitudes',
        'obtenerUnidadesProducto',

        'obtenerBodegaEncargado',
        'listarSolicitudesEncargado',
        'obtenerDetalleSolicitud',
    ];

    /** Métodos POST permitidos */
    private array $metodosPost = [
        'crearItem',
        'editarItem',
        'eliminarItem',
        'subirArchivo',
        'enviarCorreoPrueba',
        // Agrega aquí tus POSTs
        //? Tipos de producto
        'editarTipo',
        //? Categorias de productos
        'crearCategoria',
        'editarCategoria',
        'toggleCategoria',
        //? Unidades de medida
        'crearUnidad',
        'editarUnidad',
        'toggleUnidad',
        //? Productos
        'crearProducto',
        'editarProducto',
        'toggleProducto',
        //? Bodegas
        'crearBodega',
        'editarBodega',
        'toggleBodega',
        'toggleRestriccionAcceso',
        //? Encargados Bodega
        'asignarEncargado',
        'toggleEncargado',
        //? Mi bodega
        'toggleMiRestriccion',
        //? Matriz area
        'toggleCelda',
        'toggleFilaPuesto',
        'toggleColumnaAgencia',
        'limpiarMatriz',
        'agregarProductoCelda',
        'agregarCategoriaCelda',
        'eliminarProductoCelda',
        'asignarProductoACeldas',
        //?Matris producto
        'sincronizarProductoAgencia',
        'toggleFilaProducto',
        'toggleColumnaAgencia',
        'asignarProductoMultiplesAgencias',
        //? Modulo de solicitudes
        'crearSolicitud',
        'cancelarSolicitud',

        'entregarSolicitud',
        'rechazarSolicitud',

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

        // Define puestos con acceso (ajústalo)
        $this->puestosValidos = [1, 2, 3];

        $this->res = new ApiResponder();
        $this->mailer = new MailerService();
        $this->drive = new DriveService();
    }

    // ========================================================================
    // ENRUTAMIENTO / PERMISOS
    // ========================================================================

    public function esMetodoGet(string $m): bool
    {
        return in_array($m, $this->metodosGet, true);
    }

    public function esMetodoPost(string $m): bool
    {
        return in_array($m, $this->metodosPost, true);
    }

    /**
     * Valida permisos según el puesto del usuario
     */
    protected function validarPermisos(string $operacion): bool
    {
        if (!empty($this->puestosValidos)) {
            return in_array($this->puesto, $this->puestosValidos, true);
        }
        return true;
    }

    // ========================================================================
    // 🛠️ HELPERS GENÉRICOS
    // ========================================================================

    /**
     * Limpia strings de un objeto o array (igual que tu original)
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
     * Inicializa helper externo bajo demanda (lazy loading)
     * Igual que _inicializarHelpersLiquidaciones() en tu original
     */
    private function _inicializarHelper(): void
    {
        if ($this->miHelper === null) {
            $this->miHelper = new TuHelper($this->connect, $this->idUsuario);
        }
    }

    /**
     * GET: inventario/obtenerMiArea
     * Prueba de conexión y obtención del área del usuario autenticado.
     */
    public function obtenerMiArea()
    {
        try {
            // El área ya se calculó en el constructor mediante $this->area = $this->obtenerArea($this->puesto)
            // Si por algún motivo no se calculó, lo forzamos aquí
            if ($this->area === null || $this->area === 0) {
                $this->area = $this->obtenerArea($this->puesto);
            }

            return $this->res->ok('Área obtenida correctamente', [
                'idUsuario' => $this->idUsuario,
                'idAgencia' => $this->idAgencia,
                'puesto' => $this->puesto,
                'area' => $this->area,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error en obtenerMiArea: " . $e->getMessage());
            return $this->res->fail('Error al obtener el área', $e);
        }
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

    /**
     * POST: modulo/subirArchivo (desde $_FILES, igual que en tu original)
     * Usa DriveService directamente
     */
    public function subirArchivo($datos)
    {
        try {
            if (!$this->validarPermisos('subir')) {
                return $this->res->fail('Sin permiso');
            }

            // El método subirArchivo de DriveService ya detecta $_FILES automáticamente
            $resultado = $this->drive->subirArchivo(
                'mi_modulo',                       // carpeta base
                ['documentos', date('Y'), date('m')], // subcarpetas
                null,                              // nombre personalizado (se genera automático)
                ['application/pdf', 'image/jpeg', 'image/png'], // tipos permitidos
                10 * 1024 * 1024                   // 10 MB
            );

            if (!$resultado['exito']) {
                return $this->res->fail($resultado['mensaje']);
            }

            // Guardar en tu tabla si es necesario
            $sql = "INSERT INTO archivos_subidos (drive_id, nombre_original, nombre_drive, tipo_mime, tamano, subido_por)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->connect->prepare($sql);
            $stmt->execute([
                $resultado['drive_id'],
                $resultado['metadata']['nombre_original'],
                $resultado['nombre_archivo'],
                $resultado['metadata']['tipo_mime'],
                $resultado['metadata']['tamaño_bytes'],
                $this->idUsuario
            ]);

            return $this->res->ok('Archivo subido correctamente', $resultado);

        } catch (Exception $e) {
            error_log("Error en subirArchivo: " . $e->getMessage());
            return $this->res->fail('Error al subir archivo', $e);
        }
    }

    /**
     * POST: modulo/enviarCorreoPrueba
     * Usa MailerService::enviar() igual que en tu original
     */
    public function enviarCorreoPrueba($datos)
    {
        try {
            $datos = $this->limpiarDatos($datos);

            if (empty($datos->destinatario)) {
                return $this->res->fail('El destinatario es requerido');
            }

            $asunto = $datos->asunto ?? 'Prueba de correo desde módulo';
            $cuerpo = "<h1>Correo de prueba</h1><p>{$datos->mensaje}</p>";

            // MailerService::enviar() devuelve array con 'respuesta' y 'mensaje'
            $resultado = $this->mailer->enviar($datos->destinatario, $asunto, $cuerpo);

            if ($resultado['respuesta'] === 'success') {
                return $this->res->ok('Correo enviado correctamente');
            } else {
                return $this->res->fail($resultado['mensaje']);
            }

        } catch (Exception $e) {
            error_log("Error en enviarCorreoPrueba: " . $e->getMessage());
            return $this->res->fail('Error al enviar correo', $e);
        }
    }

    /**
     * Ejemplo de uso de ApiResponder::info()
     * Similar a cuando en tu original haces:
     * return $this->res->info('Esta factura ya fue liquidada anteriormente');
     */
    public function ejemploInfo($datos)
    {
        // Caso: validación no crítica, solo informativa
        if (alguna_condicion()) {
            return $this->res->info('El recurso ya existe, pero puede continuar');
        }
        return $this->res->ok('Todo bien');
    }

    // ========================================================================
    // 🔧 MÉTODOS PRIVADOS DE SOPORTE
    // ========================================================================

    /**
     * Sube un archivo a Google Drive a partir de base64
     * (similar a la lógica que usas en crearSolicitudAutorizacion)
     *
     * @param string $base64 Contenido del archivo en base64
     * @param string $nombre Nombre original del archivo
     * @param string $carpetaBase Carpeta base en Drive
     * @param array $subcarpetas Subcarpetas
     * @return string|null ID del archivo en Drive, o null si falla
     */
    private function subirArchivoDesdeBase64(string $base64, string $nombre, string $carpetaBase, array $subcarpetas = []): ?string
    {
        try {
            // Decodificar base64
            $contenido = base64_decode($base64);
            if ($contenido === false) {
                return null;
            }

            // Guardar temporalmente
            $temp = tempnam(sys_get_temp_dir(), 'upload_');
            file_put_contents($temp, $contenido);

            // Crear un objeto similar a $_FILES para DriveService
            // DriveService espera $_FILES, así que simulamos
            $_FILES['temp_file'] = [
                'name' => $nombre,
                'tmp_name' => $temp,
                'type' => mime_content_type($temp),
                'size' => filesize($temp),
                'error' => UPLOAD_ERR_OK
            ];

            // Usar DriveService para subir
            $resultado = $this->drive->subirArchivo(
                $carpetaBase,
                $subcarpetas,
                null, // nombre automático
                null, // todos los tipos permitidos
                10 * 1024 * 1024
            );

            unlink($temp);
            unset($_FILES['temp_file']);

            return $resultado['exito'] ? $resultado['drive_id'] : null;

        } catch (Exception $e) {
            error_log("Error subiendo archivo desde base64: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Envía una notificación por correo (ejemplo)
     * Similar a como lo haces en aprobarSolicitudAutorizacion
     */
    private function enviarNotificacionCreacion(string $nombreItem, int $itemId): void
    {
        try {
            $adminEmail = 'admin@example.com'; // Obtener de configuración o session
            $asunto = "Nuevo ítem creado: {$nombreItem}";
            $cuerpo = "
                <h2>Notificación de creación</h2>
                <p>El usuario <strong>{$this->idUsuario}</strong> ha creado el ítem <strong>{$nombreItem}</strong>.</p>
                <p>ID: {$itemId}</p>
                <p><em>Sistema de gestión</em></p>
            ";

            $resultado = $this->mailer->enviar($adminEmail, $asunto, $cuerpo);
            if ($resultado['respuesta'] !== 'success') {
                error_log("Error al enviar notificación: " . $resultado['mensaje']);
            }
        } catch (Exception $e) {
            error_log("Error en enviarNotificacionCreacion: " . $e->getMessage());
        }
    }




// ============================================================================
// ?CATEGORÍAS DE PRODUCTO — Funciones para agregar a la clase existente
// ============================================================================
// Reglas de negocio:
//  - Crear: nombre (único) + descripción
//  - Editar: nombre y descripción
//  - Toggle activo/inactivo (no eliminar — FK con productos)
//  - Al desactivar con productos activos → operación se completa
//    pero retorna info() con advertencia (no bloquea)
// ============================================================================


// ── GET: listarCategorias ─────────────────────────────────────────────────────

    /**
     * Devuelve todas las categorías con filtros opcionales.
     * El frontend realiza el filtrado en cliente, pero el endpoint
     * soporta filtros para consumo directo de API.
     *
     * GET bodega/catalogo/listarCategorias
     * Params opcionales: ?busqueda=texto  &activo=0|1
     */
    public function listarCategorias(): array
    {
        try {
            $busqueda = trim(filter_input(INPUT_GET, 'busqueda', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $activoParam = filter_input(INPUT_GET, 'activo', FILTER_VALIDATE_INT);

            $condiciones = [];
            $params = [];

            if ($busqueda !== '') {
                $condiciones[] = "nombre LIKE ?";
                $params[] = "%{$busqueda}%";
            }

            if ($activoParam !== null && $activoParam !== false) {
                $condiciones[] = "activo = ?";
                $params[] = (int)$activoParam;
            }

            $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

            $sql = "SELECT id, nombre, descripcion, activo
            FROM bodega_inventario.categorias_producto
            {$where}
            ORDER BY nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);

            // CAMBIO AQUÍ: Cambiamos FETCH_ASSOC por FETCH_OBJ
            $categorias = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (empty($categorias)) {
                return $this->res->info('No se encontraron categorías');
            }

            return $this->res->ok('Categorías obtenidas', [
                'categorias' => $categorias, // Ahora cada categoría será un objeto
                'total' => count($categorias),
            ]);
        } catch (Exception $e) {
            error_log("[Categorias] listarCategorias: " . $e->getMessage());
            return $this->res->fail('Error al obtener las categorías', $e);
        }
    }


// ── GET: obtenerCategoria?id=X ────────────────────────────────────────────────

    /**
     * Obtiene una categoría por su ID.
     *
     * GET bodega/catalogo/obtenerCategoria?id=1
     */
    public function obtenerCategoria(): array
    {
        try {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                return $this->res->fail('El parámetro id es requerido y debe ser un entero positivo');
            }

            $stmt = $this->connect->prepare(
                "SELECT id, nombre, descripcion, activo
         FROM bodega_inventario.categorias_producto
         WHERE id = ?"
            );
            $stmt->execute([$id]);

            // CAMBIO AQUÍ: Usamos FETCH_OBJ en lugar de FETCH_ASSOC
            // Ahora $categoria será un objeto stdClass con las propiedades id, nombre, descripcion y activo
            $categoria = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$categoria) {
                return $this->res->fail("La categoría con id={$id} no existe");
            }

            // Se retorna el objeto directamente dentro de la respuesta estructurada
            return $this->res->ok('Categoría obtenida', $categoria);
        } catch (Exception $e) {
            error_log("[Categorias] obtenerCategoria: " . $e->getMessage());
            return $this->res->fail('Error al obtener la categoría', $e);
        }
    }


// ── POST: crearCategoria ──────────────────────────────────────────────────────

    /**
     * Crea una nueva categoría.
     *
     * POST bodega/catalogo/crearCategoria
     * Body: { "nombre": "Papelería", "descripcion": "..." }
     *
     * @param object|array $datos
     */
    public function crearCategoria($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // ── Validar nombre ────────────────────────────────────────────────────
            $nombre = trim($datos->nombre ?? '');
            if (empty($nombre)) {
                return $this->res->fail('El nombre de la categoría es requerido');
            }
            if (mb_strlen($nombre) > 100) {
                return $this->res->fail('El nombre no puede superar 100 caracteres');
            }

            // ── Validar descripción ───────────────────────────────────────────────
            $descripcion = (isset($datos->descripcion) && trim($datos->descripcion) !== '')
                ? trim($datos->descripcion) : null;
            if ($descripcion !== null && mb_strlen($descripcion) > 255) {
                return $this->res->fail('La descripción no puede superar 255 caracteres');
            }

            // ── Verificar unicidad del nombre ─────────────────────────────────────
            $stmtDup = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.categorias_producto WHERE nombre = ?"
            );
            $stmtDup->execute([$nombre]);

            // fetchColumn() extrae directamente el valor escalar, óptimo para esta validación
            if ($stmtDup->fetchColumn()) {
                return $this->res->fail("Ya existe una categoría con el nombre \"{$nombre}\"");
            }

            // ── Insertar ──────────────────────────────────────────────────────────
            $stmt = $this->connect->prepare(
                "INSERT INTO bodega_inventario.categorias_producto (nombre, descripcion)
         VALUES (?, ?)"
            );
            $stmt->execute([$nombre, $descripcion]);
            $nuevoId = (int)$this->connect->lastInsertId();

            return $this->res->ok('Categoría creada correctamente', null, ['id' => $nuevoId]);
        } catch (Exception $e) {
            error_log("[Categorias] crearCategoria: " . $e->getMessage());
            return $this->res->fail('Error al crear la categoría', $e);
        }
    }


// ── POST: editarCategoria ─────────────────────────────────────────────────────

    /**
     * Edita nombre y/o descripción de una categoría existente.
     *
     * POST bodega/catalogo/editarCategoria
     * Body: { "id": 1, "nombre": "Papelería", "descripcion": "..." }
     *
     * @param object|array $datos
     */
    public function editarCategoria($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // ── Validar id ────────────────────────────────────────────────────────
            $id = (int)($datos->id ?? 0);
            if ($id < 1) {
                return $this->res->fail('El campo id es requerido');
            }

            // ── Validar nombre ────────────────────────────────────────────────────
            $nombre = trim($datos->nombre ?? '');
            if (empty($nombre)) {
                return $this->res->fail('El nombre de la categoría es requerido');
            }
            if (mb_strlen($nombre) > 100) {
                return $this->res->fail('El nombre no puede superar 100 caracteres');
            }

            // ── Validar descripción ───────────────────────────────────────────────
            $descripcion = (isset($datos->descripcion) && trim($datos->descripcion) !== '')
                ? trim($datos->descripcion) : null;
            if ($descripcion !== null && mb_strlen($descripcion) > 255) {
                return $this->res->fail('La descripción no puede superar 255 caracteres');
            }

            // ── Verificar existencia ──────────────────────────────────────────────
            $stmtExiste = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.categorias_producto WHERE id = ?"
            );
            $stmtExiste->execute([$id]);

            // fetchColumn() extrae directamente el valor escalar del ID
            if (!$stmtExiste->fetchColumn()) {
                return $this->res->fail("La categoría con id={$id} no existe");
            }

            // ── Verificar unicidad del nombre (excluye el propio registro) ─────────
            $stmtDup = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.categorias_producto WHERE nombre = ? AND id != ?"
            );
            $stmtDup->execute([$nombre, $id]);

            if ($stmtDup->fetchColumn()) {
                return $this->res->fail("Ya existe otra categoría con el nombre \"{$nombre}\"");
            }

            // ── Actualizar ────────────────────────────────────────────────────────
            $stmt = $this->connect->prepare(
                "UPDATE bodega_inventario.categorias_producto
         SET nombre = ?, descripcion = ?
         WHERE id = ?"
            );
            $stmt->execute([$nombre, $descripcion, $id]);

            return $this->res->ok('Categoría actualizada correctamente');
        } catch (Exception $e) {
            error_log("[Categorias] editarCategoria: " . $e->getMessage());
            return $this->res->fail('Error al actualizar la categoría', $e);
        }
    }


// ── POST: toggleCategoria ─────────────────────────────────────────────────────

    /**
     * Activa o desactiva una categoría.
     *
     * Al DESACTIVAR con productos activos:
     *   - La operación SE COMPLETA (categoría queda inactiva)
     *   - Retorna info() con advertencia (no bloquea)
     *   - Los productos existentes conservan su estado
     *
     * POST bodega/catalogo/toggleCategoria
     * Body: { "id": 1, "activo": 0 }   ← 0 desactiva, 1 activa
     *
     * @param object|array $datos
     */
    public function toggleCategoria($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);
            $id = (int)($datos->id ?? 0);
            $activo = isset($datos->activo) ? (int)$datos->activo : -1;

            if ($id < 1) {
                return $this->res->fail('El campo id es requerido');
            }
            if (!in_array($activo, [0, 1], true)) {
                return $this->res->fail('El campo activo debe ser 0 (desactivar) o 1 (activar)');
            }

            // ── Verificar existencia y estado actual ──────────────────────────────
            $stmtExiste = $this->connect->prepare(
                "SELECT id, nombre, activo
         FROM bodega_inventario.categorias_producto
         WHERE id = ?"
            );
            $stmtExiste->execute([$id]);

            // CAMBIO 1: Cambiamos FETCH_ASSOC por FETCH_OBJ
            $categoria = $stmtExiste->fetch(PDO::FETCH_OBJ);

            if (!$categoria) {
                return $this->res->fail("La categoría con id={$id} no existe");
            }

            // CAMBIO 2: Cambiamos $categoria['activo'] por $categoria->activo
            if ((int)$categoria->activo === $activo) {
                $estado = $activo === 1 ? 'activa' : 'inactiva';
                return $this->res->info("La categoría ya se encuentra {$estado}");
            }

            // ── Actualizar estado ─────────────────────────────────────────────────
            $stmt = $this->connect->prepare(
                "UPDATE bodega_inventario.categorias_producto SET activo = ? WHERE id = ?"
            );
            $stmt->execute([$activo, $id]);

            // ── Si se desactiva: advertir si tiene productos activos ───────────────
            if ($activo === 0) {
                $stmtProds = $this->connect->prepare(
                    "SELECT COUNT(*) FROM bodega_inventario.productos
             WHERE id_categoria = ? AND activo = 1"
                );
                $stmtProds->execute([$id]);

                // fetchColumn() extrae el conteo directamente como un valor escalar, se queda igual
                $totalActivos = (int)$stmtProds->fetchColumn();

                if ($totalActivos > 0) {
                    // Retorna info(): operación completada pero con advertencia
                    return $this->res->info(
                        "Categoría desactivada. {$totalActivos} producto(s) activo(s) en esta categoría conservan su estado.",
                        null,
                        ['productos_activos' => $totalActivos]
                    );
                }

                return $this->res->ok('Categoría desactivada correctamente');
            }

            return $this->res->ok('Categoría activada correctamente');
        } catch (Exception $e) {
            error_log("[Categorias] toggleCategoria: " . $e->getMessage());
            return $this->res->fail('Error al cambiar el estado de la categoría', $e);
        }
    }

    // ============================================================================
//? UNIDADES DE MEDIDA — Funciones para agregar a la clase existente
// ============================================================================
// Reglas de negocio:
//  - Crear: nombre + abreviatura (única)
//  - Editar: nombre y abreviatura
//  - Toggle activo/inactivo (no eliminar — FK con stock, lotes y movimientos)
//  - Desactivar con productos activos que usan esta unidad → BLOQUEAR (fail)
//    Diferencia con categorías: aquí no se completa la operación
// ============================================================================


// ── GET: listarUnidades ───────────────────────────────────────────────────────

    /**
     * Devuelve todas las unidades de medida.
     * Soporta filtros opcionales para consumo directo de API.
     *
     * GET bodega/catalogo/listarUnidades
     * Params opcionales: ?busqueda=texto  &activo=0|1
     */
    public function listarUnidades(): array
    {
        try {
            $busqueda = trim(filter_input(INPUT_GET, 'busqueda', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $activoParam = filter_input(INPUT_GET, 'activo', FILTER_VALIDATE_INT);

            $condiciones = [];
            $params = [];

            if ($busqueda !== '') {
                $condiciones[] = "(nombre LIKE ? OR abreviatura LIKE ?)";
                $params[] = "%{$busqueda}%";
                $params[] = "%{$busqueda}%";
            }

            if ($activoParam !== null && $activoParam !== false) {
                $condiciones[] = "activo = ?";
                $params[] = (int)$activoParam;
            }

            $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

            $sql = "SELECT id, nombre, abreviatura, activo
            FROM bodega_inventario.unidades_medida
            {$where}
            ORDER BY nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);

            // CAMBIO AQUÍ: Cambiamos FETCH_ASSOC por FETCH_OBJ
            // Ahora $unidades contendrá una lista de objetos stdClass
            $unidades = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (empty($unidades)) {
                return $this->res->info('No se encontraron unidades de medida');
            }

            return $this->res->ok('Unidades de medida obtenidas', [
                'unidades' => $unidades,
                'total' => count($unidades),
            ]);
        } catch (Exception $e) {
            error_log("[Unidades] listarUnidades: " . $e->getMessage());
            return $this->res->fail('Error al obtener las unidades de medida', $e);
        }
    }


// ── GET: obtenerUnidad?id=X ───────────────────────────────────────────────────

    /**
     * Obtiene una unidad de medida por su ID.
     *
     * GET bodega/catalogo/obtenerUnidad?id=1
     */
    public function obtenerUnidad(): array
    {
        try {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                return $this->res->fail('El parámetro id es requerido y debe ser un entero positivo');
            }

            $stmt = $this->connect->prepare(
                "SELECT id, nombre, abreviatura, activo
         FROM bodega_inventario.unidades_medida
         WHERE id = ?"
            );
            $stmt->execute([$id]);

            // CAMBIO AQUÍ: Usamos FETCH_OBJ en lugar de FETCH_ASSOC
            // Ahora $unidad será un objeto stdClass con las propiedades id, nombre, abreviatura y activo
            $unidad = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$unidad) {
                return $this->res->fail("La unidad de medida con id={$id} no existe");
            }

            // Retornamos el objeto directamente dentro del método ok()
            return $this->res->ok('Unidad de medida obtenida', $unidad);
        } catch (Exception $e) {
            error_log("[Unidades] obtenerUnidad: " . $e->getMessage());
            return $this->res->fail('Error al obtener la unidad de medida', $e);
        }
    }


// ── POST: crearUnidad ─────────────────────────────────────────────────────────

    /**
     * Crea una nueva unidad de medida.
     *
     * POST bodega/catalogo/crearUnidad
     * Body: { "nombre": "Libra", "abreviatura": "Lb" }
     *
     * @param object|array $datos
     */
    public function crearUnidad($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // ── Validar nombre ────────────────────────────────────────────────────
            $nombre = trim($datos->nombre ?? '');
            if (empty($nombre)) {
                return $this->res->fail('El nombre de la unidad es requerido');
            }
            if (mb_strlen($nombre) > 60) {
                return $this->res->fail('El nombre no puede superar 60 caracteres');
            }

            // ── Validar abreviatura ───────────────────────────────────────────────
            $abreviatura = trim($datos->abreviatura ?? '');
            if (empty($abreviatura)) {
                return $this->res->fail('La abreviatura de la unidad es requerida');
            }
            if (mb_strlen($abreviatura) > 20) {
                return $this->res->fail('La abreviatura no puede superar 20 caracteres');
            }

            // ── Verificar unicidad de abreviatura ─────────────────────────────────
            $stmtDupAbrev = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.unidades_medida WHERE abreviatura = ?"
            );
            $stmtDupAbrev->execute([$abreviatura]);
            if ($stmtDupAbrev->fetchColumn()) {
                return $this->res->fail("Ya existe una unidad con la abreviatura \"{$abreviatura}\"");
            }

            // ── Verificar unicidad de nombre ──────────────────────────────────────
            $stmtDupNombre = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.unidades_medida WHERE nombre = ?"
            );
            $stmtDupNombre->execute([$nombre]);
            if ($stmtDupNombre->fetchColumn()) {
                return $this->res->fail("Ya existe una unidad con el nombre \"{$nombre}\"");
            }

            // ── Insertar ──────────────────────────────────────────────────────────
            $stmt = $this->connect->prepare(
                "INSERT INTO bodega_inventario.unidades_medida (nombre, abreviatura)
             VALUES (?, ?)"
            );
            $stmt->execute([$nombre, $abreviatura]);
            $nuevoId = (int)$this->connect->lastInsertId();

            return $this->res->ok('Unidad de medida creada correctamente', null, ['id' => $nuevoId]);
        } catch (Exception $e) {
            error_log("[Unidades] crearUnidad: " . $e->getMessage());
            return $this->res->fail('Error al crear la unidad de medida', $e);
        }
    }



    // ============================================================================
//? PRODUCTOS — Funciones para agregar a la clase existente
// ============================================================================
// Reglas de negocio:
//  - Crear:  nombre, descripción, tipo (inmutable), categoría + mínimo una
//            unidad con es_default = 1
//  - Editar: nombre, descripción, categoría y unidades.
//            El tipo NO puede cambiar — rompería la lógica de lotes existentes
//  - Toggle: desactivar oculta el producto del catálogo; el stock se conserva
//  - Unidades: si se quita una unidad con stock activo → BLOQUEAR
//    (TODO: reemplazar la consulta de stock cuando las tablas estén definidas)
// ============================================================================


// ── GET: listarProductos ──────────────────────────────────────────────────────

    /**
     * Lista productos con paginación server-side y filtros opcionales.
     *
     * GET bodega/catalogo/listarProductos
     * Params: ?nombre=X  &id_tipo=X  &id_categoria=X  &activo=0|1
     *         &pagina=1  &por_pagina=20
     */
    public function listarProductos(): array
    {
        try {
            // ── Parámetros de filtro ──────────────────────────────────────────────
            $nombre = trim(filter_input(INPUT_GET, 'nombre', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $idTipo = filter_input(INPUT_GET, 'id_tipo', FILTER_VALIDATE_INT);
            $idCategoria = filter_input(INPUT_GET, 'id_categoria', FILTER_VALIDATE_INT);
            $activoParam = filter_input(INPUT_GET, 'activo', FILTER_VALIDATE_INT);

            // ── Parámetros de paginación ──────────────────────────────────────────
            $pagina = max(1, (int)filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1);
            $porPagina = min(100, max(1, (int)filter_input(INPUT_GET, 'por_pagina', FILTER_VALIDATE_INT) ?: 20));
            $offset = ($pagina - 1) * $porPagina;

            // ── Construir WHERE ───────────────────────────────────────────────────
            $condiciones = [];
            $params = [];

            if ($nombre !== '') {
                $condiciones[] = "p.nombre LIKE ?";
                $params[] = "%{$nombre}%";
            }
            if ($idTipo !== null && $idTipo !== false) {
                $condiciones[] = "p.id_tipo = ?";
                $params[] = (int)$idTipo;
            }
            if ($idCategoria !== null && $idCategoria !== false) {
                $condiciones[] = "p.id_categoria = ?";
                $params[] = (int)$idCategoria;
            }
            if ($activoParam !== null && $activoParam !== false) {
                $condiciones[] = "p.activo = ?";
                $params[] = (int)$activoParam;
            }

            $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

            // ── Total de registros (para paginación en el frontend) ───────────────
            $stmtTotal = $this->connect->prepare(
                "SELECT COUNT(*) FROM bodega_inventario.productos p {$where}"
            );
            $stmtTotal->execute($params);
            $total = (int)$stmtTotal->fetchColumn();

            // ── Datos de la página solicitada ─────────────────────────────────────
            $sql = "SELECT
                    p.id, p.nombre, p.descripcion, p.activo,
                    p.created_at, p.updated_at,
                    p.id_tipo,      tp.nombre AS nombre_tipo,
                    p.id_categoria, cp.nombre AS nombre_categoria
                FROM bodega_inventario.productos p
                INNER JOIN bodega_inventario.tipos_producto      tp ON tp.id = p.id_tipo
                INNER JOIN bodega_inventario.categorias_producto cp ON cp.id = p.id_categoria
                {$where}
                ORDER BY p.nombre ASC
                LIMIT {$porPagina} OFFSET {$offset}";

            $stmtData = $this->connect->prepare($sql);
            $stmtData->execute($params);

            // CAMBIO AQUÍ: Cambiamos FETCH_ASSOC por FETCH_OBJ
            // Ahora $productos contiene una lista de objetos stdClass
            $productos = $stmtData->fetchAll(PDO::FETCH_OBJ);

            if (empty($productos) && $pagina === 1) {
                return $this->res->info('No se encontraron productos con los filtros aplicados');
            }

            return $this->res->ok('Productos obtenidos', [
                'productos' => $productos, // Retorna la colección de objetos
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'paginas' => (int)ceil($total / $porPagina),
            ]);
        } catch (Exception $e) {
            error_log("[Productos] listarProductos: " . $e->getMessage());
            return $this->res->fail('Error al obtener los productos', $e);
        }
    }


// ── GET: obtenerProducto?id=X ─────────────────────────────────────────────────

    /**
     * Obtiene un producto con su listado completo de unidades.
     *
     * GET bodega/catalogo/obtenerProducto?id=1
     */
    public function obtenerProducto(): array
    {
        try {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                return $this->res->fail('El parámetro id es requerido y debe ser un entero positivo');
            }

            // ── Datos del producto ────────────────────────────────────────────────
            $stmtProd = $this->connect->prepare(
                "SELECT
            p.id, p.nombre, p.descripcion, p.activo,
            p.created_at, p.updated_at,
            p.id_tipo,      tp.nombre AS nombre_tipo,
            p.id_categoria, cp.nombre AS nombre_categoria
         FROM bodega_inventario.productos p
         INNER JOIN bodega_inventario.tipos_producto      tp ON tp.id = p.id_tipo
         INNER JOIN bodega_inventario.categorias_producto cp ON cp.id = p.id_categoria
         WHERE p.id = ?"
            );
            $stmtProd->execute([$id]);

            // CAMBIO 1: Cambiamos FETCH_ASSOC por FETCH_OBJ
            // Ahora $producto es un objeto stdClass
            $producto = $stmtProd->fetch(PDO::FETCH_OBJ);

            if (!$producto) {
                return $this->res->fail("El producto con id={$id} no existe");
            }

            // ── Unidades del producto (todas: activas e inactivas) ─────────────────
            $stmtUnidades = $this->connect->prepare(
                "SELECT
            pu.id, pu.id_unidad, pu.es_default, pu.activo,
            um.nombre AS nombre_unidad, um.abreviatura AS abreviatura_unidad
         FROM bodega_inventario.productos_unidades pu
         INNER JOIN bodega_inventario.unidades_medida um ON um.id = pu.id_unidad
         WHERE pu.id_producto = ?
         ORDER BY pu.es_default DESC, um.nombre ASC"
            );
            $stmtUnidades->execute([$id]);

            // CAMBIO 2: Cambiamos FETCH_ASSOC por FETCH_OBJ para la lista de unidades
            // CAMBIO 3: Asignamos usando la sintaxis de objeto ($producto->unidades)
            $producto->unidades = $stmtUnidades->fetchAll(PDO::FETCH_OBJ);

            // Se retorna la estructura donde el nodo de datos principal es un objeto
            return $this->res->ok('Producto obtenido', $producto);
        } catch (Exception $e) {
            error_log("[Productos] obtenerProducto: " . $e->getMessage());
            return $this->res->fail('Error al obtener el producto', $e);
        }
    }


// ── POST: crearProducto ───────────────────────────────────────────────────────

    /**
     * Crea un producto con sus unidades de medida.
     * Se ejecuta dentro de una transacción.
     *
     * POST bodega/catalogo/crearProducto
     * Body: {
     *   "nombre":       "Resma de papel",
     *   "descripcion":  "...",
     *   "id_tipo":      3,
     *   "id_categoria": 1,
     *   "unidades": [
     *     { "id_unidad": 1, "es_default": true  },
     *     { "id_unidad": 2, "es_default": false }
     *   ]
     * }
     *
     * @param object|array $datos
     */
    public function crearProducto($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);

            // ── Validaciones básicas ──────────────────────────────────────────────
            $validacion = $this->_validarCamposProducto($datos, false);
            if ($validacion !== null) return $validacion;

            $unidades = $this->_normalizarUnidades($datos->unidades ?? []);
            $valUnidades = $this->_validarUnidades($unidades);
            if ($valUnidades !== null) return $valUnidades;

            // ── Unicidad del nombre ───────────────────────────────────────────────
            $stmtDup = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.productos WHERE nombre = ?"
            );
            $stmtDup->execute([trim($datos->nombre)]);
            if ($stmtDup->fetchColumn()) {
                return $this->res->fail("Ya existe un producto con el nombre \"{$datos->nombre}\"");
            }

            // ── Verificar existencia de tipo y categoría ──────────────────────────
            if (!$this->_existeTipo((int)$datos->id_tipo)) {
                return $this->res->fail("El tipo de producto id={$datos->id_tipo} no existe");
            }
            if (!$this->_existeCategoria((int)$datos->id_categoria)) {
                return $this->res->fail("La categoría id={$datos->id_categoria} no existe o está inactiva");
            }

            $descripcion = (isset($datos->descripcion) && trim($datos->descripcion) !== '')
                ? trim($datos->descripcion) : null;

            // ── Transacción ───────────────────────────────────────────────────────
            $this->connect->beginTransaction();

            $stmtProd = $this->connect->prepare(
                "INSERT INTO bodega_inventario.productos
             (nombre, descripcion, id_tipo, id_categoria)
             VALUES (?, ?, ?, ?)"
            );
            $stmtProd->execute([
                trim($datos->nombre),
                $descripcion,
                (int)$datos->id_tipo,
                (int)$datos->id_categoria,
            ]);
            $nuevoId = (int)$this->connect->lastInsertId();

            $this->_insertarUnidades($nuevoId, $unidades);

            $this->connect->commit();

            return $this->res->ok('Producto creado correctamente', null, ['id' => $nuevoId]);
        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("[Productos] crearProducto: " . $e->getMessage());
            return $this->res->fail('Error al crear el producto', $e);
        }
    }


    // ============================================================================
//? BODEGAS — Funciones para agregar a la clase existente
// ============================================================================
// Tipos de bodega (semilla):
//   1 = Agencia  → id_agencia requerido (valida en dbintranet.agencia)
//   2 = Área     → id_departamento_cooperativa requerido
// Reglas:
//   - Tipo inmutable tras creación (reglas de gestión distintas §5 vs §7)
//   - Una agencia/departamento solo puede tener una bodega activa
//   - Desactivar bloqueado si tiene stock > 0 o solicitudes pendientes
//   - restriccion_acceso_activa solo aplica a bodegas de área (tipo 2)
// Nota: usa FETCH_OBJ → acceso con -> en lugar de []
// ============================================================================


// ── GET: listarBodegas ────────────────────────────────────────────────────────

    /**
     * Devuelve todas las bodegas con joins a nombre de agencia/departamento.
     *
     * GET bodega/bodegas/listarBodegas
     * Params opcionales: ?busqueda=X  &id_tipo=1|2  &activo=0|1
     */
    public function listarBodegas(): array
    {
        try {
            $busqueda = trim(filter_input(INPUT_GET, 'busqueda', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $idTipoParam = filter_input(INPUT_GET, 'id_tipo', FILTER_VALIDATE_INT);
            $activoParam = filter_input(INPUT_GET, 'activo', FILTER_VALIDATE_INT);

            $condiciones = [];
            $params = [];

            if ($busqueda !== '') {
                $condiciones[] = "b.nombre LIKE ?";
                $params[] = "%{$busqueda}%";
            }
            if ($idTipoParam !== null && $idTipoParam !== false) {
                $condiciones[] = "b.id_tipo = ?";
                $params[] = (int)$idTipoParam;
            }
            if ($activoParam !== null && $activoParam !== false) {
                $condiciones[] = "b.activo = ?";
                $params[] = (int)$activoParam;
            }

            $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

            $sql = "SELECT
                    b.id, b.nombre, b.id_tipo, tb.nombre AS nombre_tipo,
                    b.id_agencia,
                    b.id_departamento_cooperativa,
                    b.restriccion_acceso_activa,
                    b.activo, b.created_at,
                    COALESCE(a.nombre,                    '') AS nombre_agencia,
                    COALESCE(dc.departamentoCooperativa,  '') AS nombre_departamento
                FROM bodega_inventario.bodegas b
                INNER JOIN bodega_inventario.tipos_bodega tb
                       ON tb.id = b.id_tipo
                LEFT JOIN dbintranet.agencia a
                       ON a.idAgencia = b.id_agencia
                LEFT JOIN dbintranet.departamentocooperativa dc
                       ON dc.idDepartamentoCooperativa = b.id_departamento_cooperativa
                {$where}
                ORDER BY b.id_tipo ASC, b.nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute($params);
            $bodegas = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (empty($bodegas)) {
                return $this->res->info('No se encontraron bodegas');
            }

            return $this->res->ok('Bodegas obtenidas', [
                'bodegas' => $bodegas,
                'total' => count($bodegas),
            ]);
        } catch (Exception $e) {
            error_log("[Bodegas] listarBodegas: " . $e->getMessage());
            return $this->res->fail('Error al obtener las bodegas', $e);
        }
    }


// ── GET: obtenerBodega?id=X ───────────────────────────────────────────────────

    /**
     * Obtiene una bodega por su ID con datos completos.
     *
     * GET bodega/bodegas/obtenerBodega?id=1
     */
    public function obtenerBodega(): array
    {
        try {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                return $this->res->fail('El parámetro id es requerido y debe ser un entero positivo');
            }

            $stmt = $this->connect->prepare(
                "SELECT
                b.id, b.nombre, b.id_tipo, tb.nombre AS nombre_tipo,
                b.id_agencia,
                b.id_departamento_cooperativa,
                b.restriccion_acceso_activa,
                b.activo, b.created_at,
                COALESCE(a.agencia,      '') AS nombre_agencia,
                COALESCE(dc.departamento,'') AS nombre_departamento
             FROM bodega_inventario.bodegas b
             INNER JOIN bodega_inventario.tipos_bodega tb ON tb.id = b.id_tipo
             LEFT JOIN dbintranet.agencia a
                    ON a.idAgencia = b.id_agencia
             LEFT JOIN dbintranet.departamentocooperativa dc
                    ON dc.idDepartamentoCooperativa = b.id_departamento_cooperativa
             WHERE b.id = ?"
            );
            $stmt->execute([$id]);
            $bodega = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$bodega) {
                return $this->res->fail("La bodega con id={$id} no existe");
            }

            return $this->res->ok('Bodega obtenida', $bodega);
        } catch (Exception $e) {
            error_log("[Bodegas] obtenerBodega: " . $e->getMessage());
            return $this->res->fail('Error al obtener la bodega', $e);
        }
    }


// ── GET: listarAgenciasBodega ─────────────────────────────────────────────────

    /**
     * Lista agencias disponibles para asociar a una bodega tipo agencia.
     * Excluye las que ya tienen una bodega activa (unicidad por agencia).
     *
     * GET bodega/bodegas/listarAgenciasBodega
     * Param opcional: ?excluir_id=X (id de bodega en edición, para no excluir la propia agencia)
     */
    public function listarAgenciasBodega(): array
    {
        try {
            $excluirId = filter_input(INPUT_GET, 'excluir_id', FILTER_VALIDATE_INT) ?: 0;

            // Agencias que ya tienen bodega activa (excluyendo la bodega en edición)
            $subquery = "SELECT id_agencia
                     FROM bodega_inventario.bodegas
                     WHERE id_tipo = 1 AND activo = 1 AND id_agencia IS NOT NULL
                       AND id != ?";

            $sql = "SELECT idAgencia AS id, nombre
                    FROM dbintranet.agencia
                    WHERE idAgencia NOT IN ({$subquery})
                    AND (idAgencia <= 50 OR idAgencia = 99)
                    ORDER BY nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$excluirId]);
            $agencias = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $this->res->ok('Agencias disponibles', [
                'agencias' => $agencias,
                'total' => count($agencias),
            ]);
        } catch (Exception $e) {
            error_log("[Bodegas] listarAgenciasBodega: " . $e->getMessage());
            return $this->res->fail('Error al obtener las agencias', $e);
        }
    }


// ── GET: listarDepartamentosBodega ────────────────────────────────────────────

    /**
     * Lista departamentos disponibles para asociar a una bodega tipo área.
     * Excluye los que ya tienen una bodega activa.
     *
     * GET bodega/bodegas/listarDepartamentosBodega
     * Param opcional: ?excluir_id=X
     */
    public function listarDepartamentosBodega(): array
    {
        try {
            $excluirId = filter_input(INPUT_GET, 'excluir_id', FILTER_VALIDATE_INT) ?: 0;

            $subquery = "SELECT id_departamento_cooperativa
                     FROM bodega_inventario.bodegas
                     WHERE id_tipo = 2 AND activo = 1
                       AND id_departamento_cooperativa IS NOT NULL
                       AND id != ?";

            $sql = "SELECT idDepartamentoCooperativa AS id, departamentoCooperativa AS nombre
                    FROM dbintranet.departamentocooperativa
                    WHERE idDepartamentoCooperativa NOT IN ({$subquery})
                    ORDER BY departamentoCooperativa ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$excluirId]);
            $departamentos = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $this->res->ok('Departamentos disponibles', [
                'departamentos' => $departamentos,
                'total' => count($departamentos),
            ]);
        } catch (Exception $e) {
            error_log("[Bodegas] listarDepartamentosBodega: " . $e->getMessage());
            return $this->res->fail('Error al obtener los departamentos', $e);
        }
    }


// ── POST: crearBodega ─────────────────────────────────────────────────────────

    /**
     * Crea una nueva bodega.
     *
     * POST bodega/bodegas/crearBodega
     * Body tipo agencia: { "nombre": "...", "id_tipo": 1, "id_agencia": 5 }
     * Body tipo área:    { "nombre": "...", "id_tipo": 2, "id_departamento_cooperativa": 3 }
     *
     * @param object|array $datos
     */
    public function crearBodega($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);
            $idTipo = (int)($datos->id_tipo ?? 0);

            if (!in_array($idTipo, [1, 2], true)) {
                return $this->res->fail('El tipo de bodega debe ser 1 (Agencia) o 2 (Área)');
            }

            if ($idTipo === 1) {
                $idAgencia = (int)($datos->id_agencia ?? 0);
                if ($idAgencia < 1) {
                    return $this->res->fail('La agencia es requerida');
                }

                // Obtener nombre directo de dbintranet
                $stmtNombre = $this->connect->prepare(
                    "SELECT nombre FROM dbintranet.agencia WHERE idAgencia = ?"
                );
                $stmtNombre->execute([$idAgencia]);
                $nombre = $stmtNombre->fetchColumn();

                if (!$nombre) {
                    return $this->res->fail("La agencia id={$idAgencia} no existe");
                }
                if ($this->_agenciaTieneBodegaActiva($idAgencia, 0)) {
                    return $this->res->fail('Esta agencia ya tiene una bodega activa registrada');
                }
            } else {
                $idDepto = (int)($datos->id_departamento_cooperativa ?? 0);
                if ($idDepto < 1) {
                    return $this->res->fail('El departamento es requerido');
                }

                // Obtener nombre directo de dbintranet
                $stmtNombre = $this->connect->prepare(
                    "SELECT departamentoCooperativa FROM dbintranet.departamentocooperativa
                 WHERE idDepartamentoCooperativa = ?"
                );
                $stmtNombre->execute([$idDepto]);
                $nombre = $stmtNombre->fetchColumn();

                if (!$nombre) {
                    return $this->res->fail("El departamento id={$idDepto} no existe");
                }
                if ($this->_departamentoTieneBodegaActiva($idDepto, 0)) {
                    return $this->res->fail('Este departamento ya tiene una bodega activa registrada');
                }
            }

            // Unicidad del nombre
            $stmtDup = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.bodegas WHERE nombre = ?"
            );
            $stmtDup->execute([$nombre]);
            if ($stmtDup->fetchColumn()) {
                return $this->res->fail("Ya existe una bodega con el nombre \"{$nombre}\"");
            }

            $stmt = $this->connect->prepare(
                "INSERT INTO bodega_inventario.bodegas
             (nombre, id_tipo, id_agencia, id_departamento_cooperativa)
             VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $nombre,
                $idTipo,
                $idTipo === 1 ? (int)$datos->id_agencia : null,
                $idTipo === 2 ? (int)$datos->id_departamento_cooperativa : null,
            ]);
            $nuevoId = (int)$this->connect->lastInsertId();

            return $this->res->ok('Bodega creada correctamente', null, ['id' => $nuevoId]);
        } catch (Exception $e) {
            error_log("[Bodegas] crearBodega: " . $e->getMessage());
            return $this->res->fail('Error al crear la bodega', $e);
        }
    }


    // ============================================================================
//?  MI BODEGA — Autoservicio del encargado de bodega de área
// ============================================================================
// El encargado puede consultar SU bodega asignada y togglear la restricción
// de acceso únicamente sobre ella. La identidad del usuario se toma SIEMPRE
// de la sesión/token — nunca del payload — para evitar suplantación.
//
// TODO: ajustar $this->idUsuario; al método real del proyecto
//       (sesión, JWT, request attribute, etc.).
// ============================================================================


// ── GET: obtenerMiBodega ──────────────────────────────────────────────────────

    /**
     * Devuelve la bodega de la que el usuario autenticado es encargado activo.
     * Filtros aplicados (lo que no cumpla → no devuelve nada):
     *   - El usuario tiene registro en inv_encargados_bodega_area con activo=1
     *   - La bodega referenciada está activa
     *   - La bodega es de tipo área (id_tipo = 2)
     *
     * GET bodega/miBodega/obtenerMiBodega
     * Sin parámetros — usa el usuario autenticado.
     */
    public function obtenerMiBodega(): array
    {
        try {
            $idUsuario = $this->idUsuario;
            if (empty($idUsuario)) {
                return $this->res->fail('No se pudo identificar al usuario');
            }

            $sql = "SELECT
                    b.id,
                    b.nombre,
                    b.id_tipo,
                    tb.nombre AS nombre_tipo,
                    b.restriccion_acceso_activa,
                    b.activo,
                    COALESCE(dc.departamentoCooperativa, '') AS nombre_departamento,
                    e.created_at AS asignado_desde
                FROM bodega_inventario.inv_encargados_bodega_area e
                INNER JOIN bodega_inventario.bodegas b
                       ON b.id = e.id_bodega
                INNER JOIN bodega_inventario.tipos_bodega tb
                       ON tb.id = b.id_tipo
                LEFT JOIN dbintranet.departamentocooperativa dc
                       ON dc.idDepartamentoCooperativa = b.id_departamento_cooperativa
                WHERE e.id_usuario = ?
                  AND e.activo    = 1
                  AND b.activo    = 1
                  AND b.id_tipo   = 2
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$idUsuario]);
            $bodega = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$bodega) {
                return $this->res->info('No eres encargado activo de ninguna bodega');
            }

            return $this->res->ok('Bodega obtenida', $bodega);
        } catch (Exception $e) {
            error_log("[MiBodega] obtenerMiBodega: " . $e->getMessage());
            return $this->res->fail('Error al obtener la bodega', $e);
        }
    }


// ── POST: toggleMiRestriccion ─────────────────────────────────────────────────

    /**
     * Activa o desactiva la restricción de acceso de la bodega del encargado.
     *
     * SEGURIDAD: el id_bodega NUNCA se acepta del payload. Se deriva del
     * usuario autenticado vía _obtenerBodegaDelEncargado(). Si el usuario
     * no es encargado activo de ninguna bodega de área, falla.
     *
     * Al desactivar, retorna info() — la matriz de acceso se conserva.
     *
     * POST bodega/miBodega/toggleMiRestriccion
     * Body: { "restriccion_acceso_activa": 1 }
     *
     * @param object|array $datos
     */
    public function toggleMiRestriccion($datos): array
    {
        try {
            $datos = $this->limpiarDatos($datos);
            $restriccion = isset($datos->restriccion_acceso_activa)
                ? (int)$datos->restriccion_acceso_activa
                : -1;

            if (!in_array($restriccion, [0, 1], true)) {
                return $this->res->fail('El campo restriccion_acceso_activa debe ser 0 o 1');
            }

            $idUsuario = $this->idUsuario;
            if (empty($idUsuario)) {
                return $this->res->fail('No se pudo identificar al usuario');
            }

            // Fuente de verdad: la bodega se obtiene del encargado, NO del payload.
            $idBodega = $this->_obtenerBodegaDelEncargado($idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            // Idempotencia
            $stmt = $this->connect->prepare(
                "SELECT restriccion_acceso_activa
                 FROM bodega_inventario.bodegas WHERE id = ?"
            );
            $stmt->execute([$idBodega]);
            $estadoActual = (int)$stmt->fetchColumn();

            if ($estadoActual === $restriccion) {
                $estado = $restriccion === 1 ? 'activada' : 'desactivada';
                return $this->res->info("La restricción ya se encuentra {$estado}");
            }

            $this->connect->prepare(
                "UPDATE bodega_inventario.bodegas
                 SET restriccion_acceso_activa = ?
                 WHERE id = ?"
            )->execute([$restriccion, $idBodega]);

            if ($restriccion === 0) {
                return $this->res->info(
                    'Restricción desactivada. La matriz de acceso se conserva.'
                );
            }
            return $this->res->ok('Restricción de acceso activada correctamente');
        } catch (Exception $e) {
            error_log("[MiBodega] toggleMiRestriccion: " . $e->getMessage());
            return $this->res->fail('Error al cambiar la restricción', $e);
        }
    }


// ── Helper privado ────────────────────────────────────────────────────────────

    /**
     * Retorna el id de la bodega de la que un usuario es encargado activo.
     * Solo cuenta si la bodega también está activa y es de área (tipo 2).
     *
     * @return int|null  null = el usuario no es encargado activo de ninguna bodega
     */
    private function _obtenerBodegaDelEncargado(string $idUsuario): ?int
    {
        $stmt = $this->connect->prepare(
            "SELECT e.id_bodega
             FROM bodega_inventario.inv_encargados_bodega_area e
             INNER JOIN bodega_inventario.bodegas b ON b.id = e.id_bodega
             WHERE e.id_usuario = ?
               AND e.activo    = 1
               AND b.activo    = 1
               AND b.id_tipo   = 2
             LIMIT 1"
        );
        $stmt->execute([$idUsuario]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }


    // ============================================================================
//? MI MATRIZ — Autoservicio del encargado para configurar su matriz de acceso
// ============================================================================
// Toda operación deriva el id_bodega del usuario autenticado.
// El payload NUNCA contiene id_bodega — protección contra suplantación.
//
// Reutiliza el helper _obtenerBodegaDelEncargado() del módulo mi-bodega.
// ============================================================================


// ── GET: obtenerMiMatriz ──────────────────────────────────────────────────────

    /**
     * Devuelve toda la matriz de acceso de la bodega del encargado en formato
     * grid: lista de puestos, lista de agencias, y celdas activas.
     *
     * El frontend construye el grid con esta info. Una celda "activa" significa
     * que existe registro en matriz_acceso con activo=1 para (id_puesto, id_agencia)
     * dentro de la bodega del encargado.
     *
     * Respuesta:
     * {
     *   bodega: { id, nombre },
     *   puestos:  [{ id, nombre }, ...],
     *   agencias: [{ id, nombre }, ...],
     *   celdas:   [{ id, id_puesto, id_agencia, total_productos }, ...]
     * }
     *
     * GET bodega/miMatriz/obtenerMiMatriz
     */
    public function obtenerMiMatriz(): array
    {
        try {
            $idUsuario = $this->idUsuario;
            if (empty($idUsuario)) {
                return $this->res->fail('No se pudo identificar al usuario');
            }

            $idBodega = $this->_obtenerBodegaDelEncargado($idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            // Bodega — datos básicos
            $stmtBodega = $this->connect->prepare(
                "SELECT id, nombre FROM bodega_inventario.bodegas WHERE id = ?"
            );
            $stmtBodega->execute([$idBodega]);
            $bodega = $stmtBodega->fetch(PDO::FETCH_OBJ);

            // Todos los puestos disponibles
            $stmtPuestos = $this->connect->query(
                "SELECT idPuesto AS id, nombre
                 FROM dbintranet.puesto
                 ORDER BY nombre ASC"
            );
            $puestos = $stmtPuestos->fetchAll(PDO::FETCH_OBJ);

            // Todas las agencias disponibles
            $stmtAgencias = $this->connect->query(
                "SELECT idAgencia AS id, nombre
                 FROM dbintranet.agencia
                    WHERE (idAgencia <= 50 OR idAgencia = 99)
                 ORDER BY nombre ASC"
            );
            $agencias = $stmtAgencias->fetchAll(PDO::FETCH_OBJ);

            // Celdas activas en la matriz de esta bodega + conteo de productos
            $stmtCeldas = $this->connect->prepare(
                "SELECT
                    ma.id,
                    ma.id_puesto,
                    ma.id_agencia,
                    (SELECT COUNT(*)
                     FROM bodega_inventario.matriz_acceso_productos
                     WHERE id_matriz = ma.id) AS total_productos
                 FROM bodega_inventario.matriz_acceso ma
                 WHERE ma.id_bodega = ?
                   AND ma.activo    = 1"
            );
            $stmtCeldas->execute([$idBodega]);
            $celdas = $stmtCeldas->fetchAll(PDO::FETCH_OBJ);

            return $this->res->ok('Matriz obtenida', [
                'bodega'   => $bodega,
                'puestos'  => $puestos,
                'agencias' => $agencias,
                'celdas'   => $celdas,
            ]);
        } catch (Exception $e) {
            error_log("[MiMatriz] obtenerMiMatriz: " . $e->getMessage());
            return $this->res->fail('Error al obtener la matriz', $e);
        }
    }


// ── POST: toggleCelda ─────────────────────────────────────────────────────────

    /**
     * Activa o desactiva una celda específica del grid.
     * Si no existe → INSERT. Si existe → UPDATE de activo.
     *
     * POST bodega/miMatriz/toggleCelda
     * Body: { "id_puesto": 5, "id_agencia": 3, "activo": 1 }
     *
     * @param object|array $datos
     */
    public function toggleCelda($datos): array
    {
        try {
            $datos     = $this->limpiarDatos($datos);
            $idPuesto  = (int) ($datos->id_puesto  ?? 0);
            $idAgencia = (int) ($datos->id_agencia ?? 0);
            $activo    = isset($datos->activo) ? (int) $datos->activo : -1;

            if ($idPuesto < 1)  return $this->res->fail('id_puesto es requerido');
            if ($idAgencia < 1) return $this->res->fail('id_agencia es requerido');
            if (!in_array($activo, [0, 1], true)) {
                return $this->res->fail('activo debe ser 0 o 1');
            }

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            // ¿Existe ya el registro?
            $stmt = $this->connect->prepare(
                "SELECT id FROM bodega_inventario.matriz_acceso
                 WHERE id_bodega = ? AND id_puesto = ? AND id_agencia = ?"
            );
            $stmt->execute([$idBodega, $idPuesto, $idAgencia]);
            $idExistente = $stmt->fetchColumn();

            if ($idExistente) {
                $this->connect->prepare(
                    "UPDATE bodega_inventario.matriz_acceso SET activo = ? WHERE id = ?"
                )->execute([$activo, $idExistente]);
                return $this->res->ok('Celda actualizada', ['id' => (int) $idExistente]);
            }

            // INSERT solo si activo=1 (no tiene sentido crear celda ya desactivada)
            if ($activo === 0) {
                return $this->res->info('La celda ya estaba inactiva');
            }

            $this->connect->prepare(
                "INSERT INTO bodega_inventario.matriz_acceso
                 (id_bodega, id_puesto, id_agencia, activo) VALUES (?, ?, ?, 1)"
            )->execute([$idBodega, $idPuesto, $idAgencia]);

            return $this->res->ok('Celda activada', [
                'id' => (int) $this->connect->lastInsertId(),
            ]);
        } catch (Exception $e) {
            error_log("[MiMatriz] toggleCelda: " . $e->getMessage());
            return $this->res->fail('Error al actualizar la celda', $e);
        }
    }


// ── POST: toggleFilaPuesto ────────────────────────────────────────────────────

    /**
     * Activa o desactiva un puesto en TODAS las agencias (toggle fila completa).
     *
     * Estrategia:
     *   - Si activo=1: para cada agencia, INSERT IGNORE + UPDATE activo=1.
     *   - Si activo=0: UPDATE masivo activo=0 de todas las filas existentes.
     *
     * POST bodega/miMatriz/toggleFilaPuesto
     * Body: { "id_puesto": 5, "activo": 1 }
     *
     * @param object|array $datos
     */
    public function toggleFilaPuesto($datos): array
    {
        try {
            $datos    = $this->limpiarDatos($datos);
            $idPuesto = (int) ($datos->id_puesto ?? 0);
            $activo   = isset($datos->activo) ? (int) $datos->activo : -1;

            if ($idPuesto < 1) return $this->res->fail('id_puesto es requerido');
            if (!in_array($activo, [0, 1], true)) {
                return $this->res->fail('activo debe ser 0 o 1');
            }

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            $this->connect->beginTransaction();

            if ($activo === 1) {
                // Crear filas faltantes para todas las agencias
                $this->connect->prepare(
                    "INSERT IGNORE INTO bodega_inventario.matriz_acceso
                     (id_bodega, id_puesto, id_agencia, activo)
                     SELECT ?, ?, a.idAgencia, 1
                     FROM dbintranet.agencia a"
                )->execute([$idBodega, $idPuesto]);

                // Reactivar las ya existentes
                $this->connect->prepare(
                    "UPDATE bodega_inventario.matriz_acceso
                     SET activo = 1
                     WHERE id_bodega = ? AND id_puesto = ?"
                )->execute([$idBodega, $idPuesto]);
            } else {
                $this->connect->prepare(
                    "UPDATE bodega_inventario.matriz_acceso
                     SET activo = 0
                     WHERE id_bodega = ? AND id_puesto = ?"
                )->execute([$idBodega, $idPuesto]);
            }

            $this->connect->commit();
            return $this->res->ok(
                $activo === 1
                    ? 'Fila activada en todas las agencias'
                    : 'Fila desactivada en todas las agencias'
            );
        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("[MiMatriz] toggleFilaPuesto: " . $e->getMessage());
            return $this->res->fail('Error al togglear la fila', $e);
        }
    }


// ── POST: toggleColumnaAgencia ────────────────────────────────────────────────

    /**
     * Activa o desactiva una agencia para TODOS los puestos (toggle columna).
     *
     * POST bodega/miMatriz/toggleColumnaAgencia
     * Body: { "id_agencia": 3, "activo": 1 }
     *
     * @param object|array $datos
     */
    public function toggleColumnaAgencia($datos): array
    {
        try {
            $datos     = $this->limpiarDatos($datos);
            $idAgencia = (int) ($datos->id_agencia ?? 0);
            $activo    = isset($datos->activo) ? (int) $datos->activo : -1;

            if ($idAgencia < 1) return $this->res->fail('id_agencia es requerido');
            if (!in_array($activo, [0, 1], true)) {
                return $this->res->fail('activo debe ser 0 o 1');
            }

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            $this->connect->beginTransaction();

            if ($activo === 1) {
                $this->connect->prepare(
                    "INSERT IGNORE INTO bodega_inventario.matriz_acceso
                     (id_bodega, id_puesto, id_agencia, activo)
                     SELECT ?, p.idPuesto, ?, 1
                     FROM dbintranet.puesto p"
                )->execute([$idBodega, $idAgencia]);

                $this->connect->prepare(
                    "UPDATE bodega_inventario.matriz_acceso
                     SET activo = 1
                     WHERE id_bodega = ? AND id_agencia = ?"
                )->execute([$idBodega, $idAgencia]);
            } else {
                $this->connect->prepare(
                    "UPDATE bodega_inventario.matriz_acceso
                     SET activo = 0
                     WHERE id_bodega = ? AND id_agencia = ?"
                )->execute([$idBodega, $idAgencia]);
            }

            $this->connect->commit();
            return $this->res->ok(
                $activo === 1
                    ? 'Columna activada para todos los puestos'
                    : 'Columna desactivada para todos los puestos'
            );
        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("[MiMatriz] toggleColumnaAgencia: " . $e->getMessage());
            return $this->res->fail('Error al togglear la columna', $e);
        }
    }


// ── POST: limpiarMatriz ───────────────────────────────────────────────────────

    /**
     * Desactiva TODAS las celdas de la matriz del encargado.
     * Los productos configurados se conservan (re-aparecen al re-activar).
     *
     * POST bodega/miMatriz/limpiarMatriz
     * Body: {}
     *
     * @param object|array $datos
     */
    public function limpiarMatriz($datos): array
    {
        try {
            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            $this->connect->prepare(
                "UPDATE bodega_inventario.matriz_acceso
                 SET activo = 0
                 WHERE id_bodega = ?"
            )->execute([$idBodega]);

            return $this->res->info(
                'Matriz limpiada. Los productos configurados se conservan.'
            );
        } catch (Exception $e) {
            error_log("[MiMatriz] limpiarMatriz: " . $e->getMessage());
            return $this->res->fail('Error al limpiar la matriz', $e);
        }
    }


// ── GET: obtenerProductosCelda?id_matriz=X ────────────────────────────────────

    /**
     * Productos configurados en una celda específica.
     * Verifica que la celda pertenezca a la bodega del encargado.
     *
     * GET bodega/miMatriz/obtenerProductosCelda?id_matriz=5
     */
    public function obtenerProductosCelda(): array
    {
        try {
            $idMatriz = filter_input(INPUT_GET, 'id_matriz', FILTER_VALIDATE_INT);
            if (!$idMatriz || $idMatriz < 1) {
                return $this->res->fail('id_matriz es requerido');
            }

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) {
                return $this->res->fail('No eres encargado activo de ninguna bodega');
            }

            // Verificar que la celda pertenece a esta bodega
            if (!$this->_celdaPertenece($idMatriz, $idBodega)) {
                return $this->res->fail('Esta celda no pertenece a tu bodega');
            }

            $sql = "SELECT
                    map.id,
                    map.id_matriz,
                    map.id_producto,
                    map.por_categoria,
                    pr.nombre         AS nombre_producto,
                    pr.id_categoria,
                    cp.nombre         AS nombre_categoria
                FROM bodega_inventario.matriz_acceso_productos map
                INNER JOIN bodega_inventario.productos          pr ON pr.id = map.id_producto
                INNER JOIN bodega_inventario.categorias_producto cp ON cp.id = pr.id_categoria
                WHERE map.id_matriz = ?
                ORDER BY cp.nombre ASC, pr.nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$idMatriz]);
            $productos = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $this->res->ok('Productos obtenidos', [
                'productos' => $productos,
                'total'     => count($productos),
            ]);
        } catch (Exception $e) {
            error_log("[MiMatriz] obtenerProductosCelda: " . $e->getMessage());
            return $this->res->fail('Error al obtener productos', $e);
        }
    }

// ============================================================================
//? ENDPOINTS: Módulo Asignación de Productos (vista Producto × Agencia)
// ============================================================================
// Vista inversa de mi-matriz: filas = productos, columnas = agencias.
// Cada celda (producto, agencia) muestra en cuántos puestos está asignado.
//
// Agregar al array $metodosGet:
//   'obtenerMatrizProductos'
//
// Agregar al array $metodosPost:
//   'sincronizarProductoAgencia'
//   'toggleFilaProducto'
//   'toggleColumnaAgencia'
// ============================================================================


// ── GET: obtenerMatrizProductos ───────────────────────────────────────────────

    /**
     * Devuelve todos los datos necesarios para construir la grilla Producto × Agencia.
     *
     * Respuesta:
     * {
     *   productos:      [{ id, nombre, id_categoria, nombre_categoria }]
     *   agencias:       [{ id, nombre }]
     *   celdas_activas: [{ id_agencia, id_puesto, nombre_puesto }]
     *      → Puestos que tienen celda activa (matriz_acceso) por agencia.
     *        El frontend agrupa esto para saber qué puestos están disponibles
     *        en cada agencia.
     *   asignaciones:   [{ id_producto, id_agencia, id_puesto }]
     *      → Combinaciones que ya tienen el producto asignado.
     * }
     *
     * GET bodega/asignacionProductos/obtenerMatrizProductos
     */
    public function obtenerMatrizProductos(): array
    {
        try {
            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) return $this->res->fail('No eres encargado activo de ninguna bodega');

            $productos = $this->connect->query(
                "SELECT p.id, p.nombre, p.id_categoria, cp.nombre AS nombre_categoria
             FROM bodega_inventario.productos p
             INNER JOIN bodega_inventario.categorias_producto cp ON cp.id = p.id_categoria
             WHERE p.activo = 1
             ORDER BY cp.nombre ASC, p.nombre ASC"
            )->fetchAll(PDO::FETCH_OBJ);

            $agencias = $this->connect->query(
                "SELECT idAgencia AS id, nombre FROM dbintranet.agencia 
                    WHERE (idAgencia <= 50 OR idAgencia = 99) ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_OBJ);

            // ← CAMBIO: catálogo completo de puestos
            $puestos = $this->connect->query(
                "SELECT idPuesto AS id, nombre FROM dbintranet.puesto ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_OBJ);

            // Asignaciones actuales (sólo en celdas activas, igual que antes)
            $stmtAsig = $this->connect->prepare(
                "SELECT map.id_producto, ma.id_agencia, ma.id_puesto
             FROM bodega_inventario.matriz_acceso_productos map
             INNER JOIN bodega_inventario.matriz_acceso ma ON ma.id = map.id_matriz
             WHERE ma.id_bodega = ? AND ma.activo = 1"
            );
            $stmtAsig->execute([$idBodega]);
            $asignaciones = $stmtAsig->fetchAll(PDO::FETCH_OBJ);

            return $this->res->ok('Matriz de productos obtenida', [
                'productos'    => $productos,
                'agencias'     => $agencias,
                'puestos'      => $puestos,        // ← reemplaza celdas_activas
                'asignaciones' => $asignaciones,
            ]);
        } catch (Exception $e) {
            error_log("[AsignacionProductos] obtenerMatrizProductos: " . $e->getMessage());
            return $this->res->fail('Error al obtener la matriz', $e);
        }
    }


// ── POST: sincronizarProductoAgencia ─────────────────────────────────────────

    /**
     * Establece exactamente qué puestos tienen un producto en una agencia dada.
     *
     * Lógica:
     *   - Elimina las asignaciones existentes para (producto, agencia) que NO
     *     estén en la nueva lista.
     *   - Inserta las que faltan (INSERT IGNORE para evitar duplicados).
     *   - Si id_puestos está vacío → elimina todas las asignaciones.
     *
     * POST bodega/asignacionProductos/sincronizarProductoAgencia
     * Body: { "id_producto": 5, "id_agencia": 2, "id_puestos": [1, 3, 7] }
     *
     * @param object|array $datos
     */
    public function sincronizarProductoAgencia($datos): array
    {
        try {
            $datos      = $this->limpiarDatos($datos);
            $idProducto = (int) ($datos->id_producto ?? 0);
            $idAgencia  = (int) ($datos->id_agencia  ?? 0);

            $puestosRaw = $datos->id_puestos ?? [];
            if (is_string($puestosRaw)) $puestosRaw = json_decode($puestosRaw, true) ?? [];
            $idPuestos = array_values(array_unique(array_filter(
                array_map('intval', $puestosRaw), fn($v) => $v > 0
            )));

            if ($idProducto < 1) return $this->res->fail('id_producto es requerido');
            if ($idAgencia  < 1) return $this->res->fail('id_agencia es requerido');

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) return $this->res->fail('No eres encargado activo de ninguna bodega');

            $this->connect->beginTransaction();

            if (empty($idPuestos)) {
                // Quitar el producto de todos los puestos de esta agencia (no toca matriz_acceso)
                $this->connect->prepare(
                    "DELETE map FROM bodega_inventario.matriz_acceso_productos map
                 INNER JOIN bodega_inventario.matriz_acceso ma ON ma.id = map.id_matriz
                 WHERE ma.id_bodega = ? AND ma.id_agencia = ? AND map.id_producto = ?"
                )->execute([$idBodega, $idAgencia, $idProducto]);

                $this->connect->commit();
                return $this->res->ok('Producto removido de todos los puestos en esta agencia');
            }

            // ◄◄ NUEVO: 1) Crear celdas matriz_acceso que no existan
            $stmtInsertCelda = $this->connect->prepare(
                "INSERT IGNORE INTO bodega_inventario.matriz_acceso
             (id_bodega, id_puesto, id_agencia, activo) VALUES (?, ?, ?, 1)"
            );
            foreach ($idPuestos as $idP) {
                $stmtInsertCelda->execute([$idBodega, $idP, $idAgencia]);
            }

            // ◄◄ NUEVO: 2) Reactivar las que estuvieran inactivas
            $placeholders = implode(',', array_fill(0, count($idPuestos), '?'));
            $paramsReact  = array_merge([$idBodega, $idAgencia], $idPuestos);
            $this->connect->prepare(
                "UPDATE bodega_inventario.matriz_acceso SET activo = 1
             WHERE id_bodega = ? AND id_agencia = ? AND id_puesto IN ({$placeholders})"
            )->execute($paramsReact);

            // 3) Eliminar asignaciones del producto en puestos que ya no están en la lista
            $paramsDelete = array_merge([$idBodega, $idAgencia, $idProducto], $idPuestos);
            $this->connect->prepare(
                "DELETE map FROM bodega_inventario.matriz_acceso_productos map
             INNER JOIN bodega_inventario.matriz_acceso ma ON ma.id = map.id_matriz
             WHERE ma.id_bodega = ? AND ma.id_agencia = ? AND map.id_producto = ?
               AND ma.id_puesto NOT IN ({$placeholders})"
            )->execute($paramsDelete);

            // 4) Insertar las asignaciones nuevas
            $paramsInsert = array_merge([$idProducto, $idBodega, $idAgencia], $idPuestos);
            $this->connect->prepare(
                "INSERT IGNORE INTO bodega_inventario.matriz_acceso_productos
             (id_matriz, id_producto, por_categoria)
             SELECT ma.id, ?, 0
             FROM bodega_inventario.matriz_acceso ma
             WHERE ma.id_bodega = ? AND ma.id_agencia = ? AND ma.activo = 1
               AND ma.id_puesto IN ({$placeholders})"
            )->execute($paramsInsert);

            $this->connect->commit();
            return $this->res->ok('Asignación actualizada correctamente');
        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("[AsignacionProductos] sincronizarProductoAgencia: " . $e->getMessage());
            return $this->res->fail('Error al sincronizar la asignación', $e);
        }
    }


// ── POST: toggleFilaProducto ──────────────────────────────────────────────────

    /**
     * Asigna o quita un producto en TODAS las celdas activas de la bodega
     * (fila completa del producto → todas las agencias, todos los puestos).
     *
     * POST bodega/asignacionProductos/toggleFilaProducto
     * Body: { "id_producto": 5, "activo": 1 }
     *
     * @param object|array $datos
     */
    public function toggleFilaProducto($datos): array
    {
        try {
            $datos      = $this->limpiarDatos($datos);
            $idProducto = (int) ($datos->id_producto ?? 0);
            $activo     = isset($datos->activo) ? (int) $datos->activo : -1;

            if ($idProducto < 1)                   return $this->res->fail('id_producto es requerido');
            if (!in_array($activo, [0, 1], true))  return $this->res->fail('activo debe ser 0 o 1');

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) return $this->res->fail('No eres encargado activo de ninguna bodega');

            if ($activo === 1) {
                // Insertar en todas las celdas activas de la bodega
                $stmt = $this->connect->prepare(
                    "INSERT IGNORE INTO bodega_inventario.matriz_acceso_productos
                     (id_matriz, id_producto, por_categoria)
                 SELECT ma.id, ?, 0
                 FROM bodega_inventario.matriz_acceso ma
                 WHERE ma.id_bodega = ? AND ma.activo = 1"
                );
                $stmt->execute([$idProducto, $idBodega]);
                $insertados = $stmt->rowCount();

                return $this->res->ok(
                    "Producto asignado a {$insertados} celda(s)",
                    ['insertados' => $insertados]
                );
            }

            // Eliminar de todas las celdas de la bodega
            $stmt = $this->connect->prepare(
                "DELETE map FROM bodega_inventario.matriz_acceso_productos map
             INNER JOIN bodega_inventario.matriz_acceso ma ON ma.id = map.id_matriz
             WHERE ma.id_bodega = ? AND map.id_producto = ?"
            );
            $stmt->execute([$idBodega, $idProducto]);
            $eliminados = $stmt->rowCount();

            return $this->res->ok(
                "Producto removido de {$eliminados} celda(s)",
                ['eliminados' => $eliminados]
            );
        } catch (Exception $e) {
            error_log("[AsignacionProductos] toggleFilaProducto: " . $e->getMessage());
            return $this->res->fail('Error al actualizar la fila del producto', $e);
        }
    }

    /**
     * Asigna un producto a múltiples agencias en una transacción.
     * Body: {
     *   "id_producto": 5,
     *   "asignaciones": [
     *     { "id_agencia": 2, "id_puestos": [1, 3] },
     *     { "id_agencia": 7, "id_puestos": [1, 2, 5] },
     *     { "id_agencia": 9, "id_puestos": [] }   ← quita el producto de esta agencia
     *   ]
     * }
     */
    public function asignarProductoMultiplesAgencias($datos): array
    {
        try {
            $datos      = $this->limpiarDatos($datos);
            $idProducto = (int) ($datos->id_producto ?? 0);

            $asignaciones = $datos->asignaciones ?? [];
            if (is_string($asignaciones)) {
                $asignaciones = json_decode($asignaciones, true) ?? [];
            }

            if ($idProducto < 1)     return $this->res->fail('id_producto es requerido');
            if (!is_array($asignaciones) || empty($asignaciones)) {
                return $this->res->fail('Debes enviar al menos una asignación');
            }

            $idBodega = $this->_obtenerBodegaDelEncargado($this->idUsuario);
            if (!$idBodega) return $this->res->fail('No eres encargado activo de ninguna bodega');

            $this->connect->beginTransaction();
            $totalInsertados = 0;
            $totalEliminados = 0;

            foreach ($asignaciones as $asig) {
                $a         = is_array($asig) ? (object) $asig : $asig;
                $idAgencia = (int) ($a->id_agencia ?? 0);
                $puestosRaw = $a->id_puestos ?? [];
                if (is_string($puestosRaw)) $puestosRaw = json_decode($puestosRaw, true) ?? [];

                $idPuestos = array_values(array_unique(array_filter(
                    array_map('intval', $puestosRaw), fn($v) => $v > 0
                )));

                if ($idAgencia < 1) continue;

                // Caso: agencia sin puestos → eliminar todas las asignaciones del producto allí
                if (empty($idPuestos)) {
                    $stmt = $this->connect->prepare(
                        "DELETE map FROM bodega_inventario.matriz_acceso_productos map
                     INNER JOIN bodega_inventario.matriz_acceso ma ON ma.id = map.id_matriz
                     WHERE ma.id_bodega = ? AND ma.id_agencia = ? AND map.id_producto = ?"
                    );
                    $stmt->execute([$idBodega, $idAgencia, $idProducto]);
                    $totalEliminados += $stmt->rowCount();
                    continue;
                }

                // 1) Crear celdas matriz_acceso que no existan
                $stmtInsertCelda = $this->connect->prepare(
                    "INSERT IGNORE INTO bodega_inventario.matriz_acceso
                 (id_bodega, id_puesto, id_agencia, activo) VALUES (?, ?, ?, 1)"
                );
                foreach ($idPuestos as $idP) {
                    $stmtInsertCelda->execute([$idBodega, $idP, $idAgencia]);
                }

                // 2) Reactivar las que estén inactivas
                $placeholders = implode(',', array_fill(0, count($idPuestos), '?'));
                $this->connect->prepare(
                    "UPDATE bodega_inventario.matriz_acceso SET activo = 1
                 WHERE id_bodega = ? AND id_agencia = ? AND id_puesto IN ({$placeholders})"
                )->execute(array_merge([$idBodega, $idAgencia], $idPuestos));

                // 3) Eliminar asignaciones del producto en puestos NO seleccionados
                $stmtDel = $this->connect->prepare(
                    "DELETE map FROM bodega_inventario.matriz_acceso_productos map
                 INNER JOIN bodega_inventario.matriz_acceso ma ON ma.id = map.id_matriz
                 WHERE ma.id_bodega = ? AND ma.id_agencia = ? AND map.id_producto = ?
                   AND ma.id_puesto NOT IN ({$placeholders})"
                );
                $stmtDel->execute(array_merge([$idBodega, $idAgencia, $idProducto], $idPuestos));
                $totalEliminados += $stmtDel->rowCount();

                // 4) Insertar asignaciones nuevas
                $stmtIns = $this->connect->prepare(
                    "INSERT IGNORE INTO bodega_inventario.matriz_acceso_productos
                 (id_matriz, id_producto, por_categoria)
                 SELECT ma.id, ?, 0
                 FROM bodega_inventario.matriz_acceso ma
                 WHERE ma.id_bodega = ? AND ma.id_agencia = ? AND ma.activo = 1
                   AND ma.id_puesto IN ({$placeholders})"
                );
                $stmtIns->execute(array_merge([$idProducto, $idBodega, $idAgencia], $idPuestos));
                $totalInsertados += $stmtIns->rowCount();
            }

            $this->connect->commit();
            return $this->res->ok(
                "Asignaciones actualizadas: {$totalInsertados} nuevas, {$totalEliminados} eliminadas",
                ['insertadas' => $totalInsertados, 'eliminadas' => $totalEliminados]
            );
        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("[AsignacionProductos] asignarProductoMultiplesAgencias: " . $e->getMessage());
            return $this->res->fail('Error en la asignación múltiple', $e);
        }
    }

    // =============================================================================
//? SPRINT 1 — SOLICITUDES
// Métodos para agregar al archivo PHP existente
// =============================================================================
//
// 1. Añadir al array $metodosGet:
//      'obtenerBodegaAgencia',
//      'listarBodegasArea',
//      'listarProductosDisponibles',
//      'listarMisSolicitudes',
//
// 2. Pegar los métodos públicos y el helper privado al final de la clase,
//    antes del cierre del último corchete.
// =============================================================================


// ============================================================================
// SOLICITUDES — MÉTODOS PÚBLICOS (Sprint 1)
// ============================================================================

    /**
     * GET: modulo/obtenerBodegaAgencia
     *
     * Devuelve la bodega de tipo Agencia (id_tipo = 1) que corresponde
     * a la agencia del usuario autenticado (tomada de sesión).
     *
     * Respuestas:
     *   ok()   → bodega encontrada
     *   info() → la agencia no tiene bodega activa registrada
     *   fail() → error técnico o sin idAgencia en sesión
     */
    public function obtenerBodegaAgencia(): array
    {
        try {
            if (!$this->idAgencia) {
                return $this->res->fail('No se encontró la agencia del usuario en sesión');
            }

            $sql = "SELECT
                    b.id,
                    b.nombre,
                    b.id_tipo,
                    b.id_agencia,
                    b.restriccion_acceso_activa,
                    b.activo
                FROM bodega_inventario.bodegas b
                WHERE b.id_tipo   = 1
                  AND b.id_agencia = ?
                  AND b.activo     = 1
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$this->idAgencia]);
            $bodega = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                return $this->res->info('Tu agencia no tiene una bodega activa registrada');
            }

            // Convertir el flag a booleano para que el frontend lo reciba como true/false
            $bodega['restriccion_acceso_activa'] = (bool)$bodega['restriccion_acceso_activa'];

            return $this->res->ok('Bodega de agencia obtenida', ['bodega' => $bodega]);

        } catch (Exception $e) {
            error_log("Error en obtenerBodegaAgencia: " . $e->getMessage());
            return $this->res->fail('Error al obtener la bodega de agencia', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * GET: modulo/listarBodegasArea
     *
     * Devuelve todas las bodegas de área (id_tipo = 2) activas.
     * Para cada una incluye el flag `tiene_acceso`:
     *   - Si restriccion_acceso_activa = 0 → tiene_acceso siempre true
     *   - Si restriccion_acceso_activa = 1 → verifica en matriz_acceso
     *     usando el puesto e idAgencia del usuario en sesión
     *
     * Respuestas:
     *   ok()   → lista de bodegas con flag de acceso
     *   info() → no hay bodegas de área activas
     *   fail() → error técnico
     */
    public function listarBodegasArea(): array
    {
        try {
            $sql = "SELECT
                    b.id,
                    b.nombre,
                    b.id_tipo,
                    b.id_departamento_cooperativa,
                    b.restriccion_acceso_activa,
                    b.activo
                FROM bodega_inventario.bodegas b
                WHERE b.id_tipo = 2
                  AND b.activo  = 1
                ORDER BY b.nombre ASC";

            $stmt = $this->connect->query($sql);
            $bodegas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($bodegas)) {
                return $this->res->info('No hay bodegas de área activas disponibles');
            }

            foreach ($bodegas as &$bodega) {
                $bodega['restriccion_acceso_activa'] = (bool)$bodega['restriccion_acceso_activa'];

                // Si la bodega tiene restricción, verificar si el usuario tiene acceso
                if ($bodega['restriccion_acceso_activa']) {
                    $bodega['tiene_acceso'] = $this->_tieneAccesoMatriz(
                        (int)$bodega['id'],
                        $this->puesto,
                        (int)$this->idAgencia
                    );
                } else {
                    $bodega['tiene_acceso'] = true;
                }
            }
            unset($bodega); // Liberar referencia del foreach

            return $this->res->ok('Bodegas de área obtenidas', [
                'bodegas' => $bodegas,
                'total'   => count($bodegas),
            ]);

        } catch (Exception $e) {
            error_log("Error en listarBodegasArea: " . $e->getMessage());
            return $this->res->fail('Error al obtener las bodegas de área', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * GET: modulo/listarProductosDisponibles
     *
     * Catálogo paginado de la bodega con disponibilidad en tiempo real.
     * Aplica el filtro de matriz de acceso si la bodega es de área con
     * restriccion_acceso_activa = 1.
     *
     * Parámetros GET:
     *   id_bodega   int  requerido
     *   busqueda    str  opcional — filtra por nombre de producto o categoría
     *   pagina      int  opcional, default 1
     *   por_pagina  int  opcional, default 20, máximo 50
     *
     * Respuestas:
     *   ok()   → lista paginada de productos con disponibilidad
     *   info() → bodega sin productos disponibles (stock = 0 o filtro vacío)
     *   fail() → id_bodega faltante, bodega inactiva o error técnico
     */
    public function listarProductosDisponibles(): array
    {
        try {
            $idBodega  = (int)($_GET['id_bodega']  ?? 0);
            $busqueda  = trim($_GET['busqueda']     ?? '');
            $pagina    = max(1,  (int)($_GET['pagina']     ?? 1));
            $porPagina = min(50, max(1, (int)($_GET['por_pagina'] ?? 20)));
            $offset    = ($pagina - 1) * $porPagina;

            if (!$idBodega) {
                return $this->res->fail('El parámetro id_bodega es requerido');
            }

            // Verificar bodega y obtener su configuración de restricción
            $stmtBodega = $this->connect->prepare(
                "SELECT id, nombre, id_tipo, restriccion_acceso_activa
             FROM bodega_inventario.bodegas
             WHERE id = ? AND activo = 1
             LIMIT 1"
            );
            $stmtBodega->execute([$idBodega]);
            $bodega = $stmtBodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                return $this->res->fail('La bodega no existe o está inactiva');
            }

            // Parámetros base para los prepared statements
            $params = [$idBodega];

            // Filtro de búsqueda por texto
            $whereBusqueda = '';
            if ($busqueda !== '') {
                $whereBusqueda = " AND (p.nombre LIKE ? OR cp.nombre LIKE ?)";
                $like = "%{$busqueda}%";
                $params[] = $like;
                $params[] = $like;
            }

            // Filtro de matriz de acceso (solo bodegas de área con restricción)
            $whereMatriz = '';
            if ($bodega['restriccion_acceso_activa'] && (int)$bodega['id_tipo'] === 2) {
                if (!$this->_tieneAccesoMatriz($idBodega, $this->puesto, (int)$this->idAgencia)) {
                    return $this->res->info(
                        'No tienes acceso asignado a los productos de esta bodega'
                    );
                }
                // Subquery: solo productos permitidos según la matriz del usuario
                $whereMatriz = " AND p.id IN (
                SELECT DISTINCT map.id_producto
                FROM   bodega_inventario.matriz_acceso       ma
                INNER JOIN bodega_inventario.matriz_acceso_productos map ON map.id_matriz = ma.id
                WHERE  ma.id_bodega  = ?
                  AND  ma.id_puesto  = ?
                  AND  ma.id_agencia = ?
                  AND  ma.activo     = 1
            )";
                $params[] = $idBodega;
                $params[] = $this->puesto;
                $params[] = $this->idAgencia;
            }

            // Cláusula FROM + WHERE reutilizada en COUNT y en SELECT
            // NOTA: LIMIT y OFFSET se interpolan como enteros (no se enlazan con PDO)
            //       para evitar el bug de MySQL que los trata como string.
            $sqlBase = "FROM bodega_inventario.productos p
                    INNER JOIN bodega_inventario.tipos_producto       tp  ON tp.id  = p.id_tipo
                    INNER JOIN bodega_inventario.categorias_producto  cp  ON cp.id  = p.id_categoria
                    INNER JOIN bodega_inventario.stock                s
                        ON  s.id_producto = p.id
                        AND s.id_bodega   = ?
                    INNER JOIN bodega_inventario.productos_unidades   pu
                        ON  pu.id_producto = p.id
                        AND pu.es_default  = 1
                        AND pu.activo      = 1
                    INNER JOIN bodega_inventario.unidades_medida      um  ON um.id  = pu.id_unidad
                    WHERE p.activo = 1
                    {$whereBusqueda}
                    {$whereMatriz}";

            // Total de registros
            $stmtCount = $this->connect->prepare("SELECT COUNT(*) {$sqlBase}");
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            if ($total === 0) {
                return $this->res->info('No hay productos disponibles en esta bodega');
            }

            // Registros de la página solicitada
            $sqlData = "SELECT
                        p.id,
                        p.nombre,
                        p.descripcion,
                        tp.id   AS id_tipo,
                        tp.nombre AS tipo,
                        cp.id   AS id_categoria,
                        cp.nombre AS categoria,
                        um.id   AS id_unidad_default,
                        um.nombre AS unidad_default,
                        um.abreviatura AS abreviatura_unidad,
                        s.cantidad_total,
                        s.cantidad_reservada,
                        s.cantidad_disponible
                    {$sqlBase}
                    ORDER BY p.nombre ASC
                    LIMIT {$porPagina} OFFSET {$offset}";

            $stmtData = $this->connect->prepare($sqlData);
            $stmtData->execute($params);
            $productos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            $paginas = (int)ceil($total / $porPagina);

            return $this->res->ok('Productos obtenidos', [
                'productos'  => $productos,
                'total'      => $total,
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'paginas'    => $paginas,
                'bodega'     => [
                    'id'     => (int)$bodega['id'],
                    'nombre' => $bodega['nombre'],
                ],
            ]);

        } catch (Exception $e) {
            error_log("Error en listarProductosDisponibles: " . $e->getMessage());
            return $this->res->fail('Error al obtener los productos disponibles', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * GET: modulo/listarMisSolicitudes
     *
     * Solicitudes paginadas del usuario autenticado.
     * Devuelve la cabecera con un resumen del detalle (total y gestionados).
     *
     * Parámetros GET:
     *   id_bodega   int  opcional — filtra por bodega específica
     *   estado      int  opcional — filtra por id_estado (1=Reservada … 4=Cancelada)
     *   pagina      int  opcional, default 1
     *   por_pagina  int  opcional, default 20, máximo 50
     *
     * Respuestas:
     *   ok()   → siempre (array vacío si no hay solicitudes)
     *   fail() → error técnico
     */
    public function listarMisSolicitudes(): array
    {
        try {
            $idBodega  = isset($_GET['id_bodega']) && $_GET['id_bodega'] !== ''
                ? (int)$_GET['id_bodega'] : null;
            $estado    = isset($_GET['estado']) && $_GET['estado'] !== ''
                ? (int)$_GET['estado'] : null;
            $pagina    = max(1,  (int)($_GET['pagina']     ?? 1));
            $porPagina = min(50, max(1, (int)($_GET['por_pagina'] ?? 20)));
            $offset    = ($pagina - 1) * $porPagina;

            $params = [$this->idUsuario];
            $whereExtra = '';

            if ($idBodega !== null) {
                $whereExtra .= " AND s.id_bodega = ?";
                $params[] = $idBodega;
            }

            if ($estado !== null) {
                $whereExtra .= " AND s.id_estado = ?";
                $params[] = $estado;
            }

            $sqlBase = "FROM bodega_inventario.solicitudes s
                    INNER JOIN bodega_inventario.bodegas           b  ON b.id  = s.id_bodega
                    INNER JOIN bodega_inventario.tipos_bodega      tb ON tb.id = b.id_tipo
                    INNER JOIN bodega_inventario.estados_solicitud es ON es.id = s.id_estado
                    WHERE s.id_usuario = ?
                    {$whereExtra}";

            // Total de registros
            $stmtCount = $this->connect->prepare("SELECT COUNT(*) {$sqlBase}");
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            // Registros de la página solicitada
            // Las subqueries de conteo usan s.id del contexto externo — sin parámetros extra
            $sqlData = "SELECT
                        s.id,
                        s.id_bodega,
                        b.nombre               AS bodega,
                        tb.id                  AS id_tipo_bodega,
                        tb.nombre              AS tipo_bodega,
                        s.id_estado,
                        es.nombre              AS estado,
                        s.observaciones,
                        s.created_at,
                        s.updated_at,
                        (
                            SELECT COUNT(*)
                            FROM bodega_inventario.solicitudes_detalle sd
                            WHERE sd.id_solicitud = s.id
                        ) AS total_renglones,
                        (
                            SELECT COUNT(*)
                            FROM bodega_inventario.solicitudes_detalle sd
                            WHERE sd.id_solicitud = s.id
                              AND sd.id_usuario_gestion IS NOT NULL
                        ) AS renglones_gestionados
                    {$sqlBase}
                    ORDER BY s.created_at DESC
                    LIMIT {$porPagina} OFFSET {$offset}";

            $stmtData = $this->connect->prepare($sqlData);
            $stmtData->execute($params);
            $solicitudes = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            $paginas = $total > 0 ? (int)ceil($total / $porPagina) : 0;

            return $this->res->ok('Solicitudes obtenidas', [
                'solicitudes' => $solicitudes,
                'total'       => $total,
                'pagina'      => $pagina,
                'por_pagina'  => $porPagina,
                'paginas'     => $paginas,
            ]);

        } catch (Exception $e) {
            error_log("Error en listarMisSolicitudes: " . $e->getMessage());
            return $this->res->fail('Error al obtener las solicitudes', $e);
        }
    }


// ============================================================================
// SOLICITUDES — HELPERS PRIVADOS (Sprint 1)
// ============================================================================

    /**
     * Verifica si el usuario autenticado tiene una entrada activa en la
     * matriz de acceso de la bodega indicada.
     *
     * Se usa únicamente para bodegas de área con restriccion_acceso_activa = 1.
     * Para las de agencia no es necesario llamar este método.
     *
     * @param  int       $idBodega  ID de la bodega de área
     * @param  int|null  $idPuesto  Puesto del usuario (de sesión)
     * @param  int       $idAgencia Agencia del usuario (de sesión)
     * @return bool      true si tiene acceso, false si no
     */
    private function _tieneAccesoMatriz(int $idBodega, ?int $idPuesto, int $idAgencia): bool
    {
        try {
            if (!$idPuesto) {
                return false;
            }

            $sql = "SELECT COUNT(*)
                FROM bodega_inventario.matriz_acceso
                WHERE id_bodega  = ?
                  AND id_puesto  = ?
                  AND id_agencia = ?
                  AND activo     = 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$idBodega, $idPuesto, $idAgencia]);
            return (int)$stmt->fetchColumn() > 0;

        } catch (Exception $e) {
            error_log("Error en _tieneAccesoMatriz: " . $e->getMessage());
            return false;
        }
    }
    // =============================================================================
// SPRINT 2 — SOLICITUDES
// Métodos para agregar al archivo PHP existente
// =============================================================================
//
// 1. Añadir al array $metodosGet:
//      'obtenerUnidadesProducto',
//
// 2. Añadir al array $metodosPost:
//      'crearSolicitud',
//      'cancelarSolicitud',
//
// 3. Pegar los métodos públicos y los helpers privados al final de la clase.
//    Los helpers _registrarMovimiento y _obtenerTipoProducto son reutilizables
//    en Sprint 4 (entregas del encargado).
// =============================================================================


// ============================================================================
// SOLICITUDES — MÉTODOS PÚBLICOS (Sprint 2)
// ============================================================================

    /**
     * GET: modulo/obtenerUnidadesProducto
     *
     * Devuelve todas las unidades activas de un producto en una bodega
     * con su disponibilidad en tiempo real. Usado por el modal de solicitud
     * para que el usuario elija unidad antes de especificar la cantidad.
     *
     * Parámetros GET:
     *   id_producto int  requerido
     *   id_bodega   int  requerido
     *
     * Respuestas:
     *   ok()   → lista de unidades con stock
     *   info() → sin unidades activas para este producto/bodega
     *   fail() → parámetros faltantes o error técnico
     */
    public function obtenerUnidadesProducto(): array
    {
        try {
            $idProducto = (int)($_GET['id_producto'] ?? 0);
            $idBodega   = (int)($_GET['id_bodega']   ?? 0);

            if (!$idProducto || !$idBodega) {
                return $this->res->fail(
                    'Los parámetros id_producto e id_bodega son requeridos'
                );
            }

            // LEFT JOIN con stock para que también aparezcan unidades sin stock registrado
            // (puede ocurrir si aún no hay ingreso para esa unidad)
            $sql = "SELECT
                    um.id   AS id_unidad,
                    um.nombre,
                    um.abreviatura,
                    pu.es_default,
                    COALESCE(s.cantidad_total,      0) AS cantidad_total,
                    COALESCE(s.cantidad_reservada,  0) AS cantidad_reservada,
                    COALESCE(s.cantidad_disponible, 0) AS cantidad_disponible
                FROM  bodega_inventario.productos_unidades pu
                INNER JOIN bodega_inventario.unidades_medida um
                    ON um.id = pu.id_unidad
                LEFT JOIN bodega_inventario.stock s
                    ON  s.id_producto = pu.id_producto
                    AND s.id_unidad   = pu.id_unidad
                    AND s.id_bodega   = ?
                WHERE pu.id_producto = ?
                  AND pu.activo      = 1
                ORDER BY pu.es_default DESC, um.nombre ASC";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$idBodega, $idProducto]);
            $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($unidades)) {
                return $this->res->info(
                    'Este producto no tiene unidades activas registradas'
                );
            }

            // Convertir es_default a booleano
            foreach ($unidades as &$u) {
                $u['es_default']         = (bool)$u['es_default'];
                $u['cantidad_disponible'] = (float)$u['cantidad_disponible'];
            }
            unset($u);

            return $this->res->ok('Unidades obtenidas', ['unidades' => $unidades]);

        } catch (Exception $e) {
            error_log("Error en obtenerUnidadesProducto: " . $e->getMessage());
            return $this->res->fail('Error al obtener las unidades del producto', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * POST: modulo/crearSolicitud
     *
     * Crea una solicitud reservando el stock de forma atómica con SELECT FOR UPDATE.
     * Si dos usuarios solicitan el último producto simultáneamente, solo uno gana;
     * el segundo recibe fail() con el stock disponible real en ese momento.
     *
     * Payload:
     *   id_bodega      int    requerido
     *   id_producto    int    requerido
     *   id_unidad      int    requerido
     *   cantidad       float  requerido  (> 0)
     *   observaciones  str    opcional
     *
     * Flujo de la transacción:
     *   1. SELECT stock FOR UPDATE  → bloquea la fila durante la transacción
     *   2. Validar cantidad ≤ disponible
     *   3. INSERT solicitudes (cabecera, estado=1 Reservada)
     *   4. INSERT solicitudes_detalle
     *   5. UPDATE stock cantidad_reservada + cantidad
     *   6. INSERT movimientos_stock tipo 9 (Reserva)
     *   7. COMMIT
     *
     * Respuestas:
     *   ok()   → solicitud creada, id_solicitud en extra
     *   fail() → validación, stock insuficiente o error técnico
     */
    public function crearSolicitud($datos): array
    {
        try {
            if (empty($this->idUsuario)) {
                return $this->res->fail('No hay sesión de usuario activa');
            }

            $datos = $this->limpiarDatos($datos);

            $idBodega   = (int)($datos->id_bodega   ?? 0);
            $idProducto = (int)($datos->id_producto ?? 0);
            $idUnidad   = (int)($datos->id_unidad   ?? 0);
            $cantidad   = (float)($datos->cantidad  ?? 0);
            $obs        = !empty($datos->observaciones) ? $datos->observaciones : null;

            // Validar campos requeridos
            if (!$idBodega || !$idProducto || !$idUnidad || $cantidad <= 0) {
                return $this->res->fail(
                    'Campos requeridos: id_bodega, id_producto, id_unidad y cantidad (> 0)'
                );
            }

            // ── Inicio de transacción ────────────────────────────────────────────
            $this->connect->beginTransaction();

            // 1. Bloquear la fila de stock con FOR UPDATE
            //    Cualquier otra transacción concurrente para el mismo producto
            //    quedará en espera hasta que esta haga COMMIT o ROLLBACK.
            $sqlStock = "SELECT id, cantidad_disponible, cantidad_reservada
                     FROM   bodega_inventario.stock
                     WHERE  id_bodega   = ?
                       AND  id_producto = ?
                       AND  id_unidad   = ?
                     FOR UPDATE";
            $stmtStock = $this->connect->prepare($sqlStock);
            $stmtStock->execute([$idBodega, $idProducto, $idUnidad]);
            $stock = $stmtStock->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                $this->connect->rollBack();
                return $this->res->fail(
                    'No existe stock registrado para este producto y unidad en la bodega'
                );
            }

            // 2. Validar disponibilidad (ya con el lock activo)
            $disponible = (float)$stock['cantidad_disponible'];
            if ($cantidad > $disponible) {
                $this->connect->rollBack();
                return $this->res->fail(
                    "Stock insuficiente. Disponible: {$disponible}",
                    null,
                    ['disponible' => $disponible, 'solicitado' => $cantidad]
                );
            }

            // 3. INSERT cabecera
            //    created_at y updated_at tienen DEFAULT CURRENT_TIMESTAMP — no se insertan
            $sqlSol = "INSERT INTO bodega_inventario.solicitudes
                       (id_usuario, id_bodega, id_estado, observaciones)
                   VALUES (?, ?, 1, ?)";
            $stmtSol = $this->connect->prepare($sqlSol);
            $stmtSol->execute([$this->idUsuario, $idBodega, $obs]);
            $idSolicitud = (int)$this->connect->lastInsertId();

            // 4. INSERT detalle
            //    correlativo_inicial_asignado / correlativo_final_asignado / id_lote_correlativo
            //    se calculan en Sprint 5 — quedan NULL por ahora
            $sqlDet = "INSERT INTO bodega_inventario.solicitudes_detalle
                       (id_solicitud, id_producto, id_unidad, cantidad_solicitada)
                   VALUES (?, ?, ?, ?)";
            $stmtDet = $this->connect->prepare($sqlDet);
            $stmtDet->execute([$idSolicitud, $idProducto, $idUnidad, $cantidad]);
            $idDetalle = (int)$this->connect->lastInsertId();

            // 5. Incrementar cantidad_reservada en stock
            $sqlReserva = "UPDATE bodega_inventario.stock
                       SET    cantidad_reservada = cantidad_reservada + ?,
                              updated_at         = NOW()
                       WHERE  id_bodega   = ?
                         AND  id_producto = ?
                         AND  id_unidad   = ?";
            $stmtRes = $this->connect->prepare($sqlReserva);
            $stmtRes->execute([$cantidad, $idBodega, $idProducto, $idUnidad]);

            // 6. Registrar movimiento tipo 9 (Reserva) — trazabilidad inmutable
            $this->_registrarMovimiento(
                9,                      // tipo: Reserva
                $idBodega,
                $idProducto,
                $idUnidad,
                $cantidad,
                'solicitudes_detalle',  // entidad_origen
                $idDetalle              // id_entidad_origen
            );

            // ── Confirmar transacción ────────────────────────────────────────────
            $this->connect->commit();

            return $this->res->ok(
                'Solicitud creada y stock reservado correctamente',
                null,
                ['id_solicitud' => $idSolicitud]
            );

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("Error en crearSolicitud: " . $e->getMessage());
            return $this->res->fail('Error al crear la solicitud', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * POST: modulo/cancelarSolicitud
     *
     * Cancela una solicitud en estado Reservada liberando el stock reservado.
     * Solo el usuario propietario puede cancelar su propia solicitud.
     *
     * Payload:
     *   id_solicitud  int  requerido
     *
     * Flujo:
     *   1. Verificar que la solicitud pertenece al usuario y está en estado 1
     *   2. Por cada renglón del detalle:
     *      - UPDATE stock cantidad_reservada - cantidad (GREATEST para no bajar de 0)
     *      - INSERT movimientos_stock tipo 10 (Liberación de reserva)
     *   3. UPDATE solicitudes id_estado = 4 (Cancelada)
     *   4. COMMIT
     *
     * Respuestas:
     *   ok()   → cancelado y reserva liberada
     *   fail() → no encontrada, no es del usuario, ya no está Reservada, o error
     */
    public function cancelarSolicitud($datos): array
    {
        try {
            $datos       = $this->limpiarDatos($datos);
            $idSolicitud = (int)($datos->id_solicitud ?? 0);

            if (!$idSolicitud) {
                return $this->res->fail('El campo id_solicitud es requerido');
            }

            $this->connect->beginTransaction();

            // 1. Verificar propiedad y estado (FOR UPDATE para evitar doble cancelación)
            $sqlVerif = "SELECT id, id_estado, id_bodega
                     FROM   bodega_inventario.solicitudes
                     WHERE  id         = ?
                       AND  id_usuario = ?
                     FOR UPDATE";
            $stmtVerif = $this->connect->prepare($sqlVerif);
            $stmtVerif->execute([$idSolicitud, $this->idUsuario]);
            $solicitud = $stmtVerif->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                $this->connect->rollBack();
                return $this->res->fail('Solicitud no encontrada o no te pertenece');
            }

            if ((int)$solicitud['id_estado'] !== 1) {
                $this->connect->rollBack();
                return $this->res->fail(
                    'Solo se pueden cancelar solicitudes en estado Reservada'
                );
            }

            // 2. Obtener renglones del detalle para liberar la reserva
            $sqlDet = "SELECT id, id_producto, id_unidad, cantidad_solicitada
                   FROM   bodega_inventario.solicitudes_detalle
                   WHERE  id_solicitud = ?";
            $stmtDet = $this->connect->prepare($sqlDet);
            $stmtDet->execute([$idSolicitud]);
            $renglones = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            foreach ($renglones as $renglon) {
                $idBodega   = (int)$solicitud['id_bodega'];
                $idProducto = (int)$renglon['id_producto'];
                $idUnidad   = (int)$renglon['id_unidad'];
                $cantidad   = (float)$renglon['cantidad_solicitada'];

                // Liberar reserva — GREATEST(0, ...) protege contra valores negativos
                $sqlLiberar = "UPDATE bodega_inventario.stock
                           SET    cantidad_reservada = GREATEST(0, cantidad_reservada - ?),
                                  updated_at         = NOW()
                           WHERE  id_bodega   = ?
                             AND  id_producto = ?
                             AND  id_unidad   = ?";
                $stmtLib = $this->connect->prepare($sqlLiberar);
                $stmtLib->execute([$cantidad, $idBodega, $idProducto, $idUnidad]);

                // Registrar movimiento tipo 10 (Liberación de reserva)
                $this->_registrarMovimiento(
                    10,
                    $idBodega,
                    $idProducto,
                    $idUnidad,
                    $cantidad,
                    'solicitudes_detalle',
                    (int)$renglon['id']
                );
            }

            // 3. Cambiar estado a Cancelada (4)
            $sqlEstado = "UPDATE bodega_inventario.solicitudes
                      SET    id_estado  = 4,
                             updated_at = NOW()
                      WHERE  id = ?";
            $stmtEst = $this->connect->prepare($sqlEstado);
            $stmtEst->execute([$idSolicitud]);

            $this->connect->commit();

            return $this->res->ok('Solicitud cancelada y reserva liberada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("Error en cancelarSolicitud: " . $e->getMessage());
            return $this->res->fail('Error al cancelar la solicitud', $e);
        }
    }


// ============================================================================
// SOLICITUDES — HELPERS PRIVADOS (Sprint 2 · reutilizables en Sprint 4)
// ============================================================================

    /**
     * Inserta un registro en movimientos_stock.
     *
     * La tabla es SOLO INSERT (comentario en el schema): nunca se hacen
     * UPDATE ni DELETE sobre ella.
     *
     * Columnas relevantes:
     *   entidad_origen   → nombre de la tabla que originó el movimiento
     *   id_entidad_origen → ID de la fila en esa tabla
     *
     * Tipos de movimiento usados en solicitudes:
     *   9  → Reserva (al crear solicitud)
     *   10 → Liberación de reserva (al cancelar o rechazar)
     *   5  → Baja por entrega (Sprint 4)
     *
     * @param int    $tipo             ID en tipos_movimiento
     * @param int    $idBodega
     * @param int    $idProducto
     * @param int    $idUnidad
     * @param float  $cantidad
     * @param string $entidadOrigen    Nombre de la tabla origen (ej. 'solicitudes_detalle')
     * @param int    $idEntidadOrigen  ID del registro origen
     * @param string|null $idReceptor  Solo para entregas (Sprint 4)
     */
    private function _registrarMovimiento(
        int    $tipo,
        int    $idBodega,
        int    $idProducto,
        int    $idUnidad,
        float  $cantidad,
        string $entidadOrigen,
        int    $idEntidadOrigen,
        ?string $idReceptor = null
    ): void {
        $sql = "INSERT INTO bodega_inventario.movimientos_stock
                (id_bodega, id_producto, id_unidad, id_tipo_movimiento,
                 cantidad, entidad_origen, id_entidad_origen,
                 id_usuario, id_usuario_receptor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->connect->prepare($sql);
        $stmt->execute([
            $idBodega,
            $idProducto,
            $idUnidad,
            $tipo,
            $cantidad,
            $entidadOrigen,
            $idEntidadOrigen,
            $this->idUsuario,
            $idReceptor,
        ]);
    }

    /**
     * Obtiene el id_tipo del producto.
     * Usado para aplicar reglas distintas según tipo:
     *   1 = Correlativo, 2 = Expiración, 3 = Normal
     *
     * Retorna 0 si el producto no existe (no debe ocurrir si ya se validó).
     */
    private function _obtenerTipoProducto(int $idProducto): int
    {
        try {
            $sql  = "SELECT id_tipo FROM bodega_inventario.productos
                 WHERE id = ? LIMIT 1";
            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$idProducto]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error en _obtenerTipoProducto: " . $e->getMessage());
            return 0;
        }
    }

    // =============================================================================
// SPRINT 3 & 4 — SOLICITUDES (Encargado)
// Métodos para agregar al archivo PHP existente
// =============================================================================
//
// 1. Añadir al array $metodosGet:
//      'listarSolicitudesEncargado',
//      'obtenerDetalleSolicitud',
//      'obtenerBodegaEncargado',
//
// 2. Añadir al array $metodosPost:
//      'entregarSolicitud',
//      'rechazarSolicitud',
//
// 3. Pegar los métodos públicos y los helpers privados al final de la clase.
// =============================================================================


// ============================================================================
// ENCARGADO — MÉTODOS PÚBLICOS (Sprint 4)
// ============================================================================

    /**
     * GET: modulo/obtenerBodegaEncargado
     *
     * Devuelve la bodega que administra el usuario autenticado.
     *
     * Prioridad de búsqueda:
     *   1. Bodega de área → inv_encargados_bodega_area (activo = 1)
     *   2. Bodega de agencia → bodegas WHERE id_tipo = 1 AND id_agencia = sesión
     *
     * Respuestas:
     *   ok()   → bodega encontrada con flag tipo_bodega
     *   info() → el usuario no tiene bodega asignada como encargado
     *   fail() → error técnico
     */
    public function obtenerBodegaEncargado(): array
    {
        try {
            $idBodega = $this->_obtenerBodegaEncargado();

            if (!$idBodega) {
                return $this->res->info(
                    'No tienes una bodega asignada como encargado'
                );
            }

            $sql = "SELECT
                    b.id,
                    b.nombre,
                    b.id_tipo,
                    tb.nombre AS tipo_bodega,
                    b.restriccion_acceso_activa,
                    b.activo
                FROM bodega_inventario.bodegas b
                INNER JOIN bodega_inventario.tipos_bodega tb ON tb.id = b.id_tipo
                WHERE b.id = ?
                LIMIT 1";

            $stmt = $this->connect->prepare($sql);
            $stmt->execute([$idBodega]);
            $bodega = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                return $this->res->fail('La bodega asignada no existe o está inactiva');
            }

            $bodega['restriccion_acceso_activa'] = (bool)$bodega['restriccion_acceso_activa'];

            return $this->res->ok('Bodega del encargado obtenida', ['bodega' => $bodega]);

        } catch (Exception $e) {
            error_log("Error en obtenerBodegaEncargado: " . $e->getMessage());
            return $this->res->fail('Error al obtener la bodega del encargado', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * GET: modulo/listarSolicitudesEncargado
     *
     * Bandeja de solicitudes de la bodega que gestiona el encargado.
     * Incluye el nombre del primer producto como resumen visual en la tabla.
     *
     * Parámetros GET:
     *   estado      int  opcional  (1=Reservada … 4=Cancelada)
     *   busqueda    str  opcional  busca por id_usuario solicitante
     *   fecha_desde str  opcional  YYYY-MM-DD
     *   fecha_hasta str  opcional  YYYY-MM-DD
     *   pagina      int  default 1
     *   por_pagina  int  default 20, máximo 50
     *
     * Respuestas:
     *   ok()   → lista paginada (puede ser vacía)
     *   fail() → sin bodega asignada o error técnico
     */
    public function listarSolicitudesEncargado(): array
    {
        try {
            $idBodega = $this->_obtenerBodegaEncargado();

            if (!$idBodega) {
                return $this->res->fail(
                    'No tienes una bodega asignada como encargado'
                );
            }

            $estado     = isset($_GET['estado']) && $_GET['estado'] !== ''
                ? (int)$_GET['estado'] : null;
            $busqueda   = trim($_GET['busqueda']    ?? '');
            $fechaDesde = trim($_GET['fecha_desde'] ?? '');
            $fechaHasta = trim($_GET['fecha_hasta'] ?? '');
            $pagina     = max(1,  (int)($_GET['pagina']     ?? 1));
            $porPagina  = min(50, max(1, (int)($_GET['por_pagina'] ?? 20)));
            $offset     = ($pagina - 1) * $porPagina;

            $params     = [$idBodega];
            $whereExtra = '';

            if ($estado !== null) {
                $whereExtra .= " AND s.id_estado = ?";
                $params[] = $estado;
            }
            if ($busqueda !== '') {
                $whereExtra .= " AND s.id_usuario LIKE ?";
                $params[] = "%{$busqueda}%";
            }
            if ($fechaDesde !== '') {
                $whereExtra .= " AND DATE(s.created_at) >= ?";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta !== '') {
                $whereExtra .= " AND DATE(s.created_at) <= ?";
                $params[] = $fechaHasta;
            }

            $sqlBase = "FROM bodega_inventario.solicitudes s
                    INNER JOIN bodega_inventario.estados_solicitud es
                        ON es.id = s.id_estado
                    WHERE s.id_bodega = ?
                    {$whereExtra}";

            // Total
            $stmtCount = $this->connect->prepare("SELECT COUNT(*) {$sqlBase}");
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            // Datos con subqueries escalares (evitar JOIN que compliquen paginación)
            $sqlData = "SELECT
                        s.id,
                        s.id_usuario       AS solicitante,
                        s.id_bodega,
                        s.id_estado,
                        es.nombre          AS estado,
                        s.observaciones,
                        s.created_at,
                        s.updated_at,
                        (
                          SELECT p.nombre
                          FROM   bodega_inventario.solicitudes_detalle sd2
                          INNER JOIN bodega_inventario.productos p
                              ON p.id = sd2.id_producto
                          WHERE  sd2.id_solicitud = s.id
                          LIMIT  1
                        ) AS primer_producto,
                        (
                          SELECT COUNT(*)
                          FROM   bodega_inventario.solicitudes_detalle sd3
                          WHERE  sd3.id_solicitud = s.id
                        ) AS total_renglones,
                        (
                          SELECT COUNT(*)
                          FROM   bodega_inventario.solicitudes_detalle sd4
                          WHERE  sd4.id_solicitud  = s.id
                            AND  sd4.id_usuario_gestion IS NOT NULL
                        ) AS renglones_gestionados
                    {$sqlBase}
                    ORDER BY s.id_estado ASC, s.created_at DESC
                    LIMIT {$porPagina} OFFSET {$offset}";

            $stmtData = $this->connect->prepare($sqlData);
            $stmtData->execute($params);
            $solicitudes = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Solicitudes del encargado obtenidas', [
                'solicitudes' => $solicitudes,
                'total'       => $total,
                'pagina'      => $pagina,
                'por_pagina'  => $porPagina,
                'paginas'     => $total > 0 ? (int)ceil($total / $porPagina) : 0,
            ]);

        } catch (Exception $e) {
            error_log("Error en listarSolicitudesEncargado: " . $e->getMessage());
            return $this->res->fail('Error al obtener las solicitudes', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * GET: modulo/obtenerDetalleSolicitud
     *
     * Detalle completo de una solicitud: cabecera + renglones.
     * Accesible tanto por el solicitante (dueño) como por el encargado
     * de la bodega correspondiente.
     *
     * Parámetro GET:
     *   id   int  requerido
     *
     * Respuestas:
     *   ok()   → cabecera + array de renglones
     *   fail() → no encontrada, sin permiso o error técnico
     */
    public function obtenerDetalleSolicitud(): array
    {
        try {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                return $this->res->fail('El parámetro id es requerido');
            }

            // Obtener cabecera
            $sqlCab = "SELECT
                       s.id, s.id_usuario AS solicitante,
                       s.id_bodega,
                       b.nombre          AS bodega,
                       tb.nombre         AS tipo_bodega,
                       s.id_estado,
                       es.nombre         AS estado,
                       s.observaciones,
                       s.created_at,
                       s.updated_at
                   FROM bodega_inventario.solicitudes s
                   INNER JOIN bodega_inventario.bodegas           b  ON b.id  = s.id_bodega
                   INNER JOIN bodega_inventario.tipos_bodega      tb ON tb.id = b.id_tipo
                   INNER JOIN bodega_inventario.estados_solicitud es ON es.id = s.id_estado
                   WHERE s.id = ?
                   LIMIT 1";

            $stmtCab = $this->connect->prepare($sqlCab);
            $stmtCab->execute([$id]);
            $cabecera = $stmtCab->fetch(PDO::FETCH_ASSOC);

            if (!$cabecera) {
                return $this->res->fail('Solicitud no encontrada');
            }

            // Verificar acceso: debe ser el solicitante o el encargado de la bodega
            $esPropietario = $cabecera['solicitante'] === $this->idUsuario;
            $esEncargado   = $this->_obtenerBodegaEncargado() === (int)$cabecera['id_bodega'];

            if (!$esPropietario && !$esEncargado) {
                return $this->res->fail('No tienes permiso para ver esta solicitud');
            }

            // Obtener renglones
            $sqlDet = "SELECT
                       sd.id,
                       sd.id_producto,
                       p.nombre           AS producto,
                       tp.nombre          AS tipo_producto,
                       tp.id              AS id_tipo_producto,
                       sd.id_unidad,
                       um.nombre          AS unidad,
                       um.abreviatura     AS abreviatura_unidad,
                       sd.cantidad_solicitada,
                       sd.cantidad_entregada,
                       sd.correlativo_inicial_asignado,
                       sd.correlativo_final_asignado,
                       sd.id_usuario_gestion,
                       sd.fecha_gestion,
                       sd.motivo_rechazo
                   FROM bodega_inventario.solicitudes_detalle sd
                   INNER JOIN bodega_inventario.productos      p   ON p.id  = sd.id_producto
                   INNER JOIN bodega_inventario.tipos_producto tp  ON tp.id = p.id_tipo
                   INNER JOIN bodega_inventario.unidades_medida um ON um.id = sd.id_unidad
                   WHERE sd.id_solicitud = ?
                   ORDER BY sd.id ASC";

            $stmtDet = $this->connect->prepare($sqlDet);
            $stmtDet->execute([$id]);
            $renglones = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            return $this->res->ok('Detalle de solicitud obtenido', [
                'cabecera'  => $cabecera,
                'renglones' => $renglones,
            ]);

        } catch (Exception $e) {
            error_log("Error en obtenerDetalleSolicitud: " . $e->getMessage());
            return $this->res->fail('Error al obtener el detalle de la solicitud', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * POST: modulo/entregarSolicitud
     *
     * Entrega una solicitud Reservada aplicando automáticamente:
     *   - FIFO sobre lotes_normal para productos tipo Normal (3)
     *   - PEPS sobre lotes_expiracion para productos tipo Expiración (2)
     *
     * El encargado no elige el lote; el sistema aplica la regla correspondiente.
     * Los correlativos (tipo 1) se entregan en Sprint 5.
     *
     * Payload:
     *   id_solicitud  int  requerido
     *
     * Flujo:
     *   1. Verificar bodega del encargado y cierre mensual
     *   2. SELECT solicitud + detalle FOR UPDATE
     *   3. Para cada renglón: _aplicarFIFO() o _aplicarPEPS()
     *   4. UPDATE stock (total y reservada)
     *   5. UPDATE detalle (cantidad_entregada, gestion)
     *   6. UPDATE solicitud (id_estado = 2)
     *   7. COMMIT
     *
     * Respuestas:
     *   ok()   → entrega procesada
     *   fail() → validación, cierre activo, producto correlativo o error técnico
     */
    public function entregarSolicitud($datos): array
    {
        try {
            $datos       = $this->limpiarDatos($datos);
            $idSolicitud = (int)($datos->id_solicitud ?? 0);

            if (!$idSolicitud) {
                return $this->res->fail('El campo id_solicitud es requerido');
            }

            $idBodegaEncargado = $this->_obtenerBodegaEncargado();
            if (!$idBodegaEncargado) {
                return $this->res->fail('No tienes una bodega asignada como encargado');
            }

            // Bloquear entregas si hay cierre mensual activo
            $fechaCorte = $this->_verificarCierreActivo();
            if ($fechaCorte) {
                return $this->res->fail(
                    "No se pueden procesar entregas: hay un cierre mensual activo desde {$fechaCorte}"
                );
            }

            $this->connect->beginTransaction();

            // Bloquear solicitud
            $sqlSol = "SELECT id, id_estado, id_bodega, id_usuario
                   FROM   bodega_inventario.solicitudes
                   WHERE  id = ? AND id_bodega = ?
                   FOR UPDATE";
            $stmtSol = $this->connect->prepare($sqlSol);
            $stmtSol->execute([$idSolicitud, $idBodegaEncargado]);
            $solicitud = $stmtSol->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                $this->connect->rollBack();
                return $this->res->fail('Solicitud no encontrada en tu bodega');
            }

            if ((int)$solicitud['id_estado'] !== 1) {
                $this->connect->rollBack();
                return $this->res->fail('Solo se pueden entregar solicitudes en estado Reservada');
            }

            // Obtener renglones del detalle
            $sqlDet = "SELECT id, id_producto, id_unidad, cantidad_solicitada
                   FROM   bodega_inventario.solicitudes_detalle
                   WHERE  id_solicitud = ?";
            $stmtDet = $this->connect->prepare($sqlDet);
            $stmtDet->execute([$idSolicitud]);
            $renglones = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            $idBodega    = (int)$solicitud['id_bodega'];
            $idReceptor  = $solicitud['id_usuario'];

            foreach ($renglones as $renglon) {
                $idProducto = (int)$renglon['id_producto'];
                $idUnidad   = (int)$renglon['id_unidad'];
                $cantidad   = (float)$renglon['cantidad_solicitada'];
                $idDetalle  = (int)$renglon['id'];
                $idTipo     = $this->_obtenerTipoProducto($idProducto);

                // Correlativos → Sprint 5
                if ($idTipo === 1) {
                    $this->connect->rollBack();
                    return $this->res->fail(
                        'Los productos de tipo Correlativo se entregan desde entregarSolicitudCorrelativo (Sprint 5)'
                    );
                }

                // Aplicar regla de salida según tipo
                $consumido = $idTipo === 2
                    ? $this->_aplicarPEPS($idBodega, $idProducto, $idUnidad, $cantidad, $idDetalle, $idReceptor)
                    : $this->_aplicarFIFO($idBodega, $idProducto, $idUnidad, $cantidad, $idDetalle, $idReceptor);

                // Actualizar stock: bajar total por lo consumido, reservada por lo solicitado
                $sqlStock = "UPDATE bodega_inventario.stock
                         SET    cantidad_total     = GREATEST(0, cantidad_total     - ?),
                                cantidad_reservada = GREATEST(0, cantidad_reservada - ?),
                                updated_at         = NOW()
                         WHERE  id_bodega   = ?
                           AND  id_producto = ?
                           AND  id_unidad   = ?";
                $stmtSt = $this->connect->prepare($sqlStock);
                $stmtSt->execute([$consumido, $cantidad, $idBodega, $idProducto, $idUnidad]);

                // Actualizar renglón del detalle
                $sqlUpdDet = "UPDATE bodega_inventario.solicitudes_detalle
                          SET    cantidad_entregada = ?,
                                 id_usuario_gestion = ?,
                                 fecha_gestion      = NOW()
                          WHERE  id = ?";
                $stmtUpdDet = $this->connect->prepare($sqlUpdDet);
                $stmtUpdDet->execute([$consumido, $this->idUsuario, $idDetalle]);
            }

            // Cambiar cabecera a Entregada (2)
            $sqlCab = "UPDATE bodega_inventario.solicitudes
                   SET    id_estado  = 2,
                          updated_at = NOW()
                   WHERE  id = ?";
            $stmtCab = $this->connect->prepare($sqlCab);
            $stmtCab->execute([$idSolicitud]);

            $this->connect->commit();

            return $this->res->ok('Solicitud entregada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("Error en entregarSolicitud: " . $e->getMessage());
            return $this->res->fail('Error al procesar la entrega', $e);
        }
    }

// ----------------------------------------------------------------------------

    /**
     * POST: modulo/rechazarSolicitud
     *
     * Rechaza una solicitud Reservada con un motivo obligatorio.
     * Libera el stock reservado y registra el movimiento de liberación.
     *
     * Payload:
     *   id_solicitud   int  requerido
     *   motivo_rechazo str  requerido (obligatorio según regla de negocio)
     *
     * Flujo:
     *   1. Validar bodega encargado y estado Reservada
     *   2. Para cada renglón: liberar reserva + movimiento tipo 10
     *   3. UPDATE detalle (motivo_rechazo, gestion)
     *   4. UPDATE solicitud id_estado = 3 (Rechazada)
     *   5. COMMIT
     *
     * Respuestas:
     *   ok()   → rechazada y reserva liberada
     *   fail() → validación o error técnico
     */
    public function rechazarSolicitud($datos): array
    {
        try {
            $datos       = $this->limpiarDatos($datos);
            $idSolicitud = (int)($datos->id_solicitud    ?? 0);
            $motivo      = trim($datos->motivo_rechazo   ?? '');

            if (!$idSolicitud) {
                return $this->res->fail('El campo id_solicitud es requerido');
            }
            if (empty($motivo)) {
                return $this->res->fail('El motivo de rechazo es obligatorio');
            }

            $idBodegaEncargado = $this->_obtenerBodegaEncargado();
            if (!$idBodegaEncargado) {
                return $this->res->fail('No tienes una bodega asignada como encargado');
            }

            $this->connect->beginTransaction();

            // Bloquear solicitud con FOR UPDATE para evitar doble acción concurrente
            $sqlSol = "SELECT id, id_estado, id_bodega
                   FROM   bodega_inventario.solicitudes
                   WHERE  id = ? AND id_bodega = ?
                   FOR UPDATE";
            $stmtSol = $this->connect->prepare($sqlSol);
            $stmtSol->execute([$idSolicitud, $idBodegaEncargado]);
            $solicitud = $stmtSol->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                $this->connect->rollBack();
                return $this->res->fail('Solicitud no encontrada en tu bodega');
            }

            if ((int)$solicitud['id_estado'] !== 1) {
                $this->connect->rollBack();
                return $this->res->fail('Solo se pueden rechazar solicitudes en estado Reservada');
            }

            // Obtener renglones
            $sqlDet = "SELECT id, id_producto, id_unidad, cantidad_solicitada
                   FROM   bodega_inventario.solicitudes_detalle
                   WHERE  id_solicitud = ?";
            $stmtDet = $this->connect->prepare($sqlDet);
            $stmtDet->execute([$idSolicitud]);
            $renglones = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            $idBodega = (int)$solicitud['id_bodega'];

            foreach ($renglones as $renglon) {
                $idProducto = (int)$renglon['id_producto'];
                $idUnidad   = (int)$renglon['id_unidad'];
                $cantidad   = (float)$renglon['cantidad_solicitada'];
                $idDetalle  = (int)$renglon['id'];

                // Liberar reserva
                $sqlLib = "UPDATE bodega_inventario.stock
                       SET    cantidad_reservada = GREATEST(0, cantidad_reservada - ?),
                              updated_at         = NOW()
                       WHERE  id_bodega   = ?
                         AND  id_producto = ?
                         AND  id_unidad   = ?";
                $stmtLib = $this->connect->prepare($sqlLib);
                $stmtLib->execute([$cantidad, $idBodega, $idProducto, $idUnidad]);

                // Movimiento tipo 10 (Liberación de reserva)
                $this->_registrarMovimiento(
                    10, $idBodega, $idProducto, $idUnidad,
                    $cantidad, 'solicitudes_detalle', $idDetalle
                );

                // Guardar motivo y encargado en el detalle
                $sqlUpdDet = "UPDATE bodega_inventario.solicitudes_detalle
                          SET    motivo_rechazo    = ?,
                                 id_usuario_gestion = ?,
                                 fecha_gestion      = NOW()
                          WHERE  id = ?";
                $stmtUpdDet = $this->connect->prepare($sqlUpdDet);
                $stmtUpdDet->execute([$motivo, $this->idUsuario, $idDetalle]);
            }

            // Cambiar cabecera a Rechazada (3)
            $sqlCab = "UPDATE bodega_inventario.solicitudes
                   SET    id_estado  = 3,
                          updated_at = NOW()
                   WHERE  id = ?";
            $stmtCab = $this->connect->prepare($sqlCab);
            $stmtCab->execute([$idSolicitud]);

            $this->connect->commit();

            return $this->res->ok('Solicitud rechazada y reserva liberada correctamente');

        } catch (Exception $e) {
            if ($this->connect->inTransaction()) $this->connect->rollBack();
            error_log("Error en rechazarSolicitud: " . $e->getMessage());
            return $this->res->fail('Error al rechazar la solicitud', $e);
        }
    }


// ============================================================================
// ENCARGADO — HELPERS PRIVADOS (Sprint 4)
// ============================================================================

    /**
     * Determina el id_bodega que gestiona el usuario autenticado.
     *
     * Prioridad:
     *   1. Bodega de área → inv_encargados_bodega_area
     *   2. Bodega de agencia → bodegas WHERE id_tipo=1 AND id_agencia=sesión
     *
     * @return int|null  ID de la bodega o null si no es encargado de ninguna
     */
    private function _obtenerBodegaEncargado(): ?int
    {
        // 1. Bodega de área
        $sqlArea = "SELECT id_bodega
                FROM   bodega_inventario.inv_encargados_bodega_area
                WHERE  id_usuario = ? AND activo = 1
                LIMIT 1";
        $stmtArea = $this->connect->prepare($sqlArea);
        $stmtArea->execute([$this->idUsuario]);
        $idArea = $stmtArea->fetchColumn();

        if ($idArea !== false) {
            return (int)$idArea;
        }

        // 2. Bodega de agencia (solo si hay agencia en sesión)
        if ($this->idAgencia) {
            $sqlAg = "SELECT id
                  FROM   bodega_inventario.bodegas
                  WHERE  id_tipo   = 1
                    AND  id_agencia = ?
                    AND  activo     = 1
                  LIMIT 1";
            $stmtAg = $this->connect->prepare($sqlAg);
            $stmtAg->execute([$this->idAgencia]);
            $idAg = $stmtAg->fetchColumn();

            if ($idAg !== false) {
                return (int)$idAg;
            }
        }

        return null;
    }

    /**
     * Aplica FIFO sobre lotes_normal consumiendo de los más antiguos primero.
     * Puede consumir de múltiples lotes si el primero no alcanza.
     *
     * @param string|null $idReceptor  ID del usuario que recibe (para movimientos)
     * @return float  Total consumido (= solicitado si el stock estaba bien reservado)
     */
    private function _aplicarFIFO(
        int    $idBodega,
        int    $idProducto,
        int    $idUnidad,
        float  $cantidadPedida,
        int    $idDetalle,
        ?string $idReceptor = null
    ): float {
        $sqlLotes = "SELECT id, cantidad_disponible
                 FROM   bodega_inventario.lotes_normal
                 WHERE  id_bodega   = ?
                   AND  id_producto = ?
                   AND  id_unidad   = ?
                   AND  cantidad_disponible > 0
                 ORDER BY fecha_ingreso ASC
                 FOR UPDATE";

        $stmtLotes = $this->connect->prepare($sqlLotes);
        $stmtLotes->execute([$idBodega, $idProducto, $idUnidad]);
        $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);

        return $this->_consumirLotes(
            $lotes, 'lotes_normal',
            $idBodega, $idProducto, $idUnidad,
            $cantidadPedida, $idDetalle, $idReceptor
        );
    }

    /**
     * Aplica PEPS sobre lotes_expiracion consumiendo los que expiran antes primero.
     *
     * @return float  Total consumido
     */
    private function _aplicarPEPS(
        int    $idBodega,
        int    $idProducto,
        int    $idUnidad,
        float  $cantidadPedida,
        int    $idDetalle,
        ?string $idReceptor = null
    ): float {
        $sqlLotes = "SELECT id, cantidad_disponible
                 FROM   bodega_inventario.lotes_expiracion
                 WHERE  id_bodega   = ?
                   AND  id_producto = ?
                   AND  id_unidad   = ?
                   AND  cantidad_disponible > 0
                 ORDER BY fecha_expiracion ASC
                 FOR UPDATE";

        $stmtLotes = $this->connect->prepare($sqlLotes);
        $stmtLotes->execute([$idBodega, $idProducto, $idUnidad]);
        $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);

        return $this->_consumirLotes(
            $lotes, 'lotes_expiracion',
            $idBodega, $idProducto, $idUnidad,
            $cantidadPedida, $idDetalle, $idReceptor
        );
    }

    /**
     * Lógica genérica de consumo de lotes compartida por FIFO y PEPS.
     * Descuenta de cada lote y registra el movimiento tipo 5 (Baja por entrega).
     *
     * @param array  $lotes          Lotes ya ordenados (FIFO o PEPS)
     * @param string $tablaLotes     'lotes_normal' o 'lotes_expiracion'
     * @return float                 Total efectivamente consumido
     */
    private function _consumirLotes(
        array  $lotes,
        string $tablaLotes,
        int    $idBodega,
        int    $idProducto,
        int    $idUnidad,
        float  $cantidadPedida,
        int    $idDetalle,
        ?string $idReceptor
    ): float {
        $pendiente = $cantidadPedida;
        $consumidoTotal = 0.0;

        $sqlUpdLote = "UPDATE bodega_inventario.{$tablaLotes}
                   SET    cantidad_disponible = cantidad_disponible - ?
                   WHERE  id = ?";
        $stmtUpdLote = $this->connect->prepare($sqlUpdLote);

        foreach ($lotes as $lote) {
            if ($pendiente <= 0) break;

            $consumir = min($pendiente, (float)$lote['cantidad_disponible']);

            // Descontar del lote
            $stmtUpdLote->execute([$consumir, $lote['id']]);

            // Registrar movimiento de baja (tipo 5)
            $sqlMov = "INSERT INTO bodega_inventario.movimientos_stock
                       (id_bodega, id_producto, id_unidad, id_tipo_movimiento,
                        cantidad, entidad_origen, id_entidad_origen,
                        id_usuario, id_usuario_receptor)
                   VALUES (?, ?, ?, 5, ?, 'solicitudes_detalle', ?, ?, ?)";
            $stmtMov = $this->connect->prepare($sqlMov);
            $stmtMov->execute([
                $idBodega, $idProducto, $idUnidad,
                $consumir, $idDetalle,
                $this->idUsuario, $idReceptor,
            ]);

            $pendiente      -= $consumir;
            $consumidoTotal += $consumir;
        }

        return $consumidoTotal;
    }

    /**
     * Verifica si hay un cierre mensual activo que bloquee las entregas.
     *
     * @return string|null  Fecha de corte formateada si hay cierre activo, null si no
     */
    private function _verificarCierreActivo(): ?string
    {
        $sql = "SELECT fecha_corte
            FROM   bodega_inventario.cierres_mensuales
            WHERE  modulos_bloqueados = 1
            ORDER  BY id DESC
            LIMIT  1";
        $stmt = $this->connect->query($sql);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['fecha_corte'] : null;
    }
}