// ============================================================================
// MODAL DETALLE LIQUIDACIÓN - REFACTORIZADO CON STORE CENTRALIZADO
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, signal, OnInit, OnDestroy, inject, Input, Output, EventEmitter, ChangeDetectorRef, AfterViewInit } from '@angular/core';
import { FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Subject, takeUntil } from 'rxjs';
import { NgSelectModule } from '@ng-select/ng-select';

import { PagoDepositoFormComponentpresupuesto } from '../pago-forms/pago-deposito-form/pago-deposito-form.component';
import { PagoTransferenciaFormComponentpresupuesto } from '../pago-forms/pago-transferencia-form/pago-transferencia-form.component';
import { PagoChequeFormComponentpresupuesto } from '../pago-forms/pago-cheque-form/pago-cheque-form.component';
import { PagoTarjetaSelectComponentpresupuesto } from '../pago-forms/pago-tarjeta-select/pago-tarjeta-select.component';
import { PagoAnticipoSelectComponentpresupuesto } from '../pago-forms/pago-anticipo-select/pago-anticipo-select.component';

import {
    PresupuestoStore,
    PresupuestoApiService,
    DetalleLiquidacionPE,
    OrdenPE,
    GuardarDetalleLiquidacionPayload,
    FORMAS_PAGO,
    formatearMonto
} from '../../core';

@Component({
    selector: 'app-modal-detalle-liquidacion-presupuesto',
    standalone: true,
    imports: [
        CommonModule, FormsModule, ReactiveFormsModule, NgSelectModule,
        PagoDepositoFormComponentpresupuesto, PagoTransferenciaFormComponentpresupuesto,
        PagoChequeFormComponentpresupuesto, PagoTarjetaSelectComponentpresupuesto,
        PagoAnticipoSelectComponentpresupuesto
    ],
    templateUrl: './modal-detalle-liquidacion.component.html',
    styleUrls: ['./modal-detalle-liquidacion.component.scss']
})
export class ModalDetalleLiquidacionNuevoComponentpresupuesto implements OnInit, OnDestroy, AfterViewInit {

    @Input() visible = false;
    @Input() modo: 'crear' | 'editar' = 'crear';
    @Input() registro: DetalleLiquidacionPE | null = null;
    @Output() visibleChange = new EventEmitter<boolean>();

    private readonly store = inject(PresupuestoStore);
    private readonly api = inject(PresupuestoApiService);
    private readonly cdr = inject(ChangeDetectorRef);
    private readonly destroy$ = new Subject<void>();

    // ============================================================================
    // ESTADO LOCAL
    // ============================================================================

    formularioPrincipal!: FormGroup;
    tipoSeleccionado = signal<string>('');
    mostrarFormularioEspecifico = signal(false);
    datosFormularioCompletos: any = null;
    submitting = false;

    // ============================================================================
    // SIGNALS DESDE EL STORE
    // ============================================================================

    readonly facturaActual = this.store.factura;
    readonly agenciasDisponibles = this.store.agencias;
    readonly areasDisponibles = this.store.areas;
    readonly bancosDisponibles = this.store.bancos;
    readonly tiposCuentaDisponibles = this.store.tiposCuenta;
    readonly ordenesDisponibles = this.store.ordenes;
    readonly tiposPago = signal(FORMAS_PAGO);

    public math = Math;

    // ============================================================================
    // LIFECYCLE
    // ============================================================================

    ngOnInit(): void {
        this._inicializarFormulario();
        this._configurarSuscripciones();
        this.api.cargarCatalogos().subscribe();
    }

