import {initTheme} from './theme.js';

initTheme();
const form = document.querySelector('#loginForm');
const message = document.querySelector('#loginMessage');
const password = form?.querySelector('input[name="password"]');

document.querySelector('#togglePassword')?.addEventListener('click', event => {
    const visible = password.type === 'text';
    password.type = visible ? 'password' : 'text';
    event.currentTarget.textContent = visible ? 'Ver' : 'Ocultar';
    event.currentTarget.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
});

form?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const data = Object.fromEntries(new FormData(form));
    button.disabled = true;
    button.textContent = 'Validando…';
    message.textContent = '';
    try {
        const response = await fetch('api/auth/login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': data._csrf},
            body: JSON.stringify({email: data.email, password: data.password, _csrf: data._csrf}),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'No fue posible iniciar sesión.');
        localStorage.setItem('vigilancia_theme', payload.data.user.theme || 'auto');
        location.href = './';
    } catch (error) {
        message.textContent = error.message;
        message.dataset.type = 'error';
        button.disabled = false;
        button.innerHTML = 'Iniciar sesión <span>→</span>';
    }
});
