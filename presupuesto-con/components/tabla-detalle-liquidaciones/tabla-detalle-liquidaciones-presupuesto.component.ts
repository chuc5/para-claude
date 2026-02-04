// ============================================================================
// TABLA DETALLE LIQUIDACIONES - REFACTORIZADO CON STORE CENTRALIZADO
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, ViewChild, ElementRef, inject, signal, Input } from '@angular/core';
import { FormsModule } from '@angular/forms';
import Swal from 'sweetalert2';

import { ModalDetalleLiquidacionNuevoComponentpresupuesto } from '../modal-detalle-liquidacion/modal-detalle-liquidacion.component';
import { ModalConfirmarEliminacionComponentpresupuesto } from '../modal-confirmar-eliminacion/modal-confirmar-eliminacion.component';
import { ModalVerCambiosUsuarioComponent } from '../modal-cambios-usuario/modal-ver-cambios-usuario.component';

import {
    PresupuestoStore,
    PresupuestoApiService,
    DetalleLiquidacionPE,
    PermisosEdicion,
    FORMAS_PAGO,
    formatearMonto,
    toNumber
} from '../../core';

@Component({
    selector: 'app-tabla-detalle-liquidaciones-presupuesto',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        ModalDetalleLiquidacionNuevoComponentpresupuesto,
        ModalConfirmarEliminacionComponentpresupuesto,
        ModalVerCambiosUsuarioComponent
    ],
    templateUrl: './tabla-detalle-liquidaciones-presupuesto.component.html'
})
export class TablaDetalleLiquidacionesComponentpresupuesto {

    @ViewChild('montoInput') montoInput?: ElementRef<HTMLInputElement>;
    @ViewChild('agenciaInput') agenciaInput?: ElementRef<HTMLSelectElement>;

    @Input() permisosEdicion: PermisosEdicion | null = null;

    private readonly store = inject(PresupuestoStore);
    private readonly api = inject(PresupuestoApiService);

    // ============================================================================
    // SIGNALS DESDE EL STORE
    // ============================================================================

    readonly facturaActual = this.store.factura;
    readonly detallesLiquidacion = this.store.detalles;
    readonly agenciasDisponibles = this.store.agencias;

    // ============================================================================
    // ESTADO LOCAL
    // ============================================================================

    readonly mostrarModalDetalle = signal(false);
    readonly modoModal = signal<'crear' | 'editar'>('crear');
    readonly mostrarModalEliminar = signal(false);
    readonly mostrarModalCambios = signal(false);
    readonly detalleSeleccionadoParaCambios = signal<DetalleLiquidacionPE | null>(null);
    readonly cargandoDetalles = signal(false);
    readonly guardandoCambios = signal(false);

    registroEnEdicion: DetalleLiquidacionPE | null = null;
    indexEnEdicion: number | null = null;
    indexAEliminar: number | null = null;
    cargandoDetalle = false;

    readonly formasPago = FORMAS_PAGO;

    // ============================================================================
    // PERMISOS
    // ============================================================================

    private _permisos(): PermisosEdicion {
        return this.permisosEdicion || this.store.permisos();
    }

    puedeEditarDetalles(): boolean { return this._permisos().puedeEditar; }
    puedeAgregarDetalles(): boolean { return this._permisos().puedeAgregar; }
    puedeEliminarDetalles(): boolean { return this._permisos().puedeEliminar; }
    obtenerMensajePermisos(): string { return this._permisos().razon; }
    obtenerClasePermisos(): string { return this._permisos().claseCSS; }

