<?php
namespace App\Core;

use App\drive\EnvDriveClass;
use Exception;

/**
 * ============================================================================
 * SERVICIO DE GESTIÓN DE ARCHIVOS EN GOOGLE DRIVE
 * ============================================================================
 *
 * Servicio centralizado para subir, validar y gestionar archivos en Google Drive.
 * Proporciona métodos para:
 * - Subir archivos desde formularios
 * - Validar tipos y tamaños de archivo
 * - Crear estructura de carpetas
 * - Generar nombres únicos
 *
 * @author Tu Nombre
 * @version 1.0
 */
final class DriveService
{
    private EnvDriveClass $drive;
    private bool $debug;

    /**
     * Constructor
     *
     * @param bool|null $debug Modo debug (opcional, usa APP_DEBUG por defecto)
     */
    public function __construct(?bool $debug = null)
    {
        $this->drive = new EnvDriveClass();
        $this->debug = $debug ?? (($_ENV['APP_DEBUG'] ?? 'false') === 'true');

        // Suprimir warnings de deprecación de Google API
        error_reporting(E_ALL ^ E_DEPRECATED);
    }

    /**
     * Sube un archivo a Google Drive
     *
     * @param string $nombreCarpetaBase Nombre de la carpeta base en Drive
     * @param array $subcarpetas Array de subcarpetas ['carpeta1', 'carpeta2', ...]
     * @param string|null $nombrePersonalizado Nombre personalizado (opcional, se genera automático)
     * @param array|null $tiposPermitidos Tipos MIME permitidos (null = todos)
     * @param int $tamañoMaximo Tamaño máximo en bytes (default 10MB)
     *
     * @return array {
     *     exito: bool,
     *     mensaje: string,
     *     drive_id?: string,
     *     nombre_archivo?: string,
     *     url_visualizacion?: string,
     *     metadata?: array
     * }
     *
     * @example
     * $resultado = $driveService->subirArchivo(
     *     'tesoreria',
     *     ['transferencias', '2024-12'],
     *     'comprobante_ST-2024-0001.pdf',
     *     ['application/pdf'],
     *     10 * 1024 * 1024
     * );
     */
    public function subirArchivo(
        string $nombreCarpetaBase,
        array $subcarpetas = [],
        ?string $nombrePersonalizado = null,
        ?array $tiposPermitidos = null,
        int $tamañoMaximo = 10485760 // 10MB por defecto
    ): array
    {
        try {
            // 1. Detectar archivo en la petición
            $archivo = $this->_detectarArchivo();
            if (!$archivo['exito']) {
                return $archivo;
            }

            $archivoObj = $archivo['archivo'];

            // 2. Validar archivo
            $validacion = $this->validarArchivo($archivoObj, $tiposPermitidos, $tamañoMaximo);
            if (!$validacion['exito']) {
                return $validacion;
            }

            // 3. Generar nombre de archivo
            $nombreFinal = $nombrePersonalizado ?? $this->generarNombreUnico($archivoObj->name);

            // 4. Construir ruta de carpetas en Drive
            $idCarpetaFinal = $this->construirRutaCarpetas($nombreCarpetaBase, $subcarpetas);
            if (!$idCarpetaFinal) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se pudo crear/encontrar la estructura de carpetas en Drive'
                ];
            }

            // 5. Subir archivo
            $driveId = $this->_ejecutarSubida($archivoObj, $nombreFinal, $idCarpetaFinal);
            if (!$driveId) {
                return [
                    'exito' => false,
                    'mensaje' => 'Error al subir el archivo a Google Drive'
                ];
            }

