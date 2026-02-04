// ============================================================================
// COMPONENTE CONTENEDOR - REFACTORIZADO CON STORE CENTRALIZADO
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, computed } from '@angular/core';

import { OrdenesPlanEmpresarialSimpleComponentpresupuesto } from './components/ordenes-plan-empresarial/ordenes-plan-empresarial.component';
import { FacturasGestionComponentpresupuesto } from './components/facturas-gestion/facturas-gestion.component';
import { DetalleLiquidizacionesPlanEmpresarialComponentpresupuesto } from './components/detalle-liquidaciones-pe/detalle-liquidaciones-pe.component';

import { PresupuestoStore, PresupuestoApiService } from './core';

@Component({
    selector: 'app-presupuesto-con',
    standalone: true,
    imports: [
        CommonModule,
        OrdenesPlanEmpresarialSimpleComponentpresupuesto,
        FacturasGestionComponentpresupuesto,
        DetalleLiquidizacionesPlanEmpresarialComponentpresupuesto
    ],
    templateUrl: './presupuesto-con.component.html',
})
export class presupuestoConComponent implements OnInit {

    private readonly store = inject(PresupuestoStore);
    private readonly api = inject(PresupuestoApiService);

    // ============================================================================
    // SIGNALS DIRECTOS DESDE EL STORE
    // ============================================================================

    readonly facturaActual = this.store.factura;
    readonly resumenLiquidaciones = this.store.resumenLiquidacion;
    readonly resumenOrdenes = this.store.resumenOrdenes;
    readonly estaOcupado = this.store.estaCargando;
    readonly fechaUltimaActualizacion = this.store.ultimaActualizacion;

    // ============================================================================
    // COMPUTED SIGNALS
    // ============================================================================

    readonly obtenerInfoDiasHabiles = computed(() => {
        const factura = this.store.factura();
        if (!factura?.dias_habiles) return null;

        const dh = factura.dias_habiles;
        return {
            diasTranscurridos: dh.dias_transcurridos,
            totalPermitido: dh.total_permitido,
            estadoTiempo: dh.estado_tiempo,
            excedeLimite: dh.excede_limite,
            progresoDias: dh.total_permitido > 0
                ? (dh.dias_transcurridos / dh.total_permitido) * 100
                : 0
        };
    });

    // ============================================================================
    // LIFECYCLE
    // ============================================================================

    ngOnInit(): void {
        this.api.cargarOrdenes().subscribe();
    }

    // ============================================================================
    // MÉTODOS PÚBLICOS
    // ============================================================================

    refrescarTodo(): void {
        if (this.store.estaCargando()) return;

        this.api.cargarOrdenes().subscribe(() => {
            const factura = this.store.factura();
            if (factura) {
                this.api.cargarDetalles(factura.numero_dte).subscribe();
            }
        });
    }

    obtenerClaseEstadoLiquidacion(): string {
        const factura = this.store.factura();
        if (!factura) return 'text-gray-400';

        const resumen = this.store.resumenLiquidacion();
        const diferencia = Math.abs(resumen.total - factura.monto_total);
        const baseClasses = 'text-lg font-semibold';

        if (diferencia < 0.01) {
            return `${baseClasses} text-green-600 dark:text-green-400`;
        } else if (resumen.total > factura.monto_total) {
            return `${baseClasses} text-red-600 dark:text-red-400`;
        }
        return `${baseClasses} text-yellow-600 dark:text-yellow-400`;
    }
}
