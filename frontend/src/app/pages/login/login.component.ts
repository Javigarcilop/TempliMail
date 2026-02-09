import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule, HttpClientModule],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent {
  user = '';
  password = '';

  constructor(private http: HttpClient, private router: Router) { }

  onLogin() {
    const data = { user: this.user, password: this.password };

    this.http.post('http://localhost/TempliMail/backend/api/index.php/login', data).subscribe({
      next: () => {
        localStorage.setItem('loggedIn', 'true');
        this.router.navigate(['/dashboard']);
      },
      error: () => {
        alert('❌ Credenciales incorrectas');
      }
    });
  }
}
