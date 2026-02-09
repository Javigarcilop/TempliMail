import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [CommonModule, FormsModule, HttpClientModule],
  templateUrl: './contacts.component.html',
  styleUrls: ['./contacts.component.css']
})
export class ContactsComponent implements OnInit {
  contactos: any[] = [];

  nuevo = {
    nombre: '',
    apellidos: '',
    email: '',
    telefono: '',
    empresa: '',
    cargo: '',
    etiquetas: ''
  };

  editandoId: number | null = null;

  constructor(private api: ApiService) {}

  ngOnInit() {
    this.cargarContactos();
  }

  cargarContactos() {
    this.api.getContactos().subscribe(data => {
      this.contactos = data;
    });
  }

  agregarContacto() {
    if (this.editandoId) {
      this.api.updateContacto(this.editandoId, this.nuevo).subscribe(() => {
        this.cancelarEdicion();
        this.cargarContactos();
      });
    } else {
      this.api.addContacto(this.nuevo).subscribe(() => {
        this.cargarContactos();
        this.resetFormulario();
      });
    }
  }

  editarContacto(c: any) {
    this.nuevo = { ...c };
    this.editandoId = c.id;
  }

  cancelarEdicion() {
    this.resetFormulario();
    this.editandoId = null;
  }

  eliminarContacto(id: number) {
    if (confirm('¿Eliminar contacto?')) {
      this.api.deleteContacto(id).subscribe(() => {
        this.cargarContactos();
      });
    }
  }

  resetFormulario() {
    this.nuevo = {
      nombre: '',
      apellidos: '',
      email: '',
      telefono: '',
      empresa: '',
      cargo: '',
      etiquetas: ''
    };
  }
}
