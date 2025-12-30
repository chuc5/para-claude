// ============================================================================
// COMPONENTE - HISTÓRICO DE REVISIONES
// ============================================================================
// Gestión del histórico de liquidaciones revisadas por Contabilidad
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal, OnDestroy } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { Subject, takeUntil } from 'rxjs';

// Lucide Icons
import {
    LucideAngularModule,
    History,
    RefreshCw,
    Search,
    Eye,
    AlertCircle,
    FileText,
    User,
    Filter,
    X,
    CheckCircle,
    XCircle,
    RotateCcw,
    Trash2
} from 'lucide-angular';

// SweetAlert2
import Swal from 'sweetalert2';

import {
    LiquidacionContabilidad,
    FiltrosLiquidaciones,
    EstadoLiquidacion,
    ContabilidadHelper,
    FormatHelper,
    EstadoHelper
} from '../../models/contabilidad.models';

import { ContabilidadService } from '../../services/contabilidad.service';
import { ModalRevisionLiquidacionComponent } from '../../modals/modal-revision/modal-revision-liquidacion.component';

@Component({
    selector: 'app-historial-revisiones',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        LucideAngularModule,
        ModalRevisionLiquidacionComponent
    ],
    templateUrl: './historial-revisiones.component.html',
    styleUrls: ['./historial-revisiones.component.css']
})
export class HistorialRevisionesComponent implements OnInit, OnDestroy {

    private readonly fb = inject(FormBuilder);
    readonly service = inject(ContabilidadService);
    private readonly destroy$ = new Subject<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly History = History;
    readonly RefreshCw = RefreshCw;
    readonly Search = Search;
    readonly Eye = Eye;
    readonly AlertCircle = AlertCircle;
    readonly FileText = FileText;
    readonly User = User;
    readonly Filter = Filter;
    readonly X = X;
    readonly CheckCircle = CheckCircle;
    readonly XCircle = XCircle;
    readonly RotateCcw = RotateCcw;
    readonly Trash2 = Trash2;

    // ========================================================================
    // ESTADO
    // ========================================================================
    readonly liquidaciones = signal<LiquidacionContabilidad[]>([]);
    readonly error = signal<string | null>(null);
    readonly mostrarFiltros = signal<boolean>(false);

    // Modal
    readonly mostrarModal = signal<boolean>(false);
    readonly liquidacionSeleccionada = signal<LiquidacionContabilidad | null>(null);

    // Formulario de filtros
    formFiltros!: FormGroup;

    // ========================================================================
    // HELPERS
    // ========================================================================
    readonly formatFecha = FormatHelper.formatFecha;
    readonly formatFechaCorta = FormatHelper.formatFechaCorta;
    readonly formatMoneda = FormatHelper.formatMoneda;
    readonly getClaseEstado = EstadoHelper.getClaseLiquidacion;
    readonly getTextoEstado = EstadoHelper.getTextoLiquidacion;
    readonly getNombreCompleto = ContabilidadHelper.getNombreCompletoUsuario;
    readonly getNombreRevisor = ContabilidadHelper.getNombreCompletoRevisor;
    readonly getInfoVehiculo = ContabilidadHelper.getInfoVehiculo;

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        this.inicializarFormulario();
        this.cargarHistorial();

        // Suscribirse a cambios
        this.service.historialRevisiones$
            .pipe(takeUntil(this.destroy$))
            .subscribe(liquidaciones => this.liquidaciones.set(liquidaciones));
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ========================================================================
    // FORMULARIO
    // ========================================================================
    private inicializarFormulario(): void {
        this.formFiltros = this.fb.group({
            fecha_inicio: [''],
            fecha_fin: [''],
            estado: [''],
            agenciaid: [''],
            numero_factura: ['']
        });
    }

