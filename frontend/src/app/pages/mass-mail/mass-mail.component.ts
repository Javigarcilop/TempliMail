import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { ApiService } from '../../services/api.service';

@Component({
  standalone: true,
  selector: 'app-mass-mail',
  templateUrl: './mass-mail.component.html',
  styleUrls: ['./mass-mail.component.css'],
  imports: [CommonModule, FormsModule, HttpClientModule]
})
export class MassMailComponent implements OnInit {
  contactos: any[] = [];
  plantillas: any[] = [];
  seleccionados: number[] = [];
  plantillaSeleccionada: number | null = null;
  fechaProgramada: string | null = null;
  mensaje: string = '';
  mensajeVisible = false;

  constructor(private api: ApiService, private http: HttpClient) { }

  ngOnInit(): void {
    this.cargarContactos();
    this.cargarPlantillas();
    this.iniciarEnvioAutomatico();
  }

  iniciarEnvioAutomatico() {
    // Ejecuta al cargar
    this.api.ejecutarCorreosProgramados().subscribe();

    // Ejecuta cada 1 minuto automáticamente
    setInterval(() => {
      this.api.ejecutarCorreosProgramados().subscribe();
    }, 60000); // 60 segundos
  }

  cargarContactos() {
    this.api.getContactos().subscribe(data => {
      this.contactos = data;
    });
  }

  cargarPlantillas() {
    this.api.getPlantillas().subscribe(data => {
      this.plantillas = data;
    });
  }

  onToggleSeleccion(event: Event, contactoId: number) {
    const input = event.target as HTMLInputElement;
    const checked = input.checked;
    this.toggleSeleccion(contactoId, checked);
  }

  toggleSeleccion(id: number, checked: boolean) {
    if (checked) {
      if (!this.seleccionados.includes(id)) {
        this.seleccionados.push(id);
      }
    } else {
      this.seleccionados = this.seleccionados.filter(c => c !== id);
    }
  }

  toggleSeleccionTodos(event: Event) {
    const input = event.target as HTMLInputElement;
    const checked = input.checked;
    this.seleccionados = checked ? this.contactos.map(c => c.id) : [];
  }

  enviarMasivo() {
    if (!this.plantillaSeleccionada || this.seleccionados.length === 0) {
      this.mostrarMensaje('❌ Debes seleccionar al menos un contacto y una plantilla');
      return;
    }

    const payload: any = {
      contactos: this.seleccionados,
      plantilla_id: this.plantillaSeleccionada
    };

    if (this.fechaProgramada) {
      const seleccion = new Date(this.fechaProgramada);
      const ahora = new Date();
      const diferencia = (seleccion.getTime() - ahora.getTime()) / 1000;

      if (diferencia < 60) {
        this.mostrarMensaje('⚠️ La hora programada debe ser al menos 1 minuto en el futuro');
        return;
      }

      payload.fecha_programada = this.fechaProgramada;
    }

    this.api.sendMassiveMail(payload).subscribe(() => {
      this.mostrarMensaje('✅ Correos enviados correctamente');
      this.seleccionados = [];
      this.plantillaSeleccionada = null;
      this.fechaProgramada = null;
    }, err => {
      console.error(err);
      this.mostrarMensaje('❌ Error al enviar correos');
    });
  }

  mostrarMensaje(msg: string) {
    this.mensaje = msg;
    this.mensajeVisible = true;
    setTimeout(() => this.mensajeVisible = false, 3000);
  }
}
