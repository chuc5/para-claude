// ============================================================================
// STORE CENTRALIZADO - ÚNICA FUENTE DE VERDAD PARA TODO EL ESTADO
// ============================================================================

import { Injectable, computed, signal } from '@angular/core';
import {
    FacturaPE,
    DetalleLiquidacionPE,
    OrdenPE,
    AnticipoPE,
    AgenciaPE,
    AreaPresupuestoPE,
    BancoPE,
    TipoCuentaPE,
    PermisosEdicion,
    ResumenLiquidacion,
    ResumenOrdenes,
    EstadoCarga
} from './types';
import {
    validarPermisosEdicion,
    calcularTotalDetalles,
    calcularEstadoMonto
} from './utils';

@Injectable({ providedIn: 'root' })
export class PresupuestoStore {

    // ============================================================================
    // ESTADO PRINCIPAL (Signals privados)
    // ============================================================================

    private readonly _factura = signal<FacturaPE | null>(null);
    private readonly _detalles = signal<DetalleLiquidacionPE[]>([]);
    private readonly _ordenes = signal<OrdenPE[]>([]);
    private readonly _anticipos = signal<AnticipoPE[]>([]);

    // Catálogos
    private readonly _agencias = signal<AgenciaPE[]>([]);
    private readonly _areas = signal<AreaPresupuestoPE[]>([]);
    private readonly _bancos = signal<BancoPE[]>([]);
    private readonly _tiposCuenta = signal<TipoCuentaPE[]>([]);

    // Estados de carga
    private readonly _carga = signal<EstadoCarga>({
        factura: false,
        detalles: false,
        ordenes: false,
        catalogos: false,
        procesando: false
    });

    // Última actualización
    private readonly _ultimaActualizacion = signal<Date>(new Date());

    // ============================================================================
    // SIGNALS PÚBLICOS (Solo lectura)
    // ============================================================================

    readonly factura = this._factura.asReadonly();
    readonly detalles = this._detalles.asReadonly();
    readonly ordenes = this._ordenes.asReadonly();
    readonly anticipos = this._anticipos.asReadonly();
    readonly agencias = this._agencias.asReadonly();
    readonly areas = this._areas.asReadonly();
    readonly bancos = this._bancos.asReadonly();
    readonly tiposCuenta = this._tiposCuenta.asReadonly();
    readonly carga = this._carga.asReadonly();
    readonly ultimaActualizacion = this._ultimaActualizacion.asReadonly();

    // ============================================================================
    // COMPUTED SIGNALS - DERIVADOS AUTOMÁTICOS
    // ============================================================================

    /** Permisos de edición calculados automáticamente */
    readonly permisos = computed<PermisosEdicion>(() =>
        validarPermisosEdicion(this._factura())
    );

    /** Resumen de liquidación calculado automáticamente */
    readonly resumenLiquidacion = computed<ResumenLiquidacion>(() => {
        const factura = this._factura();
        const detalles = this._detalles();
        const total = calcularTotalDetalles(detalles);
        const montoFactura = factura?.monto_total || 0;
        const montoRetencion = factura?.monto_retencion || 0;

        return {
            cantidad: detalles.length,
            total,
            montoFactura,
            montoRetencion,
            estadoMonto: calcularEstadoMonto(montoFactura, total)
        };
    });

    /** Resumen de órdenes calculado automáticamente */
    readonly resumenOrdenes = computed<ResumenOrdenes>(() => {
        const ordenes = this._ordenes();
        return {
            totalOrdenes: ordenes.length,
            ordenesConPendientes: ordenes.filter(o => o.anticipos_pendientes > 0).length
        };
    });

    /** Estado general de carga */
    readonly estaCargando = computed<boolean>(() => {
        const c = this._carga();
        return c.factura || c.detalles || c.ordenes || c.catalogos || c.procesando;
    });

    /** Hay factura cargada */
    readonly tieneFactura = computed<boolean>(() => this._factura() !== null);

    /** Órdenes disponibles (sin anticipos pendientes) */
    readonly ordenesDisponibles = computed<OrdenPE[]>(() =>
        this._ordenes().filter(o => !o.anticipos_pendientes || o.anticipos_pendientes === 0)
    );

    /** Total de detalles */
    readonly totalDetalles = computed<number>(() =>
        calcularTotalDetalles(this._detalles())
    );

    /** Monto pendiente por liquidar */
    readonly montoPendiente = computed<number>(() => {
        const factura = this._factura();
        if (!factura) return 0;
        return Math.max(0, factura.monto_total - this.totalDetalles());
    });

