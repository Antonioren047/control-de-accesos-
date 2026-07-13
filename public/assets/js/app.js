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
    const profileTheme = document.querySelector('#profileTheme');
    if (profileTheme) profileTheme.textContent = {auto: 'Automático', light: 'Claro', dark: 'Oscuro'}[theme] || theme;
    try { await api('/auth/theme', {method: 'POST', body: JSON.stringify({theme})}); }
    catch { notify('El tema cambió localmente, pero no pudo guardarse.', 'error'); }
});

const sidebar = document.querySelector('#sidebar');
document.querySelector('#collapse')?.addEventListener('click', () => sidebar.classList.toggle('collapsed'));
document.querySelector('#mobileMenu')?.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('keydown', event => { if (event.key === 'Escape') sidebar?.classList.remove('open'); });

const viewMeta = {
    inicio: ['Acceso autenticado', 'Panel principal'],
    perfil: ['Identidad y alcance', 'Mi perfil'],
    seguridad: ['Protección de la cuenta', 'Seguridad'],
};

function activateView(requested, updateHash = true) {
    const view = viewMeta[requested] ? requested : 'inicio';
    document.querySelectorAll('[data-view]').forEach(section => { section.hidden = section.dataset.view !== view; });
    document.querySelectorAll('nav [data-view-target]').forEach(link => link.classList.toggle('active', link.dataset.viewTarget === view));
    const [eyebrow, title] = viewMeta[view];
    const eyebrowNode = document.querySelector('#viewEyebrow');
    const titleNode = document.querySelector('#viewTitle');
    if (eyebrowNode) eyebrowNode.textContent = eyebrow;
    if (titleNode) titleNode.textContent = title;
    sidebar?.classList.remove('open');
    if (updateHash && location.hash !== `#${view}`) history.replaceState(null, '', `#${view}`);
    if (view === 'seguridad') loadSessions();
}

document.querySelectorAll('[data-view-target]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    activateView(event.currentTarget.dataset.viewTarget);
}));
window.addEventListener('hashchange', () => activateView(location.hash.slice(1), false));

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

const sessionsList = document.querySelector('#sessionsList');
let sessionsLoaded = false;

function sessionRow(session) {
    const row = document.createElement('article');
    row.className = 'session-row';
    const info = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = session.is_current ? 'Este dispositivo' : 'Otra sesión';
    const details = document.createElement('p');
    const lastActivity = new Date(`${session.last_activity_at.replace(' ', 'T')}Z`).toLocaleString('es-MX');
    details.textContent = `${session.ip_address || 'IP no disponible'} · Última actividad: ${lastActivity}`;
    const agent = document.createElement('small');
    agent.textContent = session.user_agent || 'Navegador no identificado';
    info.append(title, details, agent);
    row.append(info);
    if (session.is_current) {
        const badge = document.createElement('span');
        badge.className = 'badge success';
        badge.textContent = 'Actual';
        row.append(badge);
    } else {
        const button = document.createElement('button');
        button.className = 'ghost-button danger-action';
        button.type = 'button';
        button.textContent = 'Revocar';
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                await api('/auth/sessions/revoke', {method: 'POST', body: JSON.stringify({session_id: session.id})});
                notify('Sesión revocada correctamente.');
                await loadSessions(true);
            } catch (error) {
                notify(error.message, 'error');
                button.disabled = false;
            }
        });
        row.append(button);
    }
    return row;
}

async function loadSessions(force = false) {
    if (!sessionsList || (sessionsLoaded && !force)) return;
    sessionsList.textContent = 'Consultando sesiones…';
    try {
        const payload = await api('/auth/sessions');
        sessionsList.textContent = '';
        const sessions = payload.data?.sessions || [];
        if (!sessions.length) sessionsList.textContent = 'No hay sesiones activas.';
        sessions.forEach(session => sessionsList.append(sessionRow(session)));
        sessionsLoaded = true;
    } catch (error) {
        sessionsList.textContent = error.message;
    }
}

document.querySelector('#refreshSessions')?.addEventListener('click', () => loadSessions(true));
activateView(location.hash.slice(1) || 'inicio', false);
