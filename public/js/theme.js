/**
 * EPCO - Sistema de Modo Oscuro
 * Incluir en todas las páginas que soporten dark mode
 */

// Verificar preferencia guardada o del sistema
function getThemePreference() {
    const stored = localStorage.getItem('epco-theme');
    if (stored) return stored;
    
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

// Aplicar tema
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('epco-theme', theme);
    
    // Actualizar icono del toggle si existe
    const toggleIcon = document.querySelector('.theme-toggle i');
    if (toggleIcon) {
        toggleIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
    
    // Actualizar icono y texto en dropdown
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';
    }
    if (themeText) {
        themeText.textContent = theme === 'dark' ? 'Modo Claro' : 'Modo Oscuro';
    }
    
    // Actualizar charts si existen
    if (typeof Chart !== 'undefined') {
        Chart.helpers.each(Chart.instances, function(instance) {
            const isDark = theme === 'dark';
            instance.options.scales.x.ticks.color = isDark ? '#94a3b8' : '#64748b';
            instance.options.scales.y.ticks.color = isDark ? '#94a3b8' : '#64748b';
            instance.options.scales.x.grid.color = isDark ? '#334155' : '#e2e8f0';
            instance.options.scales.y.grid.color = isDark ? '#334155' : '#e2e8f0';
            instance.update();
        });
    }
}

// Toggle tema
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = current === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
    
    // Guardar en servidor si el usuario está logueado
    fetch('api/preferences.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: newTheme })
    }).catch(() => {}); // Silenciar errores
}

// Crear botón de toggle
function createThemeToggle() {
    const toggle = document.createElement('button');
    toggle.className = 'theme-toggle btn btn-link';
    toggle.setAttribute('aria-label', 'Cambiar tema');
    toggle.innerHTML = '<i class="bi bi-moon-fill"></i>';
    toggle.onclick = toggleTheme;
    return toggle;
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    applyTheme(getThemePreference());
    
    // Agregar toggle al navbar si existe
    const navbar = document.querySelector('.navbar .ms-auto');
    if (navbar) {
        const toggle = createThemeToggle();
        toggle.classList.add('text-white');
        navbar.insertBefore(toggle, navbar.firstChild);
    }
});

// Escuchar cambios en preferencia del sistema
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (!localStorage.getItem('epco-theme')) {
        applyTheme(e.matches ? 'dark' : 'light');
    }
});
