import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { guestGuard } from './guards/guest.guard';

export const routes: Routes = [

  // Rutas públicas (redirige a dashboard si ya hay sesión)
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./pages/login/login.component')
        .then(m => m.LoginComponent)
  },
  {
    path: 'register',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./pages/register/register.component')
        .then(m => m.RegisterComponent)
  },

  // Rutas privadas (redirige a login si no hay sesión)
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./layout/layout.component')
        .then(m => m.LayoutComponent),
    children: [

      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full'
      },

      {
        path: 'dashboard',
        loadComponent: () =>
          import('./pages/dashboard/dashboard.component')
            .then(m => m.DashboardComponent)
      },

      {
        path: 'send',
        loadComponent: () =>
          import('./pages/send-mail/send-mail.component')
            .then(m => m.SendMailComponent)
      },

      {
        path: 'contactos',
        loadComponent: () =>
          import('./pages/contacts/contacts.component')
            .then(m => m.ContactsComponent)
      },

      {
        path: 'templates',
        loadComponent: () =>
          import('./pages/templates/templates.component')
            .then(m => m.TemplatesComponent)
      },

      {
        path: 'mass-mail',
        loadComponent: () =>
          import('./pages/mass-mail/mass-mail.component')
            .then(m => m.MassMailComponent)
      },

      {
        path: 'historial',
        loadComponent: () =>
          import('./pages/historial/historial.component')
            .then(m => m.HistorialComponent)
      }
    ]
  },

  {
    path: '**',
    redirectTo: 'login'
  }
];
