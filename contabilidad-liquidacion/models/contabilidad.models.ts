// ============================================================================
// MODELOS - CONTABILIDAD
// ============================================================================

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
    | 'pagada';

/**
 * Estados posibles de solicitud de corrección
 */
export type EstadoSolicitudCorreccion = 'pendiente' | 'realizada' | 'cancelada';

/**
 * Interface para Liquidación en Contabilidad
 */
export interface LiquidacionContabilidad {
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
    revisado_por: string | null;
    fecha_revision: string | null;
    motivo_rechazo: string | null;
    created_at: string;
    updated_at: string;
    // Joins - Usuario
    nombre_usuario: string;
    agencia_usuario: string;
    puesto_usuario: string;
    // Joins - Revisor
    nombre_revisor?: string;
    // Joins - Vehículo
    vehiculo_placa: string | null;
    vehiculo_marca: string | null;
    tipo_vehiculo: string | null;
    // Joins - Tipo Apoyo
    tipo_apoyo_codigo: string;
    tipo_apoyo: string;
    // Joins - Autorización
    autorizacion_estado: string | null;
    autorizacion_fecha: string | null;
    // Joins - Factura
    fecha_emision: string;
    nombre_emisor: string;
    // Contadores
    tiene_correccion_pendiente?: number;
}

/**
 * Interface para Solicitud de Corrección
 */
export interface SolicitudCorreccion {
    idSolicitudesCorreccion: number;
    liquidacionid: number;
    solicitado_por: string;
    motivo: string;
    estado: EstadoSolicitudCorreccion;
    fecha_solicitud: string;
    fecha_correccion: string | null;
    observaciones_correccion: string | null;
    // Joins - Solicitante
    nombre_solicitante: string;
    // Joins - Liquidación
    numero_factura: string;
    monto: number;
    descripcion: string;
    estado_liquidacion: EstadoLiquidacion;
    tipo_apoyo: string;
}

/**
 * Interface para Filtros de Liquidaciones
 */
export interface FiltrosLiquidaciones {
    fecha_inicio?: string;
    fecha_fin?: string;
    agenciaid?: number;
    tipoapoyoid?: number;
    numero_factura?: string;
    estado?: EstadoLiquidacion | '';
}

/**
 * Interface para Formulario de Aprobación
 */
export interface FormularioAprobacion {
    idLiquidaciones: number;
    observaciones?: string;
}

/**
 * Interface para Formulario de Rechazo
 */
export interface FormularioRechazo {
    idLiquidaciones: number;
    motivo_rechazo: string;
}

/**
 * Interface para Formulario de Devolución
 */
export interface FormularioDevolucion {
    idLiquidaciones: number;
    motivo: string;
}

/**
 * Interface para Formulario de Baja
 */
export interface FormularioBaja {
    idLiquidaciones: number;
    observaciones?: string;
}

/**
 * Interface para Formulario de Corrección Realizada
 */
export interface FormularioCorreccionRealizada {
    idLiquidaciones: number;
    observaciones_correccion?: string;
}

/**
 * Respuesta estándar de la API
 */
export interface ApiResponse<T = any> {
    respuesta: 'success' | 'error';
    mensaje?: string;
    mensajes?: string[];
    datos?: T;
    id?: number;
}

/**
 * Mensajes del sistema para contabilidad
 */
export const MENSAJES_CONTABILIDAD = {
    EXITO: {
        APROBAR: 'Liquidación aprobada correctamente',
        RECHAZAR: 'Liquidación rechazada correctamente',
        DEVOLVER: 'Liquidación devuelta para corrección',
        BAJA: 'Liquidación dada de baja correctamente',
        CORRECCION_MARCADA: 'Corrección marcada como realizada'
    },
    ERROR: {
        APROBAR: 'Error al aprobar la liquidación',
        RECHAZAR: 'Error al rechazar la liquidación',
        DEVOLVER: 'Error al devolver la liquidación',
        BAJA: 'Error al dar de baja la liquidación',
        CARGAR_PENDIENTES: 'Error al cargar liquidaciones pendientes',
        CARGAR_HISTORIAL: 'Error al cargar historial de revisiones',
        CARGAR_CORRECCIONES: 'Error al cargar solicitudes de corrección',
        MARCAR_CORRECCION: 'Error al marcar la corrección'
    },
    CONFIRMACION: {
        APROBAR: '¿Está seguro de aprobar esta liquidación?',
        APROBAR_DETALLE: 'La liquidación quedará lista para el proceso de pago',
        RECHAZAR: '¿Está seguro de rechazar esta liquidación?',
        RECHAZAR_DETALLE: 'Debe proporcionar un motivo del rechazo',
        DEVOLVER: '¿Desea devolver esta liquidación al usuario?',
        DEVOLVER_DETALLE: 'El usuario recibirá una notificación para realizar las correcciones',
        BAJA: '¿Está seguro de dar de baja esta liquidación?',
        BAJA_DETALLE: 'Esta liquidación se procesará de forma especial',
        MARCAR_CORRECCION: '¿Ha completado las correcciones solicitadas?',
        MARCAR_CORRECCION_DETALLE: 'La liquidación volverá a revisión de Contabilidad'
    },
    VALIDACION: {
        MOTIVO_REQUERIDO: 'El motivo es requerido'
    }
};

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
}

/**
 * Helper para estados de liquidación
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
            'de_baja': 'bg-gray-50 text-gray-700 border border-gray-200',
            'en_lote': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
            'pagada': 'bg-emerald-50 text-emerald-700 border border-emerald-200'
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
            'de_baja': 'De Baja',
            'en_lote': 'En Lote',
            'pagada': 'Pagada'
        };
        return textos[estado] || estado;
    }

    /**
     * Determina si una liquidación puede ser revisada
     */
    static puedeRevisar(estado: EstadoLiquidacion): boolean {
        return ['enviada', 'corregida'].includes(estado);
    }
}

/**
 * Helper para formateo de datos de contabilidad
 */
export class ContabilidadHelper {
    /**
     * Obtiene el nombre completo del usuario
     */
    static getNombreCompletoUsuario(liquidacion: LiquidacionContabilidad): string {
        return `${liquidacion.nombre_usuario}`.trim();
    }

    /**
     * Obtiene el nombre completo del revisor
     */
    static getNombreCompletoRevisor(liquidacion: LiquidacionContabilidad): string {
        if (!liquidacion.nombre_revisor) return '—';
        return `${liquidacion.nombre_revisor}`.trim();
    }

    /**
     * Obtiene información del vehículo
     */
    static getInfoVehiculo(liquidacion: LiquidacionContabilidad): string {
        if (!liquidacion.vehiculo_placa) return '—';
        return `${liquidacion.vehiculo_placa} - ${liquidacion.vehiculo_marca}`;
    }

    /**
     * Determina si tiene corrección pendiente
     */
    static tieneCorreccionPendiente(liquidacion: LiquidacionContabilidad): boolean {
        return (liquidacion.tiene_correccion_pendiente || 0) > 0;
    }
}