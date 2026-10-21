import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { borrarToken, obtenerToken } from '../utils/token';

/**
 * - Añade el JWT a todas las peticiones a la API.
 * - Si la API responde 401 con una sesión abierta (token caducado o invalidado),
 *   cierra la sesión y lleva al login. El 401 del propio login (credenciales
 *   incorrectas) se deja pasar para que el formulario muestre el error.
 */
export const interceptorAutenticacion: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const isApi = req.url.startsWith(environment.apiUrl);
  const token = obtenerToken();

  if (isApi && token) {
    req = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      const isLogin = req.url.endsWith('/login');

      if (isApi && error.status === 401 && !isLogin && token) {
        borrarToken();
        router.navigate(['/login'], { queryParams: { expired: 1 } });
      }

      return throwError(() => error);
    })
  );
};
