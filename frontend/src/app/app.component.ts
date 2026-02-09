import { Component } from '@angular/core';
import { RouterOutlet, RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterModule],
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent {
  isLoggedIn(): boolean {
    return localStorage.getItem('loggedIn') === 'true';
  }

  logout() {
    localStorage.removeItem('loggedIn');
    window.location.href = '/login';
  }
}
