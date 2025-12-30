// ============================================================================
// COMPONENTE - REVISIÓN DE LIQUIDACIONES
// ============================================================================
// Gestión de liquidaciones pendientes de revisión por Contabilidad
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal, OnDestroy } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { Subject, takeUntil } from 'rxjs';

// Lucide Icons
import {
    LucideAngularModule,
    ClipboardCheck,
    RefreshCw,
    Eye,
    CheckCircle,
    XCircle,
    RotateCcw,
    Trash2,
    AlertCircle,
    FileText,
    User,
    Filter,
    X,
    UserRoundX
} from 'lucide-angular';

// SweetAlert2
import Swal from 'sweetalert2';

import {
    LiquidacionContabilidad,
    FiltrosLiquidaciones,
    ContabilidadHelper,
    FormatHelper,
    EstadoHelper,
    MENSAJES_CONTABILIDAD
} from '../../models/contabilidad.models';

import { ContabilidadService } from '../../services/contabilidad.service';
import { ModalRevisionLiquidacionComponent } from '../../modals/modal-revision/modal-revision-liquidacion.component';

@Component({
    selector: 'app-revision-liquidaciones',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        LucideAngularModule,
        ModalRevisionLiquidacionComponent
    ],
    templateUrl: './revision-liquidaciones.component.html',
    styleUrls: ['./revision-liquidaciones.component.css']
})
export class RevisionLiquidacionesComponent implements OnInit, OnDestroy {

    private readonly fb = inject(FormBuilder);
    readonly service = inject(ContabilidadService);
    private readonly destroy$ = new Subject<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly ClipboardCheck = ClipboardCheck;
    readonly RefreshCw = RefreshCw;
    readonly Eye = Eye;
    readonly CheckCircle = CheckCircle;
    readonly XCircle = XCircle;
    readonly RotateCcw = RotateCcw;
    readonly Trash2 = Trash2;
    readonly AlertCircle = AlertCircle;
    readonly FileText = FileText;
    readonly User = User;
    readonly Filter = Filter;
    readonly X = X;
    readonly UserRoundX = UserRoundX;

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
    readonly getInfoVehiculo = ContabilidadHelper.getInfoVehiculo;
    readonly tieneCorreccionPendiente = ContabilidadHelper.tieneCorreccionPendiente;
    readonly puedeRevisar = EstadoHelper.puedeRevisar;

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        this.inicializarFormulario();
        this.cargarLiquidaciones();

