// ============================================================================
// FACTURAS GESTIÓN - REFACTORIZADO CON STORE CENTRALIZADO
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, OnInit, OnDestroy, signal, inject, computed } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { Subject, takeUntil, debounceTime, distinctUntilChanged } from 'rxjs';

import { ModalRegistrarFacturaComponentpresupuesto } from '../modal-registrar-factura/modal-registrar-factura.component';
import { ModalSolicitarAutorizacionComponentpresupuesto } from '../modal-solicitar-autorizacion/modal-solicitar-autorizacion.component';

import {
    PresupuestoStore,
    PresupuestoApiService,
    FacturaPE,
    CONFIGURACION,
    formatearMonto,
    formatearFecha,
    obtenerColorEstadoLiquidacion,
    obtenerColorEstadoAutorizacion,
    obtenerColorEstadoFactura,
    obtenerColorEstadoTiempo,
    mostrarBotonAutorizacion,
    hayDiferenciaSignificativa
} from '../../core';

@Component({
    selector: 'app-facturas-gestion-presupuesto',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        ModalRegistrarFacturaComponentpresupuesto,
        ModalSolicitarAutorizacionComponentpresupuesto
    ],
    templateUrl: './facturas-gestion.component.html',
    styleUrls: ['./facturas-gestion.component.css']
})
export class FacturasGestionComponentpresupuesto implements OnInit, OnDestroy {

    private readonly store = inject(PresupuestoStore);
    private readonly api = inject(PresupuestoApiService);
    private readonly destroy$ = new Subject<void>();

    // ============================================================================
    // FORMULARIO Y MODALES (Estado local)
    // ============================================================================

    readonly searchControl = new FormControl<string>('');
    readonly mostrarModalRegistrar = signal(false);
    readonly mostrarModalAutorizacion = signal(false);

    // ============================================================================
    // SIGNALS DESDE EL STORE
    // ============================================================================

    readonly facturaActual = this.store.factura;
    readonly detallesLiquidacion = this.store.detalles;
    readonly permisos = this.store.permisos;
    readonly totalDetalles = this.store.totalDetalles;
    readonly porcentajeLiquidado = this.store.porcentajeLiquidado;
    readonly progresoDias = this.store.progresoDias;

    readonly cargandoFactura = computed(() => this.store.carga().factura);

    // ============================================================================
    // UTILIDADES EXPORTADAS AL TEMPLATE
    // ============================================================================

    readonly formatearMonto = formatearMonto;
    readonly formatearFecha = formatearFecha;
    readonly obtenerColorEstadoLiquidacion = obtenerColorEstadoLiquidacion;
    readonly obtenerColorEstadoAutorizacion = obtenerColorEstadoAutorizacion;
    readonly obtenerColorEstadoFactura = obtenerColorEstadoFactura;
    readonly obtenerColorEstadoTiempo = obtenerColorEstadoTiempo;
    readonly mostrarBotonAutorizacion = mostrarBotonAutorizacion;

    // ============================================================================
    // COMPUTED SIGNALS
    // ============================================================================

    readonly obtenerInfoDiasHabiles = computed(() => {
        const factura = this.store.factura();
        if (!factura?.dias_habiles) return null;

        const dh = factura.dias_habiles;
        return {
            fechaEmision: dh.fecha_emision,
            fechaInicioCalculo: dh.fecha_inicio_calculo,
            fechaFinCalculo: dh.fecha_fin_calculo,
            diasTranscurridos: dh.dias_transcurridos,
            diasPermitidos: dh.dias_permitidos,
            diasGracia: dh.dias_gracia,
            totalPermitido: dh.total_permitido,
            excedeLimite: dh.excede_limite,
            requiereAutorizacion: dh.requiere_autorizacion,
            estadoTiempo: dh.estado_tiempo
        };
    });

