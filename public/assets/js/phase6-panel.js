const workspace = document.querySelector('[data-phase6-module]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const escapeHtml = value => String(value ?? '').replace(/[&<>"]/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[char]));
const date = value => value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('es-MX') : '—';
const statusLabels = {conflict: 'Conflicto', expired: 'Vencido'};
const typeLabels = {entry: 'Entrada', exit: 'Salida', round_start: 'Inicio de recorrido', round_end: 'Fin de recorrido', event: 'Evento', evidence: 'Evidencia', comment: 'Comentario'};

async function api(path, options = {}) {
    const response = await fetch(`api${path}`, {...options, headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...(options.headers || {})}});
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || 'No fue posible completar la solicitud.');
    return payload.data;
}

function render(items) {
    if (!items.length) return '<article class="security-card"><p>No hay conflictos ni operaciones vencidas pendientes.</p></article>';
    return items.map(item => `
        <article class="offline-review-card">
            <header><div><p class="eyebrow">${escapeHtml(typeLabels[item.operation_type] || item.operation_type)}</p><h3>${escapeHtml(item.guard_name)}</h3></div><span class="badge neutral">${escapeHtml(statusLabels[item.status] || item.status)}</span></header>
            <dl>
                <div><dt>Lugar</dt><dd>${escapeHtml(item.location_name || 'Sin lugar')}</dd></div>
                <div><dt>Punto</dt><dd>${escapeHtml(item.access_point_name || 'Sin punto')}</dd></div>
                <div><dt>Fecha capturada</dt><dd>${escapeHtml(date(item.occurred_at))}</dd></div>
                <div><dt>Recibida</dt><dd>${escapeHtml(date(item.received_at))}</dd></div>
                <div><dt>Clave de conflicto</dt><dd>${escapeHtml(item.entity_key || '—')}</dd></div>
                <div><dt>UUID</dt><dd>${escapeHtml(item.client_uuid)}</dd></div>
            </dl>
            <div class="offline-review-actions"><button class="submit" type="button" data-review="accepted" data-id="${item.id}">Aceptar</button><button class="ghost-button" type="button" data-review="rejected" data-id="${item.id}">Rechazar</button></div>
        </article>`).join('');
}

async function review(button) {
    const decision = button.dataset.review;
    const comment = await SiteUI.prompt(`Escribe el motivo para ${decision === 'accepted' ? 'aceptar' : 'rechazar'} el registro (mínimo 10 caracteres).`, {title: decision === 'accepted' ? 'Aceptar registro' : 'Rechazar registro', label: 'Motivo de la decisión'});
    if (!comment) return;
    button.disabled = true;
    try {
        await api('/offline/review', {method: 'POST', body: JSON.stringify({operation_id: Number(button.dataset.id), decision, comment})});
        await load();
    } catch (error) {
        alert(error.message);
        button.disabled = false;
    }
}

async function load() {
    if (!workspace) return;
    const content = workspace.querySelector('[data-phase6-content]');
    SiteUI.loading(content,'Consultando conflictos de sincronización…');
    try {
        const data = await api('/offline/conflicts');
        content.innerHTML = render(data.items || []);
        content.querySelectorAll('[data-review]').forEach(button => button.addEventListener('click', () => review(button)));
    } catch (error) {
        content.innerHTML = `<article class="security-card"><p>${escapeHtml(error.message)}</p></article>`;
    }
}

workspace?.querySelector('[data-phase6-refresh]')?.addEventListener('click', load);
load();