        // Suscribirse a cambios
        this.service.liquidacionesPendientes$
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
            agenciaid: [''],
            tipoapoyoid: [''],
            numero_factura: ['']
        });
    }

    // ========================================================================
    // CARGA DE DATOS
    // ========================================================================
    cargarLiquidaciones(): void {
        this.error.set(null);
        const filtros = this.construirFiltros();

        this.service.obtenerLiquidacionesPendientes(filtros)
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                error: (error) => {
                    console.error('Error al cargar liquidaciones:', error);
                    this.error.set('Error al cargar liquidaciones pendientes');
                }
            });
    }

    refrescarDatos(): void {
        this.cargarLiquidaciones();
    }

    // ========================================================================
    // FILTROS
    // ========================================================================
    toggleFiltros(): void {
        this.mostrarFiltros.set(!this.mostrarFiltros());
    }

    aplicarFiltros(): void {
        this.cargarLiquidaciones();
    }

    limpiarFiltros(): void {
        this.formFiltros.reset({
            fecha_inicio: '',
            fecha_fin: '',
            agenciaid: '',
            tipoapoyoid: '',
            numero_factura: ''
        });
        this.cargarLiquidaciones();
    }

    private construirFiltros(): FiltrosLiquidaciones {
        const valores = this.formFiltros.value;
        const filtros: FiltrosLiquidaciones = {};

        if (valores.fecha_inicio) filtros.fecha_inicio = valores.fecha_inicio;
        if (valores.fecha_fin) filtros.fecha_fin = valores.fecha_fin;
        if (valores.agenciaid) filtros.agenciaid = valores.agenciaid;
        if (valores.tipoapoyoid) filtros.tipoapoyoid = valores.tipoapoyoid;
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
        this.liquidacionSeleccionada.set(liquidacion);
        this.mostrarModal.set(true);
    }

    /**
     * Confirma y aprueba una liquidación
     */
    confirmarAprobar(liquidacion: LiquidacionContabilidad): void {
        Swal.fire({
            title: MENSAJES_CONTABILIDAD.CONFIRMACION.APROBAR,
            text: MENSAJES_CONTABILIDAD.CONFIRMACION.APROBAR_DETALLE,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.aprobarLiquidacion(liquidacion.idLiquidaciones);
            }
        });
    }

    /**
     * Abre diálogo para rechazar con motivo
     */
    async confirmarRechazar(liquidacion: LiquidacionContabilidad): Promise<void> {
        const { value: motivo } = await Swal.fire({
            title: MENSAJES_CONTABILIDAD.CONFIRMACION.RECHAZAR,
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-gray-600 mb-3">${MENSAJES_CONTABILIDAD.CONFIRMACION.RECHAZAR_DETALLE}</p>
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <p class="text-xs text-blue-600 mb-1">Factura</p>
                        <p class="font-semibold text-blue-900">${liquidacion.numero_factura}</p>
                        <p class="text-xs text-blue-700 mt-1">${this.getNombreCompleto(liquidacion)}</p>
                    </div>
                </div>
            `,
            input: 'textarea',
            inputLabel: 'Motivo del rechazo',
            inputPlaceholder: 'Escriba el motivo del rechazo...',
            inputAttributes: {
                'aria-label': 'Motivo del rechazo',
                'rows': '4',
                'maxlength': '500'
            },
            inputValidator: (value) => {
                if (!value || value.trim().length === 0) {
                    return MENSAJES_CONTABILIDAD.VALIDACION.MOTIVO_REQUERIDO;
                }
                return null;
            },
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Rechazar Liquidación',
            cancelButtonText: 'Cancelar',
            width: '600px'
        });

        if (motivo) {
            this.rechazarLiquidacion(liquidacion.idLiquidaciones, motivo.trim());
        }
    }

    /**
     * Abre diálogo para devolver con motivo
     */
    async confirmarDevolver(liquidacion: LiquidacionContabilidad): Promise<void> {
        const { value: motivo } = await Swal.fire({
            title: MENSAJES_CONTABILIDAD.CONFIRMACION.DEVOLVER,
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-gray-600 mb-3">${MENSAJES_CONTABILIDAD.CONFIRMACION.DEVOLVER_DETALLE}</p>
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <p class="text-xs text-blue-600 mb-1">Factura</p>
                        <p class="font-semibold text-blue-900">${liquidacion.numero_factura}</p>
                        <p class="text-xs text-blue-700 mt-1">${this.getNombreCompleto(liquidacion)}</p>
                    </div>
                </div>
            `,
            input: 'textarea',
            inputLabel: 'Corrección solicitada',
            inputPlaceholder: 'Describa qué debe corregir el usuario...',
            inputAttributes: {
                'aria-label': 'Corrección solicitada',
                'rows': '4',
                'maxlength': '500'
            },
            inputValidator: (value) => {
                if (!value || value.trim().length === 0) {
                    return MENSAJES_CONTABILIDAD.VALIDACION.MOTIVO_REQUERIDO;
                }
                return null;
            },
            showCancelButton: true,
            confirmButtonColor: '#eab308',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Devolver para Corrección',
            cancelButtonText: 'Cancelar',
            width: '600px'
        });

        if (motivo) {
            this.devolverLiquidacion(liquidacion.idLiquidaciones, motivo.trim());
        }
    }

    /**
     * Confirma y da de baja una liquidación
     */
    confirmarBaja(liquidacion: LiquidacionContabilidad): void {
        Swal.fire({
            title: MENSAJES_CONTABILIDAD.CONFIRMACION.BAJA,
            text: MENSAJES_CONTABILIDAD.CONFIRMACION.BAJA_DETALLE,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#016B61',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Usuarios dado de Baja',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.darDeBajaLiquidacion(liquidacion.idLiquidaciones);
            }
        });
    }

    /**
     * Aprueba una liquidación
     */
    private aprobarLiquidacion(id: number): void {
        this.service.aprobarLiquidacion({ idLiquidaciones: id })
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (exito) => {
                    if (exito) {
                        this.refrescarDatos();
                    }
                }
            });
    }

    /**
     * Rechaza una liquidación
     */
    private rechazarLiquidacion(id: number, motivo: string): void {
        this.service.rechazarLiquidacion({
            idLiquidaciones: id,
            motivo_rechazo: motivo
        })
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (exito) => {
                    if (exito) {
                        this.refrescarDatos();
                    }
                }
            });
    }

    /**
     * Devuelve una liquidación
     */
    private devolverLiquidacion(id: number, motivo: string): void {
        this.service.devolverLiquidacion({
            idLiquidaciones: id,
            motivo: motivo
        })
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (exito) => {
                    if (exito) {
                        this.refrescarDatos();
                    }
                }
            });
    }

    /**
     * Da de baja una liquidación
     */
    private darDeBajaLiquidacion(id: number): void {
        this.service.darDeBajaLiquidacion({ idLiquidaciones: id })
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
    // MANEJADORES DE MODAL
    // ========================================================================
    cerrarModal(): void {
        this.mostrarModal.set(false);
        this.liquidacionSeleccionada.set(null);
    }

    onAccionRealizada(): void {
        this.cerrarModal();
        this.refrescarDatos();
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
        return !!(valores.fecha_inicio || valores.fecha_fin || valores.agenciaid || valores.tipoapoyoid || valores.numero_factura);
    }
}