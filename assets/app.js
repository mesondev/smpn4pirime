document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('appSidebar');
    const toggle = document.getElementById('sidebarToggle');
    const layout = document.querySelector('.app-layout');
    const themeToggle = document.getElementById('themeToggle');
    const isMobile = () => window.matchMedia('(max-width: 1199.98px)').matches;

    let savedTheme = 'light';
    let desktopCollapsed = false;
    try {
        savedTheme = localStorage.getItem('sims-theme') === 'dark' ? 'dark' : 'light';
        desktopCollapsed = localStorage.getItem('sims-sidebar-collapsed') === '1';
    } catch (_) {
        // Preferensi lokal tidak wajib agar aplikasi tetap berfungsi.
    }

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        if (themeToggle) {
            const dark = theme === 'dark';
            themeToggle.innerHTML = `<i class="bi ${dark ? 'bi-sun' : 'bi-moon-stars'}"></i>`;
            themeToggle.setAttribute('aria-label', dark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap');
            themeToggle.title = dark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap';
        }
        if (window.Chart) {
            Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--ink').trim();
            Object.values(Chart.instances || {}).forEach((chart) => chart.update('none'));
        }
    };

    applyTheme(savedTheme);
    if (layout && sidebar && !isMobile()) layout.classList.toggle('is-sidebar-collapsed', desktopCollapsed);
    if (toggle && sidebar) {
        toggle.setAttribute('aria-expanded', String(isMobile()
            ? sidebar.classList.contains('is-open')
            : !(layout && layout.classList.contains('is-sidebar-collapsed'))));
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
            try { localStorage.setItem('sims-theme', nextTheme); } catch (_) { /* Tema aktif sampai halaman ditutup. */ }
        });
    }

    if (sidebar && toggle) {
        toggle.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', String(sidebar.classList.contains('is-open')));
                return;
            }
            if (!layout) return;
            const collapsed = layout.classList.toggle('is-sidebar-collapsed');
            desktopCollapsed = collapsed;
            toggle.setAttribute('aria-expanded', String(!collapsed));
            try { localStorage.setItem('sims-sidebar-collapsed', collapsed ? '1' : '0'); } catch (_) { /* Preferensi bersifat opsional. */ }
        });
        document.addEventListener('click', (event) => {
            if (isMobile() && sidebar.classList.contains('is-open')
                && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('is-open');
                let shouldCollapse = desktopCollapsed;
                try { shouldCollapse = localStorage.getItem('sims-sidebar-collapsed') === '1'; } catch (_) { /* Gunakan preferensi dalam memori. */ }
                if (layout) layout.classList.toggle('is-sidebar-collapsed', shouldCollapse);
            } else if (layout) {
                layout.classList.remove('is-sidebar-collapsed');
            }
        });
    }

    const badge = document.querySelector('[data-notification-count]');
    if (badge) {
        const refreshCount = async () => {
            try {
                const response = await fetch('api/notification_count.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const result = await response.json();
                badge.textContent = String(Math.min(Number(result.count) || 0, 99));
                badge.hidden = Number(result.count) < 1;
            } catch (_) {
                // Kegagalan polling tidak mengganggu halaman yang sedang dibuka.
            }
        };
        window.setInterval(refreshCount, 60000);
    }
});
