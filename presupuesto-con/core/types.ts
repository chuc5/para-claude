// ============================================================================
// TIPOS UNIFICADOS - ÚNICA FUENTE DE VERDAD
// ============================================================================

// ============================================================================
// TIPOS BASE
// ============================================================================

export type EstadoLiquidacion = 'Pendiente' | 'En Revisión' | 'Verificado' | 'Liquidado' | 'Pagado';
export type EstadoAutorizacion = 'ninguna' | 'pendiente' | 'aprobada' | 'rechazada';
export type EstadoFactura = 'Vigente' | 'vigente' | 'Anulado' | 'PROCESADO';
export type FormaPago = 'deposito' | 'transferencia' | 'cheque' | 'efectivo' | 'anticipo' | 'tarjeta' | 'contrasena' | 'costoasumido';
export type Moneda = 'GTQ' | 'USD';
export type EstadoTiempo = 'En tiempo' | 'En plazo' | 'Vencido' | 'Por vencer';
export type EstadoMonto = 'completo' | 'incompleto' | 'excedido';

// ============================================================================
// INTERFACES PRINCIPALES
// ============================================================================

export interface DiasHabilesInfo {
    fecha_emision: string;
    fecha_inicio_calculo: string;
    fecha_fin_calculo: string;
    dias_transcurridos: number;
    dias_permitidos: number;
    dias_gracia: number;
    total_permitido: number;
    excede_limite: boolean;
    requiere_autorizacion: boolean;
    estado_tiempo: EstadoTiempo;
}

export interface FacturaPE {
    id?: number;
    numero_dte: string;
    fecha_emision: string;
    numero_autorizacion: string;
    tipo_dte: string;
    nombre_emisor: string;
    monto_total: number;
    monto_liquidado: number;
    estado_liquidacion: EstadoLiquidacion;
    moneda: Moneda;
    dias_transcurridos?: number;
    estado_autorizacion?: EstadoAutorizacion;
    motivo_autorizacion?: string;
    fecha_solicitud?: string;
    fecha_autorizacion?: string;
    comentarios_autorizacion?: string;
    estado_factura?: EstadoFactura;
    cantidad_liquidaciones?: number;
    monto_retencion?: number;
    tipo_retencion?: number;
    solicitado_por?: string;
    autorizado_por?: string;
    autorizacion_id?: number;
    tiene_autorizacion_tardanza?: number;
    estado?: string;
    estado_id?: number;
    fecha_creacion?: string;
    fecha_actualizacion?: string;
    dias_habiles?: DiasHabilesInfo;
    detalles_liquidacion?: DetalleLiquidacionPE[];
}

export interface DetalleLiquidacionPE {
    id?: number;
    numero_orden: string;
    agencia: string;
    descripcion: string;
    monto: number;
    correo_proveedor: string;
    forma_pago: FormaPago | string;
    banco?: string;
    cuenta?: string;
    editando?: boolean;
    guardando?: boolean;
    _editandoMonto?: boolean;
    _montoTemp?: number;
    _editandoAgencia?: boolean;
    _agenciaTemp?: string;
    factura_id?: number;
    fecha_creacion?: string;
    fecha_actualizacion?: string;
    tiene_cambios_pendientes?: number;
    datos_especificos?: any;
    informacion_adicional?: any;
    area_presupuesto?: number | string;
}

export interface OrdenPE {
    numero_orden: number;
    total: number;
    monto_liquidado: number;
    monto_pendiente: number;
    total_anticipos?: number;
    anticipos_pendientes: number;
    anticipos_declarados?: number;
    area?: string | null;
    presupuesto?: string | null;
}

export interface AnticipoPE {
    id_solicitud: number;
    numero_orden: number;
    tipo_anticipo: string;
    monto: number;
    fecha_liquidacion?: string | null;
    estado_liquidacion: string;
    requiere_autorizacion: boolean;
    dias_transcurridos?: number | null;
    dias_permitidos?: number | null;
    ultimo_seguimiento?: UltimoSeguimientoPE | null;
}

export interface UltimoSeguimientoPE {
    nombre_estado?: string | null;
    descripcion_estado?: string | null;
    comentario_solicitante?: string | null;
    fecha_seguimiento?: string | null;
    fecha_autorizacion?: string | null;
    comentario_autorizador?: string | null;
}

// ============================================================================
// CATÁLOGOS
// ============================================================================

export interface AgenciaPE {
    id: number;
    nombre_liquidacion: string;
}

export interface AreaPresupuestoPE {
    id: number;
    nombre_area: string;
}

export interface BancoPE {
    id_banco: number;
    nombre: string;
}

export interface TipoCuentaPE {
    id_tipo_cuenta: number;
    nombre: string;
}

// ============================================================================
// PERMISOS
// ============================================================================

export interface PermisosEdicion {
    puedeVer: boolean;
    puedeEditar: boolean;
    puedeAgregar: boolean;
    puedeEliminar: boolean;
    razon: string;
    claseCSS: string;
}

// ============================================================================
// API
// ============================================================================

export interface ApiResponse<T = any> {
    respuesta: 'success' | 'error';
    datos?: T;
    mensaje?: string | string[];
}

// ============================================================================
// PAYLOADS
// ============================================================================

export interface BuscarFacturaPayload {
    texto: string;
}

export interface RegistrarFacturaPayload {
    numero_dte: string;
    fecha_emision: string;
    numero_autorizacion: string;
    tipo_dte: string;
    nombre_emisor: string;
    monto_total: number;
    moneda: Moneda;
}

