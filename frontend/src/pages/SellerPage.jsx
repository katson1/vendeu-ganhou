import { useCallback, useEffect, useState } from 'react';
import { ApiError, api } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { navigate } from '../router';

function formatPoints(value) {
  return new Intl.NumberFormat('pt-BR').format(Number(value) || 0);
}

function formatDateTime(value) {
  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function requestErrorMessage(error) {
  if (error instanceof ApiError) {
    return error.message;
  }

  return error instanceof Error ? error.message : 'Não foi possível carregar sua carteira.';
}

export function SellerPage() {
  const { token, user, logout, handleApiError } = useAuth();
  const [balance, setBalance] = useState(0);
  const [entries, setEntries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const loadWallet = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await api.wallet(token);
      setBalance(response.balance ?? 0);
      setEntries(response.entries ?? []);
    } catch (requestError) {
      const handledError = handleApiError(requestError);
      setError(requestErrorMessage(handledError));
    } finally {
      setLoading(false);
    }
  }, [handleApiError, token]);

  useEffect(() => {
    if (token) {
      loadWallet();
    }
  }, [loadWallet, token]);

  function handleLogout() {
    logout();
    navigate('/login', { replace: true });
  }

  return (
    <main className="seller-shell">
      <header className="seller-header">
        <div>
          <p className="eyebrow">Área do seller</p>
          <h1>Minha carteira</h1>
          <p className="description">Acompanhe seus pontos e o histórico de movimentações.</p>
        </div>
        <div className="admin-user">
          <span>{user?.name}</span>
          <button className="secondary-button" type="button" onClick={handleLogout}>Sair</button>
        </div>
      </header>

      {loading ? (
        <section className="panel loading-panel" aria-live="polite">Carregando sua carteira…</section>
      ) : error ? (
        <section className="panel wallet-error" aria-labelledby="wallet-error-title">
          <p className="eyebrow">Carteira indisponível</p>
          <h2 id="wallet-error-title">Não foi possível carregar seus dados</h2>
          <p className="description">{error}</p>
          <button type="button" onClick={loadWallet}>Tentar novamente</button>
        </section>
      ) : (
        <>
          <section className="balance-card" aria-labelledby="balance-title">
            <div>
              <p className="eyebrow">Saldo atual</p>
              <h2 id="balance-title">{formatPoints(balance)} <span>pontos</span></h2>
            </div>
            <p className="balance-note">Seu saldo é calculado a partir do ledger.</p>
          </section>

          <section className="panel ledger-panel" aria-labelledby="ledger-title">
            <div className="section-heading">
              <div><p className="eyebrow">Movimentações</p><h2 id="ledger-title">Extrato</h2></div>
              <span className="section-count">{entries.length} lançamentos</span>
            </div>

            {entries.length === 0 ? (
              <div className="wallet-empty">
                <p className="empty-state">Ainda não existem movimentações na sua carteira.</p>
                <p className="description">Quando uma venda gerar pontos, o crédito aparecerá aqui.</p>
              </div>
            ) : (
              <div className="table-wrap">
                <table>
                  <caption className="sr-only">Lançamentos da carteira</caption>
                  <thead><tr><th>Tipo</th><th>Pontos</th><th>Descrição</th><th>Data</th></tr></thead>
                  <tbody>
                    {entries.map((entry) => {
                      const isCredit = entry.type === 'credit';

                      return (
                        <tr key={entry.id}>
                          <td><span className={`ledger-type ${isCredit ? 'ledger-credit' : 'ledger-debit'}`}>{isCredit ? 'Crédito' : 'Débito'}</span></td>
                          <td><strong className={isCredit ? 'points-credit' : 'points-debit'}>{isCredit ? '+' : '-'}{formatPoints(entry.points)}</strong></td>
                          <td>{entry.description}</td>
                          <td>{formatDateTime(entry.created_at)}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </main>
  );
}
