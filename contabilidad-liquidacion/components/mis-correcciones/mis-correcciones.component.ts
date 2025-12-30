// ============================================================================
// COMPONENTE - MIS CORRECCIONES
// ============================================================================
// Gestión de solicitudes de corrección para el usuario
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal, OnDestroy } from '@angular/core';
import { Subject, takeUntil } from 'rxjs';

// Lucide Icons
import {
    LucideAngularModule,
    AlertTriangle,
    RefreshCw,
    Eye,
    CheckCircle,
    FileText
} from 'lucide-angular';

// SweetAlert2
import Swal from 'sweetalert2';

import {
    SolicitudCorreccion,
    FormatHelper,
    MENSAJES_CONTABILIDAD
} from '../../models/contabilidad.models';

import { ContabilidadService } from '../../services/contabilidad.service';

@Component({
    selector: 'app-mis-correcciones',
    standalone: true,
    imports: [
        CommonModule,
        LucideAngularModule
    ],
    templateUrl: './mis-correcciones.component.html',
    styleUrls: ['./mis-correcciones.component.css']
})
export class MisCorreccionesComponent implements OnInit, OnDestroy {

    readonly service = inject(ContabilidadService);
    private readonly destroy$ = new Subject<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly AlertTriangle = AlertTriangle;
    readonly RefreshCw = RefreshCw;
    readonly Eye = Eye;
    readonly CheckCircle = CheckCircle;
    readonly FileText = FileText;

    // ========================================================================
    // ESTADO
    // ========================================================================
    readonly solicitudes = signal<SolicitudCorreccion[]>([]);
    readonly error = signal<string | null>(null);

    // ========================================================================
    // HELPERS
    // ========================================================================
    readonly formatFecha = FormatHelper.formatFecha;
    readonly formatFechaCorta = FormatHelper.formatFechaCorta;
    readonly formatMoneda = FormatHelper.formatMoneda;

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        this.cargarSolicitudes();

        // Suscribirse a cambios
        this.service.solicitudesCorreccion$
            .pipe(takeUntil(this.destroy$))
            .subscribe(solicitudes => this.solicitudes.set(solicitudes));
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ========================================================================
    // CARGA DE DATOS
    // ========================================================================
    cargarSolicitudes(): void {
        this.error.set(null);

        this.service.obtenerMisSolicitudesCorreccion()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                error: (error) => {
                    console.error('Error al cargar solicitudes:', error);
                    this.error.set('Error al cargar solicitudes de corrección');
                }
            });
    }

    refrescarDatos(): void {
        this.cargarSolicitudes();
    }

    // ========================================================================
    // ACCIONES
    // ========================================================================

    /**
     * Muestra detalle de la solicitud
     */
    verDetalle(solicitud: SolicitudCorreccion): void {
        Swal.fire({
            title: '<strong>Solicitud de Corrección</strong>',
            html: `
                <div class="text-left space-y-3" style="font-family: system-ui, -apple-system, sans-serif;">
                    <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100">
                        <div class="text-sm text-yellow-600 mb-1">Factura</div>
                        <div class="text-xl font-bold text-yellow-900">${solicitud.numero_factura}</div>
                        <div class="text-sm text-yellow-700 mt-1">${this.formatMoneda(solicitud.monto)}</div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <div class="text-xs text-gray-500 mb-1">Solicitado por:</div>
                        <div class="text-sm font-medium text-gray-900">${solicitud.nombre_solicitante}</div>
                        <div class="text-xs text-gray-500 mt-1">Fecha: ${this.formatFecha(solicitud.fecha_solicitud)}</div>
                    </div>

                    <div class="bg-red-50 p-3 rounded-lg border border-red-100">
                        <div class="text-xs text-red-600 mb-1 font-medium">Corrección solicitada:</div>
                        <div class="text-sm text-red-900">${solicitud.motivo}</div>
                    </div>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#2563eb',
            width: '600px',
            customClass: {
                popup: 'rounded-lg'
            }
        });
    }

    /**
     * Confirma que la corrección fue realizada
     */
    confirmarCorreccionRealizada(solicitud: SolicitudCorreccion): void {
        Swal.fire({
            title: MENSAJES_CONTABILIDAD.CONFIRMACION.MARCAR_CORRECCION,
            text: MENSAJES_CONTABILIDAD.CONFIRMACION.MARCAR_CORRECCION_DETALLE,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, corrección realizada',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.marcarCorreccion(solicitud.liquidacionid);
            }
        });
    }

    /**
     * Marca la corrección como realizada
     */
    private marcarCorreccion(liquidacionId: number): void {
        this.service.marcarCorreccionRealizada({ idLiquidaciones: liquidacionId })
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (exito) => {
                    if (exito) {
                        this.refrescarDatos();
                    }
                }
            });
    }

    // ========================================================================
    // AUXILIARES
    // ========================================================================
    trackBySolicitud(_index: number, solicitud: SolicitudCorreccion): number {
        return solicitud.idSolicitudesCorreccion;
    }
}