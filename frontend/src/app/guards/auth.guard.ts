import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { clearToken, hasValidToken } from '../utils/token';

export const authGuard: CanActivateFn = () => {
  const router = inject(Router);

  if (hasValidToken()) {
    return true;
  }

  // Token ausente o caducado
  clearToken();
  return router.createUrlTree(['/login']);
};
