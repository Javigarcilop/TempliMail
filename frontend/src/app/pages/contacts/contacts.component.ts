import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { of, switchMap } from 'rxjs';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { Contacto, ContactoInput, Grupo, ResultadoImportacion } from '../../models/api.models';
import { ParsedContacts, analizarContactosCsv, convertirACsv } from '../../utils/csv';

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
    this.cargarContactos();
    this.cargarGrupos();
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

  nombreGrupo(id: number): string {
    return this.grupos.find(g => g.id === id)?.nombre ?? '';
  }

  /** Cualquier cambio de filtro vuelve a la primera página. */
  reiniciarPagina(): void {
    this.page = 1;
  }

  irAPagina(page: number): void {
    this.page = Math.min(Math.max(1, page), this.totalPages);
  }

  // ---------------------------------------------------------------
  // Carga
  // ---------------------------------------------------------------

  cargarContactos(): void {
    this.api.obtenerContactos().subscribe({
      next: response => this.contactos = response.data ?? [],
      error: () => {
        this.contactos = [];
        this.toast.fallo('No se pudieron cargar los contactos.');
      }
    });
  }

  cargarGrupos(): void {
    this.api.obtenerGrupos().subscribe({
      next: response => this.grupos = response.data ?? [],
      error: () => this.toast.fallo('No se pudieron cargar los grupos.')
    });
  }

  private recargar(): void {
    this.cargarContactos();
    this.cargarGrupos();
  }

  // ---------------------------------------------------------------
  // Alta / edición
  // ---------------------------------------------------------------

  alternarGrupo(id: number, checked: boolean): void {
    if (checked) {
      this.selectedGroupIds.add(id);
    } else {
      this.selectedGroupIds.delete(id);
    }
  }

  guardarContacto(): void {
    const groupIds = [...this.selectedGroupIds];
    const wasEditing = this.editingId !== null;

    const save$ = this.editingId !== null
      ? this.api.actualizarContacto(this.editingId, this.newContact).pipe(
          switchMap(() => this.api.establecerGruposContacto(this.editingId as number, groupIds))
        )
      : this.api.crearContacto(this.newContact).pipe(
          switchMap(response => groupIds.length > 0
            ? this.api.establecerGruposContacto(response.id, groupIds)
            : of(response))
        );

    save$.subscribe({
      next: () => {
        this.toast.exito(wasEditing ? 'Contacto actualizado.' : 'Contacto creado.');
        this.cancelarEdicion();
        this.recargar();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.fallo(err.error?.error ?? 'No se pudo guardar el contacto.')
    });
  }

  editarContacto(contact: Contacto): void {
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

  cancelarEdicion(): void {
    this.newContact = { ...CONTACTO_VACIO };
    this.selectedGroupIds = new Set<number>();
    this.editingId = null;
  }

  eliminarContacto(contact: Contacto): void {
    if (!confirm(`¿Eliminar a ${contact.correo}?`)) {
      return;
    }

    this.api.eliminarContacto(contact.id).subscribe({
      next: () => {
        this.toast.exito('Contacto eliminado.');
        this.recargar();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.fallo(err.error?.error ?? 'No se pudo eliminar el contacto.')
    });
  }

  /** Baja / alta manual: un contacto dado de baja no recibe campañas. */
  alternarSuscripcion(contact: Contacto): void {
    const subscribe = contact.baja_en !== null;

    this.api.establecerSuscripcionContacto(contact.id, subscribe).subscribe({
      next: () => {
        this.toast.exito(subscribe ? 'Contacto reactivado.' : 'Contacto dado de baja.');
        this.cargarContactos();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.fallo(err.error?.error ?? 'No se pudo actualizar la suscripción.')
    });
  }

  // ---------------------------------------------------------------
  // Grupos
  // ---------------------------------------------------------------

  crearGrupo(): void {
    const name = this.newGroupName.trim();

    if (!name) {
      return;
    }

    this.api.crearGrupo(name).subscribe({
      next: () => {
        this.newGroupName = '';
        this.toast.exito(`Grupo "${name}" creado.`);
        this.cargarGrupos();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.fallo(err.error?.error ?? 'No se pudo crear el grupo.')
    });
  }

  renombrarGrupo(group: Grupo): void {
    const name = prompt('Nuevo nombre del grupo:', group.nombre)?.trim();

    if (!name || name === group.nombre) {
      return;
    }

    this.api.renombrarGrupo(group.id, name).subscribe({
      next: () => {
        this.toast.exito('Grupo renombrado.');
        this.cargarGrupos();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.fallo(err.error?.error ?? 'No se pudo renombrar el grupo.')
    });
  }

  eliminarGrupo(group: Grupo): void {
    if (!confirm(`¿Eliminar el grupo "${group.nombre}"? Sus contactos NO se borran.`)) {
      return;
    }

    this.api.eliminarGrupo(group.id).subscribe({
      next: () => {
        if (this.groupFilter === group.id) {
          this.groupFilter = '';
        }
        this.selectedGroupIds.delete(group.id);
        this.toast.exito('Grupo eliminado.');
        this.recargar();
      },
      error: (err: HttpErrorResponse) =>
        this.toast.fallo(err.error?.error ?? 'No se pudo eliminar el grupo.')
    });
  }

  // ---------------------------------------------------------------
  // Importar / exportar CSV
  // ---------------------------------------------------------------

  seleccionarArchivo(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // permite volver a elegir el mismo archivo

    if (!file) {
      return;
    }

    if (file.size > MAX_BYTES_IMPORT) {
      this.toast.fallo('El archivo supera los 2 MB.');
      return;
    }

    file.text().then(text => {
      const parsed = analizarContactosCsv(text);

      if (!parsed) {
        this.toast.fallo('No se encontró una columna "correo". La primera fila debe ser la cabecera.');
        return;
      }

      if (parsed.contacts.length > 2000) {
        this.toast.fallo('Máximo 2000 contactos por importación. Divide el archivo.');
        return;
      }

      this.importResult = null;
      this.importGroupId = '';
      this.importPreview = { ...parsed, fileName: file.name };
    });
  }

  cancelarImportacion(): void {
    this.importPreview = null;
    this.importResult = null;
  }

  confirmarImportacion(): void {
    if (!this.importPreview) {
      return;
    }

    this.importing = true;

    this.api.importarContactos(
      this.importPreview.contacts,
      this.importGroupId === '' ? undefined : this.importGroupId
    ).subscribe({
      next: response => {
        this.importing = false;
        this.importPreview = null;
        this.importResult = response;
        this.toast.exito(`${response.creados} contacto(s) importados.`);
        this.recargar();
      },
      error: (err: HttpErrorResponse) => {
        this.importing = false;
        this.toast.fallo(err.error?.error ?? 'No se pudo importar el archivo.');
      }
    });
  }

  exportarCsv(): void {
    const rows = this.filteredContacts.map(c => [
      c.nombre, c.apellidos, c.correo, c.telefono, c.empresa, c.cargo,
      c.ids_grupo.map(id => this.nombreGrupo(id)).filter(Boolean).join(' | '),
      c.baja_en ? 'De baja' : 'Suscrito'
    ]);

    const csv = convertirACsv([
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
