import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { EditorModule } from '@tinymce/tinymce-angular';

@Component({
  selector: 'app-send-mail',
  standalone: true,
  imports: [FormsModule, EditorModule,  CommonModule,],
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

  constructor(
    private api: ApiService,
    private router: Router
  ) {
    if (!localStorage.getItem('token')) {
      this.router.navigate(['/login']);
    }
  }

  onSubmit(form: any): void {

    if (form.invalid) {
      this.showMessage('Todos los campos son obligatorios', true);
      return;
    }

    this.loading = true;
    this.message = '';

    const payload = {
      to: this.to,
      subject: this.subject,
      body: this.body
    };

    this.api.sendSingleMail(payload).subscribe({
      next: () => {
        this.loading = false;
        this.showMessage('Correo enviado correctamente ✅', false);

        form.resetForm();
      },
      error: (err: any) => {
        this.loading = false;

        if (err?.status === 401) {
          localStorage.removeItem('token');
          this.showMessage('Sesión expirada. Vuelve a iniciar sesión.', true);
          this.router.navigate(['/login']);
          return;
        }

        const backendMessage =
          err?.error?.error || 'Error al enviar el correo';

        this.showMessage(backendMessage, true);
      }
    });
  }

  private showMessage(msg: string, isError: boolean): void {
    this.message = msg;
    this.isError = isError;
  }
}
