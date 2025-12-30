// ============================================================================
// SERVICIO - CONTABILIDAD
// ============================================================================

import { Injectable, inject } from '@angular/core';
import { Observable, BehaviorSubject, map, catchError, of } from 'rxjs';
import { ServicioGeneralService } from '../../../servicios/servicio-general.service';

import {
    LiquidacionContabilidad,
    SolicitudCorreccion,
    FiltrosLiquidaciones,
    FormularioAprobacion,
    FormularioRechazo,
    FormularioDevolucion,
    FormularioBaja,
    FormularioCorreccionRealizada,
    ApiResponse,
    MENSAJES_CONTABILIDAD
} from '../models/contabilidad.models';

@Injectable({
    providedIn: 'root'
})
export class ContabilidadService {

    private readonly api = inject(ServicioGeneralService);

    // ========================================================================
    // ESTADO DEL SERVICIO
    // ========================================================================

    private readonly _liquidacionesPendientes$ = new BehaviorSubject<LiquidacionContabilidad[]>([]);
    private readonly _historialRevisiones$ = new BehaviorSubject<LiquidacionContabilidad[]>([]);
    private readonly _solicitudesCorreccion$ = new BehaviorSubject<SolicitudCorreccion[]>([]);
    private readonly _error$ = new BehaviorSubject<string | null>(null);

    // Observables públicos
    readonly liquidacionesPendientes$ = this._liquidacionesPendientes$.asObservable();
    readonly historialRevisiones$ = this._historialRevisiones$.asObservable();
    readonly solicitudesCorreccion$ = this._solicitudesCorreccion$.asObservable();
    readonly error$ = this._error$.asObservable();

    // ========================================================================
    // LIQUIDACIONES PENDIENTES
    // ========================================================================

