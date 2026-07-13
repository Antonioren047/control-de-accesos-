import {initTheme} from './theme.js';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const toast = document.querySelector('#toast');

async function api(path, options = {}) {
    const response = await fetch(`api${path}`, {
        ...options,
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...(options.headers || {})},
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || 'No fue posible completar la solicitud.');
    return payload;
}

function notify(message, type = 'success') {
    if (!toast) return;
    toast.textContent = message;
    toast.dataset.type = type;
    toast.classList.add('visible');
    setTimeout(() => toast.classList.remove('visible'), 3500);
}

initTheme(async theme => {
    try { await api('/auth/theme', {method: 'POST', body: JSON.stringify({theme})}); }
    catch { notify('El tema cambió localmente, pero no pudo guardarse.', 'error'); }
});

const sidebar = document.querySelector('#sidebar');
document.querySelector('#collapse')?.addEventListener('click', () => sidebar.classList.toggle('collapsed'));
document.querySelector('#mobileMenu')?.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('keydown', event => { if (event.key === 'Escape') sidebar?.classList.remove('open'); });

document.querySelector('#logoutButton')?.addEventListener('click', async () => {
    try {
        await api('/auth/logout', {method: 'POST', body: '{}'});
        location.href = 'login.php';
    } catch (error) { notify(error.message, 'error'); }
});

document.querySelector('#passwordForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const message = document.querySelector('#passwordMessage');
    const data = Object.fromEntries(new FormData(form));
    try {
        const payload = await api('/auth/password', {method: 'POST', body: JSON.stringify(data)});
        form.reset();
        message.textContent = payload.message;
        message.dataset.type = 'success';
    } catch (error) {
        message.textContent = error.message;
        message.dataset.type = 'error';
    }
});
