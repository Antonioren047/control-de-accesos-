(() => {
    const sheet = document.querySelector('[data-qr-type]');
    if (!sheet) return;
    const type = sheet.dataset.qrType;
    const id = Number(sheet.dataset.qrId);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const canvas = document.querySelector('#shareCanvas');
    const context = canvas.getContext('2d');
    const rawUrl = `visit-qr.php?type=${encodeURIComponent(type)}&id=${id}&raw=1`;
    let preparedFile = null;

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

    function roundedRect(x, y, width, height, radius) {
        context.beginPath();
        context.roundRect(x, y, width, height, radius);
        context.fill();
    }

    function fitText(text, maxWidth, initialSize, weight = 700) {
        let size = initialSize;
        do {
            context.font = `${weight} ${size}px Inter, Arial, sans-serif`;
            if (context.measureText(text).width <= maxWidth) return size;
            size -= 2;
        } while (size > 28);
        return size;
    }

    async function loadQrImage() {
        const response = await fetch(rawUrl, {cache: 'no-store'});
        if (!response.ok) throw new Error('No fue posible preparar el código QR.');
        const blob = await response.blob();
        if ('createImageBitmap' in window) return createImageBitmap(blob);
        return new Promise((resolve, reject) => {
            const image = new Image();
            const url = URL.createObjectURL(blob);
            image.onload = () => { URL.revokeObjectURL(url); resolve(image); };
            image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No fue posible cargar el QR.')); };
            image.src = url;
        });
    }

    async function createCardBlob() {
        const qr = await loadQrImage();
        context.fillStyle = '#F5F7FA';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.textAlign = 'center';
        context.fillStyle = '#163D7A';
        context.font = '700 25px Inter, Arial, sans-serif';
        context.fillText('ACCESO SEGURO CON CÓDIGO QR', 450, 90);

        const name = sheet.dataset.displayName || 'Acceso autorizado';
        const nameSize = fitText(name, 760, 64, 700);
        context.fillStyle = '#0B1D3A';
        context.font = `700 ${nameSize}px Inter, Arial, sans-serif`;
        context.fillText(name, 450, 190);

        const scope = `${sheet.dataset.locationName || ''} · ${sheet.dataset.unitName || ''}`;
        const scopeSize = fitText(scope, 760, 34, 400);
        context.font = `400 ${scopeSize}px Inter, Arial, sans-serif`;
        context.fillText(scope, 450, 275);

        context.fillStyle = '#FFFFFF';
        roundedRect(120, 330, 660, 660, 42);
        context.imageSmoothingEnabled = false;
        context.drawImage(qr, 175, 385, 550, 550);
        context.imageSmoothingEnabled = true;

        context.font = '700 32px Inter, Arial, sans-serif';
        context.fillStyle = '#0B1D3A';
        context.fillText(`Referencia: ${sheet.dataset.reference || ''}`, 450, 1070);

        return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('No fue posible generar la imagen completa.')), 'image/png', 0.96));
    }

    async function prepareFile() {
        if (preparedFile) return preparedFile;
        const blob = await createCardBlob();
        preparedFile = new File([blob], `acceso-${type}-${id}.png`, {type: 'image/png'});
        return preparedFile;
    }

    function downloadFile(file) {
        const url = URL.createObjectURL(file);
        const link = document.createElement('a');
        link.href = url;
        link.download = file.name;
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    prepareFile().catch(() => {});
    document.querySelector('#downloadQr')?.addEventListener('click', async () => {
        try {
            const file = await prepareFile();
            downloadFile(file);
            await log('download');
        } catch (error) {
            alert(error.message || 'No fue posible descargar el acceso.');
        }
    });
    document.querySelector('#nativeShare')?.addEventListener('click', async () => {
        try {
            const file = await prepareFile();
            if (navigator.share && navigator.canShare?.({files: [file]})) {
                await navigator.share({title: 'Acceso autorizado', text: 'Presenta este código QR en el acceso.', files: [file]});
                await log('native');
                return;
            }
            downloadFile(file);
            await log('download');
        } catch (error) {
            if (error?.name !== 'AbortError') alert(error.message || 'No fue posible compartir el acceso.');
        }
    });
})();
