import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { EditorModule } from '@tinymce/tinymce-angular';


@Component({
  selector: 'app-send-mail',
  standalone: true,
  imports: [FormsModule, HttpClientModule, EditorModule],
  templateUrl: './send-mail.component.html',
  styleUrls: ['./send-mail.component.css']
})

export class SendMailComponent {
  to: string = '';
  subject: string = '';
  body: string = '';

  constructor(private api: ApiService, private router: Router) {
    console.log('✅ SendMailComponent cargado');

    // 🔐 Protección de acceso
    if (localStorage.getItem('loggedIn') !== 'true') {
      this.router.navigate(['/login']);
    }
  }

  onSubmit() {
    const emailData = {
      to: this.to,
      subject: this.subject,
      body: this.body
    };

    this.api.sendMail(emailData).subscribe({
      next: () => alert('Correo enviado correctamente ✅'),
      error: (err) => alert('❌ Error al enviar el correo: ' + err.error?.error)
    });
  }
}
