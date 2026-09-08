import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './styles.css';

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8080';

function App() {
  const [backendStatus, setBackendStatus] = useState('checking');

  useEffect(() => {
    fetch(`${apiUrl}/health`)
      .then((response) => {
        if (!response.ok) throw new Error('health check failed');
        return response.json();
      })
      .then(() => setBackendStatus('online'))
      .catch(() => setBackendStatus('offline'));
  }, []);

  return (
    <main className="shell">
      <section className="card" aria-labelledby="title">
        <p className="eyebrow">Bootstrap concluído</p>
        <h1 id="title">Vendeu, Ganhou</h1>
        <p className="description">
          Plataforma enxuta de incentivo de vendas.
        </p>
        <p className="status" role="status">
          Backend: <strong>{backendStatus}</strong>
        </p>
      </section>
    </main>
  );
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
