import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { setToken } from '../../utils/token';

const LONGITUD_MINIMA_CONTRASENA = 8;

@Component({
  selector: 'app-account',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './account.component.html',
  styleUrls: ['./account.component.css']
})
export class AccountComponent implements OnInit {

  nombreUsuario = '';
  correo = '';
  guardandoPerfil = false;

  contrasenaActual = '';
  contrasenaNueva = '';
  confirmarContrasena = '';
  guardandoContrasena = false;
  errorContrasena = '';

  readonly longitudMinimaContrasena = LONGITUD_MINIMA_CONTRASENA;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.api.me().subscribe(response => {
      this.nombreUsuario = response.data.nombre_usuario;
      this.correo = response.data.correo;
    });
  }

  saveProfile(): void {
    this.guardandoPerfil = true;

    this.api.updateProfile(this.correo.trim()).subscribe({
      next: response => {
        this.guardandoPerfil = false;
        this.correo = response.data.correo;
        this.toast.success('Datos actualizados.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoPerfil = false;
        this.toast.error(err.error?.error ?? 'No se pudieron guardar los cambios.');
      }
    });
  }

  changePassword(): void {
    this.errorContrasena = '';

    if (this.contrasenaNueva.length < LONGITUD_MINIMA_CONTRASENA) {
      this.errorContrasena = `La nueva contraseña debe tener al menos ${LONGITUD_MINIMA_CONTRASENA} caracteres.`;
      return;
    }

    if (this.contrasenaNueva !== this.confirmarContrasena) {
      this.errorContrasena = 'Las contraseñas nuevas no coinciden.';
      return;
    }

    this.guardandoContrasena = true;

    this.api.changePassword(this.contrasenaActual, this.contrasenaNueva).subscribe({
      next: response => {
        this.guardandoContrasena = false;
        // El cambio invalida el token anterior: se guarda el nuevo para seguir dentro
        setToken(response.token);
        this.contrasenaActual = this.contrasenaNueva = this.confirmarContrasena = '';
        this.toast.success('Contraseña actualizada. Se han cerrado tus otras sesiones.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoContrasena = false;
        this.errorContrasena = err.error?.error ?? 'No se pudo cambiar la contraseña.';
      }
    });
  }
}
