import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { of, switchMap } from 'rxjs';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { Contact, ContactInput, Group, ImportResult } from '../../models/api.models';
import { ParsedContacts, parseContactsCsv, toCsv } from '../../utils/csv';

const EMPTY_CONTACT: ContactInput = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  company: '',
  position: ''
};

const PAGE_SIZE = 25;
const MAX_IMPORT_BYTES = 2 * 1024 * 1024;

@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './contacts.component.html',
  styleUrls: ['./contacts.component.css']
})
export class ContactsComponent implements OnInit {

  contacts: Contact[] = [];
  groups: Group[] = [];

  search = '';
  /** '' = todos, 'none' = sin grupo, o el id de un grupo */
  groupFilter: '' | 'none' | number = '';
  page = 1;
  readonly pageSize = PAGE_SIZE;

  newContact: ContactInput = { ...EMPTY_CONTACT };
  editingId: number | null = null;
  selectedGroupIds = new Set<number>();

  groupsPanelOpen = false;
  newGroupName = '';

  importPreview: (ParsedContacts & { fileName: string }) | null = null;
  importGroupId: number | '' = '';
  importing = false;
  importResult: ImportResult | null = null;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.loadContacts();
    this.loadGroups();
  }

  // ---------------------------------------------------------------
  // Datos derivados
  // ---------------------------------------------------------------

  get filteredContacts(): Contact[] {
    const term = this.search.trim().toLowerCase();

    return this.contacts.filter(c => {
      if (this.groupFilter === 'none' && c.group_ids.length > 0) return false;
      if (typeof this.groupFilter === 'number' && !c.group_ids.includes(this.groupFilter)) return false;

      return !term || [c.first_name, c.last_name, c.email, c.company, c.position]
        .some(value => value?.toLowerCase().includes(term));
    });
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.filteredContacts.length / this.pageSize));
  }

  get pagedContacts(): Contact[] {
    const start = (Math.min(this.page, this.totalPages) - 1) * this.pageSize;

    return this.filteredContacts.slice(start, start + this.pageSize);
  }

  groupName(id: number): string {
    return this.groups.find(g => g.id === id)?.name ?? '';
  }

  /** Cualquier cambio de filtro vuelve a la primera página. */
  resetPage(): void {
    this.page = 1;
  }

  goToPage(page: number): void {
    this.page = Math.min(Math.max(1, page), this.totalPages);
  }

  // ---------------------------------------------------------------
  // Carga
  // ---------------------------------------------------------------

  loadContacts(): void {
    this.api.getContacts().subscribe({
      next: response => this.contacts = response.data ?? [],
      error: () => {
        this.contacts = [];
        this.toast.error('No se pudieron cargar los contactos.');
      }
    });
  }

  loadGroups(): void {
    this.api.getGroups().subscribe({
      next: response => this.groups = response.data ?? [],
      error: () => this.toast.error('No se pudieron cargar los grupos.')
    });
  }

  private reload(): void {
    this.loadContacts();
    this.loadGroups();
  }

  // ---------------------------------------------------------------
  // Alta / edición
  // ---------------------------------------------------------------

  toggleGroup(id: number, checked: boolean): void {
    if (checked) {
      this.selectedGroupIds.add(id);
    } else {
      this.selectedGroupIds.delete(id);
    }
  }

  saveContact(): void {
    const groupIds = [...this.selectedGroupIds];
    const wasEditing = this.editingId !== null;

    const save$ = this.editingId !== null
      ? this.api.updateContact(this.editingId, this.newContact).pipe(
          switchMap(() => this.api.setContactGroups(this.editingId as number, groupIds))
        )
      : this.api.addContact(this.newContact).pipe(
          switchMap(response => groupIds.length > 0
            ? this.api.setContactGroups(response.id, groupIds)
            : of(response))
        );

    save$.subscribe({
      next: () => {
        this.toast.success(wasEditing ? 'Contacto actualizado.' : 'Contacto creado.');
        this.cancelEdit();
        this.reload();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo guardar el contacto.')
    });
  }

  editContact(contact: Contact): void {
    this.newContact = {
      first_name: contact.first_name ?? '',
      last_name: contact.last_name ?? '',
      email: contact.email ?? '',
      phone: contact.phone ?? '',
      company: contact.company ?? '',
      position: contact.position ?? ''
    };

    this.selectedGroupIds = new Set(contact.group_ids);
    this.editingId = contact.id;
    window.scrollTo?.({ top: 0 });
  }

  cancelEdit(): void {
    this.newContact = { ...EMPTY_CONTACT };
    this.selectedGroupIds = new Set<number>();
    this.editingId = null;
  }

  deleteContact(contact: Contact): void {
    if (!confirm(`¿Eliminar a ${contact.email}?`)) {
      return;
    }

    this.api.deleteContact(contact.id).subscribe({
      next: () => {
        this.toast.success('Contacto eliminado.');
        this.reload();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo eliminar el contacto.')
    });
  }

  /** Baja / alta manual: un contacto dado de baja no recibe campañas. */
  toggleSubscription(contact: Contact): void {
    const subscribe = contact.unsubscribed_at !== null;

    this.api.setContactSubscription(contact.id, subscribe).subscribe({
      next: () => {
        this.toast.success(subscribe ? 'Contacto reactivado.' : 'Contacto dado de baja.');
        this.loadContacts();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo actualizar la suscripción.')
    });
  }

  // ---------------------------------------------------------------
  // Grupos
  // ---------------------------------------------------------------

  createGroup(): void {
    const name = this.newGroupName.trim();

    if (!name) {
      return;
    }

    this.api.createGroup(name).subscribe({
      next: () => {
        this.newGroupName = '';
        this.toast.success(`Grupo "${name}" creado.`);
        this.loadGroups();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo crear el grupo.')
    });
  }

  renameGroup(group: Group): void {
    const name = prompt('Nuevo nombre del grupo:', group.name)?.trim();

    if (!name || name === group.name) {
      return;
    }

    this.api.renameGroup(group.id, name).subscribe({
      next: () => {
        this.toast.success('Grupo renombrado.');
        this.loadGroups();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo renombrar el grupo.')
    });
  }

  deleteGroup(group: Group): void {
    if (!confirm(`¿Eliminar el grupo "${group.name}"? Sus contactos NO se borran.`)) {
      return;
    }

    this.api.deleteGroup(group.id).subscribe({
      next: () => {
        if (this.groupFilter === group.id) {
          this.groupFilter = '';
        }
        this.selectedGroupIds.delete(group.id);
        this.toast.success('Grupo eliminado.');
        this.reload();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.error(err.error?.error ?? 'No se pudo eliminar el grupo.')
    });
  }

  // ---------------------------------------------------------------
  // Importar / exportar CSV
  // ---------------------------------------------------------------

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // permite volver a elegir el mismo archivo

    if (!file) {
      return;
    }

    if (file.size > MAX_IMPORT_BYTES) {
      this.toast.error('El archivo supera los 2 MB.');
      return;
    }

    file.text().then(text => {
      const parsed = parseContactsCsv(text);

      if (!parsed) {
        this.toast.error('No se encontró una columna "email". La primera fila debe ser la cabecera.');
        return;
      }

      if (parsed.contacts.length > 2000) {
        this.toast.error('Máximo 2000 contactos por importación. Divide el archivo.');
        return;
      }

      this.importResult = null;
      this.importGroupId = '';
      this.importPreview = { ...parsed, fileName: file.name };
    });
  }

  cancelImport(): void {
    this.importPreview = null;
    this.importResult = null;
  }

  confirmImport(): void {
    if (!this.importPreview) {
      return;
    }

    this.importing = true;

    this.api.importContacts(
      this.importPreview.contacts,
      this.importGroupId === '' ? undefined : this.importGroupId
    ).subscribe({
      next: response => {
        this.importing = false;
        this.importPreview = null;
        this.importResult = response;
        this.toast.success(`${response.created} contacto(s) importados.`);
        this.reload();
      },
      error: (err: HttpErrorResponse) => {
        this.importing = false;
        this.toast.error(err.error?.error ?? 'No se pudo importar el archivo.');
      }
    });
  }

  exportCsv(): void {
    const rows = this.filteredContacts.map(c => [
      c.first_name, c.last_name, c.email, c.phone, c.company, c.position,
      c.group_ids.map(id => this.groupName(id)).filter(Boolean).join(' | '),
      c.unsubscribed_at ? 'De baja' : 'Suscrito'
    ]);

    const csv = toCsv([
      ['Nombre', 'Apellidos', 'Email', 'Teléfono', 'Empresa', 'Cargo', 'Grupos', 'Estado'],
      ...rows
    ]);

    // BOM para que Excel reconozca UTF-8
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `contactos-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }
}
