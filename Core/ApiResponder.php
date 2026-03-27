<?php
namespace App\Core;

use Exception;

final class ApiResponder
{
    private bool $debug;

    public function __construct(?bool $debug = null)
    {
        $this->debug = $debug ?? (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
    }

    public function ok(string $mensaje = 'Listo', $datos = null, array $extra = []): array
    {
        $out = ['respuesta' => 'success', 'mensaje' => $mensaje];
        if ($datos !== null) $out['datos'] = $datos;
        if ($extra) $out = array_merge($out, $extra);
        return $out;
    }

    public function fail(string $mensaje = 'No se pudo completar la operación', ?Exception $e = null, array $extra = []): array
    {
        $out = ['respuesta' => 'error', 'mensaje' => $mensaje];
        if ($e) {
            $out['excepcion'] = ['mensaje' => $e->getMessage()];
            if ($this->debug) {
                $out['excepcion']['trace'] = $e->getTraceAsString();
            }
        }
        if ($extra) $out = array_merge($out, $extra);
        return $out;
    }
    public function info(string $mensaje = 'No se pudo completar la operación', ?Exception $e = null, array $extra = []): array
    {
        $out = ['respuesta' => 'info', 'mensaje' => $mensaje];
        if ($e) {
            $out['excepcion'] = ['mensaje' => $e->getMessage()];
            if ($this->debug) {
                $out['excepcion']['trace'] = $e->getTraceAsString();
            }
        }
        if ($extra) $out = array_merge($out, $extra);
        return $out;
    }
}