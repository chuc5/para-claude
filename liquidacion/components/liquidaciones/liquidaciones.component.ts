// ============================================================================
// COMPONENTE - LIQUIDACIONES DE COMBUSTIBLE
// ============================================================================
// Gestión completa de liquidaciones del usuario
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal, OnDestroy } from '@angular/core';
import { Subject, takeUntil } from 'rxjs';

// Lucide Icons
import {
    LucideAngularModule,
    FileText,
    RefreshCw,
    Plus,
    Eye,
    Edit,
    Trash2,
    AlertCircle
} from 'lucide-angular';

// SweetAlert2
import Swal from 'sweetalert2';

import {
    Liquidacion,
    FormatHelper,
    EstadoHelper,
    MENSAJES_LIQUIDACIONES
} from '../../models/liquidaciones.models';

import { LiquidacionesService } from '../../services/liquidaciones.service';
import { PresupuestoWidgetComponent } from '../presupuesto-widget/presupuesto-widget.component';
import { ModalLiquidacionComponent } from '../../modals/modal-liquidacion/modal-liquidacion.component';

@Component({
    selector: 'app-liquidaciones',
    standalone: true,
    imports: [
        CommonModule,
        LucideAngularModule,
        PresupuestoWidgetComponent,
        ModalLiquidacionComponent
    ],
    templateUrl: './liquidaciones.component.html',
    styleUrls: ['./liquidaciones.component.css']
})
export class LiquidacionesComponent implements OnInit, OnDestroy {

    readonly service = inject(LiquidacionesService);
    private readonly destroy$ = new Subject<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly FileText = FileText;
    readonly RefreshCw = RefreshCw;
    readonly Plus = Plus;
    readonly Eye = Eye;
    readonly Edit = Edit;
    readonly Trash2 = Trash2;
    readonly AlertCircle = AlertCircle;

    // ========================================================================
    // ESTADO
    // ========================================================================
    readonly liquidaciones = signal<Liquidacion[]>([]);
    readonly cargando = signal<boolean>(false);
    readonly error = signal<string | null>(null);

    // Modal
    readonly mostrarModal = signal<boolean>(false);
    readonly modoModal = signal<'crear' | 'editar'>('crear');
    readonly liquidacionSeleccionada = signal<Liquidacion | null>(null);

    // ========================================================================
    // HELPERS
    // ========================================================================
    readonly formatFecha = FormatHelper.formatFecha;
    readonly formatFechaCorta = FormatHelper.formatFechaCorta;
    readonly formatMoneda = FormatHelper.formatMoneda;
    readonly getClaseEstado = EstadoHelper.getClaseLiquidacion;
    readonly getTextoEstado = EstadoHelper.getTextoLiquidacion;
    readonly getClaseAutorizacion = EstadoHelper.getClaseAutorizacion;
    readonly getTextoAutorizacion = EstadoHelper.getTextoAutorizacion;
    readonly puedeEditar = EstadoHelper.puedeEditar;
    readonly puedeEliminar = EstadoHelper.puedeEliminar;

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        // Cargar presupuesto primero
        this.service.obtenerPresupuestoDisponible()
            .pipe(takeUntil(this.destroy$))
            .subscribe();

        // Luego cargar liquidaciones
        this.cargarLiquidaciones();

