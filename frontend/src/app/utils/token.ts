const TOKEN_KEY = 'token';

export function getToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem('loggedIn'); // clave antigua
}

/** Fecha de caducidad (segundos desde epoch) del JWT, o null si no se puede leer. */
export function tokenExpiration(token: string): number | null {
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
export function hasValidToken(now: number = Date.now()): boolean {
  const token = getToken();

  if (!token) {
    return false;
  }

  const exp = tokenExpiration(token);

  return exp !== null && exp * 1000 > now + 10_000;
}
