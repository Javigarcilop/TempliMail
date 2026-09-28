import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { MailPreviewComponent } from '../../shared/mail-preview/mail-preview.component';
import { Contact, Group, MailPreview, MassiveMailInput, Template } from '../../models/api.models';

@Component({
  standalone: true,
  selector: 'app-mass-mail',
  templateUrl: './mass-mail.component.html',
  styleUrls: ['./mass-mail.component.css'],
  imports: [CommonModule, FormsModule, MailPreviewComponent]
})
export class MassMailComponent implements OnInit {

  contacts: Contact[] = [];
  templates: Template[] = [];
  groups: Group[] = [];
  /** '' = todos, o el id de un grupo */
  groupFilter: '' | number = '';

  selectedContactIds = new Set<number>();
  selectedTemplateId: number | null = null;
  campaignName = '';
  scheduledAt = '';
  search = '';

  loading = false;
  busy = false;
  preview: MailPreview | null = null;

  constructor(
    private api: ApiService,
    private toast: ToastService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.api.getContacts().subscribe({
      next: response => this.contacts = response.data ?? [],
      error: () => this.toast.error('No se pudieron cargar los contactos.')
    });

    this.api.getGroups().subscribe({
      next: response => this.groups = response.data ?? [],
      error: () => { /* el filtro por grupo es opcional */ }
    });

    this.api.getTemplates().subscribe({
      next: response => this.templates = response.data ?? [],
      error: () => this.toast.error('No se pudieron cargar las plantillas.')
    });
  }

  // ---------------------------------------------------------------
  // Datos derivados
  // ---------------------------------------------------------------

  get selectedTemplate(): Template | undefined {
    return this.templates.find(t => t.id === this.selectedTemplateId);
  }

  get filteredContacts(): Contact[] {
    const term = this.search.trim().toLowerCase();

    return this.contacts.filter(c => {
      if (typeof this.groupFilter === 'number' && !c.group_ids.includes(this.groupFilter)) {
        return false;
      }

      return !term || [c.first_name, c.last_name, c.email, c.company]
        .some(value => value?.toLowerCase().includes(term));
    });
  }

  /** Contactos visibles que pueden recibir correo (los dados de baja no). */
  private get selectableVisible(): Contact[] {
    return this.filteredContacts.filter(c => !c.unsubscribed_at);
  }

  get allVisibleSelected(): boolean {
    const visible = this.selectableVisible;

    return visible.length > 0 && visible.every(c => this.selectedContactIds.has(c.id));
  }

  get unsubscribedCount(): number {
    return this.contacts.filter(c => c.unsubscribed_at).length;
  }

  /** Mínimo permitido para programar (ahora + 1 min), en formato datetime-local. */
  get minSchedule(): string {
    const d = new Date(Date.now() + 60_000);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  // ---------------------------------------------------------------
  // Selección
  // ---------------------------------------------------------------

  toggleContact(contact: Contact, checked: boolean): void {
    if (checked) {
      this.selectedContactIds.add(contact.id);
    } else {
      this.selectedContactIds.delete(contact.id);
    }
  }

  toggleAllVisible(checked: boolean): void {
    for (const contact of this.selectableVisible) {
      this.toggleContact(contact, checked);
    }
  }

  // ---------------------------------------------------------------
  // Vista previa y prueba
  // ---------------------------------------------------------------

  showPreview(): void {
    const payload = this.previewPayload();

    if (!payload) {
      return;
    }

    this.api.previewMail(payload).subscribe({
      next: response => this.preview = response.data,
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo generar la vista previa.')
    });
  }

  sendTest(): void {
    const payload = this.previewPayload();

    if (!payload) {
      return;
    }

    this.busy = true;

    this.api.sendTestMail(payload).subscribe({
      next: response => {
        this.busy = false;
        this.toast.success(`Correo de prueba enviado a ${response.sent_to}.`);
      },
      error: (err: HttpErrorResponse) => {
        this.busy = false;
        this.toast.error(err.error?.error ?? 'No se pudo enviar la prueba.');
      }
    });
  }

  // ---------------------------------------------------------------
  // Envío
  // ---------------------------------------------------------------

  sendMassive(): void {
    const template = this.selectedTemplate;

    if (!template || this.selectedContactIds.size === 0) {
      this.toast.error('Elige una plantilla y al menos un contacto.');
      return;
    }

    const payload: MassiveMailInput = {
      template_id: template.id,
      contact_ids: [...this.selectedContactIds]
    };

    if (this.campaignName.trim()) {
      payload.name = this.campaignName.trim();
    }

    if (this.scheduledAt) {
      const date = new Date(this.scheduledAt); // hora local del navegador

      if (date.getTime() - Date.now() < 60_000) {
        this.toast.error('La hora programada debe ser al menos 1 minuto posterior a la actual.');
        return;
      }

      // Se envía en UTC: el servidor y su cola trabajan en UTC
      payload.scheduled_at = date.toISOString();
    }

    const count = this.selectedContactIds.size;
    const when = payload.scheduled_at
      ? `programar para el ${new Date(payload.scheduled_at).toLocaleString()}`
      : 'enviar ahora';

    if (!confirm(`¿Quieres ${when} "${template.name}" a ${count} contacto(s)?`)) {
      return;
    }

    this.loading = true;

    this.api.sendMassiveMail(payload).subscribe({
      next: response => {
        this.loading = false;

        const excluded = response.excluded > 0 ? ` (${response.excluded} excluido/s por baja o no válido)` : '';

        this.toast.success(
          response.scheduled
            ? `Campaña programada para ${response.recipients} contacto(s)${excluded}.`
            : `Campaña en cola: ${response.recipients} contacto(s)${excluded}. Se enviará en unos segundos.`
        );

        this.router.navigate(['/historial']);
      },
      error: (err: HttpErrorResponse) => {
        this.loading = false;
        this.toast.error(err.error?.error ?? 'Error al crear la campaña.');
      }
    });
  }

  // ---------------------------------------------------------------

  /** Datos para la vista previa / prueba: la plantilla con el primer contacto elegido. */
  private previewPayload() {
    const template = this.selectedTemplate;

    if (!template) {
      this.toast.error('Elige primero una plantilla.');
      return null;
    }

    const firstContactId = this.selectedContactIds.values().next().value;

    return {
      subject: template.subject,
      content_html: template.content_html,
      ...(firstContactId !== undefined ? { contact_id: firstContactId } : {})
    };
  }
}
