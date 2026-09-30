import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { of, switchMap } from 'rxjs';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { Contacto, ContactoInput, Grupo, ResultadoImportacion } from '../../models/api.models';
import { ParsedContacts, parseContactsCsv, toCsv } from '../../utils/csv';

const CONTACTO_VACIO: ContactoInput = {
  nombre: '',
  apellidos: '',
  correo: '',
  telefono: '',
  empresa: '',
  cargo: ''
};

const TAMANO_PAGINA = 25;
const MAX_BYTES_IMPORT = 2 * 1024 * 1024;

@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './contacts.component.html',
  styleUrls: ['./contacts.component.css']
})
export class ContactsComponent implements OnInit {

  contactos: Contacto[] = [];
  grupos: Grupo[] = [];

  search = '';
  /** '' = todos, 'none' = sin grupo, o el id de un grupo */
  groupFilter: '' | 'none' | number = '';
  page = 1;
  readonly pageSize = TAMANO_PAGINA;

  newContact: ContactoInput = { ...CONTACTO_VACIO };
  editingId: number | null = null;
  selectedGroupIds = new Set<number>();

  groupsPanelOpen = false;
  newGroupName = '';

  importPreview: (ParsedContacts & { fileName: string }) | null = null;
  importGroupId: number | '' = '';
  importing = false;
  importResult: ResultadoImportacion | null = null;

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

  get filteredContacts(): Contacto[] {
    const term = this.search.trim().toLowerCase();

    return this.contactos.filter(c => {
      if (this.groupFilter === 'none' && c.ids_grupo.length > 0) return false;
      if (typeof this.groupFilter === 'number' && !c.ids_grupo.includes(this.groupFilter)) return false;

      return !term || [c.nombre, c.apellidos, c.correo, c.empresa, c.cargo]
        .some(value => value?.toLowerCase().includes(term));
    });
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.filteredContacts.length / this.pageSize));
  }

  get pagedContacts(): Contacto[] {
    const start = (Math.min(this.page, this.totalPages) - 1) * this.pageSize;

    return this.filteredContacts.slice(start, start + this.pageSize);
  }

  groupName(id: number): string {
    return this.grupos.find(g => g.id === id)?.nombre ?? '';
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
      next: response => this.contactos = response.data ?? [],
      error: () => {
        this.contactos = [];
        this.toast.error('No se pudieron cargar los contactos.');
      }
    });
  }

  loadGroups(): void {
    this.api.getGroups().subscribe({
      next: response => this.grupos = response.data ?? [],
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

  editContact(contact: Contacto): void {
    this.newContact = {
      nombre: contact.nombre ?? '',
      apellidos: contact.apellidos ?? '',
      correo: contact.correo ?? '',
      telefono: contact.telefono ?? '',
      empresa: contact.empresa ?? '',
      cargo: contact.cargo ?? ''
    };

    this.selectedGroupIds = new Set(contact.ids_grupo);
    this.editingId = contact.id;
    window.scrollTo?.({ top: 0 });
  }

  cancelEdit(): void {
    this.newContact = { ...CONTACTO_VACIO };
    this.selectedGroupIds = new Set<number>();
    this.editingId = null;
  }

  deleteContact(contact: Contacto): void {
    if (!confirm(`¿Eliminar a ${contact.correo}?`)) {
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
  toggleSubscription(contact: Contacto): void {
    const subscribe = contact.baja_en !== null;

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

  renameGroup(group: Grupo): void {
    const name = prompt('Nuevo nombre del grupo:', group.nombre)?.trim();

    if (!name || name === group.nombre) {
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

  deleteGroup(group: Grupo): void {
    if (!confirm(`¿Eliminar el grupo "${group.nombre}"? Sus contactos NO se borran.`)) {
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

    if (file.size > MAX_BYTES_IMPORT) {
      this.toast.error('El archivo supera los 2 MB.');
      return;
    }

    file.text().then(text => {
      const parsed = parseContactsCsv(text);

      if (!parsed) {
        this.toast.error('No se encontró una columna "correo". La primera fila debe ser la cabecera.');
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
        this.toast.success(`${response.creados} contacto(s) importados.`);
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
      c.nombre, c.apellidos, c.correo, c.telefono, c.empresa, c.cargo,
      c.ids_grupo.map(id => this.groupName(id)).filter(Boolean).join(' | '),
      c.baja_en ? 'De baja' : 'Suscrito'
    ]);

    const csv = toCsv([
      ['Nombre', 'Apellidos', 'Correo', 'Teléfono', 'Empresa', 'Cargo', 'Grupos', 'Estado'],
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