    /**
     * Obtiene liquidaciones pendientes de revisión
     */
    obtenerLiquidacionesPendientes(filtros: FiltrosLiquidaciones = {}): Observable<boolean> {
        this._error$.next(null);

        return this.api.query({
            ruta: 'combustible/listarLiquidacionesPendientesRevision',
            tipo: 'post',
            body: filtros
        }).pipe(
            map((response: ApiResponse<{ registros: LiquidacionContabilidad[], total: number }>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._liquidacionesPendientes$.next(response.datos.registros || []);
                    return true;
                }
                throw new Error('Error al cargar liquidaciones pendientes');
            }),
            catchError(this._manejarError(MENSAJES_CONTABILIDAD.ERROR.CARGAR_PENDIENTES))
        );
    }

    // ========================================================================
    // APROBAR LIQUIDACIÓN
    // ========================================================================

    /**
     * Aprueba una liquidación
     */
    aprobarLiquidacion(datos: FormularioAprobacion): Observable<boolean> {
        return this._ejecutarAccion(
            'combustible/aprobarLiquidacion',
            datos,
            MENSAJES_CONTABILIDAD.EXITO.APROBAR,
            MENSAJES_CONTABILIDAD.ERROR.APROBAR
        );
    }

    // ========================================================================
    // RECHAZAR LIQUIDACIÓN
    // ========================================================================

    /**
     * Rechaza una liquidación
     */
    rechazarLiquidacion(datos: FormularioRechazo): Observable<boolean> {
        return this._ejecutarAccion(
            'combustible/rechazarLiquidacion',
            datos,
            MENSAJES_CONTABILIDAD.EXITO.RECHAZAR,
            MENSAJES_CONTABILIDAD.ERROR.RECHAZAR
        );
    }

    // ========================================================================
    // DEVOLVER LIQUIDACIÓN
    // ========================================================================

    /**
     * Devuelve una liquidación solicitando corrección
     */
    devolverLiquidacion(datos: FormularioDevolucion): Observable<boolean> {
        return this._ejecutarAccion(
            'combustible/devolverLiquidacion',
            datos,
            MENSAJES_CONTABILIDAD.EXITO.DEVOLVER,
            MENSAJES_CONTABILIDAD.ERROR.DEVOLVER
        );
    }

    // ========================================================================
    // DAR DE BAJA LIQUIDACIÓN
    // ========================================================================

    /**
     * Da de baja una liquidación
     */
    darDeBajaLiquidacion(datos: FormularioBaja): Observable<boolean> {
        return this._ejecutarAccion(
            'combustible/darDeBajaLiquidacion',
            datos,
            MENSAJES_CONTABILIDAD.EXITO.BAJA,
            MENSAJES_CONTABILIDAD.ERROR.BAJA
        );
    }

    // ========================================================================
    // HISTORIAL DE REVISIONES
    // ========================================================================

    /**
     * Obtiene el historial de revisiones con filtros
     */
    obtenerHistorialRevisiones(filtros: FiltrosLiquidaciones = {}): Observable<boolean> {
        this._error$.next(null);

        return this.api.query({
            ruta: 'combustible/obtenerHistorialRevisiones',
            tipo: 'post',
            body: filtros
        }).pipe(
            map((response: ApiResponse<{ registros: LiquidacionContabilidad[], total: number }>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._historialRevisiones$.next(response.datos.registros || []);
                    return true;
                }
                throw new Error('Error al cargar historial');
            }),
            catchError(this._manejarError(MENSAJES_CONTABILIDAD.ERROR.CARGAR_HISTORIAL))
        );
    }

    // ========================================================================
    // SOLICITUDES DE CORRECCIÓN (USUARIO)
    // ========================================================================

    /**
     * Obtiene las solicitudes de corrección del usuario
     */
    obtenerMisSolicitudesCorreccion(): Observable<boolean> {
        this._error$.next(null);

        return this.api.query({
            ruta: 'combustible/listarMisSolicitudesCorreccion',
            tipo: 'get'
        }).pipe(
            map((response: ApiResponse<{ registros: SolicitudCorreccion[], total: number }>) => {
                if (response.respuesta === 'success' && response.datos) {
                    this._solicitudesCorreccion$.next(response.datos.registros || []);
                    return true;
                }
                throw new Error('Error al cargar solicitudes de corrección');
            }),
            catchError(this._manejarError(MENSAJES_CONTABILIDAD.ERROR.CARGAR_CORRECCIONES))
        );
    }

    /**
     * Marca una corrección como realizada
     */
    marcarCorreccionRealizada(datos: FormularioCorreccionRealizada): Observable<boolean> {
        return this._ejecutarAccion(
            'combustible/marcarCorreccionRealizada',
            datos,
            MENSAJES_CONTABILIDAD.EXITO.CORRECCION_MARCADA,
            MENSAJES_CONTABILIDAD.ERROR.MARCAR_CORRECCION
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
        this._error$.next(null);

        return this.api.query({
            ruta,
            tipo: 'post',
            body: payload
        }).pipe(
            map((response: ApiResponse) => {
                if (response.respuesta === 'success') {
                    this.api.mensajeServidor('success', mensajeExito);
                    return true;
                }
                throw new Error(response.mensajes?.[0] || mensajeError);
            }),
            catchError(this._manejarError(mensajeError))
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
     * Obtiene las liquidaciones pendientes actuales del estado
     */
    obtenerPendientesActuales(): LiquidacionContabilidad[] {
        return this._liquidacionesPendientes$.value;
    }

    /**
     * Obtiene el historial actual del estado
     */
    obtenerHistorialActual(): LiquidacionContabilidad[] {
        return this._historialRevisiones$.value;
    }

    /**
     * Obtiene las solicitudes de corrección actuales del estado
     */
    obtenerCorreccionesActuales(): SolicitudCorreccion[] {
        return this._solicitudesCorreccion$.value;
    }

    // ========================================================================
    // LIMPIEZA
    // ========================================================================

    /**
     * Limpia el error
     */
    limpiarError(): void {
        this._error$.next(null);
    }
}