// ============================================================================
// TABLA DETALLE LIQUIDACIONES - CON NG-SELECT PARA AGENCIA
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, ViewChild, ElementRef, inject, OnInit, OnDestroy, signal, Input, ChangeDetectorRef } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Subject, takeUntil } from 'rxjs';
import Swal from 'sweetalert2';
import { NgSelectModule } from '@ng-select/ng-select';

import { ModalDetalleLiquidacionNuevoComponentpresupuesto } from '../modal-detalle-liquidacion/modal-detalle-liquidacion.component';
import { ModalConfirmarEliminacionComponentpresupuesto } from '../modal-confirmar-eliminacion/modal-confirmar-eliminacion.component';
import { ModalVerCambiosUsuarioComponent } from '../modal-cambios-usuario/modal-ver-cambios-usuario.component';
import { PreviewJustificacionModalComponent } from '../../../preview-justificacion-modal/preview-justificacion-modal.component'; // 👈 NUEVO

import { FacturasPlanEmpresarialService } from '../../services/facturas-presupuesto.service';
import {
    DetalleLiquidacionPE,
    FORMAS_PAGO,
    FacturaPE,
    PermisosEdicion
} from '../../models/facturas-presupuesto.models';
import { toNumber, toString, formatearMonto, formatearFecha } from '../../utils/format.utils';

@Component({
    selector: 'app-tabla-detalle-liquidaciones-presupuesto',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        NgSelectModule,
        ModalDetalleLiquidacionNuevoComponentpresupuesto,
        ModalConfirmarEliminacionComponentpresupuesto,
        ModalVerCambiosUsuarioComponent,
        PreviewJustificacionModalComponent // 👈 NUEVO
    ],
    templateUrl: './tabla-detalle-liquidaciones-presupuesto.component.html',
    styleUrls: ['./tabla-detalle-liquidaciones-presupuesto.component.scss'],
})
export class TablaDetalleLiquidacionesComponentpresupuesto implements OnInit, OnDestroy {

    @ViewChild('montoInput') montoInput?: ElementRef<HTMLInputElement>;
    @Input() permisosEdicion: PermisosEdicion | null = null;

    private readonly service = inject(FacturasPlanEmpresarialService);
    private readonly destroy$ = new Subject<void>();
    private readonly cdr = inject(ChangeDetectorRef);

    // ============================================================================
    // ESTADO DEL COMPONENTE
    // ============================================================================

    readonly mostrarModalDetalle = signal(false);
    readonly modoModal = signal<'crear' | 'editar'>('crear');
    readonly mostrarModalEliminar = signal(false);
    readonly facturaActual = signal<FacturaPE | null>(null);
    readonly detallesLiquidacion = signal<DetalleLiquidacionPE[]>([]);
    readonly agenciasDisponibles = signal<any[]>([]);
    readonly cargandoDetalles = signal<boolean>(false);
    readonly guardandoCambios = signal<boolean>(false);

    // MODAL DE CAMBIOS SOLICITADOS
    readonly mostrarModalCambios = signal(false);
    readonly detalleSeleccionadoParaCambios = signal<DetalleLiquidacionPE | null>(null);

    // 👇 NUEVO: ESTADO PARA MODAL DE PREVIEW DE COMPROBANTE
    readonly mostrarPreviewComprobante = signal<boolean>(false);
    readonly previewDriveId = signal<string>('');
    readonly previewRutaArchivo = signal<string>('');
    readonly previewNombreArchivo = signal<string>('comprobante.pdf');

    // Permisos por defecto
    readonly permisosDefecto = signal({
        puedeVer: true,
        puedeEditar: false,
        puedeAgregar: false,
        puedeEliminar: false,
        razon: 'Calculando permisos...',
        claseCSS: 'text-gray-600 bg-gray-50 border-gray-200'
    });

    registroEnEdicion: DetalleLiquidacionPE | null = null;
    indexEnEdicion: number | null = null;
    indexAEliminar: number | null = null;
    cargandoDetalle = false;

    // Constantes
    readonly formasPago = FORMAS_PAGO;

