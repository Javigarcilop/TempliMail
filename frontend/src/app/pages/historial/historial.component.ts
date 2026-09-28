import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { Campaign, Delivery, DeliveryStatus } from '../../models/api.models';

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

  history: Campaign[] = [];
  loaded = false;
  visibleCount = PAGE_SIZE;

  textFilter = '';
  statusFilter = '';
  minDateFilter = '';
  maxDateFilter = '';

  expandedCampaignId: number | null = null;
  deliveries: Partial<Record<number, Delivery[]>> = {};
  loadingDeliveries: Partial<Record<number, boolean>> = {};
  actionInProgress: number | null = null;

  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.loadHistory();

    // Mientras haya campañas enviándose o en cola, la lista se actualiza sola
    this.refreshTimer = setInterval(() => {
      if (this.history.some(c => this.isActive(c))) {
        this.loadHistory();
      }
    }, REFRESH_MS);
  }

  ngOnDestroy(): void {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
    }
  }

  loadHistory(): void {
    this.api.getHistory().subscribe({
      next: response => {
        this.history = response.data ?? [];
        this.loaded = true;

        // Mantiene al día las entregas de la campaña desplegada
        if (this.expandedCampaignId !== null) {
          const expanded = this.history.find(c => c.id === this.expandedCampaignId);

          if (expanded && this.isActive(expanded)) {
            this.fetchDeliveries(expanded.id);
          }
        }
      },
      error: () => {
        if (!this.loaded) {
          this.toast.error('No se pudo cargar el historial.');
        }
        this.loaded = true;
      }
    });
  }

  // ---------------------------------------------------------------
  // Estado y progreso
  // ---------------------------------------------------------------

  /** Campaña que se está enviando o que está a punto de hacerlo. */
  isActive(c: Campaign): boolean {
    return c.status === 'processing' || this.isQueued(c);
  }

  private isQueued(c: Campaign): boolean {
    return c.status === 'scheduled'
      && (c.scheduled_at === null || new Date(c.scheduled_at).getTime() <= Date.now() + REFRESH_MS);
  }

  statusInfo(c: Campaign): StatusInfo {
    switch (c.status) {
      case 'processing':
        return { label: 'Enviando', badge: 'badge-blue' };
      case 'completed':
        return c.failed > 0
          ? { label: 'Con errores', badge: 'badge-red' }
          : { label: 'Completada', badge: 'badge-green' };
      case 'cancelled':
        return { label: 'Cancelada', badge: 'badge-gray' };
      default:
        return this.isQueued(c)
          ? { label: 'En cola', badge: 'badge-blue' }
          : { label: 'Programada', badge: 'badge-orange' };
    }
  }

  progress(c: Campaign): number {
    if (c.total_recipients === 0) {
      return 100;
    }

    return Math.round(((c.sent + c.failed + c.skipped) / c.total_recipients) * 100);
  }

  /** Fecha relevante: la programada si la hay; si no, la de creación. */
  displayDate(c: Campaign): string {
    return c.scheduled_at ?? c.created_at;
  }

  deliveryLabel(status: DeliveryStatus): string {
    return { pending: 'Pendiente', sent: 'Enviado', failed: 'Fallido', skipped: 'Omitido' }[status];
  }

  deliveryBadge(status: DeliveryStatus): string {
    return { pending: 'badge-blue', sent: 'badge-green', failed: 'badge-red', skipped: 'badge-gray' }[status];
  }

  // ---------------------------------------------------------------
  // Filtros y paginación
  // ---------------------------------------------------------------

  get filteredHistory(): Campaign[] {
    const text = this.textFilter.trim().toLowerCase();
    const min = this.minDateFilter ? new Date(this.minDateFilter + 'T00:00:00') : null;
    const max = this.maxDateFilter ? new Date(this.maxDateFilter + 'T23:59:59') : null;

    return this.history.filter(c => {
      if (text && !`${c.name ?? ''} ${c.subject}`.toLowerCase().includes(text)) {
        return false;
      }

      if (this.statusFilter && this.statusInfo(c).label !== this.statusFilter) {
        return false;
      }

      const date = new Date(this.displayDate(c));

      return (!min || date >= min) && (!max || date <= max);
    });
  }

  get visibleHistory(): Campaign[] {
    return this.filteredHistory.slice(0, this.visibleCount);
  }

  // ---------------------------------------------------------------
  // Entregas
  // ---------------------------------------------------------------

  toggleDeliveries(campaignId: number): void {
    if (this.expandedCampaignId === campaignId) {
      this.expandedCampaignId = null;
      return;
    }

    this.expandedCampaignId = campaignId;
    this.fetchDeliveries(campaignId);
  }

  private fetchDeliveries(campaignId: number): void {
    if (!this.deliveries[campaignId]) {
      this.loadingDeliveries[campaignId] = true;
    }

    this.api.getCampaignDeliveries(campaignId).subscribe({
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

  retryFailed(c: Campaign): void {
    if (!confirm(`¿Reintentar el envío a los ${c.failed} destinatario(s) fallidos?`)) {
      return;
    }

    this.actionInProgress = c.id;

    this.api.retryFailedDeliveries(c.id).subscribe({
      next: response => {
        this.actionInProgress = null;
        this.toast.success(`${response.requeued} entrega(s) vuelven a la cola.`);
        this.afterAction(c.id);
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress = null;
        this.toast.error(err.error?.error ?? 'No se pudo reintentar.');
      }
    });
  }

  cancel(c: Campaign): void {
    if (!confirm('¿Cancelar esta campaña programada?')) {
      return;
    }

    this.actionInProgress = c.id;

    this.api.cancelCampaign(c.id).subscribe({
      next: () => {
        this.actionInProgress = null;
        this.toast.success('Campaña cancelada.');
        this.afterAction(c.id);
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress = null;
        this.toast.error(err.error?.error ?? 'No se pudo cancelar.');
        this.loadHistory();
      }
    });
  }

  private afterAction(campaignId: number): void {
    delete this.deliveries[campaignId];
    this.loadHistory();

    if (this.expandedCampaignId === campaignId) {
      this.fetchDeliveries(campaignId);
    }
  }
}
