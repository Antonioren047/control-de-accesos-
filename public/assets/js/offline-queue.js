(() => {
    const DB_NAME = 'vigilancia-offline';
    const STORE = 'operations';
    const VERSION = 1;
    const BATCH_SIZE = 50;
    let token = localStorage.getItem('vigilancia_offline_token') || '';
    let syncing = false;

    function uuid() {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const value = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
        return `${value.slice(0, 8)}-${value.slice(8, 12)}-${value.slice(12, 16)}-${value.slice(16, 20)}-${value.slice(20)}`;
    }

    function database() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORE)) db.createObjectStore(STORE, {keyPath: 'uuid'});
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function transaction(mode, action) {
        const db = await database();
        return new Promise((resolve, reject) => {
            const request = action(db.transaction(STORE, mode).objectStore(STORE));
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    const all = () => transaction('readonly', store => store.getAll());
    const put = item => transaction('readwrite', store => store.put(item));
    const remove = id => transaction('readwrite', store => store.delete(id));

    async function notify(state = '') {
        const pending = (await all()).length;
        window.dispatchEvent(new CustomEvent('offline-status', {detail: {online: navigator.onLine, pending, state}}));
        return pending;
    }

    async function requestBackgroundSync() {
        if (!('serviceWorker' in navigator)) return;
        try {
            const registration = await navigator.serviceWorker.ready;
            if ('sync' in registration) await registration.sync.register('vigilancia-offline-sync');
        } catch (_) {}
    }

    async function enqueue(type, payload = {}, evidenceData = null, entityKey = '') {
        const item = {
            uuid: uuid(), type, payload, evidence_data: evidenceData, entity_key: entityKey,
            occurred_at: new Date().toISOString(), local_status: 'pending'
        };
        await put(item);
        await notify('queued');
        await requestBackgroundSync();
        if (navigator.onLine) sync().catch(() => {});
        return item;
    }

    async function sendBatch(operations) {
        const response = await fetch('api/offline/sync', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Offline-Token': token},
            body: JSON.stringify({operations})
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'No fue posible sincronizar.');
        for (const result of payload.data.results || []) {
            const item = operations.find(row => row.uuid === result.uuid);
            if (result.status === 'synchronized' || result.status === 'accepted' || result.status === 'rejected') {
                await remove(result.uuid);
            } else if (item) {
                await put({...item, evidence_data: result.delete_local_evidence ? null : item.evidence_data, local_status: result.status});
            }
        }
    }

    async function sync() {
        if (syncing || !token || !navigator.onLine) return notify();
        syncing = true;
        try {
            let pending = await all();
            while (pending.length) {
                await sendBatch(pending.slice(0, BATCH_SIZE));
                const next = await all();
                if (next.length >= pending.length) break;
                pending = next;
            }
            return await notify('synchronized');
        } catch (error) {
            await notify('error');
            throw error;
        } finally {
            syncing = false;
        }
    }

    function configure(value) {
        if (value) {
            token = value;
            localStorage.setItem('vigilancia_offline_token', value);
        }
        notify();
        if (navigator.onLine) sync().catch(() => {});
    }

    window.VigilanciaOffline = {configure, enqueue, sync, pending: all};
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(() => {});
    window.addEventListener('online', () => sync().catch(() => {}));
    window.addEventListener('offline', () => notify());
    navigator.serviceWorker?.addEventListener('message', event => {
        if (event.data?.type === 'SYNC_OFFLINE') sync().catch(() => {});
    });
    notify();
})();
