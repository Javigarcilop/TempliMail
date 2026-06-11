import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './contacts.component.html',
  styleUrls: ['./contacts.component.css']
})
export class ContactsComponent implements OnInit {

  contacts: any[] = [];

  newContact = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    position: ''
  };

  editingId: number | null = null;
  errorMessage = '';
  successMessage = '';

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.loadContacts();
  }

  loadContacts(): void {
    this.api.getContacts().subscribe({
      next: (response: any) => {
        this.contacts = response.data ?? [];
      },
      error: (error) => {
        console.error('Error cargando contactos:', error);
        this.contacts = [];
        this.errorMessage = 'No se pudieron cargar los contactos.';
      }
    });
  }

  saveContact(): void {
    this.errorMessage = '';
    this.successMessage = '';

    if (this.editingId) {
      this.api.updateContact(this.editingId, this.newContact).subscribe({
        next: () => {
          this.successMessage = 'Contacto actualizado correctamente.';
          this.cancelEdit();
          this.loadContacts();
        },
        error: (error) => {
          console.error('Error actualizando contacto:', error);
          this.errorMessage = error.error?.error ?? 'Error al actualizar el contacto.';
        }
      });

    } else {
      this.api.addContact(this.newContact).subscribe({
        next: () => {
          this.successMessage = 'Contacto creado correctamente.';
          this.loadContacts();
          this.resetForm();
        },
        error: (error) => {
          console.error('Error creando contacto:', error);
          this.errorMessage = error.error?.error ?? 'Error al crear el contacto.';
        }
      });
    }
  }

  editContact(contact: any): void {
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
    this.resetForm();
    this.editingId = null;
  }

  deleteContact(id: number): void {
    if (confirm('¿Eliminar contacto?')) {
      this.api.deleteContact(id).subscribe({
        next: () => {
          this.successMessage = 'Contacto eliminado correctamente.';
          this.loadContacts();
        },
        error: (error) => {
          console.error('Error eliminando contacto:', error);
          this.errorMessage = error.error?.error ?? 'Error al eliminar el contacto.';
        }
      });
    }
  }

  resetForm(): void {
    this.newContact = {
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      company: '',
      position: ''
    };
  }
}