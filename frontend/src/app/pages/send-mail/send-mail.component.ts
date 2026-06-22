import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { EditorModule } from '@tinymce/tinymce-angular';

@Component({
  selector: 'app-send-mail',
  standalone: true,
  imports: [FormsModule, EditorModule, CommonModule],
  templateUrl: './send-mail.component.html',
  styleUrls: ['./send-mail.component.css']
})
export class SendMailComponent {

  to = '';
  subject = '';
  body = '';

  loading = false;
  message = '';
  isError = false;

  aiTopic = '';
  aiSuggestions: string[] = [];
  aiLoading = false;
  aiError = '';

  constructor(
    private api: ApiService,
    private router: Router
  ) {}

  onSubmit(form: any): void {
    if (form.invalid) {
      this.showMessage('Todos los campos son obligatorios', true);
      return;
    }

    this.loading = true;
    this.message = '';

    this.api.sendSingleMail({ to: this.to, subject: this.subject, body: this.body })
      .subscribe({
        next: () => {
          this.loading = false;
          this.showMessage('Correo enviado correctamente ✅', false);
          form.resetForm();
          this.aiSuggestions = [];
          this.aiTopic = '';
        },
        error: (err: any) => {
          this.loading = false;

          if (err?.status === 401) {
            localStorage.removeItem('token');
            this.router.navigate(['/login']);
            return;
          }

          this.showMessage(err?.error?.error || 'Error al enviar el correo', true);
        }
      });
  }

  suggestSubjects(): void {
    if (!this.aiTopic.trim()) return;

    this.aiLoading = true;
    this.aiSuggestions = [];
    this.aiError = '';

    this.api.suggestSubjects(this.aiTopic).subscribe({
      next: (response: any) => {
        this.aiLoading = false;
        this.aiSuggestions = response.subjects ?? [];
      },
      error: (err: any) => {
        this.aiLoading = false;
        this.aiError = err?.error?.error || 'Error al conectar con la IA';
      }
    });
  }

  selectSuggestion(suggestion: string): void {
    this.subject = suggestion;
    this.aiSuggestions = [];
    this.aiTopic = '';
  }

  private showMessage(msg: string, isError: boolean): void {
    this.message = msg;
    this.isError = isError;
  }
}
