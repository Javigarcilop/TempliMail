import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    redirectTo: 'dashboard',
    pathMatch: 'full'
  },
  {
    path: 'login',
    loadComponent: () =>
      import('./pages/login/login.component').then(m => m.LoginComponent)
  },
  {
    path: 'register',
    loadComponent: () =>
      import('./pages/register/register.component').then(m => m.RegisterComponent)
  },
  {
    path: 'dashboard',
    loadComponent: () =>
      import('./pages/dashboard/dashboard.component').then(m => m.DashboardComponent)
  },
  {
    path: 'send',
    loadComponent: () =>
      import('./pages/send-mail/send-mail.component').then(m => m.SendMailComponent)
  },
  {
    path: 'contactos',
    loadComponent: () =>
      import('./pages/contacts/contacts.component').then(m => m.ContactsComponent)
  },
  {
    path: 'templates',
    loadComponent: () =>
      import('./pages/templates/templates.component').then(m => m.TemplatesComponent)
  },
  {
    path: 'mass-mail',
    loadComponent: () =>
      import('./pages/mass-mail/mass-mail.component').then(m => m.MassMailComponent)
  },
  {
    path: 'historial',
    loadComponent: () =>
      import('./pages/historial/historial.component').then(m => m.HistorialComponent)
  }
];
