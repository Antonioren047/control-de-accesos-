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
    permisos: ['Control de autorización', 'Permisos por rol'],
};

function activateView(requested, updateHash = true) {
    const requestedView = viewMeta[requested] ? requested : 'inicio';
    const isAuthorized = [...document.querySelectorAll('[data-view]')]
        .some(section => section.dataset.view === requestedView);
    const view = isAuthorized ? requestedView : 'inicio';
    if (requestedView !== view) notify('No tienes permiso para abrir ese módulo.', 'error');
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
    if (view === 'permisos') loadPermissionMatrix();
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

const permissionRole = document.querySelector('#permissionRole');
const permissionMatrix = document.querySelector('#permissionMatrix');
const permissionSummary = document.querySelector('#permissionSummary');
let authorizationMatrix = null;

function renderPermissionMatrix() {
    if (!authorizationMatrix || !permissionRole || !permissionMatrix) return;
    const role = authorizationMatrix.roles.find(item => item.code === permissionRole.value);
    if (!role) return;
    const assigned = new Set(role.permissions);
    const grouped = authorizationMatrix.permissions.reduce((modules, permission) => {
        (modules[permission.module] ||= []).push(permission);
        return modules;
    }, {});
    permissionSummary.textContent = `${role.name}: ${assigned.size} permisos activos.`;
    permissionMatrix.textContent = '';

    Object.entries(grouped).forEach(([moduleName, permissions]) => {
        const card = document.createElement('article');
        card.className = 'security-card permission-module';
        const heading = document.createElement('div');
        heading.className = 'permission-module-heading';
        const title = document.createElement('h3');
        title.textContent = moduleName;
        const count = document.createElement('span');
        count.className = 'badge neutral';
        count.textContent = `${permissions.filter(permission => assigned.has(permission.code)).length}/${permissions.length}`;
        heading.append(title, count);
        card.append(heading);

        permissions.forEach(permission => {
            const label = document.createElement('label');
            label.className = 'permission-toggle';
            const copy = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = permission.name;
            const code = document.createElement('small');
            code.textContent = `${permission.code} · acción: ${permission.action}`;
            copy.append(name, code);
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = assigned.has(permission.code);
            input.disabled = role.code === 'superadmin';
            input.addEventListener('change', async () => {
                input.disabled = true;
                try {
                    await api('/authorization/roles', {
                        method: 'POST',
                        body: JSON.stringify({
                            role_code: role.code,
                            permission_code: permission.code,
                            allowed: input.checked,
                        }),
                    });
                    if (input.checked) assigned.add(permission.code); else assigned.delete(permission.code);
                    role.permissions = [...assigned];
                    notify('Permiso actualizado correctamente.');
                    renderPermissionMatrix();
                } catch (error) {
                    input.checked = !input.checked;
                    input.disabled = false;
                    notify(error.message, 'error');
                }
            });
            label.append(copy, input);
            card.append(label);
        });
        permissionMatrix.append(card);
    });
}

async function loadPermissionMatrix(force = false) {
    if (!permissionMatrix || (authorizationMatrix && !force)) return;
    try {
        const payload = await api('/authorization/roles');
        authorizationMatrix = payload.data;
        permissionRole.textContent = '';
        authorizationMatrix.roles.forEach(role => {
            const option = document.createElement('option');
            option.value = role.code;
            option.textContent = role.name;
            permissionRole.append(option);
        });
        renderPermissionMatrix();
    } catch (error) {
        permissionMatrix.textContent = error.message;
        notify(error.message, 'error');
    }
}

permissionRole?.addEventListener('change', renderPermissionMatrix);
activateView(location.hash.slice(1) || 'inicio', false);
