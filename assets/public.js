// Public portal interactions: social feedback, hero carousel, weather, and navigation.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-social-animate]').forEach((iconButton) => {
        iconButton.addEventListener('click', () => {
            iconButton.classList.remove('is-animating');
            void iconButton.offsetWidth;
            iconButton.classList.add('is-animating');
        });
        iconButton.addEventListener('animationend', (event) => {
            if (event.animationName === 'social-pop') iconButton.classList.remove('is-animating');
        });
    });

    const carousel = document.querySelector('[data-hero-carousel]');
    if (carousel) {
        const slides = Array.from(carousel.querySelectorAll('[data-hero-slide]'));
        const dots = Array.from(carousel.querySelectorAll('[data-hero-dot]'));
        const progress = carousel.querySelector('[data-hero-progress]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
        let timer = null;
        const intervalMs = 6000;

        const displaySlide = (index) => {
            activeIndex = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                const active = slideIndex === activeIndex;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
                slide.inert = !active;
            });
            dots.forEach((dot, dotIndex) => {
                const active = dotIndex === activeIndex;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-pressed', String(active));
            });
            if (progress) {
                carousel.classList.remove('is-playing');
                progress.style.animation = 'none';
                void progress.offsetWidth;
                progress.style.animation = '';
            }
            syncTimer();
        };
        const stopTimer = () => {
            if (timer !== null) window.clearInterval(timer);
            timer = null;
            carousel.classList.remove('is-playing');
        };
        const syncTimer = () => {
            stopTimer();
            if (reducedMotion.matches || slides.length < 2 || document.hidden) return;
            carousel.classList.add('is-playing');
            timer = window.setInterval(() => displaySlide(activeIndex + 1), intervalMs);
        };

        slides.forEach((slide, index) => { slide.inert = index !== activeIndex; });
        carousel.querySelector('[data-hero-prev]')?.addEventListener('click', () => displaySlide(activeIndex - 1));
        carousel.querySelector('[data-hero-next]')?.addEventListener('click', () => displaySlide(activeIndex + 1));
        dots.forEach((dot) => dot.addEventListener('click', () => displaySlide(Number(dot.dataset.heroDot))));
        document.addEventListener('visibilitychange', syncTimer);
        reducedMotion.addEventListener?.('change', syncTimer);
        syncTimer();
    }

    const clock = document.getElementById('portalClock');
    if (clock) {
        const timeZone = clock.dataset.timezone || 'Asia/Jayapura';
        const dateFormatter = new Intl.DateTimeFormat('id-ID', {
            timeZone,
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
        const timeFormatter = new Intl.DateTimeFormat('id-ID', {
            timeZone,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23'
        });
        const updateClock = () => {
            const now = new Date();
            clock.textContent = `${dateFormatter.format(now)} | ${timeFormatter.format(now).replaceAll(':', '.')}`;
        };
        updateClock();
        window.setInterval(updateClock, 1000);
    }

    const weatherBadge = document.getElementById('portalWeather');
    if (weatherBadge) {
        const weatherTemperature = document.getElementById('portalWeatherTemperature');
        const weatherSummary = document.getElementById('portalWeatherSummary');
        const weatherPlace = document.getElementById('portalWeatherPlace');
        const weatherIcon = document.getElementById('portalWeatherIcon');
        const cacheKey = 'sims-pirime-public-weather-v1';
        const weatherText = (code) => {
            if (code === 0) return ['Cerah', 'bi-sun-fill'];
            if (code === 1) return ['Cerah berawan', 'bi-cloud-sun-fill'];
            if (code === 2) return ['Berawan sebagian', 'bi-cloud-sun-fill'];
            if (code === 3) return ['Berawan', 'bi-cloud-fill'];
            if ([45, 48].includes(code)) return ['Berkabut', 'bi-cloud-fog-fill'];
            if ([51, 53, 55, 56, 57].includes(code)) return ['Gerimis', 'bi-cloud-drizzle-fill'];
            if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return ['Hujan', 'bi-cloud-rain-fill'];
            if ([71, 73, 75, 77, 85, 86].includes(code)) return ['Salju', 'bi-cloud-snow-fill'];
            if ([95, 96, 97, 99].includes(code)) return ['Badai petir', 'bi-cloud-lightning-rain-fill'];
            return ['Kondisi cuaca', 'bi-cloud-fill'];
        };
        const showWeather = (weather, isStale = false) => {
            if (!weather || !Number.isFinite(Number(weather.temperature))) return;
            const [description, icon] = weatherText(Number(weather.code));
            weatherTemperature.textContent = `${Number(weather.temperature).toLocaleString('id-ID', { maximumFractionDigits: 1 })}°C`;
            weatherSummary.textContent = isStale ? `${description} · data terakhir` : description;
            weatherPlace.textContent = weather.place || 'Pirime';
            weatherIcon.className = `bi ${icon}`;
            weatherBadge.setAttribute('aria-label', `Cuaca ${weather.place || 'Pirime'}: ${weatherTemperature.textContent}, ${description}${isStale ? ', data terakhir tersimpan' : ''}`);
            if (weather.updatedAt) weatherBadge.title = `Data cuaca Open-Meteo diperbarui ${new Date(weather.updatedAt).toLocaleString('id-ID', { timeZone: 'Asia/Jayapura' })} WIT`;
        };
        let cachedWeather = null;
        try {
            cachedWeather = JSON.parse(localStorage.getItem(cacheKey) || 'null');
            if (cachedWeather?.weather) showWeather(cachedWeather.weather, Date.now() - cachedWeather.fetchedAt > 15 * 60 * 1000);
        } catch { /* Penyimpanan lokal tidak wajib untuk menampilkan cuaca. */ }

        const fetchJson = async (url) => {
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 10000);
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Weather service returned ${response.status}`);
                return await response.json();
            } finally {
                window.clearTimeout(timeout);
            }
        };
        const loadWeather = async () => {
            try {
                // Koordinat Pirime (sekitar SMP Negeri 4 Pirime), sesuai data peta satuan pendidikan.
                const forecastUrl = new URL('https://api.open-meteo.com/v1/forecast');
                forecastUrl.search = new URLSearchParams({
                    latitude: '-3.969',
                    longitude: '138.5385',
                    current: 'temperature_2m,weather_code',
                    timezone: 'Asia/Jayapura'
                });
                const forecast = await fetchJson(forecastUrl);
                if (!forecast.current || !Number.isFinite(Number(forecast.current.temperature_2m))) throw new Error('Weather observation is unavailable');
                const weather = {
                    temperature: forecast.current.temperature_2m,
                    code: forecast.current.weather_code,
                    place: 'Pirime',
                    updatedAt: forecast.current.time || new Date().toISOString()
                };
                showWeather(weather);
                try { localStorage.setItem(cacheKey, JSON.stringify({ weather, fetchedAt: Date.now() })); } catch { /* Continue without cache when storage is unavailable. */ }
            } catch {
                if (!cachedWeather?.weather) {
                    weatherTemperature.textContent = '--°C';
                    weatherSummary.textContent = 'Cuaca tidak tersedia';
                    weatherPlace.textContent = 'Pirime';
                    weatherBadge.setAttribute('aria-label', 'Data cuaca Pirime tidak dapat dimuat');
                }
            }
        };
        const cacheAge = cachedWeather?.fetchedAt ? Date.now() - cachedWeather.fetchedAt : Infinity;
        if (cacheAge >= 15 * 60 * 1000) loadWeather();
        window.setInterval(loadWeather, 15 * 60 * 1000);
    }

    const button = document.querySelector('.nav-toggle');
    const navigation = document.getElementById('publicNavigation');
    if (!button || !navigation) return;

    const setOpen = (open) => {
        navigation.classList.toggle('open', open);
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Tutup navigasi' : 'Buka navigasi');
        if (open) navigation.querySelector('a')?.focus({ preventScroll: true });
        else button.focus({ preventScroll: true });
    };

    button.addEventListener('click', () => setOpen(!navigation.classList.contains('open')));
    navigation.addEventListener('click', (event) => {
        if (event.target.closest('a') && window.matchMedia('(max-width: 800px)').matches) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navigation.classList.contains('open')) setOpen(false);
    });
    document.addEventListener('click', (event) => {
        if (navigation.classList.contains('open') && !navigation.contains(event.target) && !button.contains(event.target)) setOpen(false);
    });
    window.addEventListener('resize', () => {
        if (!window.matchMedia('(max-width: 800px)').matches && navigation.classList.contains('open')) {
            navigation.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Buka navigasi');
        }
    });
});
