// ============================================================================
// MODELOS - LIQUIDACIONES DE COMBUSTIBLE
// ============================================================================

/**
 * Interface para Liquidación
 */
export interface Liquidacion {
    idLiquidaciones: number;
    usuarioid: string;
    numero_factura: string;
    vehiculoid: number | null;
    tipoapoyoid: number;
    descripcion: string;
    monto: number;
    estado: EstadoLiquidacion;
    fecha_liquidacion: string;
    solicitudautorizacionid: number | null;
    created_at: string;
    updated_at: string;
    // Joins
    vehiculo_placa?: string;
    vehiculo_marca?: string;
    tipo_vehiculo?: string;
    tipo_apoyo?: string;
    tipo_apoyo_codigo?: string;
    autorizacion_estado?: EstadoAutorizacion;
    autorizacion_fecha?: string;
}

/**
 * Interface para Formulario de Liquidación
 */
export interface LiquidacionForm {
    idLiquidaciones?: number;
    numero_factura: string;
    vehiculoid: number | null;
    tipoapoyoid: number;
    descripcion: string;
}

/**
 * Estados posibles de liquidación
 */
export type EstadoLiquidacion =
    | 'enviada'
    | 'aprobada'
    | 'rechazada'
    | 'devuelta'
    | 'corregida'
    | 'de_baja'
    | 'en_lote'
    | 'pagada'
    | 'eliminada';

/**
 * Estados posibles de autorización
 */
export type EstadoAutorizacion = 'pendiente' | 'aprobada' | 'rechazada';

/**
 * Interface para Solicitud de Autorización
 */
export interface SolicitudAutorizacion {
    idSolicitudesAutorizacion: number;
    usuarioid: string;
    numero_factura: string;
    dias_habiles_excedidos: number;
    justificacion: string;
    estado: EstadoAutorizacion;
    fecha_solicitud: string;
    fecha_respuesta: string | null;
    autorizado_por: string | null;
    motivo_rechazo: string | null;
    nombre_autorizador?: string;
    nombre_solicitante?: string;
    archivos?: ArchivoAutorizacion[];
}

/**
 * Interface para Formulario de Solicitud de Autorización
 */
export interface SolicitudAutorizacionForm {
    numero_factura: string;
    justificacion: string;
    archivo?: File;
}

/**
 * Interface para Archivo de Autorización
 */
export interface ArchivoAutorizacion {
    idArchivosAutorizacion: number;
    drive_id: string;
    nombre_original: string;
    tipo_mime: string;
    tamano_bytes: number;
    fecha_subida: string;
}

/**
 * Interface para Factura del sistema SAT
 */
export interface FacturaSat {
    id: number;
    numero_dte: string;
    fecha_emision: string;
    numero_autorizacion: string;
    tipo_dte: string;
    nombre_emisor: string;
    monto_total: number;
    estado: string;
    estado_liquidacion: string;
    tiene_autorizacion_tardanza: boolean;
}

/**
 * Interface para Datos de Factura para Liquidación
 */
export interface DatosFacturaLiquidacion {
    factura: FacturaSat;
    dias_habiles_transcurridos: number;
    dias_habiles_permitidos: number;
    requiere_autorizacion: boolean;
    tiene_autorizacion_aprobada: boolean;
    puede_liquidar: boolean;
    solicitud_autorizacion: SolicitudAutorizacion | null;
    ya_liquidada: boolean;
}

/**
 * Interface para Presupuesto Disponible
 */
export interface PresupuestoDisponible {
    agencia: string;
    puesto: string;
    anio: number;
    presupuesto_anual: PresupuestoDetalle;
    presupuesto_mensual: PresupuestoMensualDetalle;
    monto_diario: number;
}

/**
 * Interface para Detalle de Presupuesto
 */
export interface PresupuestoDetalle {
    total: number;
    consumido: number;
    disponible: number;
    porcentaje_usado: number;
}

/**
 * Interface para Detalle de Presupuesto Mensual
 */
export interface PresupuestoMensualDetalle extends PresupuestoDetalle {
    dias_transcurridos: number;
    calculado: number;
}

/**
 * Interface para Vehículo
 */
export interface Vehiculo {
    idVehiculos: number;
    usuarioid: string;
    tipovehiculoid: number;
    placa: string;
    marca: string;
    activo: 0 | 1;
    created_at: string;
    updated_at: string;
    tipo_vehiculo?: string;
}

/**
 * Interface para Tipo de Apoyo
 */
export interface TipoApoyo {
    idTiposApoyo: number;
    codigo: string;
    nombre: string;
    descripcion: string;
    aplica_limite_mensual: 0 | 1;
    activo: 0 | 1;
    created_at: string;
    updated_at: string;
}

/**
 * Respuesta estándar de la API
 */
export interface ApiResponse<T = any> {
    respuesta: 'success' | 'error' | 'info';
    mensaje?: string;
    mensajes?: string[];
    datos?: T;
    id?: number;
}

/**
 * Helper para formateo de fechas
 */
