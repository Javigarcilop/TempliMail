import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-history',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './historial.component.html',
  styleUrls: ['./historial.component.css']
})
export class HistorialComponent implements OnInit {

  history: any[] = [];
  showAll = false;

  subjectFilter = '';
  minDateFilter = '';
  maxDateFilter = '';

  expandedCampaignId: number | null = null;
  deliveries: { [campaignId: number]: any[] } = {};
  loadingDeliveries: { [campaignId: number]: boolean } = {};

  constructor(private api: ApiService) {}

  ngOnInit(): void {
    this.api.getHistory().subscribe((response: any) => {
      if (response?.success) {
        this.history = response.data;
      }
    });
  }

  get visibleHistory() {
    return this.showAll ? this.history : this.history.slice(0, 5);
  }

  filteredHistory() {
    return this.visibleHistory.filter((campaign: any) => {
      const subjectMatch = campaign.subject?.toLowerCase()
        .includes(this.subjectFilter.toLowerCase());

      const sentDate = campaign.sent_at ? new Date(campaign.sent_at) : null;
      const minDate = this.minDateFilter ? new Date(this.minDateFilter) : null;
      const maxDate = this.maxDateFilter ? new Date(this.maxDateFilter) : null;

      const dateMatch =
        (!minDate || (sentDate && sentDate >= minDate)) &&
        (!maxDate || (sentDate && sentDate <= maxDate));

      return subjectMatch && dateMatch;
    });
  }

  toggleDeliveries(campaignId: number): void {
    if (this.expandedCampaignId === campaignId) {
      this.expandedCampaignId = null;
      return;
    }

    this.expandedCampaignId = campaignId;

    if (this.deliveries[campaignId]) {
      return;
    }

    this.loadingDeliveries[campaignId] = true;

    this.api.getCampaignDeliveries(campaignId).subscribe({
      next: (response: any) => {
        this.deliveries[campaignId] = response.data;
        this.loadingDeliveries[campaignId] = false;
      },
      error: () => {
        this.loadingDeliveries[campaignId] = false;
      }
    });
  }
}
