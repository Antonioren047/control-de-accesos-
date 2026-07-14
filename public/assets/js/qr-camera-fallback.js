(()=>{
    async function scanCamera({video, canvas, input, message, stopAfterRead = true}) {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('El navegador no permite acceder a la cámara.');
        if (!('BarcodeDetector' in window) && typeof window.jsQR !== 'function') throw new Error('No fue posible cargar el lector QR. Verifica la conexión y recarga la página.');
        const stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
        video.srcObject = stream; video.hidden = false; await video.play();
        if (!video.videoWidth) await new Promise(resolve=>video.addEventListener('loadedmetadata',resolve,{once:true}));
        const context = canvas.getContext('2d',{willReadFrequently:true});
        const detector = 'BarcodeDetector' in window ? new BarcodeDetector({formats:['qr_code']}) : null;
        for (;;) {
            let value = '';
            if (detector) {
                const codes = await detector.detect(video); value = codes[0]?.rawValue || '';
            } else {
                canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                context.drawImage(video,0,0,canvas.width,canvas.height);
                const frame = context.getImageData(0,0,canvas.width,canvas.height);
                value = window.jsQR(frame.data,frame.width,frame.height,{inversionAttempts:'attemptBoth'})?.data || '';
            }
            if (value) {
                input.value = value;
                if (stopAfterRead) { stream.getTracks().forEach(track=>track.stop()); video.hidden = true; }
                message.textContent = 'QR leído correctamente con la cámara.'; message.className = 'form-message success';
                return;
            }
            await new Promise(resolve=>setTimeout(resolve,180));
        }
    }
    const startButton=document.querySelector('#scanQr');
    if(startButton) startButton.onclick=async()=>{try{await scanCamera({video:document.querySelector('#camera'),canvas:document.querySelector('#snapshot'),input:document.querySelector('[name="qr_token"]'),message:document.querySelector('#operationalMessage')})}catch(error){const message=document.querySelector('#operationalMessage');message.textContent=error.message;message.className='form-message error'}};
    const closeButton=document.querySelector('#scanCloseQr');
    if(closeButton) closeButton.onclick=async()=>{try{await scanCamera({video:document.querySelector('#closeCamera'),canvas:document.querySelector('#closeCanvas'),input:document.querySelector('#closeToken'),message:document.querySelector('#closeMessage')})}catch(error){const message=document.querySelector('#closeMessage');message.textContent=error.message;message.className='form-message error'}};
})();
