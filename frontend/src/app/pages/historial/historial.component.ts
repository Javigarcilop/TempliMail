import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-historial',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './historial.component.html',
  styleUrls: ['./historial.component.css']
})
export class HistorialComponent implements OnInit {
  historial: any[] = [];
  mostrarHistorial: boolean = true;
  mostrarTodo: boolean = false;

  filtroAsunto: string = '';
  filtroFechaMin: string = '';
  filtroFechaMax: string = '';

  constructor(private api: ApiService) { }

  ngOnInit(): void {
    this.api.getHistorial().subscribe((response: any) => {
      if (response.success) {
        this.historial = response.data;
      }
    });
  }

  get historialVisible() {
    return this.mostrarTodo ? this.historial : this.historial.slice(0, 5);
  }

  historialFiltrado() {
    return this.historialVisible.filter(envio => {
      const asuntoMatch = envio.asunto.toLowerCase().includes(this.filtroAsunto.toLowerCase());

      const fechaEnvio = new Date(envio.enviado_en);
      const fechaMin = this.filtroFechaMin ? new Date(this.filtroFechaMin) : null;
      const fechaMax = this.filtroFechaMax ? new Date(this.filtroFechaMax) : null;

      const fechaMatch =
        (!fechaMin || fechaEnvio >= fechaMin) &&
        (!fechaMax || fechaEnvio <= fechaMax);

      return asuntoMatch && fechaMatch;
    });
  }
}
