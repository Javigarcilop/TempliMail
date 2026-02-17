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

  // Estructura alineada con backend nuevo
  newContact = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    position: ''
  };

  editingId: number | null = null;

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.loadContacts();
  }

  // =========================
  // Cargar contactos
  // =========================
  loadContacts(): void {
    this.api.getContacts().subscribe((data: any[]) => {
      this.contacts = data;
    });
  }

  // =========================
  // Crear o actualizar
  // =========================
  saveContact(): void {

    if (this.editingId) {

      this.api.updateContact(this.editingId, this.newContact)
        .subscribe(() => {
          this.cancelEdit();
          this.loadContacts();
        });

    } else {

      this.api.addContact(this.newContact)
        .subscribe(() => {
          this.loadContacts();
          this.resetForm();
        });
    }
  }

  // =========================
  // Editar
  // =========================
  editContact(contact: any): void {
    this.newContact = { ...contact };
    this.editingId = contact.id;
  }

  cancelEdit(): void {
    this.resetForm();
    this.editingId = null;
  }

  // =========================
  // Eliminar
  // =========================
  deleteContact(id: number): void {
    if (confirm('¿Eliminar contacto?')) {
      this.api.deleteContact(id).subscribe(() => {
        this.loadContacts();
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
