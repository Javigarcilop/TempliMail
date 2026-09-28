import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { hasValidToken } from '../utils/token';

export const guestGuard: CanActivateFn = () => {
  const router = inject(Router);

  if (!hasValidToken()) {
    return true;
  }

  return router.createUrlTree(['/dashboard']);
};
