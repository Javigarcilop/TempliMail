const TOKEN_KEY = 'token';

export function obtenerToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function establecerToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function borrarToken(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem('loggedIn'); // clave antigua
}

/** Fecha de caducidad (segundos desde epoch) del JWT, o null si no se puede leer. */
export function caducidadToken(token: string): number | null {
  try {
    const payload = token.split('.')[1];
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
    const exp = JSON.parse(json).exp;

    return typeof exp === 'number' ? exp : null;
  } catch {
    return null;
  }
}

/** true si hay token y no ha caducado (margen de 10 s). */
export function tieneTokenValido(now: number = Date.now()): boolean {
  const token = obtenerToken();

  if (!token) {
    return false;
  }

  const exp = caducidadToken(token);

  return exp !== null && exp * 1000 > now + 10_000;
}
