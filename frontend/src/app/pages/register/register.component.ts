import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [
    CommonModule,   
    FormsModule
  ],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterComponent {

  user = '';
  password = '';
  message = '';
  loading = false;

  constructor(
    private api: ApiService,
    private router: Router
  ) {}

  onRegister(): void {

    if (!this.user || !this.password) {
      this.message = 'Completa todos los campos';
      return;
    }

    this.loading = true;
    this.message = '';

    this.api.register({
      user: this.user,
      password: this.password
    }).subscribe({
      next: () => {
        this.loading = false;
        this.message = 'Usuario creado correctamente';

        // Redirigir al login tras 1 segundo
        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 1000);
      },
      error: (err) => {
        this.loading = false;
        this.message = err.error?.error || 'Error en el registro';
      }
    });
  }
}
