// ============================================================================
// SERVICIO - LIQUIDACIONES DE COMBUSTIBLE
// ============================================================================

import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject, map, catchError, of, finalize } from 'rxjs';
import { ServicioGeneralService } from '../../../servicios/servicio-general.service';

import {
    Liquidacion,
    LiquidacionForm,
    SolicitudAutorizacionForm,
    DatosFacturaLiquidacion,
    PresupuestoDisponible,
    Vehiculo,
    TipoApoyo,
    ApiResponse,
    MENSAJES_LIQUIDACIONES
} from '../models/liquidaciones.models';

@Injectable({
    providedIn: 'root'
})
export class LiquidacionesService {

    private readonly api = inject(ServicioGeneralService);

    // ========================================================================
    // ESTADO DEL SERVICIO
    // ========================================================================

    private readonly _liquidaciones$ = new BehaviorSubject<Liquidacion[]>([]);
    private readonly _vehiculos$ = new BehaviorSubject<Vehiculo[]>([]);
    private readonly _tiposApoyo$ = new BehaviorSubject<TipoApoyo[]>([]);
    private readonly _presupuesto$ = new BehaviorSubject<PresupuestoDisponible | null>(null);
    private readonly _cargando$ = new BehaviorSubject<boolean>(false);
    private readonly _error$ = new BehaviorSubject<string | null>(null);

    // Observables públicos
    readonly liquidaciones$ = this._liquidaciones$.asObservable();
    readonly vehiculos$ = this._vehiculos$.asObservable();
    readonly tiposApoyo$ = this._tiposApoyo$.asObservable();
    readonly presupuesto$ = this._presupuesto$.asObservable();
    readonly cargando$ = this._cargando$.asObservable();
    readonly error$ = this._error$.asObservable();

    // ========================================================================
    // LISTAR LIQUIDACIONES
    // ========================================================================

    /**
     * Lista todas las liquidaciones del usuario actual
     * 
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    listarMisLiquidaciones(): Observable<boolean> {
        this._cargando$.next(true);
        this._error$.next(null);

        return this.api.query({
            ruta: 'combustible/listarMisLiquidaciones',
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<{ registros: Liquidacion[], total: number }>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._liquidaciones$.next(response.datos.registros || []);
                    return true;
                }
                throw new Error('Error al cargar liquidaciones');
            }),
            catchError(this._manejarError(MENSAJES_LIQUIDACIONES.ERROR.CARGAR)),
            finalize(() => this._cargando$.next(false))
        );
    }

    // ========================================================================
    // BUSCAR FACTURA
    // ========================================================================

    /**
     * Busca una factura y valida si puede ser liquidada
     * 
     * @param numeroFactura Número de factura a buscar
     * @returns Observable con datos de la factura y validaciones
     */
    buscarFacturaParaLiquidacion(numeroFactura: string): Observable<DatosFacturaLiquidacion | null> {
        this._cargando$.next(true);
        this._error$.next(null);

        return this.api.query({
            ruta: 'combustible/buscarFacturaParaLiquidacion',
            tipo: 'post',
            body: { numero_factura: numeroFactura }
        }).pipe(
            map((response: ApiResponse<DatosFacturaLiquidacion>) => {
                if (response.respuesta === 'success' && response.datos) {
                    return response.datos;
                }
                else if (response.respuesta === 'info') {
                    this.api.mensajeServidor('info', response.mensaje || '');
                    return null;
                }
                throw new Error(response.mensaje || MENSAJES_LIQUIDACIONES.ERROR.BUSCAR_FACTURA);
            }),
            catchError((error) => {
                console.error('Error en buscarFacturaParaLiquidacion:', error);
                this._error$.next(error.mensaje || MENSAJES_LIQUIDACIONES.ERROR.BUSCAR_FACTURA);
                this.api.mensajeServidor('error', error.mensaje || MENSAJES_LIQUIDACIONES.ERROR.BUSCAR_FACTURA);
                return of(null);
            }),
            finalize(() => this._cargando$.next(false))
        );
    }

    // ========================================================================
    // PRESUPUESTO DISPONIBLE
    // ========================================================================

