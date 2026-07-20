const main=document.querySelector('#mainContent');
window.addEventListener('hashchange',()=>{requestAnimationFrame(()=>main?.focus({preventScroll:true}))});
document.addEventListener('click',event=>{const link=event.target.closest('a[href^="#"]');if(link&&link.dataset.viewTarget)requestAnimationFrame(()=>main?.focus({preventScroll:true}))});
window.addEventListener('offline',()=>document.body.dataset.connection='offline');
window.addEventListener('online',()=>delete document.body.dataset.connection);
if(!navigator.onLine)document.body.dataset.connection='offline';
