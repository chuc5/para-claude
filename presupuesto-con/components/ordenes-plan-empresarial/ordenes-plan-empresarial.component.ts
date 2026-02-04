// ============================================================================
// COMPONENTE DE ÓRDENES - REFACTORIZADO CON STORE CENTRALIZADO
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, signal, inject, computed } from '@angular/core';

import { ModalAnticiposComponentpresupuesto } from '../modal-anticipos/modal-anticipos.component';
import { PresupuestoStore, PresupuestoApiService, OrdenPE, formatearMonto } from '../../core';

@Component({
    selector: 'app-ordenes-plan-empresarial-simple-presupuesto',
    standalone: true,
    imports: [CommonModule, ModalAnticiposComponentpresupuesto],
    templateUrl: './ordenes-plan-empresarial.component.html',
})
export class OrdenesPlanEmpresarialSimpleComponentpresupuesto {

    private readonly store = inject(PresupuestoStore);
    private readonly api = inject(PresupuestoApiService);

    // ============================================================================
    // SIGNALS DESDE EL STORE
    // ============================================================================

    readonly ordenes = this.store.ordenes;
    readonly resumen = this.store.resumenOrdenes;
    readonly cargando = computed(() => this.store.carga().ordenes);

    // Estado local del modal
    readonly modalVisible = signal(false);
    readonly ordenSeleccionada = signal<OrdenPE | null>(null);

    // Utilidad de formateo
    readonly formatearMonto = formatearMonto;

    // ============================================================================
    // ACCIONES
    // ============================================================================

    refrescar(): void {
        if (!this.store.carga().ordenes) {
            this.api.cargarOrdenes().subscribe();
        }
    }

    abrirModalAnticipos(orden: OrdenPE): void {
        this.ordenSeleccionada.set(orden);
        this.modalVisible.set(true);
    }

    cerrarModal(): void {
        this.modalVisible.set(false);
        this.ordenSeleccionada.set(null);
    }

    onSolicitudExitosa(): void {
        if (!this.store.carga().ordenes) {
            this.api.cargarOrdenes().subscribe();
        }
    }

    trackByOrden(index: number, orden: OrdenPE): number {
        return orden.numero_orden;
    }
}
