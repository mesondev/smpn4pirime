// Dashboard controls: sidebar state, theme preference, charts, and page interactions.
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('appSidebar');
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
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
        const setMobileMenu = (open) => {
            sidebar.classList.toggle('is-open', open);
            if (backdrop) backdrop.hidden = !open;
            document.body.classList.toggle('sidebar-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            if (open) sidebar.querySelector('.sidebar-link')?.focus({ preventScroll: true });
            else toggle.focus({ preventScroll: true });
        };
        toggle.addEventListener('click', () => {
            if (isMobile()) {
                setMobileMenu(!sidebar.classList.contains('is-open'));
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
                && !sidebar.contains(event.target) && !toggle.contains(event.target)
                && !(backdrop && backdrop.contains(event.target))) {
                setMobileMenu(false);
            }
        });
        if (backdrop) backdrop.addEventListener('click', () => setMobileMenu(false));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isMobile() && sidebar.classList.contains('is-open')) setMobileMenu(false);
        });
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('is-open');
                if (backdrop) backdrop.hidden = true;
                document.body.classList.remove('sidebar-open');
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
        const liveRegion = document.getElementById('notificationLiveRegion');
        let lastNotificationId = 0;
        let notificationsInitialized = false;
        let notificationRequestInFlight = false;
        const showNotificationToast = (item) => {
            if (!liveRegion) return;
            const toast = document.createElement('article');
            toast.className = 'notification-toast';
            toast.setAttribute('role', 'status');
            const title = document.createElement('strong');
            title.textContent = item.judul || 'Notifikasi baru';
            const message = document.createElement('p');
            message.textContent = item.pesan || '';
            toast.append(title, message);
            if (item.tautan && /^(index\.php\?|public\/|reports\.php)/.test(item.tautan)) {
                const link = document.createElement('a');
                link.href = item.tautan;
                link.textContent = 'Buka';
                toast.append(link);
            }
            liveRegion.append(toast);
            window.setTimeout(() => toast.remove(), 9000);
        };
        const updateOperatorNotificationPanel = (item) => {
            const panel = document.getElementById('operatorDashboardNotifications');
            if (!panel || !item || !item.id || panel.querySelector(`[data-dashboard-notification-item="${Number(item.id)}"]`)) return;
            panel.querySelector('[data-notification-empty]')?.remove();
            const row = document.createElement('article');
            row.className = 'operator-notification-item operator-notification-item-new';
            row.dataset.dashboardNotificationItem = String(Number(item.id));
            const content = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = item.judul || 'Notifikasi baru';
            const message = document.createElement('p');
            message.textContent = item.pesan || '';
            content.append(title, message);
            const date = document.createElement('small');
            date.className = 'text-secondary text-nowrap';
            const parsedDate = item.created_at ? new Date(String(item.created_at).replace(' ', 'T')) : new Date();
            date.textContent = Number.isNaN(parsedDate.getTime()) ? 'Baru saja' : parsedDate.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            row.append(content, date);
            panel.prepend(row);
            Array.from(panel.querySelectorAll('[data-dashboard-notification-item]')).slice(5).forEach(oldRow => oldRow.remove());
        };
        const refreshCount = async () => {
            if (notificationRequestInFlight || document.visibilityState === 'hidden') return;
            notificationRequestInFlight = true;
            try {
                const response = await fetch(`api/notification_count.php?after=${lastNotificationId}`, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const result = await response.json();
                badge.textContent = String(Math.min(Number(result.count) || 0, 99));
                badge.hidden = Number(result.count) < 1;
                const items = Array.isArray(result.items) ? result.items : [];
                items.forEach(updateOperatorNotificationPanel);
                if (notificationsInitialized) items.filter(item => Number(item.id) > lastNotificationId).slice(0, 3).reverse().forEach(showNotificationToast);
                lastNotificationId = Math.max(lastNotificationId, Number(result.latest_id) || 0, ...items.map(item => Number(item.id) || 0));
                notificationsInitialized = true;
            } catch (_) {
                // Kegagalan polling tidak mengganggu halaman yang sedang dibuka.
            } finally {
                notificationRequestInFlight = false;
            }
        };
        refreshCount();
        window.setInterval(refreshCount, 15000);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refreshCount(); });
    }
});
