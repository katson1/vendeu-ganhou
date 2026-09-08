const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8080';

export class ApiError extends Error {
  constructor(status, code, message) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

async function request(path, { method = 'GET', token, body } = {}) {
  const headers = { Accept: 'application/json' };

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let response;

  try {
    response = await fetch(`${apiUrl}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError(0, 'network_error', 'Não foi possível conectar ao backend.');
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    throw new ApiError(
      response.status,
      payload?.error ?? 'request_failed',
      payload?.message ?? 'Não foi possível concluir a solicitação.',
    );
  }

  return payload;
}

export const api = {
  login(email, password) {
    return request('/auth/login', {
      method: 'POST',
      body: { email, password },
    });
  },

  currentUser(token) {
    return request('/me', { token });
  },

  products(token) {
    return request('/products', { token });
  },

  createProduct(token, product) {
    return request('/products', {
      method: 'POST',
      token,
      body: product,
    });
  },

  updateProduct(token, id, product) {
    return request(`/products/${id}`, {
      method: 'PUT',
      token,
      body: product,
    });
  },

  deactivateProduct(token, id) {
    return request(`/products/${id}`, {
      method: 'DELETE',
      token,
    });
  },

  campaigns(token) {
    return request('/campaigns', { token });
  },

  createCampaign(token, campaign) {
    return request('/campaigns', {
      method: 'POST',
      token,
      body: campaign,
    });
  },

  createSale(token, sale) {
    return request('/sales', {
      method: 'POST',
      token,
      body: sale,
    });
  },

  cancelSale(token, externalId) {
    return request(`/sales/${encodeURIComponent(externalId)}/cancel`, {
      method: 'POST',
      token,
    });
  },

  wallet(token) {
    return request('/me/wallet', { token });
  },

  health() {
    return request('/health');
  },
};
