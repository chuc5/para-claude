// ============================================================================
// MODAL - LIQUIDACIÓN (Crear/Editar)
// ============================================================================
// Modal completo para gestión de liquidaciones
// Incluye: búsqueda de factura, validaciones, solicitud de autorización
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, Input, Output, EventEmitter, OnInit, inject, signal, computed } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';

// Lucide Icons
import {
    LucideAngularModule,
    X,
    Save,
    Search,
    AlertCircle,
    CheckCircle,
    Clock,
    FileText,
    Car,
    AlertTriangle
} from 'lucide-angular';

import {
    Liquidacion,
    LiquidacionForm,
    DatosFacturaLiquidacion,
    Vehiculo,
    TipoApoyo,
    FormatHelper,
    EstadoHelper,
    obtenerDescripcionPredefinida
} from '../../models/liquidaciones.models';

import { LiquidacionesService } from '../../services/liquidaciones.service';
import { ModalAutorizacionComponent } from '../modal-autorizacion/modal-autorizacion.component';

@Component({
    selector: 'app-modal-liquidacion',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        LucideAngularModule,
        ModalAutorizacionComponent
    ],
    templateUrl: './modal-liquidacion.component.html',
    styleUrls: ['./modal-liquidacion.component.css']
})
export class ModalLiquidacionComponent implements OnInit {

    private readonly fb = inject(FormBuilder);
    private readonly service = inject(LiquidacionesService);

    // ========================================================================
    // INPUTS/OUTPUTS
    // ========================================================================
    @Input() modo: 'crear' | 'editar' = 'crear';
    @Input() liquidacion: Liquidacion | null = null;

    @Output() cerrar = new EventEmitter<void>();
    @Output() guardado = new EventEmitter<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly X = X;
    readonly Save = Save;
    readonly Search = Search;
    readonly AlertCircle = AlertCircle;
    readonly CheckCircle = CheckCircle;
    readonly Clock = Clock;
    readonly FileText = FileText;
    readonly Car = Car;
    readonly AlertTriangle = AlertTriangle;

    // ========================================================================
    // ESTADO
    // ========================================================================
    readonly guardando = signal<boolean>(false);
    readonly buscandoFactura = signal<boolean>(false);
    readonly error = signal<string | null>(null);

    // Datos de la factura
    readonly datosFactura = signal<DatosFacturaLiquidacion | null>(null);
    readonly facturaEncontrada = signal<boolean>(false);

    // Catálogos
    readonly vehiculos = signal<Vehiculo[]>([]);
    readonly tiposApoyo = signal<TipoApoyo[]>([]);

    // Modal de autorización
    readonly mostrarModalAutorizacion = signal<boolean>(false);

    // Formularios
    formBusqueda!: FormGroup;
    formLiquidacion!: FormGroup;

    // ========================================================================
    // HELPERS
    // ========================================================================
    readonly formatFecha = FormatHelper.formatFechaCorta;
    readonly formatMoneda = FormatHelper.formatMoneda;
    readonly getClaseAutorizacion = EstadoHelper.getClaseAutorizacion;
    readonly getTextoAutorizacion = EstadoHelper.getTextoAutorizacion;

    // ========================================================================
    // COMPUTED
    // ========================================================================

    /**
     * Determina si el formulario de liquidación debe estar habilitado
     */
    readonly formularioHabilitado = computed(() => {
        const datos = this.datosFactura();
        if (this.modo === 'editar') return true;
        return datos ? datos.puede_liquidar : false;
    });

    /**
     * Determina si debe mostrar el botón de solicitar autorización
     */
    readonly mostrarBotonAutorizacion = computed(() => {
        const datos = this.datosFactura();
        if (!datos || this.modo === 'editar' || datos.solicitud_autorizacion?.estado == 'pendiente' || datos.solicitud_autorizacion?.estado == 'rechazada') return false;
        return datos.requiere_autorizacion && !datos.tiene_autorizacion_aprobada;
    });

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        this.inicializarFormularios();
        this.cargarCatalogos();

