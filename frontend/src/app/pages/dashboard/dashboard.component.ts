import { Component, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { ActivityDay, DashboardStats } from '../../models/api.models';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [DatePipe],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {

  username = '';

  stats: DashboardStats = {
    total_campaigns: 0,
    total_contacts: 0,
    total_templates: 0,
    total_sent: 0,
    total_failed: 0
  };

  activity: ActivityDay[] = [];

  summary = {
    most_used_template: 'Ninguna',
    total_uses: 0,
    top_contact: 'Ninguno',
    total_received: 0
  };

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {
    this.api.me().subscribe(response => this.username = response.data.username);

    this.api.getDashboardStats().subscribe(response => {
      if (response?.success) {
        this.stats = response.data;
      }
    });

    this.api.getDashboardActivity().subscribe(response => {
      if (response?.success) {
        this.activity = response.data;
      }
    });

    this.api.getDashboardSummary().subscribe(response => {
      if (response?.success) {
        const data = response.data;

        this.summary = {
          most_used_template: data.top_template?.name || 'Ninguna',
          total_uses: data.top_template?.total || 0,
          top_contact: data.top_contact?.name?.trim() || 'Ninguno',
          total_received: data.top_contact?.total || 0
        };
      }
    });
  }

  /** Porcentaje de correos entregados sobre los intentados (null si aún no hay envíos). */
  get deliveryRate(): number | null {
    const attempted = this.stats.total_sent + this.stats.total_failed;

    return attempted === 0 ? null : Math.round((this.stats.total_sent / attempted) * 100);
  }

  get maxDaily(): number {
    return Math.max(1, ...this.activity.map(day => day.sent));
  }

  get totalLast14Days(): number {
    return this.activity.reduce((sum, day) => sum + day.sent, 0);
  }

  /** Altura de la barra (%), con un mínimo visible para los días con envíos. */
  barHeight(day: ActivityDay): number {
    return day.sent === 0 ? 0 : Math.max(4, Math.round((day.sent / this.maxDaily) * 100));
  }

  /** 'YYYY-MM-DD' -> Date en UTC, para que el pipe no lo desplace de día. */
  asDate(day: ActivityDay): Date {
    return new Date(day.date + 'T12:00:00Z');
  }

  goTo(path: string): void {
    this.router.navigate(['/' + path]);
  }
}
