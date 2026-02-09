import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {

  stats = {
    envios: 0,
    contactos: 0,
    plantillas: 0
  };

  resumen = {
    plantilla_mas_usada: '',
    total_usos: 0,
    contacto_destacado: '',
    total_recibidos: 0
  };

  constructor(private api: ApiService, private router: Router) { }

  ngOnInit(): void {
    this.api.getDashboardStats().subscribe(response => {
      if (response.success) {
        this.stats = response.data;
      }
    });


    this.api.getResumenDashboard().subscribe(response => {
      if (response.success) {
        const r = response.resumen;
        this.resumen = {
          plantilla_mas_usada: r.plantilla_top?.nombre || 'Ninguna',
          total_usos: r.plantilla_top?.total || 0,
          contacto_destacado: r.top_contacto?.nombre || 'Ninguno',
          total_recibidos: r.top_contacto?.enviados || 0
        };
      }
    });
  }

  goTo(path: string) {
    this.router.navigate(['/' + path]);
  }


}
