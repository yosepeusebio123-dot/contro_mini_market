const state = {
    token: localStorage.getItem('freshstock_token'),
    user: JSON.parse(localStorage.getItem('freshstock_user') || 'null'),
    categories: [],
    products: [],
    productsPage: 1,
    productsTotalPages: 1,
};

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function toast(message, type = 'success') {
    const element = $('#toast');
    element.textContent = message;
    element.className = `toast show ${type}`;
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => element.className = 'toast', 3200);
}

async function api(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (options.body) headers['Content-Type'] = 'application/json';
    if (state.token) headers.Authorization = `Bearer ${state.token}`;

    const response = await fetch(path, { ...options, headers });
    const payload = response.status === 204 ? {} : await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 401 && state.token) logout(false);
        const fields = payload.campos ? ` ${Object.values(payload.campos).join(' ')}` : '';
        throw new Error((payload.error || 'No se pudo completar la solicitud.') + fields);
    }

    return payload;
}

function setLoading(element, loading) {
    element.classList.toggle('loading', loading);
    [...element.querySelectorAll('button')].forEach(button => button.disabled = loading);
}

function loginSuccess(payload) {
    state.token = payload.token;
    state.user = payload.usuario;
    localStorage.setItem('freshstock_token', state.token);
    localStorage.setItem('freshstock_user', JSON.stringify(state.user));
    showApp();
}

function logout(showMessage = true) {
    state.token = null;
    state.user = null;
    localStorage.removeItem('freshstock_token');
    localStorage.removeItem('freshstock_user');
    $('#app-view').classList.add('hidden');
    $('#auth-view').classList.remove('hidden');
    if (showMessage) toast('Sesión cerrada correctamente.');
}

async function showApp() {
    $('#auth-view').classList.add('hidden');
    $('#app-view').classList.remove('hidden');
    $('#user-name').textContent = state.user?.nombre || 'Usuario';
    $('#user-role').textContent = state.user?.rol || 'operador';
    const admin = state.user?.rol === 'admin';
    $('#new-category-button').classList.toggle('hidden', !admin);
    await Promise.allSettled([loadCategories(), loadDashboard(), loadProducts(), loadProductOptions(), loadMovements(), loadAlerts()]);
}

function switchAuth(tab) {
    $$('.tab').forEach(button => button.classList.toggle('active', button.dataset.authTab === tab));
    $('#login-form').classList.toggle('hidden', tab !== 'login');
    $('#register-form').classList.toggle('hidden', tab !== 'register');
}

