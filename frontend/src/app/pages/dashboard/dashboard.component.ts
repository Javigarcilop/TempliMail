import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { DashboardStats } from '../../models/api.models';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {

  username = '';

  stats: DashboardStats = {
    total_campaigns: 0,
    total_contacts: 0,
    total_templates: 0
  };

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

  goTo(path: string): void {
    this.router.navigate(['/' + path]);
  }
}
