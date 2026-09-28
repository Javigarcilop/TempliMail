import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { Contact, ContactInput } from '../../models/api.models';

const EMPTY_CONTACT: ContactInput = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  company: '',
  position: ''
};

@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './contacts.component.html',
  styleUrls: ['./contacts.component.css']
})
export class ContactsComponent implements OnInit {

  contacts: Contact[] = [];
  search = '';

  newContact: ContactInput = { ...EMPTY_CONTACT };
  editingId: number | null = null;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.loadContacts();
  }

  get filteredContacts(): Contact[] {
    const term = this.search.trim().toLowerCase();

    if (!term) {
      return this.contacts;
    }

    return this.contacts.filter(c =>
      [c.first_name, c.last_name, c.email, c.company, c.position]
        .some(value => value?.toLowerCase().includes(term))
    );
  }

  loadContacts(): void {
    this.api.getContacts().subscribe({
      next: response => this.contacts = response.data ?? [],
      error: () => {
        this.contacts = [];
        this.toast.error('No se pudieron cargar los contactos.');
      }
    });
  }

  saveContact(): void {
    const request = this.editingId
      ? this.api.updateContact(this.editingId, this.newContact)
      : this.api.addContact(this.newContact);

    const wasEditing = !!this.editingId;

    request.subscribe({
      next: () => {
        this.toast.success(wasEditing ? 'Contacto actualizado.' : 'Contacto creado.');
        this.cancelEdit();
        this.loadContacts();
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

    this.editingId = contact.id;
  }

  cancelEdit(): void {
    this.newContact = { ...EMPTY_CONTACT };
    this.editingId = null;
  }

  deleteContact(contact: Contact): void {
    if (!confirm(`¿Eliminar a ${contact.email}?`)) {
      return;
    }

    this.api.deleteContact(contact.id).subscribe({
      next: () => {
        this.toast.success('Contacto eliminado.');
        this.loadContacts();
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
}
