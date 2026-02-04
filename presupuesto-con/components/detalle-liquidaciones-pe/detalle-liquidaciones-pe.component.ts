// ============================================================================
// DETALLE LIQUIDACIONES - REFACTORIZADO CON STORE CENTRALIZADO
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, inject, computed } from '@angular/core';

import { TablaDetalleLiquidacionesComponentpresupuesto } from '../tabla-detalle-liquidaciones/tabla-detalle-liquidaciones-presupuesto.component';
import { ResumenLiquidacionComponentpresupuesto } from '../resumen-liquidacion/resumen-liquidacion.component';

import { PresupuestoStore } from '../../core';

@Component({
    selector: 'app-detalle-liquidizaciones-pe-presupuesto',
    standalone: true,
    imports: [
        CommonModule,
        TablaDetalleLiquidacionesComponentpresupuesto,
        ResumenLiquidacionComponentpresupuesto
    ],
    templateUrl: './detalle-liquidaciones-pe.component.html',
})
export class DetalleLiquidizacionesPlanEmpresarialComponentpresupuesto {

    private readonly store = inject(PresupuestoStore);

    // ============================================================================
    // SIGNALS DIRECTOS DESDE EL STORE
    // ============================================================================

    readonly facturaActual = this.store.factura;
    readonly permisos = this.store.permisos;
    readonly datosResumen = this.store.resumenLiquidacion;
    readonly tieneFactura = this.store.tieneFactura;

    readonly cargandoDetalles = computed(() => this.store.carga().detalles);
    readonly procesandoLiquidacion = computed(() => this.store.carga().procesando);

    // ============================================================================
    // MÉTODOS DE ESTILO
    // ============================================================================

    obtenerClaseEstadoFactura(): string {
        const estado = this.store.factura()?.estado_liquidacion;
        const clases: Record<string, string> = {
            'Liquidado': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'Pagado': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300',
            'Verificado': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            'En Revisión': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300'
        };
        return clases[estado || ''] || 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
    }

    obtenerClaseProgreso(): string {
        const estado = this.store.resumenLiquidacion().estadoMonto;
        const clases: Record<string, string> = {
            'completo': 'text-green-600 dark:text-green-400',
            'excedido': 'text-red-600 dark:text-red-400'
        };
        return clases[estado] || 'text-gray-900 dark:text-gray-100';
    }
}
