(() => {
    const sheet = document.querySelector('[data-qr-type]');
    if (!sheet) return;
    const type = sheet.dataset.qrType;
    const id = Number(sheet.dataset.qrId);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const message = document.querySelector('#shareMessage');
    const rawUrl = `visit-qr.php?type=${encodeURIComponent(type)}&id=${id}&raw=1`;
    let preparedFile = null;

    function show(text, kind = 'success') {
        message.textContent = text;
        message.className = `form-message ${kind}`;
    }

    async function log(channel) {
        if (type !== 'visit') return;
        try {
            await fetch('api/visits/action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
                body: JSON.stringify({id, action: 'share', channel})
            });
        } catch (_) {}
    }

    async function prepareFile() {
        if (preparedFile) return preparedFile;
        const response = await fetch(rawUrl, {cache: 'no-store'});
        if (!response.ok) throw new Error('No fue posible preparar la imagen QR.');
        const blob = await response.blob();
        const extension = blob.type === 'image/png' ? 'png' : 'svg';
        preparedFile = new File([blob], `qr-${type}-${id}.${extension}`, {type: blob.type});
        return preparedFile;
    }

    function downloadFallback() {
        const link = document.createElement('a');
        link.href = `${rawUrl}&download=1`;
        link.download = '';
        document.body.append(link);
        link.click();
        link.remove();
    }

    prepareFile().catch(() => {});
    document.querySelector('#downloadQr')?.addEventListener('click', () => log('download'));
    document.querySelector('#whatsappShare')?.addEventListener('click', () => {
        log('whatsapp');
        show('WhatsApp se abrió con el mensaje preparado. Adjunta la imagen QR descargada.');
    });
    document.querySelector('#nativeShare')?.addEventListener('click', async () => {
        try {
            const file = await prepareFile();
            if (navigator.share && navigator.canShare?.({files: [file]})) {
                await navigator.share({title: 'Acceso autorizado', text: 'Presenta este código QR en el acceso.', files: [file]});
                await log('native');
                show('Se abrió el menú para compartir la imagen QR.');
                return;
            }
            downloadFallback();
            await log('download');
            show('Este navegador no permite compartir archivos directamente. La imagen QR fue descargada para que puedas enviarla.', 'success');
        } catch (error) {
            if (error?.name === 'AbortError') { show('Se canceló la acción de compartir.', 'error'); return; }
            downloadFallback();
            show('No se pudo abrir el menú de compartir. La imagen QR fue descargada.', 'error');
        }
    });
})();