            // 6. Retornar información completa
            return [
                'exito' => true,
                'mensaje' => 'Archivo subido correctamente',
                'drive_id' => $driveId,
                'nombre_archivo' => $nombreFinal,
                'url_visualizacion' => "https://drive.google.com/file/d/{$driveId}/view",
                'metadata' => [
                    'nombre_original' => $archivoObj->name,
                    'tipo_mime' => $archivoObj->type,
                    'tamaño_bytes' => $archivoObj->size,
                    'carpeta_base' => $nombreCarpetaBase,
                    'subcarpetas' => $subcarpetas
                ]
            ];

        } catch (Exception $e) {
            $mensaje = $this->debug
                ? "Error al subir archivo: {$e->getMessage()}"
                : "Error al procesar el archivo";

            error_log("DriveService::subirArchivo - " . $e->getMessage());

            return [
                'exito' => false,
                'mensaje' => $mensaje,
                'error' => $this->debug ? $e->getTraceAsString() : null
            ];
        }
    }

    /**
     * Valida un archivo según criterios
     *
     * @param object $archivo Objeto de archivo
     * @param array|null $tiposPermitidos Tipos MIME permitidos
     * @param int $tamañoMaximo Tamaño máximo en bytes
     *
     * @return array { exito: bool, mensaje: string }
     */
    public function validarArchivo(
        object $archivo,
        ?array $tiposPermitidos = null,
        int $tamañoMaximo = 10485760
    ): array
    {
        // Validar error de carga
        if (($archivo->error ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return [
                'exito' => false,
                'mensaje' => 'Error en la carga del archivo: ' . $this->_obtenerMensajeError($archivo->error)
            ];
        }

        // Validar nombre
        if (empty(trim($archivo->name ?? ''))) {
            return [
                'exito' => false,
                'mensaje' => 'El archivo debe tener un nombre válido'
            ];
        }

        // Validar tipo MIME
        if ($tiposPermitidos !== null && !in_array($archivo->type, $tiposPermitidos)) {
            $tiposLegibles = array_map(function($tipo) {
                return $this->_convertirMimeALegible($tipo);
            }, $tiposPermitidos);

            return [
                'exito' => false,
                'mensaje' => 'Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', $tiposLegibles)
            ];
        }

        // Validar tamaño
        if (($archivo->size ?? 0) > $tamañoMaximo) {
            $tamañoMB = round($tamañoMaximo / 1048576, 1);
            return [
                'exito' => false,
                'mensaje' => "El archivo excede el tamaño máximo permitido de {$tamañoMB}MB"
            ];
        }

        return [
            'exito' => true,
            'mensaje' => 'Archivo válido'
        ];
    }

    /**
     * Construye la estructura de carpetas en Drive
     *
     * @param string $carpetaBase Nombre de la carpeta base
     * @param array $subcarpetas Array de subcarpetas a crear
     *
     * @return string|null ID de la carpeta final, null si falla
     */
    public function construirRutaCarpetas(string $carpetaBase, array $subcarpetas = []): ?string
    {
        try {
            // Obtener ID de la carpeta raíz de archivos
            $idRaiz = $this->drive->getArchivosId();
            if (!$idRaiz) {
                error_log("No se pudo obtener ID de carpeta raíz");
                return null;
            }

            // Construir ruta completa: Archivos/carpetaBase/subcarpeta1/subcarpeta2/...
            $rutaCompleta = array_merge(['Archivos', $carpetaBase], $subcarpetas);

            $idActual = $idRaiz;
            foreach ($rutaCompleta as $nombreCarpeta) {
                $idActual = $this->drive->verificaExisteCreaCarpeta($nombreCarpeta, $idActual);
                if (!$idActual) {
                    error_log("Error al crear/verificar carpeta: {$nombreCarpeta}");
                    return null;
                }
            }

            return $idActual;

        } catch (Exception $e) {
            error_log("Error en construirRutaCarpetas: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera un nombre único para el archivo
     *
     * @param string $nombreOriginal Nombre original del archivo
     * @param string|null $prefijo Prefijo opcional
     *
     * @return string Nombre único generado
     */
    public function generarNombreUnico(string $nombreOriginal, ?string $prefijo = null): string
    {
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = substr(md5(uniqid(rand(), true)), 0, 6);

        $nombre = $prefijo
            ? "{$prefijo}_{$timestamp}_{$random}.{$extension}"
            : "{$timestamp}_{$random}.{$extension}";

        // Limpiar nombre (solo alfanuméricos, guiones y puntos)
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre);
    }

    /**
     * Obtiene metadata de un archivo en Drive
     *
     * @param string $driveId ID del archivo en Drive
     *
     * @return array|null Metadata del archivo, null si no existe
     */
    public function obtenerMetadata(string $driveId): ?array
    {
        try {
            // Implementar según métodos disponibles en EnvDriveClass
            // Este es un ejemplo básico
            return [
                'drive_id' => $driveId,
                'url_visualizacion' => "https://drive.google.com/file/d/{$driveId}/view",
                'url_descarga' => "https://drive.google.com/uc?export=download&id={$driveId}"
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo metadata: " . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // MÉTODOS PRIVADOS
    // ========================================================================

    /**
     * Detecta el archivo en la petición actual
     *
     * @return array { exito: bool, mensaje?: string, archivo?: object }
     */
    private function _detectarArchivo(): array
    {
        if (empty($_FILES)) {
            return [
                'exito' => false,
                'mensaje' => 'No se detectó ningún archivo en la petición'
            ];
        }

        // Buscar primer archivo válido
        foreach ($_FILES as $file) {
            if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
                return [
                    'exito' => true,
                    'archivo' => (object)[
                        'name' => $file['name'],
                        'tmp_name' => $file['tmp_name'],
                        'type' => $file['type'],
                        'size' => $file['size'],
                        'error' => $file['error']
                    ]
                ];
            }
        }

        return [
            'exito' => false,
            'mensaje' => 'No se encontró un archivo válido en la petición'
        ];
    }

    /**
     * Ejecuta la subida del archivo a Drive
     *
     * @param object $archivo Objeto de archivo
     * @param string $nombreDestino Nombre final del archivo
     * @param string $idCarpeta ID de la carpeta destino
     *
     * @return string|null ID del archivo en Drive, null si falla
     */
    private function _ejecutarSubida(object $archivo, string $nombreDestino, string $idCarpeta): ?string
    {
        try {
            // Crear wrapper compatible con el método esperado por EnvDriveClass
            $wrapper = new class($archivo, $nombreDestino) {
                private $archivo;
                private $nombre;

                public function __construct($archivo, $nombre)
                {
                    $this->archivo = $archivo;
                    $this->nombre = $nombre;
                }

                public function getClientFilename()
                {
                    return $this->nombre;
                }

                public function getClientMediaType()
                {
                    return $this->archivo->type;
                }

                public function getSize()
                {
                    return $this->archivo->size;
                }

                public function moveTo($targetPath)
                {
                    return move_uploaded_file($this->archivo->tmp_name, $targetPath);
                }

                public function getError()
                {
                    return $this->archivo->error ?? 0;
                }
            };

            // Subir a Drive
            $resultado = $this->drive->cargaADrive($wrapper, $nombreDestino, $idCarpeta);

            return isset($resultado['id']) ? $resultado['id'] : null;

        } catch (Exception $e) {
            error_log("Error en _ejecutarSubida: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Convierte tipo MIME a formato legible
     *
     * @param string $mime Tipo MIME
     * @return string Tipo legible
     */
    private function _convertirMimeALegible(string $mime): string
    {
        $mapa = [
            'application/pdf' => 'PDF',
            'image/jpeg' => 'JPG',
            'image/jpg' => 'JPG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WEBP',
            'application/vnd.ms-excel' => 'Excel (XLS)',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (XLSX)',
            'application/msword' => 'Word (DOC)',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (DOCX)',
            'text/plain' => 'Texto',
            'text/csv' => 'CSV'
        ];

        return $mapa[$mime] ?? strtoupper(explode('/', $mime)[1] ?? 'archivo');
    }

    /**
     * Obtiene mensaje de error de carga según código
     *
     * @param int $errorCode Código de error de PHP
     * @return string Mensaje descriptivo
     */
    private function _obtenerMensajeError(int $errorCode): string
    {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL => 'El archivo solo se cargó parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se cargó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta una carpeta temporal en el servidor',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la carga del archivo'
        ];

        return $errores[$errorCode] ?? 'Error desconocido al cargar el archivo';
    }
}