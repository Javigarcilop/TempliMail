import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule
  ],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterComponent {

  username = '';
  email = '';
  password = '';

  message = '';
  loading = false;
  isError = false;

  constructor(
    private api: ApiService,
    private router: Router
  ) {}

  onRegister(): void {

    // =========================
    // Validaciones básicas
    // =========================

    if (!this.username || !this.email || !this.password) {
      this.showMessage('Todos los campos son obligatorios', true);
      return;
    }

    if (this.password.length < 6) {
      alert(this.showMessage('La contraseña debe tener al menos 6 caracteres', true));
      return;
    }

    this.loading = true;
    this.message = '';

    // =========================
    // Llamada API
    // =========================

    this.api.register({
      username: this.username,
      email: this.email,
      password: this.password
    }).subscribe({
      next: () => {

        this.loading = false;
        this.showMessage('Usuario creado correctamente', false);

        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 1000);

      },
      error: (err: any) => {

        this.loading = false;

        const backendMessage =
          err?.error?.error || 'Error en el registro';

        this.showMessage(backendMessage, true);
      }
    });
  }

  // =========================
  // UI Message helper
  // =========================

  private showMessage(msg: string, isError: boolean): void {
    this.message = msg;
    this.isError = isError;
  }
}
