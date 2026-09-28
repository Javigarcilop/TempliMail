import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { NgIf } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { setToken } from '../../utils/token';
import { DEMO_MODE } from '../../demo/demo-mode';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [NgIf, FormsModule, RouterModule],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent implements OnInit {

  demo = DEMO_MODE;
  username = DEMO_MODE ? 'demo' : '';
  password = DEMO_MODE ? 'demo1234' : '';

  loading = false;
  errorMessage = '';
  infoMessage = '';

  constructor(
    private api: ApiService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    if (this.route.snapshot.queryParamMap.has('expired')) {
      this.infoMessage = 'Tu sesión ha caducado. Inicia sesión de nuevo.';
    }
  }

  onLogin(): void {
    if (!this.username || !this.password) {
      this.errorMessage = 'Completa todos los campos.';
      return;
    }

    this.loading = true;
    this.errorMessage = '';
    this.infoMessage = '';

    this.api.login({ username: this.username, password: this.password }).subscribe({
      next: response => {
        this.loading = false;

        if (!response?.success || !response?.token) {
          this.errorMessage = 'Respuesta de inicio de sesión no válida.';
          return;
        }

        setToken(response.token);
        this.router.navigate(['/dashboard']);
      },
      error: (err: HttpErrorResponse) => {
        this.loading = false;

        if (err.status === 0) {
          this.errorMessage = 'No se pudo conectar con el servidor.';
        } else {
          // 401 credenciales incorrectas, 429 demasiados intentos...
          this.errorMessage = err.error?.error ?? 'No se pudo iniciar sesión.';
        }
      }
    });
  }
}