    /**
     * Obtiene el presupuesto disponible del usuario actual
     * 
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    obtenerPresupuestoDisponible(): Observable<boolean> {
        this._cargando$.next(true);
        this._error$.next(null);

        return this.api.query({
            ruta: 'combustible/obtenerPresupuestoDisponible',
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<PresupuestoDisponible>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._presupuesto$.next(response.datos);
                    return true;
                }
                throw new Error(MENSAJES_LIQUIDACIONES.ERROR.PRESUPUESTO);
            }),
            catchError(this._manejarError(MENSAJES_LIQUIDACIONES.ERROR.PRESUPUESTO)),
            finalize(() => this._cargando$.next(false))
        );
    }

    // ========================================================================
    // CREAR LIQUIDACIÓN
    // ========================================================================

    /**
     * Crea una nueva liquidación
     * 
     * @param datos Datos de la liquidación
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    crearLiquidacion(datos: LiquidacionForm): Observable<boolean> {
        return this._ejecutarAccion(
            'combustible/crearLiquidacion',
            datos,
            MENSAJES_LIQUIDACIONES.EXITO.CREAR,
            MENSAJES_LIQUIDACIONES.ERROR.CREAR
        );
    }

    // ========================================================================
    // EDITAR LIQUIDACIÓN
    // ========================================================================

    /**
     * Edita una liquidación existente
     * 
     * @param datos Datos de la liquidación
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    editarLiquidacion(datos: LiquidacionForm): Observable<boolean> {
        if (!datos.idLiquidaciones) {
            this.api.mensajeServidor('error', 'ID de liquidación es requerido');
            return of(false);
        }

        return this._ejecutarAccion(
            'combustible/editarLiquidacion',
            datos,
            MENSAJES_LIQUIDACIONES.EXITO.EDITAR,
            MENSAJES_LIQUIDACIONES.ERROR.EDITAR
        );
    }

    // ========================================================================
    // ELIMINAR LIQUIDACIÓN
    // ========================================================================

    /**
     * Elimina una liquidación
     * 
     * @param id ID de la liquidación
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    eliminarLiquidacion(id: number): Observable<boolean> {
        const payload = { idLiquidaciones: id };

        return this._ejecutarAccion(
            'combustible/eliminarLiquidacion',
            payload,
            MENSAJES_LIQUIDACIONES.EXITO.ELIMINAR,
            MENSAJES_LIQUIDACIONES.ERROR.ELIMINAR
        );
    }

    // ========================================================================
    // SOLICITUD DE AUTORIZACIÓN
    // ========================================================================

    /**
     * Crea una solicitud de autorización
     * 
     * @param datos Datos de la solicitud
     * @param archivo Archivo opcional
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    crearSolicitudAutorizacion(datos: SolicitudAutorizacionForm, archivo?: File): Observable<boolean> {
        this._cargando$.next(true);
        this._error$.next(null);

        // Definimos el objeto con el tipo que espera queryFormData
        const archivos: { [key: string]: File } = {};

        if (archivo) {
            archivos['archivo'] = archivo;
        }

        return this.api.queryFormData(
            'combustible/crearSolicitudAutorizacion',
            datos,
            archivos // Ahora el tipo coincide perfectamente
        ).pipe(
            map((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', MENSAJES_LIQUIDACIONES.EXITO.AUTORIZACION_CREADA);
                    return true;
                }
                throw new Error(response.mensajes?.[0] || MENSAJES_LIQUIDACIONES.ERROR.AUTORIZACION);
            }),
            catchError(this._manejarError(MENSAJES_LIQUIDACIONES.ERROR.AUTORIZACION)),
            finalize(() => this._cargando$.next(false))
        );
    }

    // ========================================================================
    // DATOS AUXILIARES
    // ========================================================================

    /**
     * Lista vehículos del usuario actual
     * 
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    listarMisVehiculos(): Observable<boolean> {
        return this.api.query({
            ruta: 'combustible/listarMisVehiculos',
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<{ vehiculos: Vehiculo[] }>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._vehiculos$.next(response.datos.vehiculos || []);
                    return true;
                }
                return false;
            }),
            catchError(() => of(false))
        );
    }

    /**
     * Lista tipos de apoyo activos
     * 
     * @returns Observable<boolean> true si la operación fue exitosa
     */
    listarTiposApoyoActivos(): Observable<boolean> {
        return this.api.query({
            ruta: 'combustible/listarTiposApoyoActivos',
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<{ registros: TipoApoyo[] }>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._tiposApoyo$.next(response.datos.registros || []);
                    return true;
                }
                return false;
            }),
            catchError(() => of(false))
        );
    }

    // ========================================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ========================================================================

    /**
 * Ejecuta una acción genérica con manejo de errores estándar
 */
    private _ejecutarAccion(
        ruta: string,
        payload: any,
        mensajeExito: string,
        mensajeError: string
    ): Observable<boolean> {
        this._cargando$.next(true);
        this._error$.next(null);

        return this.api.query({
            ruta,
            tipo: 'post',
            body: payload
        }).pipe(
            map((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', mensajeExito);
                    // Actualizar presupuesto después de operación exitosa
                    this.obtenerPresupuestoDisponible().subscribe();
                    return true;
                }
                throw new Error(response.mensajes?.[0] || mensajeError);
            }),
            catchError(this._manejarError(mensajeError)),
            finalize(() => this._cargando$.next(false))
        );
    }

    /**
     * Manejador de errores estándar
     */
    private _manejarError(mensaje: string) {
        return (error: any) => {
            console.error('Error en servicio:', error);
            this._error$.next(mensaje);
            this.api.mensajeServidor('error', mensaje);
            return of(false);
        };
    }

    // ========================================================================
    // GETTERS PARA ACCESO DIRECTO AL ESTADO
    // ========================================================================

    /**
     * Obtiene las liquidaciones actuales del estado
     */
    obtenerLiquidacionesActuales(): Liquidacion[] {
        return this._liquidaciones$.value;
    }

    /**
     * Obtiene los vehículos actuales del estado
     */
    obtenerVehiculosActuales(): Vehiculo[] {
        return this._vehiculos$.value;
    }

    /**
     * Obtiene los tipos de apoyo actuales del estado
     */
    obtenerTiposApoyoActuales(): TipoApoyo[] {
        return this._tiposApoyo$.value;
    }

    /**
     * Obtiene el presupuesto actual del estado
     */
    obtenerPresupuestoActual(): PresupuestoDisponible | null {
        return this._presupuesto$.value;
    }

    // ========================================================================
    // LIMPIEZA
    // ========================================================================

    /**
     * Limpia el estado del servicio
     */
    limpiarEstado(): void {
        this._error$.next(null);
    }

    /**
     * Resetea solo el error
     */
    limpiarError(): void {
        this._error$.next(null);
    }
}