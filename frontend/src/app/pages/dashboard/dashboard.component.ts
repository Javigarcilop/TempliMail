import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {

  // Estadísticas principales
  stats = {
    total_campaigns: 0,
    total_contacts: 0,
    total_templates: 0
  };

  // Resumen avanzado
  summary = {
    most_used_template: '',
    total_uses: 0,
    top_contact: '',
    total_received: 0
  };

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {

    // =========================
    // Stats
    // =========================
    this.api.getDashboardStats().subscribe((response: any) => {
      if (response?.success) {
        this.stats = response.data;
      }
    });

    // =========================
    // Summary
    // =========================
    this.api.getDashboardSummary().subscribe((response: any) => {

      if (response?.success) {

        const data = response.data;

        this.summary = {
          most_used_template: data?.top_template?.name || 'None',
          total_uses: data?.top_template?.total || 0,
          top_contact: data?.top_contact?.name || 'None',
          total_received: data?.top_contact?.total || 0
        };
      }
    });
  }

  goTo(path: string): void {
    this.router.navigate(['/' + path]);
  }
}
