// ============================================================================
// MODAL — CREAR SOLICITUD (Sprint 2)
// ============================================================================

import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
    Component, Input, Output, EventEmitter,
    OnInit, inject, signal, computed, DestroyRef,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

// Lucide Icons
import {
    LucideAngularModule,
    X, ShoppingCart, AlertCircle, Package,
} from 'lucide-angular';

// ng-select
import { NgSelectModule } from '@ng-select/ng-select';

// Animaciones (mismo par que el resto de modales del proyecto)
import { overlayAnimation, modalAnimation } from '../../../animations/modal.animations';

import {
    ProductoDisponible,
    UnidadConStock,
    TipoProductoId,
    FormatSolicitudes,
} from '../models/solicitudes.models';
import { SolicitudesService } from '../services/solicitudes.service';

@Component({
    selector: 'app-modal-crear-solicitud',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        LucideAngularModule,
        NgSelectModule,
    ],
    templateUrl: './modal-crear-solicitud.component.html',
    animations: [overlayAnimation, modalAnimation],
})
export class ModalCrearSolicitudComponent implements OnInit {

    private readonly fb = inject(FormBuilder);
    private readonly service = inject(SolicitudesService);
    private readonly destroyRef = inject(DestroyRef);

    // ========================================================================
    // INPUTS / OUTPUTS
    // ========================================================================

    /** Producto sobre el que se crea la solicitud */
    @Input() producto!: ProductoDisponible;

    /** Bodega donde se hace la solicitud */
    @Input() idBodega!: number;

    @Output() cerrar = new EventEmitter<void>();
    @Output() guardado = new EventEmitter<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly X = X;
    readonly ShoppingCart = ShoppingCart;
    readonly AlertCircle = AlertCircle;
    readonly Package = Package;

    // ========================================================================
    // ESTADO LOCAL
    // ========================================================================

    readonly guardando = signal<boolean>(false);
    readonly error = signal<string | null>(null);
    readonly unidades = signal<UnidadConStock[]>([]);
    readonly cargandoUnidades = signal<boolean>(false);
    readonly unidadActiva = signal<UnidadConStock | null>(null);

    form!: FormGroup;

    // ========================================================================
    // COMPUTED
    // ========================================================================

    /** Disponibilidad de la unidad seleccionada */
    readonly disponible = computed(() =>
        FormatSolicitudes.cantidad(this.unidadActiva()?.cantidad_disponible)
    );

    /** Clase CSS del indicador de disponibilidad */
    readonly claseDisponible = computed(() =>
        FormatSolicitudes.claseDisponibilidad(this.disponible())
    );

    /** true para mostrar la nota de correlativos */
    readonly esCorrelativo = computed(() =>
        this.producto?.id_tipo === TipoProductoId.CORRELATIVO
    );

    /** Clase CSS del badge de tipo de producto */
    readonly claseTipo = computed(() =>
        FormatSolicitudes.claseTipoProducto(this.producto?.id_tipo)
    );

    // ========================================================================
    // LIFECYCLE
    // ========================================================================

    ngOnInit(): void {
        this._inicializarFormulario();
        this._cargarUnidades();

        // Reaccionar al cambio de unidad para actualizar validadores y estado
        this.form.get('id_unidad')!.valueChanges
            .pipe(takeUntilDestroyed(this.destroyRef))
            .subscribe((id: number | null) => this._onUnidadChange(id));
    }

    // ========================================================================
    // INICIALIZACIÓN
    // ========================================================================

    private _inicializarFormulario(): void {
        this.form = this.fb.group({
            id_unidad: [null, [Validators.required]],
            // Deshabilitado hasta que el usuario seleccione una unidad con stock
            cantidad: [{ value: null, disabled: true }, [Validators.required, Validators.min(0.01)]],
            observaciones: ['', [Validators.maxLength(500)]],
        });
    }

    private _cargarUnidades(): void {
        this.cargandoUnidades.set(true);

        this.service.obtenerUnidadesProducto(this.producto.id, this.idBodega)
            .pipe(takeUntilDestroyed(this.destroyRef))
            .subscribe(unidades => {
                this.unidades.set(unidades);
                this.cargandoUnidades.set(false);

                // Auto-seleccionar la unidad por defecto
                const def = unidades.find(u => u.es_default) ?? unidades[0] ?? null;
                if (def) {
                    this.form.patchValue({ id_unidad: def.id_unidad });
                    // El valueChanges de arriba se dispara y actualiza unidadActiva
                }
            });
    }

    // ========================================================================
    // CAMBIO DE UNIDAD
    // ========================================================================

    private _onUnidadChange(idUnidad: number | null): void {
        const u = idUnidad
            ? (this.unidades().find(x => x.id_unidad === idUnidad) ?? null)
            : null;
        this.unidadActiva.set(u);

        const disponible = FormatSolicitudes.cantidad(u?.cantidad_disponible);
        const ctrl = this.form.get('cantidad')!;

        ctrl.setValidators([
            Validators.required,
            Validators.min(0.01),
            Validators.max(disponible),
        ]);

        // ← Aquí el cambio clave: API del control, no [disabled] en el template
        if (!u || disponible === 0) {
            ctrl.disable({ emitEvent: false });
        } else {
            ctrl.enable({ emitEvent: false });
        }

        ctrl.updateValueAndValidity({ emitEvent: false });

        const cantActual = ctrl.value as number | null;
        if (cantActual !== null && cantActual > disponible) {
            ctrl.setValue(null, { emitEvent: false });
        }
    }

    // ========================================================================
    // ENVIAR
    // ========================================================================

    confirmar(): void {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }

        this.guardando.set(true);
        this.error.set(null);

        // getRawValue() incluye controles deshabilitados — form.value los omite
        const raw = this.form.getRawValue();

        this.service.crearSolicitud({
            id_bodega: this.idBodega,
            id_producto: this.producto.id,
            id_unidad: Number(raw.id_unidad),
            cantidad: parseFloat(raw.cantidad),
            observaciones: raw.observaciones?.trim() || undefined,
        })
            .pipe(takeUntilDestroyed(this.destroyRef))
            .subscribe({
                next: idSolicitud => {
                    this.guardando.set(false);
                    if (idSolicitud !== null) {
                        this.guardado.emit();
                    }
                },
                error: () => {
                    this.error.set('Error al confirmar la solicitud. Intenta de nuevo.');
                    this.guardando.set(false);
                },
            });
    }

    cerrarModal(): void {
        if (!this.guardando()) this.cerrar.emit();
    }

    // ========================================================================
    // HELPERS DE VALIDACIÓN (mismo patrón que modal-presupuesto-general)
    // ========================================================================

    esInvalido(campo: string): boolean {
        const c = this.form.get(campo);
        return !!(c?.invalid && (c.dirty || c.touched));
    }

    obtenerError(campo: string): string {
        const c = this.form.get(campo);
        if (!c) return '';
        if (c.hasError('required')) return 'Este campo es requerido';
        if (c.hasError('min')) return `El valor mínimo es ${c.getError('min').min}`;
        if (c.hasError('max')) return `La cantidad máxima disponible es ${c.getError('max').max}`;
        if (c.hasError('maxlength')) return `Máximo ${c.getError('maxlength').requiredLength} caracteres`;
        return '';
    }

    // ========================================================================
    // GETTERS DEL TEMPLATE
    // ========================================================================

    get textoBoton(): string {
        return this.guardando() ? 'Confirmando...' : 'Confirmar solicitud';
    }
}