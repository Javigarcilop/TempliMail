import { provideRouter } from '@angular/router';
import { routes } from './app.routes';
import { importProvidersFrom } from '@angular/core';
import { HttpClientModule, provideHttpClient, withInterceptors } from '@angular/common/http';
import { DEMO_MODE } from './demo/demo-mode';
import { demoInterceptor } from './demo/demo.interceptor';

export const appConfig = {
  providers: [
    provideRouter(routes),
    DEMO_MODE
      ? provideHttpClient(withInterceptors([demoInterceptor]))
      : importProvidersFrom(HttpClientModule)
  ],
};
