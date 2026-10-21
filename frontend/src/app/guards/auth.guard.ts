import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { borrarToken, tieneTokenValido } from '../utils/token';

export const guardiaAutenticado: CanActivateFn = () => {
  const router = inject(Router);

  if (tieneTokenValido()) {
    return true;
  }

  // Token ausente o caducado
  borrarToken();
  return router.createUrlTree(['/login']);
};
