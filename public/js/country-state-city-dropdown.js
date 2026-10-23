(function () {
    function init() {
        const d = window.CountryStateCity && window.CountryStateCity.data;
        if (!d) return;

        const countrySel = document.querySelector('#country-select');
        const stateSel = document.querySelector('#state-select');
        const citySel = document.querySelector('#city-select');

        if (!countrySel) return;

        function currentCountry() {
            return countrySel.value || countrySel.dataset.init || '';
        }

        function iso2ForCountry() {
            const val = currentCountry();
            if (!val) return null;

            const opt = countrySel.querySelector('option[value="' + val.replace(/"/g, '\\"') + '"]');
            if (opt && opt.dataset.iso2) return opt.dataset.iso2;

            const found = (d.countries || []).find(function (c) { return c.name === val; });
            return found ? found.iso2 : null;
        }

        function fillCountries() {
            const prev = currentCountry();
            countrySel.innerHTML = '<option value="">-- Select Country --</option>';

            (d.countries || []).forEach(function (c) {
                const opt = document.createElement('option');
                opt.value = c.name;
                opt.textContent = c.name;
                opt.dataset.iso2 = c.iso2;
                countrySel.appendChild(opt);
            });

            if (prev) {
                countrySel.value = prev;
                if (countrySel.selectedIndex < 0) {
                    const match = (d.countries || []).find(function (c) { return c.iso2 === prev; });
                    countrySel.value = match ? match.name : prev;
                }
            }
        }

        function fillStates(iso2, selectedState) {
            if (!stateSel) return;

            const states = (iso2 && d.states && d.states[iso2]) || [];
            stateSel.innerHTML = '<option value="">-- Select State/Province --</option>';

            states.forEach(function (s) {
                const opt = document.createElement('option');
                opt.value = s.name;
                opt.textContent = s.name;
                stateSel.appendChild(opt);
            });

            const prev = selectedState || stateSel.dataset.init || '';
            if (prev && states.some(function (s) { return s.name === prev; })) {
                stateSel.value = prev;
            }

            stateSel.disabled = states.length === 0;
        }

        function fillCities(iso2, selectedCity) {
            if (!citySel) return;

            const cities = (iso2 && d.cities && d.cities[iso2]) || [];
            citySel.innerHTML = '<option value="">-- Select City --</option>';

            cities.forEach(function (ct) {
                const opt = document.createElement('option');
                opt.value = ct;
                opt.textContent = ct;
                citySel.appendChild(opt);
            });

            const prev = selectedCity || citySel.dataset.init || '';
            if (prev && cities.indexOf(prev) !== -1) {
                citySel.value = prev;
            }

            citySel.disabled = cities.length === 0;
        }

        function notify(el) {
            if (el) {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        fillCountries();
        fillStates(iso2ForCountry(), '');
        fillCities(iso2ForCountry(), '');

        countrySel.addEventListener('change', function () {
            const iso2 = iso2ForCountry();

            // A new country invalidates the previously stored state / city.
            if (stateSel) stateSel.dataset.init = '';
            if (citySel) citySel.dataset.init = '';

            fillStates(iso2, '');
            fillCities(iso2, '');
            notify(stateSel);
            notify(citySel);
        });

        if (stateSel) {
            stateSel.addEventListener('change', function () {
                if (citySel) citySel.dataset.init = '';
                fillCities(iso2ForCountry(), '');
                notify(citySel);
            });
        }
    }

    window.hmsInitLocationDropdowns = init;

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('tenant-form-opened', init);

    // The script may finish loading after the form has already been opened.
    init();
})();
