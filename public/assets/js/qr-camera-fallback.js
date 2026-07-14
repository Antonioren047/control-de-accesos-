(()=>{
    const delay=milliseconds=>new Promise(resolve=>setTimeout(resolve,milliseconds));
    async function waitForFrame(video,timeout=8000){
        const started=Date.now();
        while((video.readyState<2||video.videoWidth<1||video.videoHeight<1)&&Date.now()-started<timeout)await delay(80);
        if(video.videoWidth<1||video.videoHeight<1)throw new Error('La cámara abrió, pero no entregó imagen. Cierra otras aplicaciones que usen la cámara e inténtalo nuevamente.');
    }
    async function openCamera(video){
        if(!navigator.mediaDevices?.getUserMedia)throw new Error('El navegador no permite acceder a la cámara.');
        const current=video.srcObject;
        if(current)current.getTracks().forEach(track=>track.stop());
        const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});
        video.srcObject=stream;video.hidden=false;await video.play();await waitForFrame(video);return stream;
    }
    async function scanCamera({video,canvas,input,message}){
        if(!('BarcodeDetector'in window)&&typeof window.jsQR!=='function')throw new Error('No fue posible cargar el lector QR. Verifica la conexión y recarga la página.');
        const stream=await openCamera(video),context=canvas.getContext('2d',{willReadFrequently:true}),detector='BarcodeDetector'in window?new BarcodeDetector({formats:['qr_code']}):null;
        try{
            for(;;){
                await waitForFrame(video);let value='';
                if(detector){const codes=await detector.detect(video);value=codes[0]?.rawValue||'';}
                else{const width=video.videoWidth,height=video.videoHeight;if(width<1||height<1){await delay(100);continue}canvas.width=width;canvas.height=height;context.drawImage(video,0,0,width,height);const frame=context.getImageData(0,0,width,height);value=window.jsQR(frame.data,width,height,{inversionAttempts:'attemptBoth'})?.data||'';}
                if(value){input.value=value;message.textContent='QR leído correctamente con la cámara.';message.className='form-message success';return;}
                await delay(180);
            }
        }finally{stream.getTracks().forEach(track=>track.stop());video.srcObject=null;video.hidden=true;}
    }
    async function takePhoto(){
        const video=document.querySelector('#camera'),canvas=document.querySelector('#snapshot'),preview=document.querySelector('#preview'),input=document.querySelector('[name="photo_data"]'),message=document.querySelector('#operationalMessage');
        let stream;
        try{preview.hidden=true;stream=await openCamera(video);await waitForFrame(video);await delay(250);const width=video.videoWidth,height=video.videoHeight;canvas.width=width;canvas.height=height;const context=canvas.getContext('2d');context.drawImage(video,0,0,width,height);const data=canvas.toDataURL('image/jpeg',.82);if(!data||data==='data:,')throw new Error('No fue posible capturar la imagen de la cámara.');input.value=data;preview.src=data;preview.hidden=false;video.hidden=true;message.textContent='Fotografía tomada correctamente.';message.className='form-message success';}
        catch(error){message.textContent=error.message||'No se pudo tomar la fotografía.';message.className='form-message error';}
        finally{if(stream)stream.getTracks().forEach(track=>track.stop());video.srcObject=null;}
    }
    const startButton=document.querySelector('#scanQr');
    if(startButton)startButton.onclick=async()=>{const message=document.querySelector('#operationalMessage');try{await scanCamera({video:document.querySelector('#camera'),canvas:document.querySelector('#snapshot'),input:document.querySelector('[name="qr_token"]'),message})}catch(error){message.textContent=error.message;message.className='form-message error'}};
    const photoButton=document.querySelector('#takePhoto');if(photoButton)photoButton.onclick=takePhoto;
    const closeButton=document.querySelector('#scanCloseQr');
    if(closeButton)closeButton.onclick=async()=>{const message=document.querySelector('#closeMessage');try{await scanCamera({video:document.querySelector('#closeCamera'),canvas:document.querySelector('#closeCanvas'),input:document.querySelector('#closeToken'),message})}catch(error){message.textContent=error.message;message.className='form-message error'}};
})();
