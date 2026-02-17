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

  showHistory = true;
  showAll = false;

  subjectFilter = '';
  minDateFilter = '';
  maxDateFilter = '';

  constructor(private api: ApiService) {}

  ngOnInit(): void {

    this.api.getHistory().subscribe((response: any) => {

      if (response?.success) {
        this.history = response.data;
      }

    });
  }

  // Mostrar solo 5 o todos
  get visibleHistory() {
    return this.showAll
      ? this.history
      : this.history.slice(0, 5);
  }

  // Filtro dinámico
  filteredHistory() {

    return this.visibleHistory.filter((campaign: any) => {

      const subjectMatch =
        campaign.subject?.toLowerCase()
          .includes(this.subjectFilter.toLowerCase());

      const sentDate = campaign.sent_at
        ? new Date(campaign.sent_at)
        : null;

      const minDate =
        this.minDateFilter
          ? new Date(this.minDateFilter)
          : null;

      const maxDate =
        this.maxDateFilter
          ? new Date(this.maxDateFilter)
          : null;

      const dateMatch =
        (!minDate || (sentDate && sentDate >= minDate)) &&
        (!maxDate || (sentDate && sentDate <= maxDate));

      return subjectMatch && dateMatch;
    });
  }
}
