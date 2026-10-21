import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { Campana, Entrega, EstadoEntrega } from '../../models/api.models';

interface StatusInfo {
  label: string;
  badge: string;
}

const PAGE_SIZE = 10;
const REFRESH_MS = 4000;

@Component({
  selector: 'app-history',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './historial.component.html',
  styleUrls: ['./historial.component.css']
})
export class HistorialComponent implements OnInit, OnDestroy {

  history: Campana[] = [];
  loaded = false;
  visibleCount = PAGE_SIZE;

  textFilter = '';
  statusFilter = '';
  minDateFilter = '';
  maxDateFilter = '';

  expandedCampaignId: number | null = null;
  deliveries: Partial<Record<number, Entrega[]>> = {};
  loadingDeliveries: Partial<Record<number, boolean>> = {};
  actionInProgress: number | null = null;

  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.cargarHistorial();

    // Mientras haya campañas enviándose o en cola, la lista se actualiza sola
    this.refreshTimer = setInterval(() => {
      if (this.history.some(c => this.estaActiva(c))) {
        this.cargarHistorial();
      }
    }, REFRESH_MS);
  }

  ngOnDestroy(): void {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
    }
  }

  cargarHistorial(): void {
    this.api.obtenerHistorial().subscribe({
      next: response => {
        this.history = response.data ?? [];
        this.loaded = true;

        // Mantiene al día las entregas de la campaña desplegada
        if (this.expandedCampaignId !== null) {
          const expanded = this.history.find(c => c.id === this.expandedCampaignId);

          if (expanded && this.estaActiva(expanded)) {
            this.obtenerEntregas(expanded.id);
          }
        }
      },
      error: () => {
        if (!this.loaded) {
          this.toast.fallo('No se pudo cargar el historial.');
        }
        this.loaded = true;
      }
    });
  }

  // ---------------------------------------------------------------
  // Estado y progreso
  // ---------------------------------------------------------------

  /** Campaña que se está enviando o que está a punto de hacerlo. */
  estaActiva(c: Campana): boolean {
    return c.estado === 'processing' || this.estaEnCola(c);
  }

  private estaEnCola(c: Campana): boolean {
    return c.estado === 'scheduled'
      && (c.programado_en === null || new Date(c.programado_en).getTime() <= Date.now() + REFRESH_MS);
  }

  infoEstado(c: Campana): StatusInfo {
    switch (c.estado) {
      case 'processing':
        return { label: 'Enviando', badge: 'badge-blue' };
      case 'completed':
        return c.fallidos > 0
          ? { label: 'Con errores', badge: 'badge-red' }
          : { label: 'Completada', badge: 'badge-green' };
      case 'cancelled':
        return { label: 'Cancelada', badge: 'badge-gray' };
      default:
        return this.estaEnCola(c)
          ? { label: 'En cola', badge: 'badge-blue' }
          : { label: 'Programada', badge: 'badge-orange' };
    }
  }

  progreso(c: Campana): number {
    if (c.total_destinatarios === 0) {
      return 100;
    }

    return Math.round(((c.enviados + c.fallidos + c.omitidos) / c.total_destinatarios) * 100);
  }

  /** Fecha relevante: la programada si la hay; si no, la de creación. */
  fechaMostrar(c: Campana): string {
    return c.programado_en ?? c.creado_en;
  }

  etiquetaEntrega(status: EstadoEntrega): string {
    return { pending: 'Pendiente', sent: 'Enviado', failed: 'Fallido', skipped: 'Omitido' }[status];
  }

  insigniaEntrega(status: EstadoEntrega): string {
    return { pending: 'badge-blue', sent: 'badge-green', failed: 'badge-red', skipped: 'badge-gray' }[status];
  }

  // ---------------------------------------------------------------
  // Filtros y paginación
  // ---------------------------------------------------------------

  get filteredHistory(): Campana[] {
    const text = this.textFilter.trim().toLowerCase();
    const min = this.minDateFilter ? new Date(this.minDateFilter + 'T00:00:00') : null;
    const max = this.maxDateFilter ? new Date(this.maxDateFilter + 'T23:59:59') : null;

    return this.history.filter(c => {
      if (text && !`${c.nombre ?? ''} ${c.asunto}`.toLowerCase().includes(text)) {
        return false;
      }

      if (this.statusFilter && this.infoEstado(c).label !== this.statusFilter) {
        return false;
      }

      const date = new Date(this.fechaMostrar(c));

      return (!min || date >= min) && (!max || date <= max);
    });
  }

  get visibleHistory(): Campana[] {
    return this.filteredHistory.slice(0, this.visibleCount);
  }

  // ---------------------------------------------------------------
  // Entregas
  // ---------------------------------------------------------------

  alternarEntregas(campaignId: number): void {
    if (this.expandedCampaignId === campaignId) {
      this.expandedCampaignId = null;
      return;
    }

    this.expandedCampaignId = campaignId;
    this.obtenerEntregas(campaignId);
  }

  private obtenerEntregas(campaignId: number): void {
    if (!this.deliveries[campaignId]) {
      this.loadingDeliveries[campaignId] = true;
    }

    this.api.obtenerEntregasCampana(campaignId).subscribe({
      next: response => {
        this.deliveries[campaignId] = response.data ?? [];
        this.loadingDeliveries[campaignId] = false;
      },
      error: () => this.loadingDeliveries[campaignId] = false
    });
  }

  // ---------------------------------------------------------------
  // Acciones
  // ---------------------------------------------------------------

  reintentarFallidos(c: Campana): void {
    if (!confirm(`¿Reintentar el envío a los ${c.fallidos} destinatario(s) fallidos?`)) {
      return;
    }

    this.actionInProgress = c.id;

    this.api.reintentarEntregasFallidas(c.id).subscribe({
      next: response => {
        this.actionInProgress = null;
        this.toast.exito(`${response.reencolados} entrega(s) vuelven a la cola.`);
        this.despuesDeAccion(c.id);
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress = null;
        this.toast.fallo(err.error?.error ?? 'No se pudo reintentar.');
      }
    });
  }

  cancelar(c: Campana): void {
    if (!confirm('¿Cancelar esta campaña programada?')) {
      return;
    }

    this.actionInProgress = c.id;

    this.api.cancelarCampana(c.id).subscribe({
      next: () => {
        this.actionInProgress = null;
        this.toast.exito('Campaña cancelada.');
        this.despuesDeAccion(c.id);
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress = null;
        this.toast.fallo(err.error?.error ?? 'No se pudo cancelar.');
        this.cargarHistorial();
      }
    });
  }

  private despuesDeAccion(campaignId: number): void {
    delete this.deliveries[campaignId];
    this.cargarHistorial();

    if (this.expandedCampaignId === campaignId) {
      this.obtenerEntregas(campaignId);
    }
  }
}
