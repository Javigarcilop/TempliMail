import { Component, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { DiaActividad, EstadisticasPanel } from '../../models/api.models';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [DatePipe],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {

  nombreUsuario = '';

  stats: EstadisticasPanel = {
    total_campanas: 0,
    total_contactos: 0,
    total_plantillas: 0,
    total_enviados: 0,
    total_fallidos: 0
  };

  actividad: DiaActividad[] = [];

  resumen = {
    plantillaMasUsada: 'Ninguna',
    totalUsos: 0,
    contactoTop: 'Ninguno',
    totalRecibidos: 0
  };

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {
    this.api.me().subscribe(response => this.nombreUsuario = response.data.nombre_usuario);

    this.api.getDashboardStats().subscribe(response => {
      if (response?.success) {
        this.stats = response.data;
      }
    });

    this.api.getDashboardActivity().subscribe(response => {
      if (response?.success) {
        this.actividad = response.data;
      }
    });

    this.api.getDashboardSummary().subscribe(response => {
      if (response?.success) {
        const data = response.data;

        this.resumen = {
          plantillaMasUsada: data.plantilla_top?.nombre || 'Ninguna',
          totalUsos: data.plantilla_top?.total || 0,
          contactoTop: data.contacto_top?.nombre?.trim() || 'Ninguno',
          totalRecibidos: data.contacto_top?.total || 0
        };
      }
    });
  }

  /** Porcentaje de correos entregados sobre los intentados (null si aún no hay envíos). */
  get deliveryRate(): number | null {
    const attempted = this.stats.total_enviados + this.stats.total_fallidos;

    return attempted === 0 ? null : Math.round((this.stats.total_enviados / attempted) * 100);
  }

  get maxDaily(): number {
    return Math.max(1, ...this.actividad.map(dia => dia.enviados));
  }

  get totalLast14Days(): number {
    return this.actividad.reduce((sum, dia) => sum + dia.enviados, 0);
  }

  /** Altura de la barra (%), con un mínimo visible para los días con envíos. */
  barHeight(dia: DiaActividad): number {
    return dia.enviados === 0 ? 0 : Math.max(4, Math.round((dia.enviados / this.maxDaily) * 100));
  }

  /** 'YYYY-MM-DD' -> Date en UTC, para que el pipe no lo desplace de día. */
  asDate(dia: DiaActividad): Date {
    return new Date(dia.fecha + 'T12:00:00Z');
  }

  goTo(path: string): void {
    this.router.navigate(['/' + path]);
  }
}
