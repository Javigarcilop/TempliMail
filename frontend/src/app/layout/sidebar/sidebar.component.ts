import { Component, OnInit, signal } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { finalize } from 'rxjs';
import { ApiService } from '../../services/api.service';
import { borrarToken } from '../../utils/token';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.css']
})
export class SidebarComponent implements OnInit {

  readonly nombreUsuario = signal('');

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {
    this.api.obtenerPerfil().subscribe({
      next: response => this.nombreUsuario.set(response.data.nombre_usuario),
      error: () => { /* el interceptor ya gestiona la sesión caducada */ }
    });
  }

  cerrarSesion(event: Event): void {
    event.preventDefault();

    // Invalida el token en el servidor; se cierra la sesión local pase lo que pase
    this.api.cerrarSesion()
      .pipe(finalize(() => {
        borrarToken();
        this.router.navigate(['/login']);
      }))
      .subscribe({ error: () => { /* sin conexión: basta con borrar el token local */ } });
  }
}
