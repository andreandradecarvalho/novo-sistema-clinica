document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', `${visible ? 'Ocultar' : 'Mostrar'} senha: ${document.querySelector(`label[for="${input.id}"]`).textContent}`);
        const icon = button.querySelector('i');
        icon.classList.toggle('tabler-eye', visible);
        icon.classList.toggle('tabler-eye-off', !visible);
    });
});

if (document.getElementById('layout-menu')) {
    import('./layout.js');
}

// Only illustrations used by the application's Blade views enter the Vite manifest.
import.meta.glob([
    '../../docs/layout/assets/img/illustrations/auth-login-illustration-light.png',
    '../../docs/layout/assets/img/illustrations/auth-forgot-password-illustration-light.png',
    '../../docs/layout/assets/img/illustrations/auth-reset-password-illustration-light.png',
    '../../docs/layout/assets/img/illustrations/auth-two-step-illustration-light.png',
    '../../docs/layout/assets/img/illustrations/boy-with-laptop-light.png',
    '../../docs/layout/assets/img/illustrations/page-misc-error.png',
], { eager: true, query: '?url', import: 'default' });
