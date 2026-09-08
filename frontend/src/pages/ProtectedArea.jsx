import { useAuth } from '../auth/AuthContext';
import { navigate } from '../router';

export function ProtectedArea({ role, title, description }) {
  const { user, logout } = useAuth();

  function handleLogout() {
    logout();
    navigate('/login', { replace: true });
  }

  return (
    <main className="shell">
      <section className="card area-card" aria-labelledby="area-title">
        <div className="area-header">
          <div>
            <p className="eyebrow">{role === 'admin' ? 'Admin' : 'Seller'}</p>
            <h1 id="area-title">{title}</h1>
          </div>
          <button className="secondary-button" type="button" onClick={handleLogout}>Sair</button>
        </div>
        <p className="description">Olá, {user.name}. {description}</p>
        <p className="status" role="status">Sessão autenticada como <strong>{user.email}</strong>.</p>
      </section>
    </main>
  );
}

export function ForbiddenPage() {
  const { user, logout } = useAuth();

  function returnToOwnArea() {
    navigate(user?.role === 'admin' ? '/admin' : '/seller', { replace: true });
  }

  return (
    <main className="shell">
      <section className="card area-card" aria-labelledby="forbidden-title">
        <p className="eyebrow">403 · Sem permissão</p>
        <h1 id="forbidden-title">Acesso restrito</h1>
        <p className="description">Seu papel não permite acessar esta área.</p>
        <div className="action-row">
          <button type="button" onClick={returnToOwnArea}>Ir para minha área</button>
          <button className="secondary-button" type="button" onClick={() => { logout(); navigate('/login', { replace: true }); }}>Sair</button>
        </div>
      </section>
    </main>
  );
}
