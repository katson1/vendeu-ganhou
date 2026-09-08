const tokenKey = 'vendeu-ganhou.token';

export function readToken() {
  return window.localStorage.getItem(tokenKey);
}

export function writeToken(token) {
  window.localStorage.setItem(tokenKey, token);
}

export function clearToken() {
  window.localStorage.removeItem(tokenKey);
}
