(() => {
    'use strict';

    const form = document.querySelector('.biodata-form-body');
    if (!form) return;

    const addressSets = [
        {
            fields: ['provinsi_guru', 'kabupaten_guru', 'kecamatan_guru', 'distrik_guru', 'kampung_guru'],
            papuaDistrictField: 'distrik_guru',
        },
        {
            fields: ['provinsi', 'kabupaten', 'kecamatan', 'distrik', 'kampung'],
            papuaDistrictField: 'distrik',
        },
    ];
    const placeholders = {
        provinsi_guru: 'Pilih provinsi',
        provinsi: 'Pilih provinsi',
        kabupaten_guru: 'Pilih kabupaten/kota',
        kabupaten: 'Pilih kabupaten/kota',
        kecamatan_guru: 'Pilih kecamatan',
        kecamatan: 'Pilih kecamatan',
        distrik_guru: 'Pilih distrik',
        distrik: 'Pilih distrik',
        kampung_guru: 'Pilih kelurahan/kampung',
        kampung: 'Pilih kelurahan/desa',
    };
    const makeOption = (label, value, code = '') => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        if (code) option.dataset.code = code;
        return option;
    };
    const normalizeRegionName = (name) => String(name)
        .trim()
        .toLocaleLowerCase('id')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/^(kabupaten administrasi|kabupaten|kota administrasi|kota|kab\.)\s+/u, '')
        .trim();
    const endpointFor = (level, parentCode = '') => {
        if (level === 0) return 'provinces.json';
        if (!/^[0-9.]+$/.test(parentCode)) return '';
        if (level === 1) return `regencies/${parentCode}.json`;
        if (level === 2) return `districts/${parentCode}.json`;
        if (level === 3) return `villages/${parentCode}.json`;
        return '';
    };

    async function getRegionList(path) {
        const cacheKey = `sims-region:${path}`;
        try {
            const [resource, parentFile = ''] = path.split('/');
            const level = resource.replace(/\.json$/, '');
            const parent = parentFile.replace(/\.json$/, '');
            const query = new URLSearchParams({ level, parent });
            const response = await fetch(`api/regions.php?${query.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (!Array.isArray(payload.data)) throw new Error('Format data wilayah tidak valid.');
            try {
                localStorage.setItem(cacheKey, JSON.stringify({ savedAt: Date.now(), data: payload.data }));
            } catch (_) {
                // The dropdown remains usable if browser storage is unavailable.
            }
            return payload.data;
        } catch (_) {
            try {
                const cached = JSON.parse(localStorage.getItem(cacheKey) || 'null');
                if (Array.isArray(cached?.data)) return cached.data;
            } catch (_) {
                // The saved location will remain available as a legacy option.
            }
            return null;
        }
    }

    for (const addressSet of addressSets) {
        const { fields, papuaDistrictField } = addressSet;
        const sourceInputs = fields.map((name) => form.querySelector(`[name="${name}"]`));
        if (!sourceInputs[0]) continue;

        const savedValues = sourceInputs.map((input) => input?.value?.trim() || '');
        const controls = sourceInputs.map((input, index) => {
            if (!input) return null;
            const select = document.createElement('select');
            select.className = 'form-select';
            select.id = input.id;
            select.name = input.name;
            select.required = input.required;
            select.setAttribute('aria-label', placeholders[fields[index]]);
            select.append(makeOption(placeholders[fields[index]], ''));
            if (savedValues[index]) select.append(makeOption(`${savedValues[index]} (tersimpan)`, savedValues[index]));
            select.value = savedValues[index];
            select.disabled = !savedValues[index];
            input.replaceWith(select);
            return select;
        });
        const [province, regency, kecamatan, distrik, village] = controls;
        const districtControls = [kecamatan, distrik].filter(Boolean);
        const requestVersions = new Array(fields.length).fill(0);

        const showManualFallbackNote = () => {
            if (form.querySelector('.address-region-fallback-note')) return;
            const note = document.createElement('div');
            note.className = 'alert alert-warning py-2 px-3 small address-region-fallback-note';
            note.setAttribute('role', 'status');
            note.textContent = 'Daftar wilayah otomatis belum dapat dimuat. Kolom alamat bisa diisi manual; periksa koneksi internet XAMPP untuk memuat pilihan dropdown.';
            const anchor = controls.find(Boolean)?.closest('.col-12');
            if (anchor) anchor.insertAdjacentElement('afterend', note);
        };

        const resetControl = (select, fieldIndex) => {
            if (!select) return;
            select.replaceChildren(makeOption(placeholders[fields[fieldIndex]], ''));
            select.disabled = true;
        };
        const resetFrom = (index) => {
            for (let fieldIndex = index; fieldIndex < controls.length; fieldIndex++) {
                requestVersions[fieldIndex]++;
                resetControl(controls[fieldIndex], fieldIndex);
            }
        };
        const fillControl = (select, fieldIndex, items, savedValue = '') => {
            if (!select) return;
            select.replaceChildren(makeOption(placeholders[fields[fieldIndex]], ''));
            const sorted = [...items].sort((a, b) => String(a.name).localeCompare(String(b.name), 'id'));
            for (const item of sorted) select.append(makeOption(item.name, item.name, item.code));
            const normalizedSavedValue = normalizeRegionName(savedValue);
            const match = sorted.find((item) => normalizeRegionName(item.name) === normalizedSavedValue);
            if (match) {
                if (savedValue !== match.name) select.append(makeOption(`${savedValue} (tersimpan)`, savedValue, match.code));
                select.value = savedValue;
            } else if (savedValue) {
                select.append(makeOption(`${savedValue} (tersimpan)`, savedValue));
                select.value = savedValue;
            }
            select.disabled = sorted.length === 0 && !savedValue;
        };
        const restoreSavedFrom = (index) => {
            for (let fieldIndex = index; fieldIndex < controls.length; fieldIndex++) {
                const select = controls[fieldIndex];
                if (!select) continue;
                resetControl(select, fieldIndex);
                if (savedValues[fieldIndex]) {
                    select.append(makeOption(`${savedValues[fieldIndex]} (tersimpan)`, savedValues[fieldIndex]));
                    select.value = savedValues[fieldIndex];
                    select.disabled = false;
                }
            }
        };
        const fallbackToManual = (index, useSavedValues = false) => {
            showManualFallbackNote();
            for (let fieldIndex = index; fieldIndex < controls.length; fieldIndex++) {
                const select = controls[fieldIndex];
                if (!select) continue;
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control';
                input.id = select.id;
                input.name = select.name;
                input.maxLength = fieldIndex === fields.length - 1 ? 120 : 100;
                input.placeholder = `Isi ${placeholders[fields[fieldIndex]].replace(/^Pilih /, '').toLowerCase()} secara manual`;
                input.value = useSavedValues ? savedValues[fieldIndex] : select.value;
                input.setAttribute('aria-label', placeholders[fields[fieldIndex]]);
                select.replaceWith(input);
                controls[fieldIndex] = null;
            }
        };
        const chosenDistrictField = () => province?.value?.toLocaleLowerCase('id').startsWith('papua')
            ? papuaDistrictField
            : fields[2];
        const syncDistrictVisibility = () => {
            const preferred = chosenDistrictField();
            for (const select of districtControls) {
                const wrapper = select.closest('.col-12');
                if (!wrapper) continue;
                wrapper.hidden = select.name !== preferred && !select.value;
            }
        };
        const selectedDistrict = () => {
            const preferred = districtControls.find((select) => select.name === chosenDistrictField());
            const alternate = districtControls.find((select) => select.name !== chosenDistrictField());
            return (preferred?.selectedOptions[0]?.dataset.code ? preferred : null)
                || (alternate?.selectedOptions[0]?.dataset.code ? alternate : null);
        };

        province?.addEventListener('change', async () => {
            resetFrom(1);
            syncDistrictVisibility();
            if (!province.value) return;
            const requestVersion = ++requestVersions[1];
            const path = endpointFor(1, province.selectedOptions[0]?.dataset.code || '');
            if (!path) return fallbackToManual(1);
            const regions = await getRegionList(path);
            if (requestVersions[1] !== requestVersion) return;
            if (!regions) return fallbackToManual(1);
            fillControl(regency, 1, regions);
        });
        regency?.addEventListener('change', async () => {
            resetFrom(2);
            syncDistrictVisibility();
            if (!regency.value) return;
            const requestVersion = ++requestVersions[2];
            const path = endpointFor(2, regency.selectedOptions[0]?.dataset.code || '');
            if (!path) return fallbackToManual(2);
            const regions = await getRegionList(path);
            if (requestVersions[2] !== requestVersion) return;
            if (!regions) return fallbackToManual(2);
            for (const select of districtControls) fillControl(select, fields.indexOf(select.name), regions);
            syncDistrictVisibility();
        });
        for (const select of districtControls) {
            select.addEventListener('change', async () => {
                resetControl(village, fields.length - 1);
                if (!select.value) return;
                const requestVersion = ++requestVersions[fields.length - 1];
                const path = endpointFor(3, select.selectedOptions[0]?.dataset.code || '');
                if (!path) return fallbackToManual(fields.length - 1);
                const regions = await getRegionList(path);
                if (requestVersions[fields.length - 1] !== requestVersion) return;
                if (!regions) return fallbackToManual(fields.length - 1);
                fillControl(village, fields.length - 1, regions);
            });
        }

        (async () => {
            const provinces = await getRegionList(endpointFor(0));
            if (!provinces) return fallbackToManual(0, true);
            fillControl(province, 0, provinces, savedValues[0]);
            if (!province?.selectedOptions[0]?.dataset.code) {
                restoreSavedFrom(1);
                syncDistrictVisibility();
                return;
            }

            const regencies = await getRegionList(endpointFor(1, province.selectedOptions[0].dataset.code));
            if (!regencies) return fallbackToManual(1, true);
            fillControl(regency, 1, regencies, savedValues[1]);
            if (!regency?.selectedOptions[0]?.dataset.code) {
                restoreSavedFrom(2);
                syncDistrictVisibility();
                return;
            }

            const districts = await getRegionList(endpointFor(2, regency.selectedOptions[0].dataset.code));
            if (!districts) return fallbackToManual(2, true);
            for (const select of districtControls) fillControl(select, fields.indexOf(select.name), districts, savedValues[fields.indexOf(select.name)]);
            syncDistrictVisibility();

            const district = selectedDistrict();
            if (!district) return;
            const villages = await getRegionList(endpointFor(3, district.selectedOptions[0].dataset.code));
            if (!villages) return fallbackToManual(fields.length - 1, true);
            fillControl(village, fields.length - 1, villages, savedValues[fields.length - 1]);
        })();
    }
})();
