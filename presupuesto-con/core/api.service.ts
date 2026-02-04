// ============================================================================
// SERVICIO API SIMPLIFICADO - TODAS LAS OPERACIONES HTTP
// ============================================================================

import { Injectable, inject } from '@angular/core';
import { Observable, of, catchError, map, tap, finalize, switchMap } from 'rxjs';
import { ServicioGeneralService } from '../../../servicios/servicio-general.service';
import { PresupuestoStore } from './store';
import { toNumber, toBoolean } from './utils';
import {
    FacturaPE,
    DetalleLiquidacionPE,
    OrdenPE,
    AnticipoPE,
    AgenciaPE,
    AreaPresupuestoPE,
    BancoPE,
    TipoCuentaPE,
    DiasHabilesInfo,
    ApiResponse,
    BuscarFacturaPayload,
    RegistrarFacturaPayload,
    SolicitarAutorizacionPayload,
    LiquidarFacturaPayload,
    GuardarDetalleLiquidacionPayload,
    SolicitudAutorizacionAnticipoPayload,
    ENDPOINTS
} from './types';

@Injectable({ providedIn: 'root' })
export class PresupuestoApiService {

    private readonly api = inject(ServicioGeneralService);
    private readonly store = inject(PresupuestoStore);

    // ============================================================================
    // FACTURAS
    // ============================================================================

    buscarFactura(numeroDte: string): Observable<boolean> {
        const texto = numeroDte.trim();
        if (!texto) {
            this.store.limpiarFactura();
            return of(false);
        }

        if (this.store.estaCargandoTipo('factura')) return of(false);

        this.store.setCargando('factura', true);

        return this.api.query({
            ruta: ENDPOINTS.BUSCAR_FACTURA,
            tipo: 'post',
            body: { texto } as BuscarFacturaPayload
        }).pipe(
            switchMap((response: ApiResponse<FacturaPE[]>) => {
                if (response.respuesta === 'success' && response.datos?.length) {
                    const factura = this._mapearFactura(response.datos[0]);
                    this.store.setFactura(factura);
                    return this.cargarDetalles(factura.numero_dte).pipe(map(() => true));
                }
                this.store.limpiarFactura();
                this.api.mensajeServidor('info', 'Factura no encontrada');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al buscar factura:', error);
                this.store.limpiarFactura();
                this.api.mensajeServidor('error', 'Error al buscar la factura');
                return of(false);
            }),
            finalize(() => this.store.setCargando('factura', false))
        );
    }

