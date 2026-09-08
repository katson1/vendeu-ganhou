import { StrictMode, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { AuthProvider, useAuth } from './auth/AuthContext';
import { AdminPage } from './pages/AdminPage';
import { LoginPage } from './pages/LoginPage';
import { ForbiddenPage, ProtectedArea } from './pages/ProtectedArea';
import { Redirect, navigate, usePathname } from './router';
import './styles.css';

function LoadingScreen() {
  return (
    <main className="shell">
      <section className="card" aria-live="polite">
        <p className="eyebrow">Vendeu, Ganhou</p>
        <h1>Validando sessão…</h1>
        <p className="description">Aguarde enquanto confirmamos seu acesso.</p>
      </section>
    </main>
  );
}

function NotFoundPage() {
  const { user, logout } = useAuth();

  return (
    <main className="shell">
      <section className="card area-card" aria-labelledby="not-found-title">
        <p className="eyebrow">404</p>
        <h1 id="not-found-title">Página não encontrada</h1>
        <p className="description">Esta rota ainda não faz parte da etapa atual.</p>
        <div className="action-row">
          <button type="button" onClick={() => navigate(user.role === 'admin' ? '/admin' : '/seller')}>Voltar</button>
          <button className="secondary-button" type="button" onClick={() => { logout(); navigate('/login', { replace: true }); }}>Sair</button>
        </div>
      </section>
    </main>
  );
}

function ProtectedRoute({ role, children }) {
  const { status, user } = useAuth();

  if (status === 'loading') {
    return <LoadingScreen />;
  }

  if (status !== 'authenticated' || !user) {
    return <Redirect to="/login" />;
  }

  if (user.role !== role) {
    return <ForbiddenPage />;
  }

  return children;
}

function AppRouter() {
  const path = usePathname();
  const { status, user } = useAuth();

  useEffect(() => {
    if (status === 'anonymous' && path !== '/login') {
      navigate('/login', { replace: true });
    }
  }, [path, status]);

  if (status === 'loading') {
    return <LoadingScreen />;
  }

  if (path === '/login') {
    return status === 'authenticated' && user
      ? <Redirect to={user.role === 'admin' ? '/admin' : '/seller'} />
      : <LoginPage />;
  }

  if (path === '/') {
    return status === 'authenticated' && user
      ? <Redirect to={user.role === 'admin' ? '/admin' : '/seller'} />
      : <Redirect to="/login" />;
  }

  if (path === '/admin') {
    return (
      <ProtectedRoute role="admin">
        <AdminPage />
      </ProtectedRoute>
    );
  }

  if (path === '/seller') {
    return (
      <ProtectedRoute role="seller">
        <ProtectedArea
          role="seller"
          title="Área do seller"
          description="A autenticação está pronta. A carteira e o extrato serão exibidos na etapa de wallet."
        />
      </ProtectedRoute>
    );
  }

  if (status !== 'authenticated' || !user) {
    return <Redirect to="/login" />;
  }

  return <NotFoundPage />;
}

function App() {
  return (
    <AuthProvider>
      <AppRouter />
    </AuthProvider>
  );
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
