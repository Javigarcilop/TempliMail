import { Component, OnInit, signal } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { finalize } from 'rxjs';
import { ApiService } from '../../services/api.service';
import { clearToken } from '../../utils/token';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.css']
})
export class SidebarComponent implements OnInit {

  readonly username = signal('');

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {
    this.api.me().subscribe({
      next: response => this.username.set(response.data.username),
      error: () => { /* el interceptor ya gestiona la sesión caducada */ }
    });
  }

  logout(event: Event): void {
    event.preventDefault();

    // Invalida el token en el servidor; se cierra la sesión local pase lo que pase
    this.api.logout()
      .pipe(finalize(() => {
        clearToken();
        this.router.navigate(['/login']);
      }))
      .subscribe({ error: () => { /* sin conexión: basta con borrar el token local */ } });
  }
}