    ngAfterViewInit(): void {
        this.cdr.detectChanges();
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    // ============================================================================
    // INICIALIZACIÓN
    // ============================================================================

    private _inicializarFormulario(): void {
        this.formularioPrincipal = new FormGroup({
            numero_orden: new FormControl('', [Validators.required]),
            agencia: new FormControl('', [Validators.required]),
            descripcion: new FormControl('', [Validators.required, Validators.minLength(5), Validators.maxLength(800)]),
            monto: new FormControl(null, [Validators.required, Validators.min(0.01)]),
            correo_proveedor: new FormControl('', [Validators.email]),
            forma_pago: new FormControl('', [Validators.required]),
            banco: new FormControl(''),
            cuenta: new FormControl(''),
            area_presupuesto: new FormControl('', [Validators.required])
        });

        if (this.registro) {
            this._cargarDatosParaEdicion();
        }
    }

    private _configurarSuscripciones(): void {
        this.formularioPrincipal.get('forma_pago')?.valueChanges
            .pipe(takeUntil(this.destroy$))
            .subscribe(value => {
                this.tipoSeleccionado.set(value);
                this._manejarCambiosFormaPago(value);
                this._actualizarValidacionOrden(value);
            });

        this.formularioPrincipal.get('monto')?.valueChanges
            .pipe(takeUntil(this.destroy$))
            .subscribe(monto => this._validarMontoConServicio(monto));
    }

    private _cargarDatosParaEdicion(): void {
        if (!this.registro) return;

        this.formularioPrincipal.patchValue({
            numero_orden: this.registro.numero_orden || '',
            agencia: this.registro.agencia || '',
            descripcion: this.registro.descripcion || '',
            monto: this.registro.monto || null,
            correo_proveedor: this.registro.correo_proveedor || '',
            forma_pago: this.registro.forma_pago || '',
            banco: this.registro.banco || '',
            cuenta: this.registro.cuenta || '',
            area_presupuesto: this.registro.area_presupuesto ? Number(this.registro.area_presupuesto) : null
        });

        this.tipoSeleccionado.set(this.registro.forma_pago || '');

        if (this._requiereFormularioEspecifico(this.registro.forma_pago)) {
            this.mostrarFormularioEspecifico.set(true);
        }
    }

    // ============================================================================
    // VALIDACIONES
    // ============================================================================

    private _actualizarValidacionOrden(formaPago: string): void {
        const ordenControl = this.formularioPrincipal.get('numero_orden');
        const areaControl = this.formularioPrincipal.get('area_presupuesto');

        if (!ordenControl) return;

        if (formaPago === 'costoasumido') {
            ordenControl.clearValidators();
            ordenControl.setValue('0');
            ordenControl.disable();
            areaControl?.setValidators([Validators.required]);
            areaControl?.enable();
        } else {
            ordenControl.setValidators([Validators.required]);
            ordenControl.enable();
            areaControl?.clearValidators();
            areaControl?.setValue('');
        }

        ordenControl.updateValueAndValidity();
        areaControl?.updateValueAndValidity();
    }

    private _manejarCambiosFormaPago(forma_pago: string): void {
        const bancoControl = this.formularioPrincipal.get('banco');
        const cuentaControl = this.formularioPrincipal.get('cuenta');

        if (!bancoControl || !cuentaControl) return;

        bancoControl.clearValidators();
        cuentaControl.clearValidators();
        bancoControl.setValue('');
        cuentaControl.setValue('');

        this.mostrarFormularioEspecifico.set(this._requiereFormularioEspecifico(forma_pago));

        bancoControl.updateValueAndValidity();
        cuentaControl.updateValueAndValidity();
    }

    private _requiereFormularioEspecifico(formaPago?: string): boolean {
        return !!formaPago && ['deposito', 'transferencia', 'cheque'].includes(formaPago);
    }

    private _validarMontoConServicio(monto: number | null): void {
        const factura = this.store.factura();
        if (!factura || monto === null || monto === undefined) return;

        const montoControl = this.formularioPrincipal.get('monto');
        if (!montoControl) return;

        const montoDisponible = this.store.calcularMontoDisponible(
            this.modo === 'editar' ? this.registro?.id : undefined
        );

        const montoRedondeado = Math.round(monto * 100) / 100;
        const montoDisponibleRedondeado = Math.round(montoDisponible * 100) / 100;

        const errores = montoControl.errors || {};
        delete errores['montoExcedido'];
        delete errores['montoInsuficiente'];

        if (montoRedondeado <= 0) {
            errores['montoInsuficiente'] = true;
        } else if (montoRedondeado > montoDisponibleRedondeado) {
            errores['montoExcedido'] = { montoDisponible: montoDisponibleRedondeado, montoIngresado: montoRedondeado };
        }

        montoControl.setErrors(Object.keys(errores).length > 0 ? errores : null);
    }

    // ============================================================================
    // GUARDADO
    // ============================================================================

    onGuardarDesdeForm(datosEspecificos: any): void {
        this.datosFormularioCompletos = {
            ...this.formularioPrincipal.value,
            ...datosEspecificos,
            forma_pago: this.tipoSeleccionado(),
            factura_id: this.store.factura()?.id
        };
        this._completarGuardado();
    }

    onGuardarBasico(): void {
        if (!this.formularioPrincipal.valid) {
            this._marcarCamposComoTocados();
            return;
        }

        if (this._requiereFormularioEspecifico(this.formularioPrincipal.get('forma_pago')?.value)) {
            return;
        }

        this.datosFormularioCompletos = {
            ...this.formularioPrincipal.value,
            forma_pago: this.tipoSeleccionado(),
            factura_id: this.store.factura()?.id
        };
        this._completarGuardado();
    }

    private _completarGuardado(): void {
        if (!this.datosFormularioCompletos) return;

        this.submitting = true;

        const payload: GuardarDetalleLiquidacionPayload = {
            id: this.registro?.id,
            numero_factura: this.store.factura()?.numero_dte || '',
            numero_orden: this.datosFormularioCompletos.numero_orden,
            agencia: this.datosFormularioCompletos.agencia,
            descripcion: this.datosFormularioCompletos.descripcion,
            monto: parseFloat(this.datosFormularioCompletos.monto),
            correo_proveedor: this.datosFormularioCompletos.correo_proveedor || '',
            forma_pago: this.datosFormularioCompletos.forma_pago,
            banco: this.datosFormularioCompletos.banco || '',
            cuenta: this.datosFormularioCompletos.cuenta || '',
            area_presupuesto: this.datosFormularioCompletos.area_presupuesto || '',
            datos_especificos: this._extraerDatosEspecificos(this.datosFormularioCompletos)
        };

        this.api.guardarDetalle(payload).pipe(takeUntil(this.destroy$)).subscribe({
            next: (success) => {
                this.submitting = false;
                if (success) this._cerrarModal();
            },
            error: () => this.submitting = false
        });
    }

    private _extraerDatosEspecificos(datos: any): any {
        const especificos: any = {};
        const campos: Record<string, string[]> = {
            'deposito': ['id_socio', 'nombre_socio', 'numero_cuenta_deposito', 'producto_cuenta', 'observaciones'],
            'cheque': ['nombre_beneficiario', 'consignacion', 'no_negociable', 'observaciones'],
            'transferencia': ['nombre_cuenta', 'numero_cuenta', 'banco', 'tipo_cuenta', 'observaciones'],
            'tarjeta': ['nota'],
            'anticipo': ['nota']
        };

        const camposParaForma = campos[datos.forma_pago] || [];
        camposParaForma.forEach(campo => {
            if (datos[campo] !== undefined) especificos[campo] = datos[campo];
        });

        return especificos;
    }

    // ============================================================================
    // UI
    // ============================================================================

    onCancelar(): void {
        this._cerrarModal();
    }

    private _cerrarModal(): void {
        this._resetearFormulario();
        this.visible = false;
        this.visibleChange.emit(false);
        this.mostrarFormularioEspecifico.set(false);
        this.tipoSeleccionado.set('');
        this.datosFormularioCompletos = null;
    }

    private _marcarCamposComoTocados(): void {
        Object.keys(this.formularioPrincipal.controls).forEach(key => {
            this.formularioPrincipal.get(key)?.markAsTouched();
        });
    }

    private _resetearFormulario(): void {
        this.formularioPrincipal.reset();
        this.formularioPrincipal.get('forma_pago')?.setValue('');
        this.submitting = false;
    }

    // ============================================================================
    // GETTERS Y UTILIDADES
    // ============================================================================

    get esFormularioValido(): boolean {
        return this.formularioPrincipal.valid;
    }

    get montoDisponible(): number {
        return this.store.calcularMontoDisponible(this.modo === 'editar' ? this.registro?.id : undefined);
    }

    obtenerOrdenesDisponibles(): OrdenPE[] {
        return this.store.ordenes().filter(orden => {
            if (this.modo === 'editar' && this.registro) {
                if (orden.numero_orden.toString() === this.registro.numero_orden.toString()) return true;
            }
            return !orden.anticipos_pendientes || orden.anticipos_pendientes === 0;
        });
    }

    get ordenSeleccionadaInfo(): OrdenPE | undefined {
        const no = this.formularioPrincipal.get('numero_orden')?.value;
        return this.store.ordenes().find(o => o.numero_orden.toString() === no);
    }

    campoInvalido(nombreCampo: string): boolean {
        const control = this.formularioPrincipal.get(nombreCampo);
        return !!(control?.touched && control?.errors);
    }

    obtenerErrorMensaje(nombreCampo: string): string | null {
        const control = this.formularioPrincipal.get(nombreCampo);
        if (!control?.touched || !control?.errors) return null;

        const e = control.errors;
        if (e['required']) return 'Este campo es obligatorio';
        if (e['email']) return 'Ingrese un correo electrónico válido';
        if (e['minlength']) return `Mínimo ${e['minlength'].requiredLength} caracteres`;
        if (e['maxlength']) return `Máximo ${e['maxlength'].requiredLength} caracteres`;
        if (e['min']) return `El valor mínimo es ${e['min'].min}`;
        if (e['montoExcedido']) return `El monto excede el disponible (Q${e['montoExcedido'].montoDisponible.toFixed(2)})`;
        if (e['montoInsuficiente']) return 'El monto debe ser mayor a 0';
        return 'Campo inválido';
    }

    calcularMontoPendienteOrden(orden: OrdenPE): number {
        return orden.monto_pendiente || (orden.total - (orden.monto_liquidado || 0));
    }

    onModalClick(event: Event): void { event.stopPropagation(); }
    onOverlayClick(): void { this.onCancelar(); }
    formatearMonto = formatearMonto;
    obtenerTextoFormaPago(formaPago: string): string {
        return FORMAS_PAGO.find(f => f.id === formaPago)?.nombre || formaPago || 'Sin especificar';
    }

    trackByOrden(index: number, orden: OrdenPE): number { return orden.numero_orden; }
    trackByTipoPago(index: number, tipo: any): string { return tipo.id; }
    trackByAgencia(index: number, agencia: any): number { return agencia.id; }
    trackByArea(index: number, area: any): number { return area.id; }
    trackByBanco(index: number, banco: any): number { return banco.id_banco; }

    requiereFormularioEspecifico(formaPago?: string): boolean {
        return this._requiereFormularioEspecifico(formaPago);
    }

    calcularMontoDisponible(): number {
        return this.montoDisponible;
    }

    calcularMontoPendiente(): number {
        return this.montoDisponible;
    }
}