    /** Porcentaje liquidado */
    readonly porcentajeLiquidado = computed<number>(() => {
        const factura = this._factura();
        if (!factura || factura.monto_total <= 0) return 0;
        return (this.totalDetalles() / factura.monto_total) * 100;
    });

    /** Progreso de días hábiles */
    readonly progresoDias = computed<number>(() => {
        const factura = this._factura();
        if (!factura?.dias_habiles || factura.dias_habiles.total_permitido === 0) return 0;
        return Math.min(
            (factura.dias_habiles.dias_transcurridos / factura.dias_habiles.total_permitido) * 100,
            100
        );
    });

    /** Factura vencida */
    readonly estaVencida = computed<boolean>(() =>
        this._factura()?.dias_habiles?.excede_limite || false
    );

    /** Requiere autorización */
    readonly requiereAutorizacion = computed<boolean>(() =>
        this._factura()?.dias_habiles?.requiere_autorizacion || false
    );

    // ============================================================================
    // MÉTODOS DE ACTUALIZACIÓN (Setters)
    // ============================================================================

    setFactura(factura: FacturaPE | null): void {
        this._factura.set(factura);
        this._actualizarTimestamp();
    }

    setDetalles(detalles: DetalleLiquidacionPE[]): void {
        this._detalles.set(detalles);
        this._actualizarTimestamp();
    }

    setOrdenes(ordenes: OrdenPE[]): void {
        this._ordenes.set(ordenes);
        this._actualizarTimestamp();
    }

    setAnticipos(anticipos: AnticipoPE[]): void {
        this._anticipos.set(anticipos);
    }

    setAgencias(agencias: AgenciaPE[]): void {
        this._agencias.set(agencias);
    }

    setAreas(areas: AreaPresupuestoPE[]): void {
        this._areas.set(areas);
    }

    setBancos(bancos: BancoPE[]): void {
        this._bancos.set(bancos);
    }

    setTiposCuenta(tipos: TipoCuentaPE[]): void {
        this._tiposCuenta.set(tipos);
    }

    // ============================================================================
    // MÉTODOS DE CARGA
    // ============================================================================

    setCargando(tipo: keyof EstadoCarga, valor: boolean): void {
        this._carga.update(c => ({ ...c, [tipo]: valor }));
    }

    estaCargandoTipo(tipo: keyof EstadoCarga): boolean {
        return this._carga()[tipo];
    }

    // ============================================================================
    // MÉTODOS DE LIMPIEZA
    // ============================================================================

    limpiarFactura(): void {
        this._factura.set(null);
        this._detalles.set([]);
        this._actualizarTimestamp();
    }

    limpiarOrdenes(): void {
        this._ordenes.set([]);
        this._anticipos.set([]);
    }

    limpiarCatalogos(): void {
        this._agencias.set([]);
        this._areas.set([]);
        this._bancos.set([]);
        this._tiposCuenta.set([]);
    }

    limpiarTodo(): void {
        this.limpiarFactura();
        this.limpiarOrdenes();
        this.limpiarCatalogos();
    }

    // ============================================================================
    // MÉTODOS DE ACTUALIZACIÓN DE DETALLES
    // ============================================================================

    agregarDetalle(detalle: DetalleLiquidacionPE): void {
        this._detalles.update(detalles => [...detalles, detalle]);
        this._actualizarTimestamp();
    }

    actualizarDetalle(id: number, cambios: Partial<DetalleLiquidacionPE>): void {
        this._detalles.update(detalles =>
            detalles.map(d => d.id === id ? { ...d, ...cambios } : d)
        );
        this._actualizarTimestamp();
    }

    eliminarDetalle(id: number): void {
        this._detalles.update(detalles => detalles.filter(d => d.id !== id));
        this._actualizarTimestamp();
    }

    // ============================================================================
    // MÉTODOS DE UTILIDAD
    // ============================================================================

    obtenerDetalle(id: number): DetalleLiquidacionPE | undefined {
        return this._detalles().find(d => d.id === id);
    }

    obtenerOrden(numeroOrden: number): OrdenPE | undefined {
        return this._ordenes().find(o => o.numero_orden === numeroOrden);
    }

    calcularMontoDisponible(idDetalleExcluir?: number): number {
        const factura = this._factura();
        if (!factura) return 0;

        const totalOtros = this._detalles()
            .filter(d => d.id !== idDetalleExcluir)
            .reduce((sum, d) => sum + (d.monto || 0), 0);

        return Math.round((factura.monto_total - totalOtros) * 100) / 100;
    }

    // ============================================================================
    // PRIVADOS
    // ============================================================================

    private _actualizarTimestamp(): void {
        this._ultimaActualizacion.set(new Date());
    }
}