    ngOnInit(): void {
        this.inicializarSuscripciones();
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ============================================================================
    // INICIALIZACIÓN
    // ============================================================================

    private inicializarSuscripciones(): void {
        // Suscripción a la factura actual
        this.service.facturaActual$
            .pipe(takeUntil(this.destroy$))
            .subscribe(factura => {
                this.facturaActual.set(factura);
            });

        // Suscripción a los detalles de liquidación
        this.service.detallesLiquidacion$
            .pipe(takeUntil(this.destroy$))
            .subscribe(detalles => {
                this.detallesLiquidacion.set(detalles);
            });

        // Suscripción a las agencias
        this.service.agencias$
            .pipe(takeUntil(this.destroy$))
            .subscribe(agencias => this.agenciasDisponibles.set(agencias));

        // Suscripción a estados de carga
        this.service.cargandoDetalles$
            .pipe(takeUntil(this.destroy$))
            .subscribe(cargando => this.cargandoDetalles.set(cargando));

        this.service.procesandoLiquidacion$
            .pipe(takeUntil(this.destroy$))
            .subscribe(guardando => this.guardandoCambios.set(guardando));
    }

    // ============================================================================
    // MÉTODOS PARA VERIFICAR PERMISOS
    // ============================================================================

    private obtenerPermisos(): PermisosEdicion {
        return this.permisosEdicion || this.permisosDefecto();
    }

    puedeEditarDetalles(): boolean {
        return this.obtenerPermisos().puedeEditar;
    }

    puedeAgregarDetalles(): boolean {
        return this.obtenerPermisos().puedeAgregar;
    }

    puedeEliminarDetalles(): boolean {
        return this.obtenerPermisos().puedeEliminar;
    }

    obtenerMensajePermisos(): string {
        return this.obtenerPermisos().razon;
    }

    obtenerClasePermisos(): string {
        return this.obtenerPermisos().claseCSS;
    }

    // ============================================================================
    // MODAL DE CAMBIOS SOLICITADOS
    // ============================================================================

    abrirModalCambios(detalle: DetalleLiquidacionPE): void {
        if (!detalle.id) return;

        this.detalleSeleccionadoParaCambios.set(detalle);
        this.mostrarModalCambios.set(true);
    }

    cerrarModalCambios(): void {
        this.mostrarModalCambios.set(false);
        this.detalleSeleccionadoParaCambios.set(null);
    }

    // ============================================================================
    // ACCIONES PRINCIPALES
    // ============================================================================

    abrirModal(): void {
        if (!this.puedeAgregarDetalles()) {
            Swal.fire({
                icon: 'warning',
                title: 'Acción no permitida',
                text: this.obtenerMensajePermisos()
            });
            return;
        }

        const factura = this.facturaActual();
        if (!factura?.numero_dte) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No hay factura seleccionada para agregar detalles'
            });
            return;
        }

        this.registroEnEdicion = this.crearDetalleVacio();
        this.indexEnEdicion = null;
        this.modoModal.set('crear');
        this.mostrarModalDetalle.set(true);
    }

    abrirModalEditar(index: number): void {
        if (!this.puedeEditarDetalles()) {
            Swal.fire({
                icon: 'warning',
                title: 'Edición no permitida',
                text: this.obtenerMensajePermisos()
            });
            return;
        }

        const detalles = this.detallesLiquidacion();
        if (index < 0 || index >= detalles.length) return;

        const detalleAEditar = detalles[index];
        this.indexEnEdicion = index;

        // Si el detalle tiene ID, cargar datos completos desde el servidor
        if (detalleAEditar.id) {
            this.cargandoDetalle = true;

            Swal.fire({
                title: 'Cargando datos...',
                html: 'Obteniendo información completa del registro',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            this.service.obtenerDetalleCompleto(detalleAEditar.id).subscribe({
                next: (response) => {
                    Swal.close();
                    this.cargandoDetalle = false;

                    if (response && response.respuesta === 'success' && response.datos) {
                        // Usar el detalle completo que incluye datos_especificos
                        this.registroEnEdicion = { ...response.datos };
                        this.modoModal.set('editar');
                        this.mostrarModalDetalle.set(true);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo cargar el detalle completo'
                        });
                    }
                },
                error: () => {
                    Swal.close();
                    this.cargandoDetalle = false;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Advertencia',
                        text: 'Error al cargar el detalle. Intentando con datos disponibles...'
                    });
                    // Fallback: usar datos básicos si falla la petición
                    this.registroEnEdicion = detalleAEditar ? { ...detalleAEditar } : null;
                    this.modoModal.set('editar');
                    this.mostrarModalDetalle.set(true);
                }
            });
        } else {
            // Si no tiene ID, es un registro nuevo sin guardar
            this.registroEnEdicion = detalleAEditar ? { ...detalleAEditar } : null;
            this.modoModal.set('editar');
            this.mostrarModalDetalle.set(true);
        }
    }

    confirmarEliminacion(): void {
        if (!this.puedeEliminarDetalles()) {
            Swal.fire({
                icon: 'warning',
                title: 'Eliminación no permitida',
                text: this.obtenerMensajePermisos()
            });
            return;
        }

        if (this.indexAEliminar !== null) {
            const detalles = this.detallesLiquidacion();
            const detalle = detalles[this.indexAEliminar];

            if (detalle?.id) {
                this.service.eliminarDetalle(detalle.id).subscribe({
                    next: (success) => {
                        if (success) {
                            this.cancelarEliminacion();
                        }
                    }
                });
            } else {
                const nuevosDetalles = [...detalles];
                nuevosDetalles.splice(this.indexAEliminar, 1);
                this.detallesLiquidacion.set(nuevosDetalles);
                this.cancelarEliminacion();

                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: 'Detalle eliminado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }
    }

    cancelarEliminacion(): void {
        this.indexAEliminar = null;
        this.mostrarModalEliminar.set(false);
    }

    private crearDetalleVacio(): DetalleLiquidacionPE {
        return {
            id: undefined,
            numero_orden: '',
            agencia: '',
            descripcion: '',
            monto: 0,
            correo_proveedor: '',
            forma_pago: '',
            banco: '',
            cuenta: '',
            editando: false,
            guardando: false
        };
    }

    // ============================================================================
    // MÉTODOS DE EVENTO
    // ============================================================================

    onAgregar(): void {
        this.abrirModal();
    }

    onEditar(index: number): void {
        this.abrirModalEditar(index);
    }

    onEliminar(index: number): void {
        if (!this.puedeEliminarDetalles()) {
            Swal.fire({
                icon: 'warning',
                title: 'Eliminación no permitida',
                text: this.obtenerMensajePermisos()
            });
            return;
        }

        const detalles = this.detallesLiquidacion();
        if (index < 0 || index >= detalles.length) return;

        this.indexAEliminar = index;
        this.mostrarModalEliminar.set(true);
    }

    onCopiar(index: number): void {
        if (!this.puedeAgregarDetalles()) {
            Swal.fire({
                icon: 'warning',
                title: 'Copia no permitida',
                text: this.obtenerMensajePermisos()
            });
            return;
        }

        const detalles = this.detallesLiquidacion();
        if (index < 0 || index >= detalles.length) return;

        const detalleOriginal = detalles[index];

        if (detalleOriginal.id) {
            this.service.copiarDetalle(detalleOriginal.id).subscribe({
                next: (success) => {
                    if (success) {
                        // Opcional: recargar detalles
                    }
                }
            });
        } else {
            const copia: DetalleLiquidacionPE = {
                ...detalleOriginal,
                id: undefined,
                descripcion: '[COPIA] ' + detalleOriginal.descripcion
            };

            const nuevosDetalles = [...detalles];
            nuevosDetalles.splice(index + 1, 0, copia);
            this.detallesLiquidacion.set(nuevosDetalles);

            Swal.fire({
                icon: 'success',
                title: 'Copiado',
                text: 'Detalle copiado correctamente',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }

    // ============================================================================
    // EDICIÓN INLINE - MONTO
    // ============================================================================

    iniciarEdicionMonto(index: number): void {
        if (!this.puedeEditarDetalles()) {
            Swal.fire({
                icon: 'info',
                title: 'Edición no permitida',
                text: this.obtenerMensajePermisos(),
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }

        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (!detalle) return;

        this.cancelarTodasLasEdiciones();

        detalle._editandoMonto = true;
        detalle._montoTemp = detalle.monto;

        const nuevosDetalles = [...detalles];
        nuevosDetalles[index] = { ...detalle };
        this.detallesLiquidacion.set(nuevosDetalles);

        setTimeout(() => {
            if (this.montoInput?.nativeElement) {
                this.montoInput.nativeElement.focus();
                this.montoInput.nativeElement.select();
            }
        }, 0);
    }

    guardarMonto(index: number): void {
        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (!detalle || !detalle._editandoMonto) return;

        const nuevoMonto = parseFloat(String(detalle._montoTemp || 0));

        if (isNaN(nuevoMonto) || nuevoMonto <= 0) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El monto debe ser mayor a 0'
            });
            return;
        }

        if (nuevoMonto === detalle.monto) {
            this.cancelarEdicionMonto(index);
            return;
        }

        const factura = this.facturaActual();
        if (factura) {
            const totalOtrosDetalles = detalles
                .filter((_, i) => i !== index)
                .reduce((sum, d) => sum + d.monto, 0);

            const nuevoTotal = totalOtrosDetalles + nuevoMonto;

            if (nuevoTotal > factura.monto_total) {
                const disponible = factura.monto_total - totalOtrosDetalles;
                Swal.fire({
                    icon: 'error',
                    title: 'Monto excedido',
                    text: `El monto máximo disponible es Q${disponible.toFixed(2)}`
                });
                return;
            }
        }

        if (detalle.id) {
            this.service.actualizarDetalle({ id: detalle.id, monto: nuevoMonto }).subscribe({
                next: (success) => {
                    if (success) {
                        detalle.monto = nuevoMonto;
                        detalle._editandoMonto = false;
                        delete detalle._montoTemp;

                        const nuevosDetalles = [...detalles];
                        nuevosDetalles[index] = detalle;
                        this.detallesLiquidacion.set(nuevosDetalles);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo actualizar el monto'
                        });
                    }
                },
                error: (error) => {
                    console.error('Error en suscripción:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error de conexión al actualizar el monto'
                    });
                }
            });
        } else {
            detalle.monto = nuevoMonto;
            detalle._editandoMonto = false;
            delete detalle._montoTemp;

            const nuevosDetalles = [...detalles];
            nuevosDetalles[index] = detalle;
            this.detallesLiquidacion.set(nuevosDetalles);

            Swal.fire({
                icon: 'success',
                title: 'Actualizado',
                text: 'Monto actualizado correctamente',
                timer: 1500,
                showConfirmButton: false
            });
        }
    }

    cancelarEdicionMonto(index: number): void {
        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (detalle) {
            detalle._editandoMonto = false;
            delete detalle._montoTemp;

            const nuevosDetalles = [...detalles];
            nuevosDetalles[index] = { ...detalle };
            this.detallesLiquidacion.set(nuevosDetalles);
        }
    }

    // ============================================================================
    // EDICIÓN INLINE - AGENCIA CON NG-SELECT
    // ============================================================================

    iniciarEdicionAgencia(index: number): void {
        if (!this.puedeEditarDetalles()) return;

        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (!detalle) return;

        // Cancelar otras ediciones
        this.cancelarTodasLasEdiciones();

        // Activar edición
        (detalle as any)._editandoAgencia = true;
        (detalle as any)._agenciaTemp = detalle.agencia;

        this.detallesLiquidacion.set([...detalles]);
    }
    guardarAgencia(index: number): void {
        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (!detalle) return;

        const nuevaAgencia = ((detalle as any)._agenciaTemp as string)?.trim();

        if (!nuevaAgencia || nuevaAgencia === detalle.agencia) {
            this.cancelarEdicionAgencia(index);
            return;
        }

        if (detalle.id) {
            // Guardar en servidor
            this.service.actualizarDetalle({ id: detalle.id, agencia: nuevaAgencia }).subscribe({
                next: (success) => {
                    if (success) {
                        detalle.agencia = nuevaAgencia;
                        (detalle as any)._editandoAgencia = false;
                        delete (detalle as any)._agenciaTemp;
                        this.detallesLiquidacion.set([...detalles]);
                    }
                }
            });
        } else {
            // Guardar local
            detalle.agencia = nuevaAgencia;
            (detalle as any)._editandoAgencia = false;
            delete (detalle as any)._agenciaTemp;
            this.detallesLiquidacion.set([...detalles]);
        }
    }

    cancelarEdicionAgencia(index: number): void {
        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (detalle) {
            (detalle as any)._editandoAgencia = false;
            delete (detalle as any)._agenciaTemp;
            this.detallesLiquidacion.set([...detalles]);
        }
    }

    onAgenciaBlur(index: number): void {
        setTimeout(() => {
            this.cancelarEdicionAgencia(index);
        }, 150);
    }

    // ============================================================================
    // UTILIDADES
    // ============================================================================

    private cancelarTodasLasEdiciones(): void {
        const detalles = this.detallesLiquidacion();
        let cambios = false;

        detalles.forEach((detalle, index) => {
            if (detalle._editandoMonto) {
                detalle._editandoMonto = false;
                delete detalle._montoTemp;
                cambios = true;
            }
            if ((detalle as any)._editandoAgencia) {
                (detalle as any)._editandoAgencia = false;
                delete (detalle as any)._agenciaTemp;
                cambios = true;
            }
        });

        if (cambios) {
            this.detallesLiquidacion.set([...detalles]);
        }
    }

    async verDetalleCompleto(index: number): Promise<void> {
        const detalles = this.detallesLiquidacion();
        const detalle = detalles[index];
        if (!detalle) return;

        if (detalle.id) {
            this.cargandoDetalle = true;

            Swal.fire({
                title: 'Cargando detalle...',
                html: 'Obteniendo información completa del registro',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            this.service.obtenerDetalleCompleto(detalle.id).subscribe({
                next: (response) => {
                    Swal.close();
                    this.cargandoDetalle = false;

                    if (response && response.respuesta === 'success' && response.datos) {
                        this.mostrarDetalleCompleto(response.datos);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo obtener el detalle completo'
                        });
                    }
                },
                error: () => {
                    Swal.close();
                    this.cargandoDetalle = false;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Advertencia',
                        text: 'Error al obtener el detalle'
                    });
                }
            });
        } else {
            this.mostrarDetalleBasico(detalle);
        }
    }

    private mostrarDetalleCompleto(detalleCompleto: any): void {
        const montoNormalizado = toNumber(detalleCompleto.monto, 0);
        const fechaCreacion = detalleCompleto.fecha_creacion ?
            new Date(detalleCompleto.fecha_creacion).toLocaleString('es-GT') : 'No registrada';
        const fechaActualizacion = detalleCompleto.fecha_actualizacion ?
            new Date(detalleCompleto.fecha_actualizacion).toLocaleString('es-GT') : 'No registrada';

        const html = `
        <div class="text-left space-y-3">
            <div><strong>Orden:</strong> ${detalleCompleto.numero_orden || 'No especificada'}</div>
            <div><strong>Agencia:</strong> ${detalleCompleto.agencia || 'No especificada'}</div>
            <div><strong>Descripción:</strong> ${detalleCompleto.descripcion || 'Sin descripción'}</div>
            <div><strong>Monto:</strong> ${formatearMonto(montoNormalizado)}</div>
            <div><strong>Forma de Pago:</strong> ${this.obtenerTextoFormaPago(detalleCompleto.forma_pago)}</div>
            <div><strong>Correo Proveedor:</strong> ${detalleCompleto.correo_proveedor || 'No especificado'}</div>
            ${detalleCompleto.cuenta ? `<div><strong>Cuenta:</strong> ${detalleCompleto.cuenta}</div>` : ''}
            ${detalleCompleto.nom_area_presupuesto != '99' ? `<div><strong>Area Presupuesto:</strong> ${detalleCompleto.nom_area_presupuesto}</div>` : ''}
            <div><strong>Fecha Creación:</strong> ${fechaCreacion}</div>
            <div><strong>Fecha Actualización:</strong> ${fechaActualizacion}</div>
            ${detalleCompleto.datos_especificos ? this.formatearDatosEspecificos(detalleCompleto.datos_especificos) : ''}
        </div>
    `;

        Swal.fire({
            title: `Detalle de Liquidación #${detalleCompleto.id || 'Nuevo'}`,
            html: html,
            width: '500px',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#6b7280'
        });
    }

    private mostrarDetalleBasico(detalle: DetalleLiquidacionPE): void {
        const montoNormalizado = toNumber(detalle.monto, 0);

        const html = `
        <div class="text-left space-y-3">
            <div><strong>Orden:</strong> ${detalle.numero_orden || 'No especificada'}</div>
            <div><strong>Agencia:</strong> ${detalle.agencia || 'No especificada'}</div>
            <div><strong>Descripción:</strong> ${detalle.descripcion || 'Sin descripción'}</div>
            <div><strong>Monto:</strong> ${formatearMonto(montoNormalizado)}</div>
            <div><strong>Forma de Pago:</strong> ${this.obtenerTextoFormaPago(detalle.forma_pago)}</div>
            <div><strong>Correo Proveedor:</strong> ${detalle.correo_proveedor || 'No especificado'}</div>
            ${detalle.banco ? `<div><strong>Banco:</strong> ${detalle.banco}</div>` : ''}
            ${detalle.cuenta ? `<div><strong>Cuenta:</strong> ${detalle.cuenta}</div>` : ''}
        </div>
    `;

        Swal.fire({
            title: 'Detalle de Liquidación',
            html: html,
            width: '500px',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#6b7280'
        });
    }

    private formatearDatosEspecificos(datosEspecificos: any): string {
        if (!datosEspecificos || typeof datosEspecificos !== 'object') return '';

        let html = '<div class="mt-4 pt-3 border-t border-gray-200"><strong>Información Específica:</strong></div>';

        Object.keys(datosEspecificos).forEach(key => {
            const valor = datosEspecificos[key];
            if (valor !== null && valor !== undefined && valor !== '') {
                const labelFormateado = this.formatearLabelCampo(key);
                const valorFormateado = this.formatearValorCampo(key, valor);
                html += `<div><strong>${labelFormateado}:</strong> ${valorFormateado}</div>`;
            }
        });

        return html;
    }

    private formatearLabelCampo(key: string): string {
        const labels: { [key: string]: string } = {
            'id_socio': 'ID Socio',
            'nombre_socio': 'Nombre del Socio',
            'numero_cuenta_deposito': 'Número de Cuenta',
            'producto_cuenta': 'Producto',
            'nombre_beneficiario': 'Beneficiario',
            'consignacion': 'Consignación',
            'no_negociable': 'No Negociable',
            'nombre_cuenta': 'Nombre de Cuenta',
            'numero_cuenta': 'Número de Cuenta',
            'tipo_cuenta': 'Tipo de Cuenta',
            'observaciones': 'Observaciones',
            'nota': 'Nota'
        };

        return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    private formatearValorCampo(key: string, valor: any): string {
        if (typeof valor === 'boolean') {
            return valor ? 'Sí' : 'No';
        }

        if (key.includes('monto') && typeof valor === 'number') {
            return formatearMonto(valor);
        }

        return String(valor);
    }

    obtenerTextoFormaPago(formaPago: string): string {
        const forma = this.formasPago.find(f => f.id === formaPago);
        return forma?.nombre || formaPago || 'Sin especificar';
    }

    obtenerClaseFormaPago(formaPago: string): string {
        const colores: { [key: string]: string } = {
            'deposito': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            'transferencia': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            'cheque': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            'efectivo': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300'
        };
        return colores[formaPago] || 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
    }

    trackById(index: number, detalle: DetalleLiquidacionPE): any {
        return detalle?.id ?? index;
    }

    trackByAgencia(index: number, agencia: any): any {
        return agencia?.id ?? index;
    }


    /**
     * Abre el modal de preview del comprobante
     */
    verComprobante(detalle: DetalleLiquidacionPE): void {
        console.log('🔍 Ver comprobante - detalle:', detalle);

        // Verificar si tiene drive_id
        if (!detalle.driveId || detalle.driveId === '-1') {
            Swal.fire({
                icon: 'info',
                title: 'Sin comprobante',
                text: 'Este detalle no tiene comprobante asociado en Drive',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        console.log('✅ Abriendo modal con drive_id:', detalle.driveId);

        // Configurar el modal de preview
        this.previewDriveId.set(detalle.driveId);
        this.previewRutaArchivo.set(''); // No usamos ruta local, solo Drive
        this.previewNombreArchivo.set(
            `comprobante_${detalle.numero_orden || detalle.id || 'detalle'}.pdf`
        );

        // Mostrar el modal
        this.mostrarPreviewComprobante.set(true);
    }

    /**
     * Cierra el modal de preview del comprobante
     */
    cerrarPreviewComprobante(): void {
        this.mostrarPreviewComprobante.set(false);
        this.previewDriveId.set('');
        this.previewRutaArchivo.set('');
        this.previewNombreArchivo.set('comprobante.pdf');
    }

}