    registrarFactura(payload: RegistrarFacturaPayload): Observable<boolean> {
        this.store.setCargando('factura', true);

        return this.api.query({
            ruta: ENDPOINTS.REGISTRAR_FACTURA,
            tipo: 'post',
            body: payload
        }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Factura registrada correctamente');
                    return this.buscarFactura(payload.numero_dte);
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al registrar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al registrar factura:', error);
                this.api.mensajeServidor('error', 'Error al registrar la factura');
                return of(false);
            }),
            finalize(() => this.store.setCargando('factura', false))
        );
    }

    liquidarFactura(numeroDte: string): Observable<boolean> {
        this.store.setCargando('procesando', true);

        return this.api.query({
            ruta: ENDPOINTS.LIQUIDAR_FACTURA,
            tipo: 'post',
            body: { numero_dte: numeroDte, confirmar: true } as LiquidarFacturaPayload
        }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Factura liquidada correctamente');
                    return this.buscarFactura(numeroDte);
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al liquidar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al liquidar:', error);
                this.api.mensajeServidor('error', 'Error al liquidar la factura');
                return of(false);
            }),
            finalize(() => this.store.setCargando('procesando', false))
        );
    }

    solicitarAutorizacion(payload: SolicitarAutorizacionPayload): Observable<boolean> {
        return this.api.query({
            ruta: ENDPOINTS.SOLICITAR_AUTORIZACION,
            tipo: 'post',
            body: payload
        }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Solicitud enviada correctamente');
                    return this.buscarFactura(payload.numero_dte);
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al enviar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al solicitar autorización:', error);
                this.api.mensajeServidor('error', 'Error al enviar la solicitud');
                return of(false);
            })
        );
    }

    // ============================================================================
    // DETALLES DE LIQUIDACIÓN
    // ============================================================================

    cargarDetalles(numeroFactura: string): Observable<DetalleLiquidacionPE[]> {
        this.store.setCargando('detalles', true);

        return this.api.query({
            ruta: ENDPOINTS.OBTENER_DETALLES,
            tipo: 'post',
            body: { numero_factura: numeroFactura }
        }).pipe(
            map((response: ApiResponse<DetalleLiquidacionPE[]>) => {
                const detalles = response.respuesta === 'success' && response.datos
                    ? response.datos.map(d => this._mapearDetalle(d))
                    : [];
                this.store.setDetalles(detalles);
                return detalles;
            }),
            catchError(() => {
                this.api.mensajeServidor('error', 'Error al cargar detalles');
                this.store.setDetalles([]);
                return of([]);
            }),
            finalize(() => this.store.setCargando('detalles', false))
        );
    }

    guardarDetalle(payload: GuardarDetalleLiquidacionPayload): Observable<boolean> {
        return this.api.query({
            ruta: ENDPOINTS.GUARDAR_DETALLE,
            tipo: 'post',
            body: payload
        }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Detalle guardado correctamente');
                    return this._recargarDetallesSiHayFactura();
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al guardar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al guardar detalle:', error);
                this.api.mensajeServidor('error', 'Error al guardar detalle');
                return of(false);
            })
        );
    }

    actualizarDetalle(payload: Partial<GuardarDetalleLiquidacionPayload>): Observable<boolean> {
        const camposEnviados = Object.keys(payload).filter(k => k !== 'id');
        const soloMontoOAgencia = camposEnviados.length <= 2 &&
            (camposEnviados.includes('monto') || camposEnviados.includes('agencia'));

        const endpoint = soloMontoOAgencia
            ? ENDPOINTS.ACTUALIZAR_MONTO_AGENCIA
            : ENDPOINTS.ACTUALIZAR_DETALLE;

        return this.api.query({ ruta: endpoint, tipo: 'post', body: payload }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Detalle actualizado');
                    return this._recargarDetallesSiHayFactura();
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al actualizar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al actualizar:', error);
                this.api.mensajeServidor('error', 'Error al actualizar detalle');
                return of(false);
            })
        );
    }

    eliminarDetalle(id: number): Observable<boolean> {
        return this.api.query({
            ruta: ENDPOINTS.ELIMINAR_DETALLE,
            tipo: 'post',
            body: { id }
        }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Detalle eliminado');
                    return this._recargarDetallesSiHayFactura();
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al eliminar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al eliminar:', error);
                this.api.mensajeServidor('error', 'Error al eliminar detalle');
                return of(false);
            })
        );
    }

    copiarDetalle(id: number): Observable<boolean> {
        return this.api.query({
            ruta: ENDPOINTS.REALIZAR_COPIA,
            tipo: 'post',
            body: { id }
        }).pipe(
            switchMap((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Detalle copiado');
                    return this._recargarDetallesSiHayFactura();
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error al copiar');
                return of(false);
            }),
            catchError(error => {
                console.error('Error al copiar:', error);
                this.api.mensajeServidor('error', 'Error al copiar detalle');
                return of(false);
            })
        );
    }

    obtenerDetalleCompleto(id: number): Observable<ApiResponse> {
        return this.api.query({
            ruta: ENDPOINTS.OBTENER_DETALLE_COMPLETO,
            tipo: 'post',
            body: { id }
        }).pipe(
            catchError(error => {
                console.error('Error al obtener detalle:', error);
                return of({ respuesta: 'error' as const, mensaje: 'Error' });
            })
        );
    }

    // ============================================================================
    // ÓRDENES Y ANTICIPOS
    // ============================================================================

    cargarOrdenes(): Observable<boolean> {
        if (this.store.estaCargandoTipo('ordenes')) return of(false);

        this.store.setCargando('ordenes', true);

        return this.api.query({
            ruta: ENDPOINTS.OBTENER_ORDENES,
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<any[]>) => {
                if (response.respuesta === 'success' && response.datos) {
                    const ordenes = response.datos.map(o => this._mapearOrden(o)).filter(o => o.numero_orden > 0);
                    this.store.setOrdenes(ordenes);
                    return true;
                }
                return false;
            }),
            catchError(error => {
                console.error('Error al cargar órdenes:', error);
                this.api.mensajeServidor('error', 'No se pudieron cargar las órdenes');
                return of(false);
            }),
            finalize(() => this.store.setCargando('ordenes', false))
        );
    }

    cargarAnticipos(numeroOrden: number): Observable<boolean> {
        if (!numeroOrden || numeroOrden <= 0) {
            this.store.setAnticipos([]);
            return of(true);
        }

        return this.api.query({
            ruta: `${ENDPOINTS.OBTENER_ANTICIPOS}?numeroOrden=${numeroOrden}`,
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<any[]>) => {
                if (response.respuesta === 'success') {
                    const anticipos = (response.datos || []).map(a => this._mapearAnticipo(a));
                    this.store.setAnticipos(anticipos);
                    return true;
                }
                return false;
            }),
            catchError(error => {
                console.error('Error al cargar anticipos:', error);
                this.api.mensajeServidor('error', 'No se pudieron cargar los anticipos');
                return of(false);
            })
        );
    }

    solicitarAutorizacionAnticipo(payload: SolicitudAutorizacionAnticipoPayload): Observable<boolean> {
        return this.api.query({
            ruta: ENDPOINTS.SOLICITAR_AUTORIZACION_ANTICIPO,
            tipo: 'post',
            body: payload
        }).pipe(
            map((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', 'Solicitud enviada correctamente');
                    return true;
                }
                this.api.mensajeServidor('error', response.respuesta || 'Error');
                return false;
            }),
            catchError(error => {
                console.error('Error:', error);
                this.api.mensajeServidor('error', 'Error al enviar la solicitud');
                return of(false);
            })
        );
    }

    // ============================================================================
    // CATÁLOGOS
    // ============================================================================

    cargarCatalogos(): Observable<boolean> {
        this.store.setCargando('catalogos', true);

        return new Observable(observer => {
            Promise.all([
                this._cargarAgencias().toPromise(),
                this._cargarAreas().toPromise()
            ]).then(() => {
                observer.next(true);
                observer.complete();
            }).catch(() => {
                observer.next(false);
                observer.complete();
            }).finally(() => {
                this.store.setCargando('catalogos', false);
            });
        });
    }

    private _cargarAgencias(): Observable<boolean> {
        return this.api.query({ ruta: ENDPOINTS.OBTENER_AGENCIAS, tipo: 'get' }).pipe(
            map((response: ApiResponse<AgenciaPE[]>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this.store.setAgencias(response.datos);
                    return true;
                }
                return false;
            }),
            catchError(() => of(false))
        );
    }

    private _cargarAreas(): Observable<boolean> {
        return this.api.query({ ruta: ENDPOINTS.OBTENER_AREAS_PRESUPUESTO, tipo: 'get' }).pipe(
            map((response: ApiResponse<AreaPresupuestoPE[]>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this.store.setAreas(response.datos);
                    return true;
                }
                return false;
            }),
            catchError(() => of(false))
        );
    }

    cargarBancos(): Observable<boolean> {
        return this.api.query({ ruta: ENDPOINTS.OBTENER_BANCOS, tipo: 'get' }).pipe(
            map((response: ApiResponse<BancoPE[]>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this.store.setBancos(response.datos);
                    return true;
                }
                return false;
            }),
            catchError(() => of(false))
        );
    }

    cargarTiposCuenta(): Observable<boolean> {
        return this.api.query({ ruta: ENDPOINTS.OBTENER_TIPOS_CUENTA, tipo: 'get' }).pipe(
            map((response: ApiResponse<TipoCuentaPE[]>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this.store.setTiposCuenta(response.datos);
                    return true;
                }
                return false;
            }),
            catchError(() => of(false))
        );
    }

    // ============================================================================
    // MÉTODOS PRIVADOS - MAPPERS
    // ============================================================================

    private _mapearFactura(api: any): FacturaPE {
        return {
            id: api.id,
            numero_dte: api.numero_dte || '',
            fecha_emision: api.fecha_emision || '',
            numero_autorizacion: api.numero_autorizacion || '',
            tipo_dte: api.tipo_dte || '',
            nombre_emisor: api.nombre_emisor || '',
            monto_total: toNumber(api.monto_total),
            monto_liquidado: toNumber(api.monto_liquidado),
            estado_liquidacion: this._mapearEstadoLiquidacion(api.estado_liquidacion || api.estado),
            moneda: (api.moneda as 'GTQ' | 'USD') || 'GTQ',
            dias_transcurridos: toNumber(api.dias_transcurridos),
            estado_autorizacion: this._mapearEstadoAutorizacion(api.estado_autorizacion),
            motivo_autorizacion: api.motivo_autorizacion,
            fecha_solicitud: api.fecha_solicitud,
            fecha_autorizacion: api.fecha_autorizacion,
            comentarios_autorizacion: api.comentarios_autorizacion,
            estado_factura: api.estado_factura || 'vigente',
            cantidad_liquidaciones: toNumber(api.cantidad_liquidaciones),
            monto_retencion: toNumber(api.monto_retencion),
            tipo_retencion: api.tipo_retencion,
            solicitado_por: api.solicitado_por,
            autorizado_por: api.autorizado_por,
            autorizacion_id: api.autorizacion_id,
            tiene_autorizacion_tardanza: api.tiene_autorizacion_tardanza,
            estado: api.estado,
            estado_id: api.estado_id,
            fecha_creacion: api.fecha_creacion,
            fecha_actualizacion: api.fecha_actualizacion,
            dias_habiles: api.dias_habiles ? this._mapearDiasHabiles(api.dias_habiles) : undefined
        };
    }

    private _mapearDiasHabiles(api: any): DiasHabilesInfo {
        return {
            fecha_emision: api.fecha_emision || '',
            fecha_inicio_calculo: api.fecha_inicio_calculo || '',
            fecha_fin_calculo: api.fecha_fin_calculo || '',
            dias_transcurridos: toNumber(api.dias_transcurridos),
            dias_permitidos: toNumber(api.dias_permitidos),
            dias_gracia: toNumber(api.dias_gracia),
            total_permitido: toNumber(api.total_permitido),
            excede_limite: Boolean(api.excede_limite),
            requiere_autorizacion: Boolean(api.requiere_autorizacion),
            estado_tiempo: api.estado_tiempo || 'En tiempo'
        };
    }

    private _mapearDetalle(api: any): DetalleLiquidacionPE {
        return {
            id: api.id,
            numero_orden: String(api.numero_orden || ''),
            agencia: api.agencia || '',
            descripcion: api.descripcion || '',
            monto: toNumber(api.monto),
            correo_proveedor: api.correo_proveedor || '',
            forma_pago: api.forma_pago || 'deposito',
            banco: api.banco || '',
            cuenta: api.cuenta || '',
            tiene_cambios_pendientes: toNumber(api.tiene_cambios_pendientes),
            area_presupuesto: api.area_presupuesto,
            editando: false,
            guardando: false
        };
    }

    private _mapearOrden(api: any): OrdenPE {
        return {
            numero_orden: toNumber(api.numero_orden),
            total: toNumber(api.total),
            monto_liquidado: toNumber(api.monto_liquidado),
            monto_pendiente: toNumber(api.monto_pendiente || (api.total - api.monto_liquidado)),
            total_anticipos: toNumber(api.total_anticipos),
            anticipos_pendientes: toNumber(api.anticipos_pendientes_o_tardios),
            anticipos_declarados: toNumber(api.anticipos_declarados),
            area: api.area || null,
            presupuesto: api.presupuesto || null
        };
    }

    private _mapearAnticipo(api: any): AnticipoPE {
        return {
            id_solicitud: toNumber(api.id_solicitud),
            numero_orden: toNumber(api.numero_orden),
            tipo_anticipo: api.tipo_anticipo || 'CHEQUE',
            monto: toNumber(api.monto),
            fecha_liquidacion: api.fecha_liquidacion || null,
            estado_liquidacion: api.estado_liquidacion || 'NO_LIQUIDADO',
            requiere_autorizacion: toBoolean(api.requiere_autorizacion),
            dias_transcurridos: api.dias_transcurridos != null ? toNumber(api.dias_transcurridos) : null,
            dias_permitidos: api.dias_permitidos != null ? toNumber(api.dias_permitidos) : null,
            ultimo_seguimiento: api.ultimo_seguimiento ? {
                nombre_estado: api.ultimo_seguimiento.nombre_estado || null,
                descripcion_estado: api.ultimo_seguimiento.descripcion_estado || null,
                comentario_solicitante: api.ultimo_seguimiento.comentario_solicitante || null,
                fecha_seguimiento: api.ultimo_seguimiento.fecha_seguimiento || null,
                fecha_autorizacion: api.ultimo_seguimiento.fecha_autorizacion || null,
                comentario_autorizador: api.ultimo_seguimiento.comentario_autorizador || null
            } : null
        };
    }

    private _mapearEstadoLiquidacion(estado: string): 'Pendiente' | 'En Revisión' | 'Verificado' | 'Liquidado' | 'Pagado' {
        const s = (estado || '').toLowerCase();
        if (s.includes('pagado')) return 'Pagado';
        if (s.includes('liquidado')) return 'Liquidado';
        if (s.includes('verificado')) return 'Verificado';
        if (s.includes('revisión') || s.includes('revision')) return 'En Revisión';
        return 'Pendiente';
    }

    private _mapearEstadoAutorizacion(estado: string): 'ninguna' | 'pendiente' | 'aprobada' | 'rechazada' {
        const s = (estado || '').toLowerCase();
        if (!s || s === 'null' || s === 'undefined') return 'ninguna';
        if (s === 'aprobada' || s === 'autorizada') return 'aprobada';
        if (s === 'rechazada') return 'rechazada';
        if (s === 'pendiente') return 'pendiente';
        return 'ninguna';
    }

    private _recargarDetallesSiHayFactura(): Observable<boolean> {
        const factura = this.store.factura();
        if (factura) {
            return this.cargarDetalles(factura.numero_dte).pipe(map(() => true));
        }
        return of(true);
    }
}
