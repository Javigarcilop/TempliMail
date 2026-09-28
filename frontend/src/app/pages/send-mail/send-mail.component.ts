import { Component } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { EditorModule } from '@tinymce/tinymce-angular';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-send-mail',
  standalone: true,
  imports: [FormsModule, EditorModule, CommonModule],
  templateUrl: './send-mail.component.html',
  styleUrls: ['./send-mail.component.css']
})
export class SendMailComponent {

  readonly tinymceApiKey = environment.tinymceApiKey;

  to = '';
  subject = '';
  body = '';

  loading = false;

  aiTopic = '';
  aiSuggestions: string[] = [];
  aiLoading = false;
  aiError = '';

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  onSubmit(form: NgForm): void {
    if (form.invalid || !this.body.trim()) {
      this.toast.error('Destinatario, asunto y mensaje son obligatorios.');
      return;
    }

    this.loading = true;

    this.api.sendSingleMail({ to: this.to, subject: this.subject, body: this.body })
      .subscribe({
        next: () => {
          this.loading = false;
          this.toast.success('Correo enviado correctamente.');
          form.resetForm();
          this.body = '';
          this.aiSuggestions = [];
          this.aiTopic = '';
        },
        error: (err: HttpErrorResponse) => {
          this.loading = false;
          // La sesión caducada (401) la gestiona el interceptor
          if (err.status !== 401) {
            this.toast.error(err.error?.error ?? 'Error al enviar el correo.');
          }
        }
      });
  }

  suggestSubjects(): void {
    if (!this.aiTopic.trim()) return;

    this.aiLoading = true;
    this.aiSuggestions = [];
    this.aiError = '';

    this.api.suggestSubjects(this.aiTopic).subscribe({
      next: response => {
        this.aiLoading = false;
        this.aiSuggestions = response.subjects ?? [];
      },
      error: (err: HttpErrorResponse) => {
        this.aiLoading = false;
        this.aiError = err.error?.error ?? 'Error al conectar con la IA.';
      }
    });
  }

  selectSuggestion(suggestion: string): void {
    this.subject = suggestion;
    this.aiSuggestions = [];
    this.aiTopic = '';
  }
}