function switchSection(name) {
    const titles = { dashboard: 'Resumen', products: 'Productos', categories: 'Categorías', movements: 'Movimientos', alerts: 'Alertas' };
    $$('.page-section').forEach(section => section.classList.add('hidden'));
    $(`#section-${name}`).classList.remove('hidden');
    $$('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.section === name));
    $('#section-title').textContent = titles[name];
}

async function loadDashboard() {
    const [summary, low, expiring] = await Promise.all([
        api('/api/dashboard/summary'),
        api('/api/alerts/low-stock'),
        api('/api/alerts/expiring?days=30'),
    ]);
    const metrics = [
        ['Productos activos', summary.data.total_productos],
        ['Categorías', summary.data.total_categorias],
        ['Stock bajo', summary.data.productos_stock_bajo],
        ['Por vencer', summary.data.productos_por_vencer],
        ['Movimientos hoy', summary.data.movimientos_hoy],
    ];
    $('#summary-cards').innerHTML = metrics.map(([label, value]) => `<article class="metric-card"><span>${label}</span><strong>${value}</strong></article>`).join('');
    $('#dashboard-low-stock').innerHTML = renderAlertList(low.data, item => `${item.stock_actual}/${item.stock_minimo} unidades`);
    $('#dashboard-expiring').innerHTML = renderAlertList(expiring.data, item => `${item.dias_restantes} días`);
}

async function loadCategories() {
    const payload = await api('/api/categories?limit=100');
    state.categories = payload.data;
    const options = '<option value="">Seleccione</option>' + state.categories.map(item => `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`).join('');
    $('#product-category').innerHTML = options;
    $('#product-category-filter').innerHTML = '<option value="">Todas las categorías</option>' + state.categories.map(item => `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`).join('');
    renderCategories();
}

function renderCategories() {
    const admin = state.user?.rol === 'admin';
    $('#categories-list').innerHTML = state.categories.length
        ? state.categories.map(item => `<article class="category-card"><h3>${escapeHtml(item.nombre)}</h3><p>${escapeHtml(item.descripcion || 'Sin descripción')}</p>${admin ? `<div class="actions"><button class="button ghost" data-edit-category="${item.id}">Editar</button><button class="button danger" data-delete-category="${item.id}">Eliminar</button></div>` : ''}</article>`).join('')
        : '<p class="empty">No hay categorías registradas.</p>';
}

async function loadProducts(page = state.productsPage) {
    const filter = encodeURIComponent($('#product-filter')?.value || '');
    const category = $('#product-category-filter')?.value || '';
    const payload = await api(`/api/products?page=${page}&limit=8&filter=${filter}&category_id=${category}`);
    state.products = payload.data;
    state.productsPage = payload.meta.page;
    state.productsTotalPages = Math.max(1, payload.meta.total_pages);
    renderProducts();
}

function renderProducts() {
    const admin = state.user?.rol === 'admin';
    $('#products-table').innerHTML = state.products.length ? state.products.map(item => {
        const status = item.stock_bajo ? '<span class="badge danger">Stock bajo</span>' : item.proximo_a_vencer ? '<span class="badge warning">Por vencer</span>' : '<span class="badge success">Normal</span>';
        return `<tr>
            <td class="row-title"><strong>${escapeHtml(item.nombre)}</strong><br><small>${escapeHtml(item.codigo_barras)}</small></td>
            <td>${escapeHtml(item.categoria)}</td>
            <td>${item.stock_actual} / mín. ${item.stock_minimo}</td>
            <td>S/ ${Number(item.precio).toFixed(2)}</td>
            <td>${item.fecha_vencimiento || 'No aplica'}</td>
            <td>${status}</td>
            <td><div class="actions"><button class="button ghost" data-edit-product="${item.id}">Editar</button>${admin ? `<button class="button danger" data-delete-product="${item.id}">Eliminar</button>` : ''}</div></td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="empty">No se encontraron productos.</td></tr>';
    $('#products-page').textContent = `Página ${state.productsPage} de ${state.productsTotalPages}`;
    $('#products-prev').disabled = state.productsPage <= 1;
    $('#products-next').disabled = state.productsPage >= state.productsTotalPages;
}

async function loadProductOptions() {
    const payload = await api('/api/products?limit=100');
    const options = payload.data.map(item => `<option value="${item.id}">${escapeHtml(item.nombre)} (stock ${item.stock_actual})</option>`).join('');
    $('#movement-product').innerHTML = options || '<option value="">Sin productos</option>';
}

async function loadMovements() {
    const payload = await api('/api/stock/movements?limit=30');
    $('#movements-table').innerHTML = payload.data.length ? payload.data.map(item => `<tr>
        <td>${new Date(item.fecha).toLocaleString('es-PE')}</td>
        <td>${escapeHtml(item.producto)}</td>
        <td><span class="badge ${item.tipo_movimiento === 'ENTRADA' ? 'success' : 'warning'}">${item.tipo_movimiento}</span></td>
        <td>${item.cantidad}</td><td>${item.stock_anterior} → ${item.stock_resultante}</td><td>${escapeHtml(item.usuario)}</td><td>${escapeHtml(item.motivo || '-')}</td>
    </tr>`).join('') : '<tr><td colspan="7" class="empty">Aún no hay movimientos.</td></tr>';
}

async function loadAlerts() {
    const [low, expiring] = await Promise.all([api('/api/alerts/low-stock'), api('/api/alerts/expiring?days=30')]);
    $('#alerts-low-stock').innerHTML = renderAlertList(low.data, item => `Faltan ${Math.max(0, item.faltante)} unidades`);
    $('#alerts-expiring').innerHTML = renderAlertList(expiring.data, item => `Vence en ${item.dias_restantes} días`);
}

function renderAlertList(items, detail) {
    return items.length ? items.slice(0, 8).map(item => `<div class="alert-row"><div><strong>${escapeHtml(item.nombre)}</strong><br><small>${escapeHtml(item.categoria || '')}</small></div><span>${detail(item)}</span></div>`).join('') : '<p class="empty">No hay alertas activas.</p>';
}

function openProductForm(product = null) {
    $('#product-form').classList.remove('hidden');
    $('#product-id').value = product?.id || '';
    $('#product-name').value = product?.nombre || '';
    $('#product-code').value = product?.codigo_barras || '';
    $('#product-category').value = product?.categoria_id || '';
    $('#product-unit').value = product?.unidad_medida || 'unidad';
    $('#product-price').value = product?.precio || 0;
    $('#product-stock').value = product?.stock_actual || 0;
    $('#product-stock').readOnly = Boolean(product);
    $('#product-min-stock').value = product?.stock_minimo || 0;
    $('#product-expiry').value = product?.fecha_vencimiento || '';
    $('#product-description').value = product?.descripcion || '';
    $('#product-name').focus();
}

function closeProductForm() { $('#product-form').classList.add('hidden'); $('#product-form').reset(); $('#product-id').value = ''; $('#product-stock').readOnly = false; }
function openCategoryForm(category = null) { $('#category-form').classList.remove('hidden'); $('#category-id').value = category?.id || ''; $('#category-name').value = category?.nombre || ''; $('#category-description').value = category?.descripcion || ''; }
function closeCategoryForm() { $('#category-form').classList.add('hidden'); $('#category-form').reset(); $('#category-id').value = ''; }

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

$$('[data-auth-tab]').forEach(button => button.addEventListener('click', () => switchAuth(button.dataset.authTab)));
$$('.nav-link').forEach(button => button.addEventListener('click', () => switchSection(button.dataset.section)));
$$('[data-section-target]').forEach(button => button.addEventListener('click', () => switchSection(button.dataset.sectionTarget)));
$('#logout-button').addEventListener('click', () => logout());

$('#login-form').addEventListener('submit', async event => {
    event.preventDefault();
    setLoading(event.currentTarget, true);
    try {
        const payload = await api('/auth/login', { method: 'POST', body: JSON.stringify({ correo: $('#login-email').value, contrasena: $('#login-password').value }) });
        loginSuccess(payload);
        toast('Bienvenido a FreshStock.');
    } catch (error) { toast(error.message, 'error'); } finally { setLoading(event.currentTarget, false); }
});

$('#register-form').addEventListener('submit', async event => {
    event.preventDefault();
    setLoading(event.currentTarget, true);
    try {
        await api('/auth/register', { method: 'POST', body: JSON.stringify({ nombre: $('#register-name').value, correo: $('#register-email').value, contrasena: $('#register-password').value }) });
        toast('Cuenta creada. Ahora inicia sesión.');
        $('#login-email').value = $('#register-email').value;
        switchAuth('login');
    } catch (error) { toast(error.message, 'error'); } finally { setLoading(event.currentTarget, false); }
});

$('#new-product-button').addEventListener('click', () => openProductForm());
$('#cancel-product-button').addEventListener('click', closeProductForm);
$('#product-search-button').addEventListener('click', () => loadProducts(1).catch(error => toast(error.message, 'error')));
$('#products-prev').addEventListener('click', () => loadProducts(state.productsPage - 1));
$('#products-next').addEventListener('click', () => loadProducts(state.productsPage + 1));

$('#product-form').addEventListener('submit', async event => {
    event.preventDefault();
    const id = $('#product-id').value;
    const body = {
        nombre: $('#product-name').value,
        codigo_barras: $('#product-code').value,
        categoria_id: Number($('#product-category').value),
        descripcion: $('#product-description').value,
        unidad_medida: $('#product-unit').value,
        precio: Number($('#product-price').value),
        stock_actual: Number($('#product-stock').value),
        stock_minimo: Number($('#product-min-stock').value),
        fecha_vencimiento: $('#product-expiry').value || null,
    };
    setLoading(event.currentTarget, true);
    try {
        await api(id ? `/api/products/${id}` : '/api/products', { method: id ? 'PUT' : 'POST', body: JSON.stringify(body) });
        toast(id ? 'Producto actualizado.' : 'Producto creado.');
        closeProductForm();
        await Promise.all([loadProducts(1), loadProductOptions(), loadDashboard(), loadAlerts()]);
    } catch (error) { toast(error.message, 'error'); } finally { setLoading(event.currentTarget, false); }
});

$('#products-table').addEventListener('click', async event => {
    const editId = event.target.dataset.editProduct;
    const deleteId = event.target.dataset.deleteProduct;
    if (editId) {
        try { const payload = await api(`/api/products/${editId}`); openProductForm(payload.data); } catch (error) { toast(error.message, 'error'); }
    }
    if (deleteId && confirm('¿Eliminar este producto?')) {
        try { await api(`/api/products/${deleteId}`, { method: 'DELETE' }); toast('Producto eliminado.'); await loadProducts(1); } catch (error) { toast(error.message, 'error'); }
    }
});

$('#new-category-button').addEventListener('click', () => openCategoryForm());
$('#cancel-category-button').addEventListener('click', closeCategoryForm);
$('#category-form').addEventListener('submit', async event => {
    event.preventDefault();
    const id = $('#category-id').value;
    setLoading(event.currentTarget, true);
    try {
        await api(id ? `/api/categories/${id}` : '/api/categories', { method: id ? 'PUT' : 'POST', body: JSON.stringify({ nombre: $('#category-name').value, descripcion: $('#category-description').value }) });
        toast(id ? 'Categoría actualizada.' : 'Categoría creada.');
        closeCategoryForm();
        await loadCategories();
    } catch (error) { toast(error.message, 'error'); } finally { setLoading(event.currentTarget, false); }
});

$('#categories-list').addEventListener('click', async event => {
    const editId = event.target.dataset.editCategory;
    const deleteId = event.target.dataset.deleteCategory;
    const category = state.categories.find(item => String(item.id) === String(editId));
    if (category) openCategoryForm(category);
    if (deleteId && confirm('¿Eliminar esta categoría?')) {
        try { await api(`/api/categories/${deleteId}`, { method: 'DELETE' }); toast('Categoría eliminada.'); await loadCategories(); } catch (error) { toast(error.message, 'error'); }
    }
});

$('#movement-form').addEventListener('submit', async event => {
    event.preventDefault();
    setLoading(event.currentTarget, true);
    try {
        await api('/api/stock/movements', { method: 'POST', body: JSON.stringify({ producto_id: Number($('#movement-product').value), tipo_movimiento: $('#movement-type').value, cantidad: Number($('#movement-quantity').value), motivo: $('#movement-reason').value }) });
        toast('Movimiento registrado.');
        event.currentTarget.reset();
        await Promise.all([loadProducts(), loadProductOptions(), loadMovements(), loadDashboard(), loadAlerts()]);
    } catch (error) { toast(error.message, 'error'); } finally { setLoading(event.currentTarget, false); }
});

if (state.token && state.user) showApp().catch(() => logout(false));
