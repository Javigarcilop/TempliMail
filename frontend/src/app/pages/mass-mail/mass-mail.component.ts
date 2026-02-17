import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';

@Component({
  standalone: true,
  selector: 'app-mass-mail',
  templateUrl: './mass-mail.component.html',
  styleUrls: ['./mass-mail.component.css'],
  imports: [CommonModule, FormsModule]
})
export class MassMailComponent implements OnInit, OnDestroy {

  contacts: any[] = [];
  templates: any[] = [];
  selectedContactIds: number[] = [];
  selectedTemplateId: number | null = null;
  scheduledAt: string | null = null;

  message: string = '';
  messageVisible = false;

  private intervalId: any;

  constructor(private api: ApiService) {}

  // =====================================================
  // INIT
  // =====================================================

  ngOnInit(): void {
    this.loadContacts();
    this.loadTemplates();
    this.startAutoProcessor();
  }

  ngOnDestroy(): void {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  }

  // =====================================================
  // AUTO PROCESS SCHEDULED CAMPAIGNS
  // =====================================================

  startAutoProcessor(): void {

    // Ejecutar al cargar
    this.api.processScheduledCampaigns().subscribe();

    // Ejecutar cada 60s
    this.intervalId = setInterval(() => {
      this.api.processScheduledCampaigns().subscribe();
    }, 60000);
  }

  // =====================================================
  // LOAD DATA
  // =====================================================

  loadContacts(): void {
    this.api.getContacts().subscribe(data => {
      this.contacts = data;
    });
  }

  loadTemplates(): void {
    this.api.getTemplates().subscribe(data => {
      this.templates = data;
    });
  }

  // =====================================================
  // SELECTION HANDLING
  // =====================================================

  onToggleSelection(event: Event, contactId: number): void {
    const input = event.target as HTMLInputElement;
    this.toggleSelection(contactId, input.checked);
  }

  toggleSelection(id: number, checked: boolean): void {
    if (checked) {
      if (!this.selectedContactIds.includes(id)) {
        this.selectedContactIds.push(id);
      }
    } else {
      this.selectedContactIds =
        this.selectedContactIds.filter(c => c !== id);
    }
  }

  toggleSelectAll(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedContactIds =
      input.checked ? this.contacts.map(c => c.id) : [];
  }

  // =====================================================
  // SEND MASSIVE
  // =====================================================

  sendMassive(): void {

    if (!this.selectedTemplateId || this.selectedContactIds.length === 0) {
      this.showMessage('❌ Select at least one contact and one template');
      return;
    }

    const payload: any = {
      template_id: this.selectedTemplateId,
      contact_ids: this.selectedContactIds
    };

    if (this.scheduledAt) {

      const selectedDate = new Date(this.scheduledAt);
      const now = new Date();

      const diffSeconds =
        (selectedDate.getTime() - now.getTime()) / 1000;

      if (diffSeconds < 60) {
        this.showMessage('⚠️ Scheduled time must be at least 1 minute in the future');
        return;
      }

      payload.scheduled_at = this.scheduledAt;
    }

    this.api.sendMassiveMail(payload).subscribe({
      next: () => {
        this.showMessage('✅ Emails processed successfully');

        this.selectedContactIds = [];
        this.selectedTemplateId = null;
        this.scheduledAt = null;
      },
      error: (err) => {
        console.error(err);
        this.showMessage('❌ Error sending emails');
      }
    });
  }

  // =====================================================
  // UI MESSAGE
  // =====================================================

  showMessage(msg: string): void {
    this.message = msg;
    this.messageVisible = true;

    setTimeout(() => {
      this.messageVisible = false;
    }, 3000);
  }
}
