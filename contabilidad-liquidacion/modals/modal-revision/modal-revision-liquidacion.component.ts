// ============================================================================
// MODAL - REVISIÓN DE LIQUIDACIÓN
// ============================================================================
// Modal completo para visualizar y gestionar liquidaciones desde Contabilidad
// Diseño minimalista estilo Microsoft 365
// ============================================================================

import { CommonModule } from '@angular/common';
import { Component, Input, Output, EventEmitter, inject } from '@angular/core';

// Lucide Icons
import {
    LucideAngularModule,
    X,
    FileText,
    User,
    Calendar,
    DollarSign,
    Car,
    CheckCircle,
    XCircle,
    RotateCcw,
    Trash2
} from 'lucide-angular';

import {
    LiquidacionContabilidad,
    ContabilidadHelper,
    FormatHelper,
    EstadoHelper
} from '../../models/contabilidad.models';

@Component({
    selector: 'app-modal-revision-liquidacion',
    standalone: true,
    imports: [
        CommonModule,
        LucideAngularModule
    ],
    templateUrl: './modal-revision-liquidacion.component.html',
    styleUrls: ['./modal-revision-liquidacion.component.css']
})
export class ModalRevisionLiquidacionComponent {

    // ========================================================================
    // INPUTS/OUTPUTS
    // ========================================================================
    @Input() liquidacion: LiquidacionContabilidad | null = null;

    @Output() cerrar = new EventEmitter<void>();
    @Output() accionRealizada = new EventEmitter<void>();

    // ========================================================================
    // ICONOS
    // ========================================================================
    readonly X = X;
    readonly FileText = FileText;
    readonly User = User;
    readonly Calendar = Calendar;
    readonly DollarSign = DollarSign;
    readonly Car = Car;
    readonly CheckCircle = CheckCircle;
    readonly XCircle = XCircle;
    readonly RotateCcw = RotateCcw;
    readonly Trash2 = Trash2;

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

    // ========================================================================
    // ACCIONES
    // ========================================================================

    emitirAprobar(): void {
        this.accionRealizada.emit();
    }

    emitirRechazar(): void {
        this.accionRealizada.emit();
    }

    emitirDevolver(): void {
        this.accionRealizada.emit();
    }

    emitirBaja(): void {
        this.accionRealizada.emit();
    }

    cerrarModal(): void {
        this.cerrar.emit();
    }
}