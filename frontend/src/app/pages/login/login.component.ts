import { Component } from '@angular/core';
import { Router, RouterModule } from '@angular/router'
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule, RouterModule, CommonModule], 
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent {

  username = '';
  password = '';

  constructor(
    private api: ApiService,
    private router: Router
  ) {}

  onLogin(): void {

    if (!this.username || !this.password) {
      alert('Completa todos los campos');
      return;
    }

    this.api.login({
      username: this.username,
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