    readonly obtenerEstadisticasFactura = computed(() => {
        const factura = this.store.factura();
        if (!factura) return null;

        const totalLiquidado = this.store.totalDetalles();
        const montoPendiente = factura.monto_total - totalLiquidado;
        const porcentaje = this.store.porcentajeLiquidado();

        return {
            montoTotal: factura.monto_total,
            totalLiquidado,
            montoRetencion: factura.monto_retencion || 0,
            montoPendiente,
            cantidadDetalles: this.store.detalles().length,
            porcentajeLiquidado: porcentaje,
            diasTranscurridos: factura.dias_habiles?.dias_transcurridos || 0,
            estadoTiempo: factura.dias_habiles?.estado_tiempo || 'No disponible',
            excedeLimite: factura.dias_habiles?.excede_limite || false,
            requiereAutorizacion: factura.dias_habiles?.requiere_autorizacion || false
        };
    });

    // ============================================================================
    // LIFECYCLE
    // ============================================================================

    ngOnInit(): void {
        this._configurarBusqueda();
        this.api.cargarCatalogos().subscribe();
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ============================================================================
    // BÚSQUEDA
    // ============================================================================

    private _configurarBusqueda(): void {
        this.searchControl.valueChanges.pipe(
            debounceTime(CONFIGURACION.DEBOUNCE_BUSQUEDA_MS),
            distinctUntilChanged(),
            takeUntil(this.destroy$)
        ).subscribe(valor => {
            const termino = (valor || '').trim();
            if (termino.length >= CONFIGURACION.DTE_MIN_LENGTH) {
                this.api.buscarFactura(termino).subscribe();
            } else if (termino.length === 0) {
                this.store.limpiarFactura();
            }
        });
    }

    buscarManual(): void {
        const termino = (this.searchControl.value || '').trim();
        if (termino) {
            this.api.buscarFactura(termino).subscribe();
        }
    }

    // ============================================================================
    // MÉTODOS SIMPLES
    // ============================================================================

    calcularTotalDetalles(): number {
        return this.store.totalDetalles();
    }

    obtenerMontoRetencion(): number {
        return this.store.factura()?.monto_retencion || 0;
    }

    obtenerDiasTranscurridos(): number {
        return this.store.factura()?.dias_habiles?.dias_transcurridos || 0;
    }

    obtenerProgresoDias(): number {
        return this.store.progresoDias();
    }

    hayDiferenciaMontos(): boolean {
        const factura = this.store.factura();
        if (!factura) return false;
        return hayDiferenciaSignificativa(factura.monto_total, this.store.totalDetalles());
    }

    tieneAutorizacion(): boolean {
        const factura = this.store.factura();
        return !!(factura?.estado_autorizacion && factura.estado_autorizacion !== 'ninguna');
    }

    obtenerMensajeEstado(): string {
        const factura = this.store.factura();
        if (!factura) return '';

        switch (factura.estado_liquidacion) {
            case 'Liquidado': return 'Factura completamente liquidada';
            case 'Pagado': return 'Factura liquidada y pagada';
            case 'Verificado': return 'Factura verificada, pendiente de pago';
        }

        if (this.hayDiferenciaMontos()) {
            const dif = Math.abs(factura.monto_total - this.store.totalDetalles());
            return `Diferencia de ${formatearMonto(dif)} entre factura y liquidación`;
        }

        if (this.store.detalles().length === 0) {
            return 'Sin detalles de liquidación registrados';
        }

        return 'Factura lista para liquidación';
    }

    // ============================================================================
    // GESTIÓN DE MODALES
    // ============================================================================

    abrirModalRegistrar(): void {
        this.mostrarModalRegistrar.set(true);
    }

    cerrarModalRegistrar(): void {
        this.mostrarModalRegistrar.set(false);
    }

    abrirModalAutorizacion(): void {
        this.mostrarModalAutorizacion.set(true);
    }

    cerrarModalAutorizacion(): void {
        this.mostrarModalAutorizacion.set(false);
    }

    onFacturaRegistrada(): void {
        this.cerrarModalRegistrar();
    }

    onSolicitudEnviada(): void {
        this.cerrarModalAutorizacion();
    }
}
