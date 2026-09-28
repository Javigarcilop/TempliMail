import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../services/api.service';
import { ToastService } from '../../services/toast.service';
import { setToken } from '../../utils/token';

const MIN_PASSWORD_LENGTH = 8;

@Component({
  selector: 'app-account',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './account.component.html',
  styleUrls: ['./account.component.css']
})
export class AccountComponent implements OnInit {

  username = '';
  email = '';
  savingProfile = false;

  currentPassword = '';
  newPassword = '';
  confirmPassword = '';
  savingPassword = false;
  passwordError = '';

  readonly minPasswordLength = MIN_PASSWORD_LENGTH;

  constructor(
    private api: ApiService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.api.me().subscribe(response => {
      this.username = response.data.username;
      this.email = response.data.email;
    });
  }

  saveProfile(): void {
    this.savingProfile = true;

    this.api.updateProfile(this.email.trim()).subscribe({
      next: response => {
        this.savingProfile = false;
        this.email = response.data.email;
        this.toast.success('Datos actualizados.');
      },
      error: (err: HttpErrorResponse) => {
        this.savingProfile = false;
        this.toast.error(err.error?.error ?? 'No se pudieron guardar los cambios.');
      }
    });
  }

  changePassword(): void {
    this.passwordError = '';

    if (this.newPassword.length < MIN_PASSWORD_LENGTH) {
      this.passwordError = `La nueva contraseña debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`;
      return;
    }

    if (this.newPassword !== this.confirmPassword) {
      this.passwordError = 'Las contraseñas nuevas no coinciden.';
      return;
    }

    this.savingPassword = true;

    this.api.changePassword(this.currentPassword, this.newPassword).subscribe({
      next: response => {
        this.savingPassword = false;
        // El cambio invalida el token anterior: se guarda el nuevo para seguir dentro
        setToken(response.token);
        this.currentPassword = this.newPassword = this.confirmPassword = '';
        this.toast.success('Contraseña actualizada. Se han cerrado tus otras sesiones.');
      },
      error: (err: HttpErrorResponse) => {
        this.savingPassword = false;
        this.passwordError = err.error?.error ?? 'No se pudo cambiar la contraseña.';
      }
    });
  }
}