        // Suscribirse a cambios de liquidaciones
        this.service.liquidaciones$
            .pipe(takeUntil(this.destroy$))
            .subscribe(liquidaciones => this.liquidaciones.set(liquidaciones));
    }


    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ========================================================================
    // CARGA DE DATOS
    // ========================================================================
    // ========================================================================
    // CARGA DE DATOS
    // ========================================================================
    cargarLiquidaciones(): void {
        this.cargando.set(true);
        this.error.set(null);

        this.service.listarMisLiquidaciones()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (exito) => {
                    // NO actualizar presupuesto aquí en ngOnInit
                    // Solo actualizar cuando sea un refresh manual
                    this.cargando.set(false);
                },
                error: (error) => {
                    console.error('Error al cargar liquidaciones:', error);
                    this.error.set('Error al cargar liquidaciones');
                    this.cargando.set(false);
                }
            });
    }

    refrescarDatos(): void {
        // Actualizar presupuesto cuando sea refresh manual
        this.service.obtenerPresupuestoDisponible()
            .pipe(takeUntil(this.destroy$))
            .subscribe();

        this.cargarLiquidaciones();
    }

    // ========================================================================
    // ACCIONES CRUD
    // ========================================================================

    /**
     * Abre modal para crear nueva liquidación
     */
    abrirModalCrear(): void {
        this.modoModal.set('crear');
        this.liquidacionSeleccionada.set(null);
        this.mostrarModal.set(true);
    }

    /**
     * Abre modal para editar liquidación existente
     */
    abrirModalEditar(liquidacion: Liquidacion): void {
        if (!this.puedeEditar(liquidacion.estado)) {
            Swal.fire({
                title: 'No disponible',
                text: 'Solo se pueden editar liquidaciones en estado "enviada" o "devuelta"',
                icon: 'info',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        this.modoModal.set('editar');
        this.liquidacionSeleccionada.set(liquidacion);
        this.mostrarModal.set(true);
    }

    /**
     * Muestra detalle de la liquidación con SweetAlert
     */
    verDetalle(liquidacion: Liquidacion): void {
        const estadoHTML = `<span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium ${this.getClaseEstado(liquidacion.estado)}">
            ${this.getTextoEstado(liquidacion.estado)}
        </span>`;

        const autorizacionHTML = liquidacion.autorizacion_estado
            ? `<span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium ${this.getClaseAutorizacion(liquidacion.autorizacion_estado)}">
                ${this.getTextoAutorizacion(liquidacion.autorizacion_estado)}
            </span>`
            : '<span class="text-gray-500 text-sm">—</span>';

        const vehiculoHTML = liquidacion.vehiculo_placa
            ? `${liquidacion.vehiculo_placa} - ${liquidacion.vehiculo_marca} (${liquidacion.tipo_vehiculo})`
            : '<span class="text-gray-500">Sin vehículo asignado</span>';

        Swal.fire({
            title: '<strong>Detalle de Liquidación</strong>',
            html: `
                <div class="text-left space-y-4" style="font-family: system-ui, -apple-system, sans-serif;">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                        <div class="text-sm text-blue-600 mb-1">Factura</div>
                        <div class="text-2xl font-bold text-blue-900">${liquidacion.numero_factura}</div>
                    </div>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Tipo de apoyo:</span>
                            <span class="font-medium text-gray-900">${liquidacion.tipo_apoyo}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Vehículo:</span>
                            <span class="font-medium text-gray-900">${vehiculoHTML}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Monto:</span>
                            <span class="font-medium text-gray-900">${this.formatMoneda(liquidacion.monto)}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Estado:</span>
                            <span>${estadoHTML}</span>
                        </div>
                        ${liquidacion.autorizacion_estado ? `
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Autorización:</span>
                            <span>${autorizacionHTML}</span>
                        </div>
                        ` : ''}
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Fecha liquidación:</span>
                            <span class="font-medium text-gray-900">${this.formatFecha(liquidacion.fecha_liquidacion)}</span>
                        </div>
                    </div>

                    ${liquidacion.descripcion ? `
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <div class="text-xs text-gray-500 mb-1">Descripción:</div>
                        <div class="text-sm text-gray-700">${liquidacion.descripcion}</div>
                    </div>
                    ` : ''}
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#2563eb',
            width: '600px',
            customClass: {
                popup: 'rounded-lg'
            }
        });
    }

    /**
     * Confirma y elimina una liquidación
     */
    confirmarEliminar(liquidacion: Liquidacion): void {
        if (!this.puedeEliminar(liquidacion.estado)) {
            Swal.fire({
                title: 'No disponible',
                text: 'Solo se pueden eliminar liquidaciones en estado "enviada"',
                icon: 'info',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        Swal.fire({
            title: MENSAJES_LIQUIDACIONES.CONFIRMACION.ELIMINAR,
            text: MENSAJES_LIQUIDACIONES.CONFIRMACION.ELIMINAR_DETALLE,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.eliminarLiquidacion(liquidacion.idLiquidaciones);
            }
        });
    }

    /**
     * Elimina una liquidación
     */
    private eliminarLiquidacion(id: number): void {
        this.cargando.set(true);

        this.service.eliminarLiquidacion(id)
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (exito) => {
                    if (exito) {
                        this.refrescarDatos();
                    }
                    this.cargando.set(false);
                },
                error: (error) => {
                    console.error('Error al eliminar:', error);
                    this.cargando.set(false);
                }
            });
    }

    // ========================================================================
    // MANEJADORES DE MODAL
    // ========================================================================
    cerrarModal(): void {
        this.mostrarModal.set(false);
        this.liquidacionSeleccionada.set(null);
    }

    onLiquidacionGuardada(): void {
        this.cerrarModal();
        this.refrescarDatos(); // Esto ya actualizará tanto liquidaciones como presupuesto
    }

    // ========================================================================
    // AUXILIARES
    // ========================================================================
    trackByLiquidacion(_index: number, liquidacion: Liquidacion): number {
        return liquidacion.idLiquidaciones;
    }

    /**
     * Obtiene el nombre completo del vehículo
     */
    getNombreVehiculo(liquidacion: Liquidacion): string {
        if (!liquidacion.vehiculo_placa) return '—';
        return `${liquidacion.vehiculo_placa} - ${liquidacion.vehiculo_marca}`;
    }
}