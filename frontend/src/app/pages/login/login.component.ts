import { Component } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule, RouterModule], // 👈 AQUÍ
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent {

  user = '';
  password = '';

  constructor(
    private api: ApiService,
    private router: Router
  ) {}

  onLogin(): void {

    if (!this.user || !this.password) {
      alert('Completa todos los campos');
      return;
    }

    this.api.login({
      user: this.user,
      password: this.password
    }).subscribe({
      next: () => {
        localStorage.setItem('loggedIn', 'true');
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        console.error('Error login:', err);
        alert('❌ Credenciales incorrectas');
      }
    });
  }
}
