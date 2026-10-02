const d = window.CountryStateCity?.data;
if (!d) return;

const countrySel = document.querySelector('#country-select');
const stateSel = document.querySelector('#state-select');
const citySel = document.querySelector('#city-select');
const cityInput = document.querySelector('#city-input');

function fillCountries(selected) {
  if (!countrySel) return;
  const prev = selected || countrySel.dataset.init || '';
  countrySel.innerHTML = '<option value="">-- Select Country --</option>';
  (d.countries || []).forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.name;
    opt.textContent = c.name;
    opt.dataset.iso2 = c.iso2;
    countrySel.appendChild(opt);
  });
  if (prev) {
    countrySel.value = prev;
    if (countrySel.selectedIndex < 0) {
      countrySel.value = (d.countries.find(x=>x.iso2===prev)||{}).name || prev;
    }
  }
}

function getIso2() {
  if (!countrySel) return null;
  const val = countrySel.value;
  if (!val) return null;
  const opt = countrySel.querySelector('option[value="'+val.replace(/"/g,'\\"')+'"]');
  if (opt && opt.dataset.iso2) return opt.dataset.iso2;
  const found = (d.countries || []).find(c => c.name === val);
  return found ? found.iso2 : null;
}

function fillStates(iso2, selectedState) {
  if (stateSel) {
    const states = (iso2 && d.states && d.states[iso2]) || [];
    stateSel.innerHTML = '<option value="">-- Select State/Province --</option>';
    states.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.name;
      opt.textContent = s.name;
      stateSel.appendChild(opt);
    });
    const prev = selectedState || stateSel.dataset.init || '';
    if (prev && states.some(s=>s.name===prev)) stateSel.value = prev;
    stateSel.disabled = states.length === 0;
  }
}

function fillCities(iso2, selectedCity) {
  if (citySel) {
    const cities = (iso2 && d.cities && d.cities[iso2]) || [];
    citySel.innerHTML = '<option value="">-- Select City --</option>';
    cities.forEach(ct => {
      const opt = document.createElement('option');
      opt.value = ct;
      opt.textContent = ct;
      citySel.appendChild(opt);
    });
    const prev = selectedCity || citySel.dataset.init || (cityInput ? cityInput.dataset.init || cityInput.value : '');
    if (prev && cities.includes(prev)) citySel.value = prev;
    citySel.disabled = cities.length === 0;
  }
  if (cityInput) {
    const v = (citySel && citySel.value) ? citySel.value : (cityInput.dataset.init || '');
    cityInput.value = v;
  }
}

function sync() {
  const iso2 = getIso2();
  fillStates(iso2, stateSel ? (stateSel.value || stateSel.dataset.init) : '');
  fillCities(iso2, citySel ? citySel.value : (cityInput ? cityInput.value : ''));
}

document.addEventListener('DOMContentLoaded', () => {
  fillCountries(countrySel ? (countrySel.value || countrySel.dataset.init) : '');
  sync();
  if (cityInput) cityInput.value = cityInput.dataset.init || '';
});

if (countrySel) countrySel.addEventListener('change', () => { sync(); });
if (stateSel) stateSel.addEventListener('change', () => { fillCities(getIso2(), citySel ? citySel.value : ''); });
if (citySel) citySel.addEventListener('change', () => { if (cityInput) cityInput.value = citySel.value; });