    // ============================================================================
    // MODAL DE CAMBIOS
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
            this._mostrarAlerta('warning', 'Acción no permitida', this.obtenerMensajePermisos());
            return;
        }

        if (!this.store.factura()?.numero_dte) {
            this._mostrarAlerta('error', 'Error', 'No hay factura seleccionada');
            return;
        }

        this.registroEnEdicion = this._crearDetalleVacio();
        this.indexEnEdicion = null;
        this.modoModal.set('crear');
        this.mostrarModalDetalle.set(true);
    }

    abrirModalEditar(index: number): void {
        if (!this.puedeEditarDetalles()) {
            this._mostrarAlerta('warning', 'Edición no permitida', this.obtenerMensajePermisos());
            return;
        }

        const detalles = this.store.detalles();
        if (index < 0 || index >= detalles.length) return;

        this.indexEnEdicion = index;
        this.registroEnEdicion = { ...detalles[index] };
        this.modoModal.set('editar');
        this.mostrarModalDetalle.set(true);
    }

    confirmarEliminacion(): void {
        if (!this.puedeEliminarDetalles()) {
            this._mostrarAlerta('warning', 'Eliminación no permitida', this.obtenerMensajePermisos());
            return;
        }

        if (this.indexAEliminar !== null) {
            const detalle = this.store.detalles()[this.indexAEliminar];
            if (detalle?.id) {
                this.api.eliminarDetalle(detalle.id).subscribe(() => this.cancelarEliminacion());
            } else {
                this.store.eliminarDetalle(this.indexAEliminar);
                this.cancelarEliminacion();
            }
        }
    }

    cancelarEliminacion(): void {
        this.indexAEliminar = null;
        this.mostrarModalEliminar.set(false);
    }

    // ============================================================================
    // EVENTOS
    // ============================================================================

    onAgregar(): void { this.abrirModal(); }
    onEditar(index: number): void { this.abrirModalEditar(index); }

    onEliminar(index: number): void {
        if (!this.puedeEliminarDetalles()) {
            this._mostrarAlerta('warning', 'Eliminación no permitida', this.obtenerMensajePermisos());
            return;
        }
        this.indexAEliminar = index;
        this.mostrarModalEliminar.set(true);
    }

    onCopiar(index: number): void {
        if (!this.puedeAgregarDetalles()) {
            this._mostrarAlerta('warning', 'Copia no permitida', this.obtenerMensajePermisos());
            return;
        }

        const detalle = this.store.detalles()[index];
        if (detalle?.id) {
            this.api.copiarDetalle(detalle.id).subscribe();
        }
    }

    // ============================================================================
    // EDICIÓN INLINE - MONTO
    // ============================================================================

    iniciarEdicionMonto(index: number): void {
        if (!this.puedeEditarDetalles()) {
            this._mostrarAlertaTemporal('info', 'Edición no permitida', this.obtenerMensajePermisos());
            return;
        }

        this._cancelarTodasLasEdiciones();
        const detalle = this.store.detalles()[index];
        if (!detalle) return;

        this.store.actualizarDetalle(detalle.id!, { _editandoMonto: true, _montoTemp: detalle.monto });

        setTimeout(() => this.montoInput?.nativeElement?.focus(), 0);
    }

    guardarMonto(index: number): void {
        const detalle = this.store.detalles()[index];
        if (!detalle?._editandoMonto) return;

        const nuevoMonto = parseFloat(String(detalle._montoTemp || 0));

        if (isNaN(nuevoMonto) || nuevoMonto <= 0) {
            this._mostrarAlerta('error', 'Error', 'El monto debe ser mayor a 0');
            return;
        }

        if (nuevoMonto === detalle.monto) {
            this.cancelarEdicionMonto(index);
            return;
        }

        const disponible = this.store.calcularMontoDisponible(detalle.id);
        if (nuevoMonto > disponible + detalle.monto) {
            this._mostrarAlerta('error', 'Monto excedido', `El monto máximo disponible es Q${disponible.toFixed(2)}`);
            return;
        }

        if (detalle.id) {
            this.api.actualizarDetalle({ id: detalle.id, monto: nuevoMonto }).subscribe();
        } else {
            this.store.actualizarDetalle(index, { monto: nuevoMonto, _editandoMonto: false });
        }
    }

    cancelarEdicionMonto(index: number): void {
        const detalle = this.store.detalles()[index];
        if (detalle?.id) {
            this.store.actualizarDetalle(detalle.id, { _editandoMonto: false, _montoTemp: undefined });
        }
    }

    // ============================================================================
    // EDICIÓN INLINE - AGENCIA
    // ============================================================================

    iniciarEdicionAgencia(index: number): void {
        if (!this.puedeEditarDetalles()) {
            this._mostrarAlertaTemporal('info', 'Edición no permitida', this.obtenerMensajePermisos());
            return;
        }

        this._cancelarTodasLasEdiciones();
        const detalle = this.store.detalles()[index];
        if (!detalle) return;

        this.store.actualizarDetalle(detalle.id!, { _editandoAgencia: true, _agenciaTemp: detalle.agencia });

        setTimeout(() => this.agenciaInput?.nativeElement?.focus(), 0);
    }

    guardarAgencia(index: number): void {
        const detalle = this.store.detalles()[index];
        if (!detalle?._editandoAgencia) return;

        const nuevaAgencia = detalle._agenciaTemp?.trim();
        if (!nuevaAgencia) {
            this._mostrarAlerta('error', 'Error', 'Debe seleccionar una agencia');
            return;
        }

        if (nuevaAgencia === detalle.agencia) {
            this.cancelarEdicionAgencia(index);
            return;
        }

        if (detalle.id) {
            this.api.actualizarDetalle({ id: detalle.id, agencia: nuevaAgencia }).subscribe();
        } else {
            this.store.actualizarDetalle(index, { agencia: nuevaAgencia, _editandoAgencia: false });
        }
    }

    cancelarEdicionAgencia(index: number): void {
        const detalle = this.store.detalles()[index];
        if (detalle?.id) {
            this.store.actualizarDetalle(detalle.id, { _editandoAgencia: false, _agenciaTemp: undefined });
        }
    }

    // ============================================================================
    // VER DETALLE COMPLETO
    // ============================================================================

    async verDetalleCompleto(index: number): Promise<void> {
        const detalle = this.store.detalles()[index];
        if (!detalle?.id) {
            this._mostrarDetalleBasico(detalle);
            return;
        }

        Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        this.api.obtenerDetalleCompleto(detalle.id).subscribe({
            next: (response) => {
                Swal.close();
                if (response.respuesta === 'success' && response.datos) {
                    this._mostrarDetalleCompleto(response.datos);
                } else {
                    this._mostrarAlerta('error', 'Error', 'No se pudo obtener el detalle');
                }
            },
            error: () => {
                Swal.close();
                this._mostrarAlerta('warning', 'Advertencia', 'Error al obtener el detalle');
            }
        });
    }

    // ============================================================================
    // UTILIDADES
    // ============================================================================

    private _cancelarTodasLasEdiciones(): void {
        this.store.detalles().forEach(d => {
            if (d.id && (d._editandoMonto || d._editandoAgencia)) {
                this.store.actualizarDetalle(d.id, {
                    _editandoMonto: false, _montoTemp: undefined,
                    _editandoAgencia: false, _agenciaTemp: undefined
                });
            }
        });
    }

    private _crearDetalleVacio(): DetalleLiquidacionPE {
        return {
            numero_orden: '', agencia: '', descripcion: '', monto: 0,
            correo_proveedor: '', forma_pago: '', banco: '', cuenta: ''
        };
    }

    private _mostrarAlerta(icon: 'success' | 'error' | 'warning' | 'info', title: string, text: string): void {
        Swal.fire({ icon, title, text });
    }

    private _mostrarAlertaTemporal(icon: 'success' | 'error' | 'warning' | 'info', title: string, text: string): void {
        Swal.fire({ icon, title, text, timer: 3000, showConfirmButton: false });
    }

    private _mostrarDetalleCompleto(data: any): void {
        const monto = toNumber(data.monto, 0);
        Swal.fire({
            title: `Detalle #${data.id || 'Nuevo'}`,
            html: `
                <div class="text-left space-y-2">
                    <div><strong>Orden:</strong> ${data.numero_orden || '-'}</div>
                    <div><strong>Agencia:</strong> ${data.agencia || '-'}</div>
                    <div><strong>Descripción:</strong> ${data.descripcion || '-'}</div>
                    <div><strong>Monto:</strong> ${formatearMonto(monto)}</div>
                    <div><strong>Forma de Pago:</strong> ${this.obtenerTextoFormaPago(data.forma_pago)}</div>
                    ${data.banco ? `<div><strong>Banco:</strong> ${data.banco}</div>` : ''}
                    ${data.cuenta ? `<div><strong>Cuenta:</strong> ${data.cuenta}</div>` : ''}
                </div>`,
            width: '500px',
            confirmButtonText: 'Cerrar'
        });
    }

    private _mostrarDetalleBasico(detalle: DetalleLiquidacionPE): void {
        this._mostrarDetalleCompleto(detalle);
    }

    obtenerTextoFormaPago(formaPago: string): string {
        return this.formasPago.find(f => f.id === formaPago)?.nombre || formaPago || 'Sin especificar';
    }

    obtenerClaseFormaPago(formaPago: string): string {
        const colores: Record<string, string> = {
            'deposito': 'bg-blue-100 text-blue-800',
            'transferencia': 'bg-green-100 text-green-800',
            'cheque': 'bg-purple-100 text-purple-800',
            'efectivo': 'bg-orange-100 text-orange-800'
        };
        return colores[formaPago] || 'bg-gray-100 text-gray-800';
    }

    trackById(index: number, detalle: DetalleLiquidacionPE): any {
        return detalle?.id ?? index;
    }

    trackByAgencia(index: number, agencia: any): any {
        return agencia?.id ?? index;
    }
}
