import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiError, api } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { navigate } from '../router';

const emptyProduct = {
  name: '',
  sku: '',
  points_per_unit: '10',
  active: true,
};

const emptySale = {
  external_id: '',
  campaign_id: '',
  seller_id: '',
  product_id: '',
  quantity: '1',
  unit_value: '1.00',
};

function localDateTime(daysFromNow = 0) {
  const date = new Date();
  date.setDate(date.getDate() + daysFromNow);

  const pad = (value) => String(value).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function emptyCampaign() {
  return {
    name: '',
    budget_total: '1000',
    starts_at: localDateTime(),
    ends_at: localDateTime(30),
    status: 'active',
  };
}

function toApiDateTime(value) {
  return value ? `${value.replace('T', ' ')}:00` : value;
}

function isActiveProduct(product) {
  return product.active === true || Number(product.active) === 1;
}

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

function campaignProgress(campaign) {
  const total = Number(campaign.budget_total) || 0;
  const used = Number(campaign.budget_used) || 0;

  return total > 0 ? Math.min(100, Math.max(0, (used / total) * 100)) : 0;
}

function requestErrorMessage(error) {
  if (error instanceof ApiError) {
    return error.message;
  }

  return error instanceof Error ? error.message : 'Não foi possível concluir a operação.';
}

function replaceById(items, item) {
  return items.some((current) => current.id === item.id)
    ? items.map((current) => (current.id === item.id ? item : current))
    : [item, ...items];
}

export function AdminPage() {
  const { token, user, logout, handleApiError } = useAuth();
  const [products, setProducts] = useState([]);
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [productForm, setProductForm] = useState(emptyProduct);
  const [editingProductId, setEditingProductId] = useState(null);
  const [campaignForm, setCampaignForm] = useState(emptyCampaign);
  const [saleForm, setSaleForm] = useState(emptySale);
  const [cancelExternalId, setCancelExternalId] = useState('');
  const [lastSale, setLastSale] = useState(null);
  const [savingProduct, setSavingProduct] = useState(false);
  const [savingCampaign, setSavingCampaign] = useState(false);
  const [savingSale, setSavingSale] = useState(false);
  const [cancelingSale, setCancelingSale] = useState(false);
  const [notice, setNotice] = useState(null);
  const [pageError, setPageError] = useState(null);

  const activeProducts = useMemo(
    () => products.filter(isActiveProduct),
    [products],
  );

  const activeCampaigns = useMemo(
    () => campaigns.filter((campaign) => campaign.status === 'active'),
    [campaigns],
  );

  const showRequestError = useCallback((error) => {
    const handledError = handleApiError(error);
    setPageError(requestErrorMessage(handledError));
    setNotice(null);
  }, [handleApiError]);

  const loadData = useCallback(async () => {
    setLoading(true);
    setPageError(null);

    try {
      const [productsResponse, campaignsResponse] = await Promise.all([
        api.products(token),
        api.campaigns(token),
      ]);

      setProducts(productsResponse.products ?? []);
      setCampaigns(campaignsResponse.campaigns ?? []);
    } catch (error) {
      showRequestError(error);
    } finally {
      setLoading(false);
    }
  }, [showRequestError, token]);

  useEffect(() => {
    if (token) {
      loadData();
    }
  }, [loadData, token]);

  useEffect(() => {
    setSaleForm((current) => ({
      ...current,
      product_id: activeProducts.some((product) => String(product.id) === current.product_id)
        ? current.product_id
        : String(activeProducts[0]?.id ?? ''),
      campaign_id: activeCampaigns.some((campaign) => String(campaign.id) === current.campaign_id)
        ? current.campaign_id
        : String(activeCampaigns[0]?.id ?? ''),
    }));
  }, [activeCampaigns, activeProducts]);

  function updateProductField(event) {
    const { name, value, type, checked } = event.target;
    setProductForm((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
  }

  function beginProductEdit(product) {
    setEditingProductId(product.id);
    setProductForm({
      name: product.name,
      sku: product.sku,
      points_per_unit: String(product.points_per_unit),
      active: isActiveProduct(product),
    });
    setPageError(null);
  }

  function resetProductForm() {
    setEditingProductId(null);
    setProductForm(emptyProduct);
  }

  async function submitProduct(event) {
    event.preventDefault();
    setSavingProduct(true);
    setPageError(null);
    setNotice(null);

    const payload = {
      name: productForm.name.trim(),
      sku: productForm.sku.trim(),
      points_per_unit: Number(productForm.points_per_unit),
      active: productForm.active,
    };

    try {
      const response = editingProductId
        ? await api.updateProduct(token, editingProductId, payload)
        : await api.createProduct(token, payload);

      setProducts((current) => replaceById(current, response.product));
      setNotice(editingProductId ? 'Produto atualizado.' : 'Produto criado.');
      resetProductForm();
    } catch (error) {
      showRequestError(error);
    } finally {
      setSavingProduct(false);
    }
  }

  async function deactivateProduct(id) {
    if (!window.confirm('Desativar este produto? Ele permanecerá no histórico, mas não poderá receber novas vendas.')) {
      return;
    }

    setPageError(null);
    setNotice(null);

    try {
      const response = await api.deactivateProduct(token, id);
      setProducts((current) => replaceById(current, response.product));
      setNotice('Produto desativado.');
    } catch (error) {
      showRequestError(error);
    }
  }

  function updateCampaignField(event) {
    const { name, value } = event.target;
    setCampaignForm((current) => ({ ...current, [name]: value }));
  }

  async function submitCampaign(event) {
    event.preventDefault();
    setSavingCampaign(true);
    setPageError(null);
    setNotice(null);

    const payload = {
      name: campaignForm.name.trim(),
      budget_total: Number(campaignForm.budget_total),
      starts_at: toApiDateTime(campaignForm.starts_at),
      ends_at: toApiDateTime(campaignForm.ends_at),
      status: campaignForm.status,
    };

    try {
      const response = await api.createCampaign(token, payload);
      setCampaigns((current) => replaceById(current, response.campaign));
      setCampaignForm(emptyCampaign());
      setNotice('Campanha criada.');
    } catch (error) {
      showRequestError(error);
    } finally {
      setSavingCampaign(false);
    }
  }

  function updateSaleField(event) {
    const { name, value } = event.target;
    setSaleForm((current) => ({ ...current, [name]: value }));
  }

  async function refreshCampaigns() {
    const response = await api.campaigns(token);
    setCampaigns(response.campaigns ?? []);
  }

  async function submitSale(event) {
    event.preventDefault();
    setSavingSale(true);
    setPageError(null);
    setNotice(null);

    const payload = {
      external_id: saleForm.external_id.trim(),
      campaign_id: Number(saleForm.campaign_id),
      seller_id: Number(saleForm.seller_id),
      product_id: Number(saleForm.product_id),
      quantity: Number(saleForm.quantity),
      unit_value: saleForm.unit_value.trim(),
    };

    try {
      const response = await api.createSale(token, payload);
      setLastSale(response.sale);
      setCancelExternalId(response.sale.external_id);
      setSaleForm((current) => ({ ...current, external_id: '', quantity: '1' }));
      await refreshCampaigns();
      setNotice(response.sale.status === 'approved' ? 'Venda aprovada e pontuada.' : 'Venda processada.');
    } catch (error) {
      showRequestError(error);
    } finally {
      setSavingSale(false);
    }
  }

  async function cancelSale(event) {
    event.preventDefault();

    if (!window.confirm('Cancelar esta venda e estornar os pontos?')) {
      return;
    }

    setCancelingSale(true);
    setPageError(null);
    setNotice(null);

    try {
      const response = await api.cancelSale(token, cancelExternalId.trim());
      const wasAlreadyCanceled = lastSale?.external_id === response.sale.external_id
        && lastSale.status === 'canceled';
      setLastSale(response.sale);
      await refreshCampaigns();
      setNotice(wasAlreadyCanceled ? 'A venda já estava cancelada.' : 'Venda cancelada e pontos estornados.');
    } catch (error) {
      showRequestError(error);
    } finally {
      setCancelingSale(false);
    }
  }

  function handleLogout() {
    logout();
    navigate('/login', { replace: true });
  }

  return (
    <main className="admin-shell">
      <header className="admin-header">
        <div>
          <p className="eyebrow">Painel admin</p>
          <h1>Vendeu, Ganhou</h1>
          <p className="description">Gerencie produtos, campanhas e o processamento de vendas.</p>
        </div>
        <div className="admin-user">
          <span>{user?.name}</span>
          <button className="secondary-button" type="button" onClick={handleLogout}>Sair</button>
        </div>
      </header>

      {pageError && <p className="error-message page-message" role="alert">{pageError}</p>}
      {notice && <p className="success-message page-message" role="status">{notice}</p>}

      {loading ? (
        <section className="panel loading-panel" aria-live="polite">Carregando dados administrativos…</section>
      ) : (
        <div className="admin-sections">
          <section className="panel panel-wide" aria-labelledby="products-title">
            <div className="section-heading">
              <div>
                <p className="eyebrow">Catálogo</p>
                <h2 id="products-title">Produtos</h2>
              </div>
              <span className="section-count">{products.length} cadastrados</span>
            </div>

            <div className="section-layout">
              <form className="admin-form" onSubmit={submitProduct}>
                <h3>{editingProductId ? 'Editar produto' : 'Novo produto'}</h3>
                <label htmlFor="product-name">Nome</label>
                <input id="product-name" name="name" value={productForm.name} onChange={updateProductField} required maxLength={160} />
                <label htmlFor="product-sku">SKU</label>
                <input id="product-sku" name="sku" value={productForm.sku} onChange={updateProductField} required maxLength={64} />
                <label htmlFor="product-points">Pontos por unidade</label>
                <input id="product-points" name="points_per_unit" type="number" min="1" step="1" value={productForm.points_per_unit} onChange={updateProductField} required />
                {editingProductId && (
                  <label className="checkbox-label" htmlFor="product-active">
                    <input id="product-active" name="active" type="checkbox" checked={productForm.active} onChange={updateProductField} />
                    Produto ativo
                  </label>
                )}
                <div className="action-row">
                  <button type="submit" disabled={savingProduct}>{savingProduct ? 'Salvando…' : editingProductId ? 'Salvar alterações' : 'Criar produto'}</button>
                  {editingProductId && <button className="secondary-button" type="button" onClick={resetProductForm}>Cancelar</button>}
                </div>
              </form>

              <div className="table-wrap">
                <table>
                  <caption className="sr-only">Produtos cadastrados</caption>
                  <thead><tr><th>Produto</th><th>SKU</th><th>Pontos</th><th>Status</th><th>Ações</th></tr></thead>
                  <tbody>
                    {products.length === 0 ? (
                      <tr><td colSpan="5" className="empty-cell">Nenhum produto cadastrado.</td></tr>
                    ) : products.map((product) => (
                      <tr key={product.id}>
                        <td>{product.name}</td>
                        <td>{product.sku}</td>
                        <td>{formatPoints(product.points_per_unit)}</td>
                        <td><span className={`status-pill ${isActiveProduct(product) ? 'status-active' : 'status-inactive'}`}>{isActiveProduct(product) ? 'Ativo' : 'Inativo'}</span></td>
                        <td><div className="table-actions"><button className="small-button secondary-button" type="button" onClick={() => beginProductEdit(product)}>Editar</button>{isActiveProduct(product) && <button className="small-button danger-button" type="button" onClick={() => deactivateProduct(product.id)}>Desativar</button>}</div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section className="panel" aria-labelledby="campaigns-title">
            <div className="section-heading">
              <div><p className="eyebrow">Incentivo</p><h2 id="campaigns-title">Campanhas</h2></div>
              <span className="section-count">{campaigns.length} cadastradas</span>
            </div>
            <form className="admin-form" onSubmit={submitCampaign}>
              <h3>Nova campanha</h3>
              <label htmlFor="campaign-name">Nome</label>
              <input id="campaign-name" name="name" value={campaignForm.name} onChange={updateCampaignField} required maxLength={160} />
              <label htmlFor="campaign-budget">Budget total (pontos)</label>
              <input id="campaign-budget" name="budget_total" type="number" min="1" step="1" value={campaignForm.budget_total} onChange={updateCampaignField} required />
              <div className="form-grid">
                <div><label htmlFor="campaign-starts">Início</label><input id="campaign-starts" name="starts_at" type="datetime-local" value={campaignForm.starts_at} onChange={updateCampaignField} required /></div>
                <div><label htmlFor="campaign-ends">Fim</label><input id="campaign-ends" name="ends_at" type="datetime-local" value={campaignForm.ends_at} onChange={updateCampaignField} required /></div>
              </div>
              <button type="submit" disabled={savingCampaign}>{savingCampaign ? 'Salvando…' : 'Criar campanha'}</button>
            </form>
            <div className="campaign-list">
              {campaigns.length === 0 ? <p className="empty-state">Nenhuma campanha cadastrada.</p> : campaigns.map((campaign) => (
                <article className="campaign-card" key={campaign.id}>
                  <div className="campaign-title"><h3>{campaign.name}</h3><span className={`status-pill ${campaign.status === 'active' ? 'status-active' : 'status-inactive'}`}>{campaign.status === 'active' ? 'Ativa' : 'Fechada'}</span></div>
                  <p className="budget-label"><strong>{formatPoints(campaign.budget_used)}</strong> de {formatPoints(campaign.budget_total)} pontos usados</p>
                  <div className="budget-track" aria-label={`${campaignProgress(campaign).toFixed(0)}% do budget utilizado`}><span style={{ width: `${campaignProgress(campaign)}%` }} /></div>
                  <p className="campaign-remaining">Restante: <strong>{formatPoints(campaign.budget_remaining)}</strong> pontos</p>
                  <p className="campaign-period">{formatDateTime(campaign.starts_at)} — {formatDateTime(campaign.ends_at)}</p>
                </article>
              ))}
            </div>
          </section>

          <section className="panel" aria-labelledby="sales-title">
            <div className="section-heading"><div><p className="eyebrow">Pontuação</p><h2 id="sales-title">Vendas</h2></div></div>
            <div className="section-layout sale-layout">
              <form className="admin-form" onSubmit={submitSale}>
                <h3>Registrar venda</h3>
                <label htmlFor="sale-external-id">ID externo</label>
                <input id="sale-external-id" name="external_id" value={saleForm.external_id} onChange={updateSaleField} required maxLength={191} placeholder="ex.: pedido-1001" />
                <label htmlFor="sale-seller-id">ID do seller</label>
                <input id="sale-seller-id" name="seller_id" type="number" min="1" step="1" value={saleForm.seller_id} onChange={updateSaleField} required />
                <p className="field-help">Informe o ID numérico do seller que receberá os pontos.</p>
                <div className="form-grid">
                  <div><label htmlFor="sale-product-id">Produto</label><select id="sale-product-id" name="product_id" value={saleForm.product_id} onChange={updateSaleField} required><option value="">Selecione</option>{activeProducts.map((product) => <option key={product.id} value={product.id}>{product.name} ({product.points_per_unit} pts)</option>)}</select></div>
                  <div><label htmlFor="sale-campaign-id">Campanha</label><select id="sale-campaign-id" name="campaign_id" value={saleForm.campaign_id} onChange={updateSaleField} required><option value="">Selecione</option>{activeCampaigns.map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.name}</option>)}</select></div>
                </div>
                <div className="form-grid">
                  <div><label htmlFor="sale-quantity">Quantidade</label><input id="sale-quantity" name="quantity" type="number" min="1" step="1" value={saleForm.quantity} onChange={updateSaleField} required /></div>
                  <div><label htmlFor="sale-unit-value">Valor unitário</label><input id="sale-unit-value" name="unit_value" inputMode="decimal" value={saleForm.unit_value} onChange={updateSaleField} required /></div>
                </div>
                <button type="submit" disabled={savingSale || activeProducts.length === 0 || activeCampaigns.length === 0}>{savingSale ? 'Processando…' : 'Registrar venda'}</button>
              </form>

              <div className="sale-result">
                <h3>Cancelar venda</h3>
                <form className="cancel-form" onSubmit={cancelSale}>
                  <label htmlFor="cancel-external-id">ID externo da venda</label>
                  <input id="cancel-external-id" value={cancelExternalId} onChange={(event) => setCancelExternalId(event.target.value)} required maxLength={191} placeholder="ex.: pedido-1001" />
                  <button className="danger-button" type="submit" disabled={cancelingSale}>{cancelingSale ? 'Cancelando…' : 'Cancelar venda'}</button>
                </form>
                {lastSale ? (
                  <div className="sale-summary" role="status">
                    <p className="eyebrow">Último resultado</p>
                    <p><strong>{lastSale.external_id}</strong> · <span className={`status-pill ${lastSale.status === 'approved' ? 'status-active' : 'status-inactive'}`}>{lastSale.status === 'approved' ? 'Aprovada' : 'Cancelada'}</span></p>
                    <dl><div><dt>Pontos</dt><dd>{formatPoints(lastSale.points)}</dd></div><div><dt>Quantidade</dt><dd>{formatPoints(lastSale.quantity)}</dd></div><div><dt>Data</dt><dd>{formatDateTime(lastSale.created_at)}</dd></div></dl>
                  </div>
                ) : <p className="empty-state">O resultado da próxima venda aparecerá aqui.</p>}
              </div>
            </div>
          </section>
        </div>
      )}
    </main>
  );
}
