import { ApplicationConfig } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { routes } from './app.routes';
import { authInterceptor } from './interceptors/auth.interceptor';
import { DEMO_MODE } from './demo/demo-mode';
import { demoInterceptor } from './demo/demo.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(routes),
    // En la demo publicada (GitHub Pages) un backend simulado en memoria sustituye a la API
    provideHttpClient(withInterceptors(DEMO_MODE ? [demoInterceptor] : [authInterceptor]))
  ],
};