export interface SolicitarAutorizacionPayload {
    numero_dte: string;
    motivo: string;
    dias_transcurridos: number;
}

export interface LiquidarFacturaPayload {
    numero_dte: string;
    confirmar: boolean;
}

export interface GuardarDetalleLiquidacionPayload {
    id?: number;
    numero_factura: string;
    numero_orden: string;
    agencia: string;
    descripcion: string;
    monto: number;
    correo_proveedor?: string;
    forma_pago: string;
    banco?: string;
    cuenta?: string;
    area_presupuesto?: string;
    datos_especificos?: any;
}

export interface SolicitudAutorizacionAnticipoPayload {
    id_solicitud: number;
    justificacion: string;
    tipo: 'autorizacion';
}

// ============================================================================
// RESUMEN Y ESTADÍSTICAS
// ============================================================================

export interface ResumenLiquidacion {
    cantidad: number;
    total: number;
    montoFactura: number;
    montoRetencion: number;
    estadoMonto: EstadoMonto;
}

export interface ResumenOrdenes {
    totalOrdenes: number;
    ordenesConPendientes: number;
}

// ============================================================================
// ESTADO DE CARGA
// ============================================================================

export interface EstadoCarga {
    factura: boolean;
    detalles: boolean;
    ordenes: boolean;
    catalogos: boolean;
    procesando: boolean;
}

// ============================================================================
// CONSTANTES
// ============================================================================

export const TIPOS_DTE = [
    { codigo: '1', nombre: 'RECIBO' },
    { codigo: '2', nombre: 'FACT' }
] as const;

export const AUTORIZACIONES = [
    { codigo: '1', nombre: 'COOPERATIVA EL BIENESTAR' },
    { codigo: '2', nombre: 'PEDRO NOE YAC' }
] as const;

export const MONEDAS: Moneda[] = ['GTQ', 'USD'];

export const FORMAS_PAGO = [
    { id: 'deposito', nombre: 'Depósito' },
    { id: 'transferencia', nombre: 'Transferencia' },
    { id: 'cheque', nombre: 'Cheque' },
    { id: 'anticipo', nombre: 'Anticipo' },
    { id: 'tarjeta', nombre: 'Tarjeta de Credito' },
    { id: 'contrasena', nombre: 'Pago por Contraseña' },
    { id: 'costoasumido', nombre: 'Costo Asumido por el Colaborador' }
] as const;

export const ESTADOS_LIQUIDACION = [
    { codigo: 'Pendiente', nombre: 'Pendiente' },
    { codigo: 'En Revisión', nombre: 'En Revisión' },
    { codigo: 'Verificado', nombre: 'Verificado' },
    { codigo: 'Liquidado', nombre: 'Liquidado' },
    { codigo: 'Pagado', nombre: 'Pagado' }
] as const;

export const ENDPOINTS = {
    BUSCAR_FACTURA: 'contabilidad/buscarPorNumeroDtepresupuesto',
    REGISTRAR_FACTURA: 'facturas/registro/facturaManual',
    LIQUIDAR_FACTURA: 'contabilidad/liquidarFactura',
    SOLICITAR_AUTORIZACION: 'facturas/solicitarAutorizacionTardanza',
    OBTENER_DETALLES: 'contabilidad/obtenerDetallesLiquidacionpresupuesto',
    GUARDAR_DETALLE: 'contabilidad/guardarDetalleLiquidacionpresupuesto',
    ELIMINAR_DETALLE: 'contabilidad/eliminarDetalleLiquidacion',
    ACTUALIZAR_DETALLE: 'contabilidad/actualizarDetalleLiquidacion',
    ACTUALIZAR_MONTO_AGENCIA: 'contabilidad/actualizarMontoAgencia',
    OBTENER_ORDENES: 'contabilidad/obtenerOrdenesAutorizadasPresupuesto',
    OBTENER_AGENCIAS: 'contabilidad/buscarNombreLiquidacion',
    OBTENER_AREAS_PRESUPUESTO: 'contabilidad/obtenerAreasPresupuesto',
    OBTENER_BANCOS: 'facturas/bancos/lista',
    OBTENER_TIPOS_CUENTA: 'facturas/tiposCuenta/lista',
    REALIZAR_COPIA: 'contabilidad/copiarDetalleLiquidacion',
    OBTENER_DETALLE_COMPLETO: 'contabilidad/obtenerDetalleCompleto',
    OBTENER_ANTICIPOS: 'contabilidad/obtenerSolicitudesPendientesAnticiposPresupuesto',
    SOLICITAR_AUTORIZACION_ANTICIPO: 'contabilidad/solicitarAutorizacionAnticiposPendientes'
} as const;

export const CONFIGURACION = {
    DTE_MIN_LENGTH: 3,
    DTE_MAX_LENGTH: 25,
    NOMBRE_EMISOR_MIN_LENGTH: 3,
    NOMBRE_EMISOR_MAX_LENGTH: 200,
    MOTIVO_MIN_LENGTH: 10,
    MOTIVO_MAX_LENGTH: 500,
    DESCRIPCION_MIN_LENGTH: 5,
    MONTO_MAX: 999999999.99,
    DEBOUNCE_BUSQUEDA_MS: 1000,
    TOLERANCIA_DIFERENCIA_MONTOS: 0.01
} as const;
