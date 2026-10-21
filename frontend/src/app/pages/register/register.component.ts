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

  nombreUsuario = '';
  correo = '';
  contrasena = '';

  message = '';
  loading = false;
  isError = false;

  constructor(
    private api: ApiService,
    private router: Router
  ) {}

  registrarUsuario(): void {

    // =========================
    // Validaciones básicas
    // =========================

    if (!this.nombreUsuario || !this.correo || !this.contrasena) {
      this.mostrarMensaje('Todos los campos son obligatorios', true);
      return;
    }

    if (this.contrasena.length < 8) {
      this.mostrarMensaje('La contraseña debe tener al menos 8 caracteres', true);
      return;
    }

    this.loading = true;
    this.message = '';

    // =========================
    // Llamada API
    // =========================

    this.api.registrar({
      nombre_usuario: this.nombreUsuario,
      correo: this.correo,
      contrasena: this.contrasena
    }).subscribe({
      next: () => {

        this.loading = false;
        this.mostrarMensaje('Usuario creado correctamente', false);

        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 1000);

      },
      error: (err: any) => {

        this.loading = false;

        const backendMessage =
          err?.error?.error || 'Error en el registro';

        this.mostrarMensaje(backendMessage, true);
      }
    });
  }

  // =========================
  // UI Message helper
  // =========================

  private mostrarMensaje(msg: string, isError: boolean): void {
    this.message = msg;
    this.isError = isError;
  }
}
