// ============================================================================
// COMPONENTE - WIDGET DE PRESUPUESTO DISPONIBLE
// ============================================================================
// Muestra el presupuesto anual y mensual disponible del usuario
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { Component, OnInit, inject, signal, computed, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, takeUntil } from 'rxjs';

// Lucide Icons
import { LucideAngularModule, Wallet, TrendingUp, TrendingDown, Calendar } from 'lucide-angular';

import { LiquidacionesService } from '../../services/liquidaciones.service';
import {
    PresupuestoDisponible,
    FormatHelper
} from '../../models/liquidaciones.models';

@Component({
    selector: 'app-presupuesto-widget',
    standalone: true,
    imports: [CommonModule, LucideAngularModule],
    templateUrl: './presupuesto-widget.component.html',
    styleUrls: ['./presupuesto-widget.component.css']
})
export class PresupuestoWidgetComponent implements OnInit, OnDestroy {

    readonly service = inject(LiquidacionesService);
    private readonly destroy$ = new Subject<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly Wallet = Wallet;
    readonly TrendingUp = TrendingUp;
    readonly TrendingDown = TrendingDown;
    readonly Calendar = Calendar;

    // ========================================================================
    // ESTADO
    // ========================================================================
    readonly presupuesto = signal<PresupuestoDisponible | null>(null);
    readonly cargando = signal<boolean>(false);

    // ========================================================================
    // HELPERS
    // ========================================================================
    readonly formatMoneda = FormatHelper.formatMoneda;
    readonly formatPorcentaje = FormatHelper.formatPorcentaje;

    // ========================================================================
    // COMPUTED
    // ========================================================================

    /**
     * Determina si el presupuesto anual está en nivel crítico (>90%)
     */
    readonly presupuestoAnualCritico = computed(() => {
        const p = this.presupuesto();
        return p ? p.presupuesto_anual.porcentaje_usado >= 90 : false;
    });

    /**
     * Determina si el presupuesto anual está en nivel de advertencia (>75%)
     */
    readonly presupuestoAnualAdvertencia = computed(() => {
        const p = this.presupuesto();
        return p ? p.presupuesto_anual.porcentaje_usado >= 75 && p.presupuesto_anual.porcentaje_usado < 90 : false;
    });

    /**
     * Determina si el presupuesto mensual está en nivel crítico (>90%)
     */
    readonly presupuestoMensualCritico = computed(() => {
        const p = this.presupuesto();
        return p ? p.presupuesto_mensual.porcentaje_usado >= 90 : false;
    });

    /**
     * Determina si el presupuesto mensual está en nivel de advertencia (>75%)
     */
    readonly presupuestoMensualAdvertencia = computed(() => {
        const p = this.presupuesto();
        return p ? p.presupuesto_mensual.porcentaje_usado >= 75 && p.presupuesto_mensual.porcentaje_usado < 90 : false;
    });

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        // NO cargar aquí, dejar que el componente padre lo maneje
        // Solo suscribirse a los cambios
        this.service.presupuesto$
            .pipe(takeUntil(this.destroy$))
            .subscribe(presupuesto => {
                if (presupuesto) {
                    this.presupuesto.set(presupuesto);
                }
            });
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ========================================================================
    // MÉTODOS
    // ========================================================================

    cargarPresupuesto(): void {
        this.cargando.set(true);

        this.service.obtenerPresupuestoDisponible()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: () => this.cargando.set(false),
                error: () => this.cargando.set(false)
            });
    }

    /**
     * Obtiene clase CSS para barra de progreso según porcentaje
     */
    getClaseBarraProgreso(porcentaje: number): string {
        if (porcentaje >= 90) return 'bg-red-600';
        if (porcentaje >= 75) return 'bg-yellow-600';
        return 'bg-blue-600';
    }

    /**
     * Obtiene clase CSS para texto según porcentaje
     */
    getClaseTextoProgreso(porcentaje: number): string {
        if (porcentaje >= 90) return 'text-red-700';
        if (porcentaje >= 75) return 'text-yellow-700';
        return 'text-blue-700';
    }
}