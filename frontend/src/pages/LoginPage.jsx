import { useState } from 'react';
import { ApiError } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { navigate } from '../router';

export function LoginPage() {
  const { login, status, error } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [formError, setFormError] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setFormError(null);
    setSubmitting(true);

    try {
      const user = await login(email.trim(), password);
      navigate(user.role === 'admin' ? '/admin' : '/seller', { replace: true });
    } catch (requestError) {
      setFormError(
        requestError instanceof ApiError && requestError.status === 401
          ? 'Email ou senha inválidos.'
          : null,
      );
    } finally {
      setSubmitting(false);
    }
  }

  const message = formError ?? (status === 'anonymous' ? error : null);

  return (
    <main className="shell auth-shell">
      <section className="card auth-card" aria-labelledby="login-title">
        <p className="eyebrow">Acesso seguro</p>
        <h1 id="login-title">Entrar no Vendeu, Ganhou</h1>
        <p className="description">Use sua conta para acessar a área correspondente ao seu papel.</p>

        <form className="login-form" onSubmit={handleSubmit}>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />

          <label htmlFor="password">Senha</label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
          />

          {message && <p className="error-message" role="alert">{message}</p>}

          <button type="submit" disabled={submitting || status === 'loading'}>
            {submitting ? 'Entrando…' : 'Entrar'}
          </button>
        </form>
      </section>
    </main>
  );
}