    // ========================================================================
    // CARGA DE DATOS
    // ========================================================================
    cargarHistorial(): void {
        this.error.set(null);
        const filtros = this.construirFiltros();

        this.service.obtenerHistorialRevisiones(filtros)
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                error: (error) => {
                    console.error('Error al cargar historial:', error);
                    this.error.set('Error al cargar historial de revisiones');
                }
            });
    }

    refrescarDatos(): void {
        this.cargarHistorial();
    }

    // ========================================================================
    // FILTROS
    // ========================================================================
    toggleFiltros(): void {
        this.mostrarFiltros.set(!this.mostrarFiltros());
    }

    aplicarFiltros(): void {
        this.cargarHistorial();
    }

    limpiarFiltros(): void {
        this.formFiltros.reset({
            fecha_inicio: '',
            fecha_fin: '',
            estado: '',
            agenciaid: '',
            numero_factura: ''
        });
        this.cargarHistorial();
    }

    private construirFiltros(): FiltrosLiquidaciones {
        const valores = this.formFiltros.value;
        const filtros: FiltrosLiquidaciones = {};

        if (valores.fecha_inicio) filtros.fecha_inicio = valores.fecha_inicio;
        if (valores.fecha_fin) filtros.fecha_fin = valores.fecha_fin;
        if (valores.estado) filtros.estado = valores.estado as EstadoLiquidacion;
        if (valores.agenciaid) filtros.agenciaid = valores.agenciaid;
        if (valores.numero_factura) filtros.numero_factura = valores.numero_factura.trim();

        return filtros;
    }

    // ========================================================================
    // ACCIONES
    // ========================================================================

    /**
     * Abre modal con detalle de la liquidación
     */
    verDetalle(liquidacion: LiquidacionContabilidad): void {
        this.mostrarDetalleCompleto(liquidacion);
    }

    /**
     * Muestra detalle completo con información de revisión
     */
    private mostrarDetalleCompleto(liquidacion: LiquidacionContabilidad): void {
        const estadoHTML = `<span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium ${this.getClaseEstado(liquidacion.estado)}">
            ${this.getTextoEstado(liquidacion.estado)}
        </span>`;

        const vehiculoHTML = liquidacion.vehiculo_placa
            ? `${liquidacion.vehiculo_placa} - ${liquidacion.vehiculo_marca} (${liquidacion.tipo_vehiculo})`
            : '<span class="text-gray-500">Sin vehículo asignado</span>';

        const motivoRechazoHTML = liquidacion.motivo_rechazo
            ? `<div class="bg-red-50 p-3 rounded-lg border border-red-100 mt-3">
                <div class="text-xs text-red-600 mb-1 font-medium">Motivo del Rechazo:</div>
                <div class="text-sm text-red-900">${liquidacion.motivo_rechazo}</div>
            </div>`
            : '';

        Swal.fire({
            title: '<strong>Detalle de Liquidación Revisada</strong>',
            html: `
                <div class="text-left space-y-4" style="font-family: system-ui, -apple-system, sans-serif;">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                        <div class="text-sm text-blue-600 mb-1">Factura</div>
                        <div class="text-2xl font-bold text-blue-900">${liquidacion.numero_factura}</div>
                        <div class="text-sm text-blue-700 mt-1">${this.formatMoneda(liquidacion.monto)}</div>
                    </div>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Usuario:</span>
                            <span class="font-medium text-gray-900">${this.getNombreCompleto(liquidacion)}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Agencia:</span>
                            <span class="font-medium text-gray-900">${liquidacion.agencia_usuario}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Tipo de apoyo:</span>
                            <span class="font-medium text-gray-900">${liquidacion.tipo_apoyo}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Vehículo:</span>
                            <span class="font-medium text-gray-900">${vehiculoHTML}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Estado:</span>
                            <span>${estadoHTML}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Revisado por:</span>
                            <span class="font-medium text-gray-900">${this.getNombreRevisor(liquidacion)}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Fecha revisión:</span>
                            <span class="font-medium text-gray-900">${liquidacion.fecha_revision ? this.formatFecha(liquidacion.fecha_revision) : '—'}</span>
                        </div>
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

                    ${motivoRechazoHTML}
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#2563eb',
            width: '700px',
            customClass: {
                popup: 'rounded-lg'
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

    // ========================================================================
    // AUXILIARES
    // ========================================================================
    trackByLiquidacion(_index: number, liquidacion: LiquidacionContabilidad): number {
        return liquidacion.idLiquidaciones;
    }

    /**
     * Determina si hay filtros activos
     */
    hayFiltrosActivos(): boolean {
        const valores = this.formFiltros.value;
        return !!(valores.fecha_inicio || valores.fecha_fin || valores.estado || valores.agenciaid || valores.numero_factura);
    }

    /**
     * Obtiene el icono según el estado
     */
    getIconoEstado(estado: EstadoLiquidacion): any {
        const iconos: Record<EstadoLiquidacion, any> = {
            'enviada': FileText,
            'aprobada': CheckCircle,
            'rechazada': XCircle,
            'devuelta': RotateCcw,
            'corregida': FileText,
            'de_baja': Trash2,
            'en_lote': FileText,
            'pagada': CheckCircle
        };
        return iconos[estado] || FileText;
    }
}