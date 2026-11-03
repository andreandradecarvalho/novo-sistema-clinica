import '../../docs/layout/assets/vendor/libs/popper/popper.js';
import '../../docs/layout/assets/vendor/js/bootstrap.js';
import '../../docs/layout/assets/vendor/js/helpers.js';
import '../../docs/layout/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js';
import '../../docs/layout/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css';
import '../../docs/layout/assets/vendor/js/menu.js';

window.templateName = 'precordis';
const sidebar = document.getElementById('layout-menu');
const toggles = document.querySelectorAll('[data-layout-toggle]');
const mobileToggle = document.querySelector('#layout-navbar [data-layout-toggle]');
window.Helpers.mainMenu = new window.Menu(sidebar, { orientation: 'vertical', autoClose: false });

function syncMenu() {
    const mobile = window.Helpers.isSmallScreen();
    const open = !window.Helpers.isCollapsed();
    sidebar.inert = mobile && !open;
    document.querySelector('.layout-page').inert = mobile && open;
    toggles.forEach(button => {
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Recolher menu' : 'Abrir menu');
    });
}

function closeMobileMenu() {
    if (!window.Helpers.isSmallScreen() || window.Helpers.isCollapsed()) return;
    window.Helpers.setCollapsed(true, false);
    syncMenu();
    mobileToggle?.focus();
}

toggles.forEach(button => button.addEventListener('click', () => {
    window.Helpers.toggleCollapsed(false);
    window.setTimeout(() => {
        syncMenu();
        if (window.Helpers.isSmallScreen() && !window.Helpers.isCollapsed()) sidebar.querySelector('a, button')?.focus();
    }, 30);
}));
document.querySelector('[data-layout-overlay]')?.addEventListener('click', closeMobileMenu);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeMobileMenu();
});
window.addEventListener('resize', syncMenu);
syncMenu();
