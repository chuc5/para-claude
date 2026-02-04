// ============================================================================
// UTILIDADES COMPARTIDAS - ÚNICA FUENTE DE VERDAD
// ============================================================================

import {
    FacturaPE,
    DetalleLiquidacionPE,
    PermisosEdicion,
    EstadoMonto,
    DiasHabilesInfo,
    RegistrarFacturaPayload,
    SolicitarAutorizacionPayload,
    CONFIGURACION
} from './types';

// ============================================================================
// CONVERSORES
// ============================================================================

export function toNumber(value: any, defaultValue: number = 0): number {
    if (value === null || value === undefined || value === '') return defaultValue;
    const num = typeof value === 'string' ? parseFloat(value) : Number(value);
    return isNaN(num) ? defaultValue : num;
}

export function toBoolean(value: any, defaultValue: boolean = false): boolean {
    if (value === null || value === undefined) return defaultValue;
    if (typeof value === 'boolean') return value;
    if (typeof value === 'string') {
        return ['true', '1', 'yes', 'si', 'sí'].includes(value.toLowerCase());
    }
    if (typeof value === 'number') return value !== 0;
    return defaultValue;
}

// ============================================================================
// FORMATEO
// ============================================================================

export function formatearMonto(monto: number, moneda: 'GTQ' | 'USD' = 'GTQ'): string {
    return new Intl.NumberFormat('es-GT', {
        style: 'currency',
        currency: moneda,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(monto);
}

export function formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) return '-';
    try {
        const fechaLocal = new Date(`${fecha}T00:00:00`);
        return fechaLocal.toLocaleDateString(undefined, {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return '-';
    }
}

export function formatearFechaHora(fecha: string | null | undefined): string {
    if (!fecha) return '-';
    try {
        const fechaHora = new Date(fecha);
        return fechaHora.toLocaleString(undefined, {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } catch {
        return '-';
    }
}

export function truncarTexto(texto: string, longitud: number = 50): string {
    if (!texto) return '';
    if (texto.length <= longitud) return texto;
    return texto.substring(0, longitud).trim() + '...';
}

export function normalizarTexto(texto: string): string {
    if (!texto) return '';
    return texto
        .trim()
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

// ============================================================================
// COLORES Y ESTILOS CSS
// ============================================================================

const COLORES_ESTADO_LIQUIDACION: Record<string, string> = {
    'Pendiente': 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'En Revisión': 'bg-blue-100 text-blue-800 border-blue-200',
    'Verificado': 'bg-indigo-100 text-indigo-800 border-indigo-200',
    'Liquidado': 'bg-green-100 text-green-800 border-green-200',
    'Pagado': 'bg-emerald-100 text-emerald-800 border-emerald-200'
};

const COLORES_ESTADO_AUTORIZACION: Record<string, string> = {
    'aprobada': 'bg-green-100 text-green-800 border-green-200',
    'pendiente': 'bg-amber-100 text-amber-800 border-amber-200',
    'rechazada': 'bg-red-100 text-red-800 border-red-200',
    'ninguna': 'bg-gray-100 text-gray-700 border-gray-200'
};

const COLORES_ESTADO_FACTURA: Record<string, string> = {
    'vigente': 'bg-green-100 text-green-800 border-green-200',
    'Vigente': 'bg-green-100 text-green-800 border-green-200',
    'anulada': 'bg-red-100 text-red-800 border-red-200',
    'Anulado': 'bg-red-100 text-red-800 border-red-200',
    'suspendida': 'bg-orange-100 text-orange-800 border-orange-200'
};

const COLORES_ESTADO_TIEMPO: Record<string, string> = {
    'En tiempo': 'bg-green-100 text-green-800 border-green-200',
    'En plazo': 'bg-green-100 text-green-800 border-green-200',
    'Por vencer': 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'Vencido': 'bg-red-100 text-red-800 border-red-200'
};

const COLORES_FORMA_PAGO: Record<string, string> = {
    'deposito': 'bg-blue-100 text-blue-800',
    'transferencia': 'bg-green-100 text-green-800',
    'cheque': 'bg-purple-100 text-purple-800',
    'efectivo': 'bg-orange-100 text-orange-800',
    'anticipo': 'bg-cyan-100 text-cyan-800',
    'tarjeta': 'bg-pink-100 text-pink-800',
    'contrasena': 'bg-indigo-100 text-indigo-800',
    'costoasumido': 'bg-gray-100 text-gray-800'
};

export function obtenerColorEstadoLiquidacion(estado: string): string {
    return COLORES_ESTADO_LIQUIDACION[estado] || 'bg-gray-100 text-gray-800 border-gray-200';
}

export function obtenerColorEstadoAutorizacion(estado: string): string {
    return COLORES_ESTADO_AUTORIZACION[estado] || 'bg-gray-100 text-gray-700 border-gray-200';
}

export function obtenerColorEstadoFactura(estado: string): string {
    return COLORES_ESTADO_FACTURA[estado] || 'bg-gray-100 text-gray-800 border-gray-200';
}

export function obtenerColorEstadoTiempo(estadoTiempo: string): string {
    return COLORES_ESTADO_TIEMPO[estadoTiempo] || 'bg-gray-100 text-gray-800 border-gray-200';
}

export function obtenerColorFormaPago(formaPago: string): string {
    return COLORES_FORMA_PAGO[formaPago] || 'bg-gray-100 text-gray-800';
}

// ============================================================================
// PERMISOS DE EDICIÓN
// ============================================================================

export function validarPermisosEdicion(factura: FacturaPE | null): PermisosEdicion {
    const permisosDenegados: PermisosEdicion = {
        puedeVer: true,
        puedeEditar: false,
        puedeAgregar: false,
        puedeEliminar: false,
        razon: 'Sin factura seleccionada',
        claseCSS: 'text-gray-600 bg-gray-50 border-gray-200'
    };

    if (!factura) return permisosDenegados;

    const facturaVigente = factura.estado_factura === 'Vigente' || factura.estado_factura === 'vigente';
    const liquidacionPendiente = factura.estado_liquidacion === 'Pendiente';

    if (!facturaVigente) {
        return {
            ...permisosDenegados,
            razon: `Factura ${factura.estado_factura || 'no vigente'} - Solo lectura`,
            claseCSS: 'text-red-700 bg-red-50 border-red-200'
        };
    }

    if (!liquidacionPendiente) {
        return {
            ...permisosDenegados,
            razon: `Estado '${factura.estado_liquidacion}' - Solo lectura`,
            claseCSS: 'text-blue-700 bg-blue-50 border-blue-200'
        };
    }

    // Factura vigente Y liquidación pendiente - evaluar criterios
    const tieneDetalles = (factura.cantidad_liquidaciones ?? 0) > 0;
    const enTiempo = factura.dias_habiles && !factura.dias_habiles.excede_limite;
    const tieneAutorizacionAprobada = factura.estado_autorizacion === 'aprobada';

    const permisosCompletos: PermisosEdicion = {
        puedeVer: true,
        puedeEditar: true,
        puedeAgregar: true,
        puedeEliminar: true,
        razon: '',
        claseCSS: 'text-green-700 bg-green-50 border-green-200'
    };

    if (tieneDetalles) {
        return {
            ...permisosCompletos,
            razon: `Edición permitida - ${factura.cantidad_liquidaciones} liquidaciones registradas`
        };
    }

    if (enTiempo) {
        const dh = factura.dias_habiles!;
        return {
            ...permisosCompletos,
            razon: `Edición permitida - ${dh.estado_tiempo} (${dh.dias_transcurridos}/${dh.total_permitido} días)`
        };
    }

    if (tieneAutorizacionAprobada) {
        return {
            ...permisosCompletos,
            razon: 'Edición permitida - Autorización aprobada para liquidación tardía',
            claseCSS: 'text-blue-700 bg-blue-50 border-blue-200'
        };
    }

    // Fuera de tiempo sin autorización
    if (!factura.dias_habiles) {
        return {
            ...permisosDenegados,
            razon: 'Validando tiempo de liquidación...',
            claseCSS: 'text-yellow-700 bg-yellow-50 border-yellow-200'
        };
    }

    const dh = factura.dias_habiles;
    return {
        ...permisosDenegados,
        razon: `${dh.estado_tiempo} - Requiere autorización especial (${dh.dias_transcurridos}/${dh.total_permitido} días)`,
        claseCSS: 'text-red-700 bg-red-50 border-red-200'
    };
}

// ============================================================================
// DÍAS HÁBILES
// ============================================================================

export function obtenerMensajeEstadoTiempo(factura: FacturaPE): string {
    if (!factura.dias_habiles) return 'Estado de tiempo no disponible';
    const dh = factura.dias_habiles;
    return `${dh.estado_tiempo} - ${dh.dias_transcurridos}/${dh.total_permitido} días hábiles transcurridos`;
}

export function requiereAutorizacionPorTardanza(factura: FacturaPE): boolean {
    return factura.dias_habiles?.requiere_autorizacion || false;
}

export function estaEnTiempoPermitido(factura: FacturaPE): boolean {
    return !factura.dias_habiles?.excede_limite;
}

export function obtenerProgresoDias(factura: FacturaPE): number {
    if (!factura.dias_habiles || factura.dias_habiles.total_permitido === 0) return 0;
    return Math.min(
        (factura.dias_habiles.dias_transcurridos / factura.dias_habiles.total_permitido) * 100,
        100
    );
}

// ============================================================================
// CÁLCULOS DE MONTOS
// ============================================================================

export function calcularTotalDetalles(detalles: DetalleLiquidacionPE[]): number {
    return detalles.reduce((sum, d) => sum + (d.monto || 0), 0);
}

export function calcularEstadoMonto(montoFactura: number, totalDetalles: number): EstadoMonto {
    if (totalDetalles <= 0) return 'incompleto';
    const diferencia = Math.abs(totalDetalles - montoFactura);
    if (diferencia < CONFIGURACION.TOLERANCIA_DIFERENCIA_MONTOS) return 'completo';
    if (totalDetalles > montoFactura) return 'excedido';
    return 'incompleto';
}

export function calcularDiferenciaMontos(montoFactura: number, totalDetalles: number): number {
    return Math.abs(montoFactura - totalDetalles);
}

export function hayDiferenciaSignificativa(montoFactura: number, totalDetalles: number): boolean {
    return calcularDiferenciaMontos(montoFactura, totalDetalles) > CONFIGURACION.TOLERANCIA_DIFERENCIA_MONTOS;
}

// ============================================================================
// VALIDACIONES
// ============================================================================

export function esNumeroOrdenValido(numeroOrden: string): boolean {
    if (!numeroOrden) return false;
    const numero = parseInt(numeroOrden, 10);
    return !isNaN(numero) && numero > 0 && numero <= 999999999;
}

export function esDteValido(numeroDte: string): boolean {
    if (!numeroDte) return false;
    const dteNormalizado = numeroDte.trim();
    return dteNormalizado.length >= CONFIGURACION.DTE_MIN_LENGTH &&
        dteNormalizado.length <= CONFIGURACION.DTE_MAX_LENGTH &&
        /^[A-Za-z0-9\-_]+$/.test(dteNormalizado);
}

export function validarFactura(factura: Partial<RegistrarFacturaPayload>): { valido: boolean; errores: string[] } {
    const errores: string[] = [];

    if (!factura.numero_dte?.trim()) {
        errores.push('Número DTE es requerido');
    } else if (factura.numero_dte.trim().length < CONFIGURACION.DTE_MIN_LENGTH) {
        errores.push(`Número DTE debe tener al menos ${CONFIGURACION.DTE_MIN_LENGTH} caracteres`);
    } else if (factura.numero_dte.trim().length > CONFIGURACION.DTE_MAX_LENGTH) {
        errores.push(`Número DTE no puede exceder ${CONFIGURACION.DTE_MAX_LENGTH} caracteres`);
    }

    if (!factura.fecha_emision) {
        errores.push('Fecha de emisión es requerida');
    } else {
        const fecha = new Date(factura.fecha_emision);
        if (fecha > new Date()) {
            errores.push('La fecha de emisión no puede ser futura');
        }
    }

    if (!factura.numero_autorizacion?.trim()) {
        errores.push('Número de autorización es requerido');
    }

    if (!factura.tipo_dte?.trim()) {
        errores.push('Tipo DTE es requerido');
    }

    if (!factura.nombre_emisor?.trim()) {
        errores.push('Nombre del emisor es requerido');
    } else if (factura.nombre_emisor.trim().length < CONFIGURACION.NOMBRE_EMISOR_MIN_LENGTH) {
        errores.push(`Nombre del emisor debe tener al menos ${CONFIGURACION.NOMBRE_EMISOR_MIN_LENGTH} caracteres`);
    } else if (factura.nombre_emisor.trim().length > CONFIGURACION.NOMBRE_EMISOR_MAX_LENGTH) {
        errores.push(`Nombre del emisor no puede exceder ${CONFIGURACION.NOMBRE_EMISOR_MAX_LENGTH} caracteres`);
    }

    if (!factura.monto_total || factura.monto_total <= 0) {
        errores.push('Monto total debe ser mayor a 0');
    } else if (factura.monto_total > CONFIGURACION.MONTO_MAX) {
        errores.push('Monto total excede el límite permitido');
    }

    if (!factura.moneda || !['GTQ', 'USD'].includes(factura.moneda)) {
        errores.push('Moneda debe ser GTQ o USD');
    }

    return { valido: errores.length === 0, errores };
}

export function validarAutorizacion(payload: Partial<SolicitarAutorizacionPayload>): { valido: boolean; errores: string[] } {
    const errores: string[] = [];

    if (!payload.numero_dte?.trim()) {
        errores.push('Número DTE es requerido');
    }

    if (!payload.motivo?.trim()) {
        errores.push('Motivo es requerido');
    } else if (payload.motivo.trim().length < CONFIGURACION.MOTIVO_MIN_LENGTH) {
        errores.push(`Motivo debe tener al menos ${CONFIGURACION.MOTIVO_MIN_LENGTH} caracteres`);
    } else if (payload.motivo.trim().length > CONFIGURACION.MOTIVO_MAX_LENGTH) {
        errores.push(`Motivo no puede exceder ${CONFIGURACION.MOTIVO_MAX_LENGTH} caracteres`);
    }

    if (!payload.dias_transcurridos || payload.dias_transcurridos < 0) {
        errores.push('Días transcurridos debe ser un número válido');
    }

    return { valido: errores.length === 0, errores };
}

export function validarDetalleLiquidacion(detalle: Partial<DetalleLiquidacionPE>): { valido: boolean; errores: string[] } {
    const errores: string[] = [];

    if (!detalle.numero_orden?.trim()) {
        errores.push('Número de orden es requerido');
    }

    if (!detalle.agencia?.trim()) {
        errores.push('Agencia es requerida');
    }

    if (!detalle.descripcion?.trim()) {
        errores.push('Descripción es requerida');
    } else if (detalle.descripcion.trim().length < CONFIGURACION.DESCRIPCION_MIN_LENGTH) {
        errores.push(`Descripción debe tener al menos ${CONFIGURACION.DESCRIPCION_MIN_LENGTH} caracteres`);
    }

    if (!detalle.monto || detalle.monto <= 0) {
        errores.push('Monto debe ser mayor a 0');
    }

    if (!detalle.forma_pago?.trim()) {
        errores.push('Forma de pago es requerida');
    }

    return { valido: errores.length === 0, errores };
}

// ============================================================================
// UTILIDADES PARA BOTÓN DE AUTORIZACIÓN
// ============================================================================

export function mostrarBotonAutorizacion(factura: FacturaPE): boolean {
    if (factura.estado_factura === 'Anulado') return false;

    const estado = (factura.estado_autorizacion || 'ninguna').toLowerCase() as
        'ninguna' | 'pendiente' | 'aprobada' | 'rechazada';

    if (estado === 'rechazada') return true;

    const requiereAutorizacion = factura.dias_habiles?.requiere_autorizacion || false;
    if (!requiereAutorizacion) return false;

    const tieneAutorizacionProcesada = !!(
        factura.autorizacion_id && (estado === 'pendiente' || estado === 'aprobada')
    );

    return requiereAutorizacion && !tieneAutorizacionProcesada;
}
