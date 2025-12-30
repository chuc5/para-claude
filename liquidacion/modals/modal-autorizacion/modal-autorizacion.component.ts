// ============================================================================
// MODAL - SOLICITUD DE AUTORIZACIÓN
// ============================================================================
// Modal para solicitar autorización de factura fuera de plazo
// Incluye: justificación y upload opcional de archivo
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, Input, Output, EventEmitter, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';

// Lucide Icons
import {
    LucideAngularModule,
    X,
    Send,
    AlertCircle,
    FileText,
    Upload,
    File,
    Trash2
} from 'lucide-angular';

import {
    SolicitudAutorizacionForm,
    FormatHelper
} from '../../models/liquidaciones.models';

import { LiquidacionesService } from '../../services/liquidaciones.service';

@Component({
    selector: 'app-modal-autorizacion',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        LucideAngularModule
    ],
    templateUrl: './modal-autorizacion.component.html',
    styleUrls: ['./modal-autorizacion.component.css']
})
export class ModalAutorizacionComponent implements OnInit {

    private readonly fb = inject(FormBuilder);
    private readonly service = inject(LiquidacionesService);

    // ========================================================================
    // INPUTS/OUTPUTS
    // ========================================================================
    @Input() numeroFactura: string = '';
    @Input() diasHabilesExcedidos: number = 0;

    @Output() cerrar = new EventEmitter<void>();
    @Output() guardado = new EventEmitter<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly X = X;
    readonly Send = Send;
    readonly AlertCircle = AlertCircle;
    readonly FileText = FileText;
    readonly Upload = Upload;
    readonly File = File;
    readonly Trash2 = Trash2;

    // ========================================================================
    // ESTADO
    // ========================================================================
    readonly guardando = signal<boolean>(false);
    readonly error = signal<string | null>(null);
    readonly archivoSeleccionado = signal<File | null>(null);

    form!: FormGroup;

    // ========================================================================
    // HELPERS
    // ========================================================================
    readonly formatTamanoArchivo = FormatHelper.formatTamanoArchivo;

    // ========================================================================
    // LIFECYCLE
    // ========================================================================
    ngOnInit(): void {
        this.inicializarFormulario();
    }

    // ========================================================================
    // INICIALIZACIÓN FORMULARIO
    // ========================================================================

    private inicializarFormulario(): void {
        this.form = this.fb.group({
            justificacion: [
                '',
                [
                    Validators.required,
                    Validators.minLength(20),
                    Validators.maxLength(1000)
                ]
            ]
        });
    }

    // ========================================================================
    // MANEJO DE ARCHIVO
    // ========================================================================

    onArchivoSeleccionado(event: Event): void {
        const input = event.target as HTMLInputElement;
        if (input.files && input.files.length > 0) {
            const archivo = input.files[0];

            // Validar tamaño (max 5MB)
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (archivo.size > maxSize) {
                this.error.set('El archivo no debe superar los 5MB');
                input.value = '';
                return;
            }

            // Validar tipo de archivo
            const tiposPermitidos = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/jpg',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            if (!tiposPermitidos.includes(archivo.type)) {
                this.error.set('Solo se permiten archivos PDF, imágenes (JPG, PNG) o documentos Word');
                input.value = '';
                return;
            }

            this.archivoSeleccionado.set(archivo);
            this.error.set(null);
        }
    }

    removerArchivo(): void {
        this.archivoSeleccionado.set(null);

        // Limpiar el input file
        const fileInput = document.getElementById('archivo-input') as HTMLInputElement;
        if (fileInput) {
            fileInput.value = '';
        }
    }

    // ========================================================================
    // GUARDAR SOLICITUD
    // ========================================================================

    guardar(): void {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }

        this.guardando.set(true);
        this.error.set(null);

        const datos: SolicitudAutorizacionForm = {
            numero_factura: this.numeroFactura,
            justificacion: this.form.value.justificacion.trim()
        };

        this.service.crearSolicitudAutorizacion(datos, this.archivoSeleccionado() || undefined)
            .subscribe({
                next: (exito: boolean) => {
                    if (exito) {
                        this.guardado.emit();
                    }
                    this.guardando.set(false);
                },
                error: (error: any) => {
                    console.error('Error al crear solicitud:', error);
                    this.error.set('Error al crear la solicitud de autorización');
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

    esInvalido(campo: string): boolean {
        const control = this.form.get(campo);
        return !!(control && control.invalid && (control.dirty || control.touched));
    }

    obtenerError(campo: string): string {
        const control = this.form.get(campo);
        if (!control) return '';

        if (control.hasError('required')) return 'Este campo es requerido';
        if (control.hasError('minlength')) {
            const min = control.getError('minlength').requiredLength;
            return `Mínimo ${min} caracteres`;
        }
        if (control.hasError('maxlength')) {
            const max = control.getError('maxlength').requiredLength;
            return `Máximo ${max} caracteres`;
        }

        return '';
    }

    // ========================================================================
    // COMPUTED
    // ========================================================================

    get textoBoton(): string {
        if (this.guardando()) return 'Enviando...';
        return 'Enviar Solicitud';
    }

    get nombreArchivoSeleccionado(): string {
        const archivo = this.archivoSeleccionado();
        return archivo ? archivo.name : '';
    }

    get tamanoArchivoSeleccionado(): string {
        const archivo = this.archivoSeleccionado();
        return archivo ? this.formatTamanoArchivo(archivo.size) : '';
    }

    get iconoArchivo(): any {
        const archivo = this.archivoSeleccionado();
        if (!archivo) return this.File;

        if (archivo.type.includes('pdf')) return this.FileText;
        if (archivo.type.includes('image')) return this.File;
        return this.FileText;
    }
}