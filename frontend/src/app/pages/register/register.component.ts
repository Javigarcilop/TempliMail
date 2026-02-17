import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { Router } from '@angular/router';
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
      alert(this.message = 'Completa todos los campos');
      return;
    }

    if (this.password.length < 4) {
      this.message = 'Contraseña demasiado corta';
      alert(this.message);
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
