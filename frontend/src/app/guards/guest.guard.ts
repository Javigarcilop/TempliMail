import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { tieneTokenValido } from '../utils/token';

export const guardiaInvitado: CanActivateFn = () => {
  const router = inject(Router);

  if (!tieneTokenValido()) {
    return true;
  }

  return router.createUrlTree(['/dashboard']);
};
