import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, ApiError } from '../api/client';
import { clearToken, readToken, writeToken } from './storage';

const AuthContext = createContext(null);

function friendlyError(error) {
  if (error instanceof ApiError && error.status === 401) {
    return 'Sua sessão expirou ou as credenciais são inválidas.';
  }

  if (error instanceof ApiError && error.status === 403) {
    return 'Você não tem permissão para acessar este recurso.';
  }

  return error instanceof Error ? error.message : 'Não foi possível concluir a operação.';
}

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => readToken());
  const [user, setUser] = useState(null);
  const [status, setStatus] = useState(() => (readToken() ? 'loading' : 'anonymous'));
  const [error, setError] = useState(null);

  const logout = useCallback(() => {
    clearToken();
    setToken(null);
    setUser(null);
    setError(null);
    setStatus('anonymous');
  }, []);

  const handleApiError = useCallback(
    (requestError) => {
      if (requestError instanceof ApiError && requestError.status === 401) {
        logout();
      }

      setError(friendlyError(requestError));
      return requestError;
    },
    [logout],
  );

  const establishSession = useCallback(async (nextToken) => {
    setStatus('loading');
    setError(null);

    try {
      const response = await api.currentUser(nextToken);
      writeToken(nextToken);
      setToken(nextToken);
      setUser(response.user);
      setStatus('authenticated');
      return response.user;
    } catch (requestError) {
      clearToken();
      setToken(null);
      setUser(null);
      setStatus('anonymous');
      throw requestError;
    }
  }, []);

  const login = useCallback(
    async (email, password) => {
      setStatus('loading');
      setError(null);

      try {
        const response = await api.login(email, password);
        return await establishSession(response.token);
      } catch (requestError) {
        setStatus('anonymous');
        setError(friendlyError(requestError));
        throw requestError;
      }
    },
    [establishSession],
  );

  useEffect(() => {
    const storedToken = readToken();

    if (!storedToken) {
      return;
    }

    establishSession(storedToken).catch((requestError) => {
      if (requestError instanceof ApiError && requestError.status !== 401) {
        setError(friendlyError(requestError));
      }
    });
  }, [establishSession]);

  const value = useMemo(
    () => ({
      token,
      user,
      status,
      error,
      login,
      logout,
      handleApiError,
    }),
    [error, handleApiError, login, logout, status, token, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider.');
  }

  return context;
}