        if (this.modo === 'editar' && this.liquidacion) {
            this.cargarDatosEdicion();
        } else {
            // Asegurar que el formulario esté deshabilitado al inicio en modo crear
            this.deshabilitarFormulario();
        }
    }

    // ========================================================================
    // INICIALIZACIÓN FORMULARIOS
    // ========================================================================

    private inicializarFormularios(): void {
        // Formulario de búsqueda
        this.formBusqueda = this.fb.group({
            numero_factura: ['', [Validators.required]]
        });

        // Formulario de liquidación - TODOS los campos inician deshabilitados
        this.formLiquidacion = this.fb.group({
            vehiculoid: [{ value: '', disabled: true }, [Validators.required]], // ← Agregar disabled: true
            tipoapoyoid: [{ value: '', disabled: true }, [Validators.required]],
            descripcion: [{ value: '', disabled: true }, [Validators.required, Validators.maxLength(500)]]
        });
    }



    private cargarDatosEdicion(): void {
        if (!this.liquidacion) return;

        // Pre-llenar búsqueda
        this.formBusqueda.patchValue({
            numero_factura: this.liquidacion.numero_factura
        });
        this.formBusqueda.disable();

        // Habilitar formulario para edición
        this.formLiquidacion.enable();

        // Pre-llenar formulario
        this.formLiquidacion.patchValue({
            vehiculoid: this.liquidacion.vehiculoid,
            tipoapoyoid: this.liquidacion.tipoapoyoid,
            descripcion: this.liquidacion.descripcion
        });

        this.facturaEncontrada.set(true);
    }

    // ========================================================================
    // CARGA DE CATÁLOGOS
    // ========================================================================

    private cargarCatalogos(): void {
        // Cargar vehículos
        this.service.listarMisVehiculos().subscribe(() => {
            this.service.vehiculos$.subscribe(vehiculos => {
                this.vehiculos.set(vehiculos.filter(v => v.activo === 1));
            });
        });

        // Cargar tipos de apoyo
        this.service.listarTiposApoyoActivos().subscribe(() => {
            this.service.tiposApoyo$.subscribe(tipos => {
                this.tiposApoyo.set(tipos);
            });
        });
    }

    // ========================================================================
    // BÚSQUEDA DE FACTURA
    // ========================================================================

    buscarFactura(): void {
        if (this.formBusqueda.invalid) {
            this.formBusqueda.markAllAsTouched();
            return;
        }

        const numeroFactura = this.formBusqueda.value.numero_factura.trim();
        this.buscandoFactura.set(true);
        this.error.set(null);
        this.datosFactura.set(null);

        this.service.buscarFacturaParaLiquidacion(numeroFactura).subscribe({
            next: (datos) => {
                if (datos) {
                    this.datosFactura.set(datos);
                    this.facturaEncontrada.set(true);

                    // Habilitar o deshabilitar formulario según si puede liquidar
                    if (datos.puede_liquidar) {
                        this.habilitarFormulario();
                    } else {
                        this.deshabilitarFormulario();
                    }
                }
                this.buscandoFactura.set(false);
            },
            error: (error) => {
                console.error('Error al buscar factura:', error);
                this.buscandoFactura.set(false);
                this.facturaEncontrada.set(false);
                this.deshabilitarFormulario();
            }
        });
    }

    /**
 * Habilita todos los campos del formulario de liquidación
 */
    private habilitarFormulario(): void {
        this.formLiquidacion.get('vehiculoid')?.enable();
        this.formLiquidacion.get('tipoapoyoid')?.enable();
        this.formLiquidacion.get('descripcion')?.enable();
    }

    /**
     * Deshabilita todos los campos del formulario de liquidación
     */
    private deshabilitarFormulario(): void {
        this.formLiquidacion.get('vehiculoid')?.disable();
        this.formLiquidacion.get('tipoapoyoid')?.disable();
        this.formLiquidacion.get('descripcion')?.disable();
    }
    // ========================================================================
    // SOLICITUD DE AUTORIZACIÓN
    // ========================================================================

    abrirModalAutorizacion(): void {
        this.mostrarModalAutorizacion.set(true);
    }

    cerrarModalAutorizacion(): void {
        this.mostrarModalAutorizacion.set(false);
    }

    onAutorizacionCreada(): void {
        this.cerrarModalAutorizacion();
        // Volver a buscar la factura para actualizar el estado
        this.buscarFactura();
    }

    // ========================================================================
    // CAMBIO DE TIPO DE APOYO
    // ========================================================================

    onTipoApoyoChange(): void {
        const tipoapoyoid = this.formLiquidacion.value.tipoapoyoid;
        const tipoSeleccionado = this.tiposApoyo().find(t => t.idTiposApoyo == tipoapoyoid);

        if (tipoSeleccionado) {
            const descripcion = obtenerDescripcionPredefinida(tipoSeleccionado.codigo);
            if (descripcion) {
                this.formLiquidacion.patchValue({ descripcion });
            }
        }
    }

    // ========================================================================
    // GUARDAR LIQUIDACIÓN
    // ========================================================================

    guardar(): void {
        if (this.formLiquidacion.invalid) {
            this.formLiquidacion.markAllAsTouched();
            return;
        }

        this.guardando.set(true);
        this.error.set(null);

        const formData: LiquidacionForm = {
            numero_factura: this.modo === 'crear'
                ? this.formBusqueda.value.numero_factura.trim()
                : this.liquidacion!.numero_factura,
            vehiculoid: this.formLiquidacion.value.vehiculoid || null,
            tipoapoyoid: this.formLiquidacion.value.tipoapoyoid,
            descripcion: this.formLiquidacion.value.descripcion.trim()
        };

        // Agregar ID si es edición
        if (this.modo === 'editar' && this.liquidacion) {
            formData.idLiquidaciones = this.liquidacion.idLiquidaciones;
        }

        const operacion = this.modo === 'crear'
            ? this.service.crearLiquidacion(formData)
            : this.service.editarLiquidacion(formData);

        operacion.subscribe({
            next: (exito) => {
                if (exito) {
                    this.guardado.emit();
                }
                this.guardando.set(false);
            },
            error: (error) => {
                console.error('Error al guardar:', error);
                this.error.set('Error al guardar la liquidación');
                this.guardando.set(false);
            }
        });
    }

    cerrarModal(): void {
        if (!this.guardando()) {
            this.cerrar.emit();
        }
    }

    // ========================================================================
    // HELPERS VALIDACIÓN
    // ========================================================================

    esInvalido(form: FormGroup, campo: string): boolean {
        const control = form.get(campo);
        return !!(control && control.invalid && (control.dirty || control.touched));
    }

    obtenerError(form: FormGroup, campo: string): string {
        const control = form.get(campo);
        if (!control) return '';

        if (control.hasError('required')) return 'Este campo es requerido';
        if (control.hasError('maxlength')) {
            const max = control.getError('maxlength').requiredLength;
            return `Máximo ${max} caracteres`;
        }

        return '';
    }

    // ========================================================================
    // COMPUTED PROPERTIES
    // ========================================================================

    get tituloModal(): string {
        return this.modo === 'crear' ? 'Nueva Liquidación' : 'Editar Liquidación';
    }

    get textoBoton(): string {
        if (this.guardando()) return 'Guardando...';
        return this.modo === 'crear' ? 'Crear Liquidación' : 'Guardar Cambios';
    }
}