export class FormatHelper {
    /**
     * Formatea fecha a formato legible DD/MM/YYYY HH:mm
     */
    static formatFecha(fecha: string): string {
        if (!fecha) return '—';
        const date = new Date(fecha);
        return date.toLocaleString('es-GT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Formatea fecha a formato corto DD/MM/YYYY
     */
    static formatFechaCorta(fecha: string): string {
        if (!fecha) return '—';
        const date = new Date(fecha);
        return date.toLocaleDateString('es-GT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    /**
     * Formatea monto a formato de moneda Q X,XXX.XX
     */
    static formatMoneda(monto: number): string {
        return `Q ${monto.toLocaleString('es-GT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    }

    /**
     * Formatea porcentaje
     */
    static formatPorcentaje(porcentaje: number): string {
        return `${porcentaje.toFixed(2)}%`;
    }

    /**
     * Formatea tamaño de archivo en bytes a formato legible
     */
    static formatTamanoArchivo(bytes: number): string {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
}

/**
 * Helper para estados
 */
export class EstadoHelper {
    /**
     * Obtiene clase CSS según estado de liquidación
     */
    static getClaseLiquidacion(estado: EstadoLiquidacion): string {
        const clases: Record<EstadoLiquidacion, string> = {
            'enviada': 'bg-blue-50 text-blue-700 border border-blue-200',
            'aprobada': 'bg-green-50 text-green-700 border border-green-200',
            'rechazada': 'bg-red-50 text-red-700 border border-red-200',
            'devuelta': 'bg-yellow-50 text-yellow-700 border border-yellow-200',
            'corregida': 'bg-purple-50 text-purple-700 border border-purple-200',
            'de_baja': 'bg-green-50 text-green-700 border border-green-200',
            'en_lote': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
            'pagada': 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'eliminada': 'bg-red-50 text-red-700 border border-red-200'
        };
        return clases[estado] || 'bg-gray-50 text-gray-700 border border-gray-200';
    }

    /**
     * Obtiene texto en español según estado de liquidación
     */
    static getTextoLiquidacion(estado: EstadoLiquidacion): string {
        const textos: Record<EstadoLiquidacion, string> = {
            'enviada': 'Enviada',
            'aprobada': 'Aprobada',
            'rechazada': 'Rechazada',
            'devuelta': 'Devuelta',
            'corregida': 'Corregida',
            'de_baja': 'Aprobada',
            'en_lote': 'En Lote',
            'pagada': 'Pagada',
            'eliminada': 'Eliminada'
        };
        return textos[estado] || estado;
    }

    /**
     * Obtiene clase CSS según estado de autorización
     */
    static getClaseAutorizacion(estado: EstadoAutorizacion): string {
        const clases: Record<EstadoAutorizacion, string> = {
            'pendiente': 'bg-yellow-50 text-yellow-700 border border-yellow-200',
            'aprobada': 'bg-green-50 text-green-700 border border-green-200',
            'rechazada': 'bg-red-50 text-red-700 border border-red-200'
        };
        return clases[estado] || 'bg-gray-50 text-gray-700 border border-gray-200';
    }

    /**
     * Obtiene texto en español según estado de autorización
     */
    static getTextoAutorizacion(estado: EstadoAutorizacion): string {
        const textos: Record<EstadoAutorizacion, string> = {
            'pendiente': 'Pendiente',
            'aprobada': 'Aprobada',
            'rechazada': 'Rechazada'
        };
        return textos[estado] || estado;
    }

    /**
     * Determina si una liquidación puede ser editada
     */
    static puedeEditar(estado: EstadoLiquidacion): boolean {
        return ['enviada', 'devuelta'].includes(estado);
    }

    /**
     * Determina si una liquidación puede ser eliminada
     */
    static puedeEliminar(estado: EstadoLiquidacion): boolean {
        return estado === 'enviada';
    }
}

/**
 * Mensajes del sistema
 */
export const MENSAJES_LIQUIDACIONES = {
    EXITO: {
        CREAR: 'Liquidación creada correctamente',
        EDITAR: 'Liquidación actualizada correctamente',
        ELIMINAR: 'Liquidación eliminada correctamente',
        AUTORIZACION_CREADA: 'Solicitud de autorización enviada correctamente'
    },
    ERROR: {
        CREAR: 'Error al crear la liquidación',
        EDITAR: 'Error al editar la liquidación',
        ELIMINAR: 'Error al eliminar la liquidación',
        CARGAR: 'Error al cargar liquidaciones',
        BUSCAR_FACTURA: 'Error al buscar la factura',
        AUTORIZACION: 'Error al crear la solicitud de autorización',
        PRESUPUESTO: 'Error al obtener presupuesto disponible'
    },
    CONFIRMACION: {
        ELIMINAR: '¿Está seguro de eliminar esta liquidación?',
        ELIMINAR_DETALLE: 'Esta acción liberará la factura para que pueda ser liquidada nuevamente'
    },
    VALIDACION: {
        FACTURA_REQUERIDA: 'El número de factura es requerido',
        TIPO_APOYO_REQUERIDO: 'El tipo de apoyo es requerido',
        DESCRIPCION_REQUERIDA: 'La descripción es requerida',
        JUSTIFICACION_REQUERIDA: 'La justificación es requerida'
    }
};

/**
 * Descripciones predefinidas por tipo de apoyo
 */
export const DESCRIPCIONES_TIPO_APOYO: Record<string, string> = {
    'COMBUSTIBLE': 'Liquidación de combustible para vehículo asignado',
    'KILOMETRAJE': 'Liquidación por kilometraje recorrido en actividades laborales',
    'MANTENIMIENTO': 'Liquidación de gastos de mantenimiento vehicular',
    'OTROS': 'Liquidación de otros gastos relacionados con transporte'
};

/**
 * Obtiene descripción predefinida según código de tipo de apoyo
 */
export function obtenerDescripcionPredefinida(codigoTipoApoyo: string): string {
    return DESCRIPCIONES_TIPO_APOYO[codigoTipoApoyo] || '';
}