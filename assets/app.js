import './styles/app.css';

const MAX = 4;
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- Catalogue : sélection ordonnée, 4 au plus, conservée d'un filtre à l'autre ---------- */
const form = document.querySelector('[data-compare-form]');
if (form) {
    const boxes = [...form.querySelectorAll('input[type=checkbox]')];
    const hidden = () => [...form.querySelectorAll('input[type=hidden][name="ids[]"]')];
    const labels = new Map([...boxes, ...hidden()].map((input) => [input.value, input.dataset.label]));
    const chips = form.querySelector('[data-chips]');
    const count = form.querySelector('[data-compare-count]');
    const submit = form.querySelector('[data-compare-submit]');
    // L'ordre de l'adresse est l'ordre des colonnes de la comparaison
    let order = (document.querySelector('[data-selection]')?.value ?? '').split(',').filter((id) => labels.has(id));

    const withSelection = (href) => {
        const url = new URL(href, location.href);
        if (order.length) url.searchParams.set('v', order.join(','));
        else url.searchParams.delete('v');
        return url.pathname + url.search;
    };

    const render = () => {
        const n = order.length;
        count.textContent = `${n} / ${MAX}`;
        submit.disabled = n < 2;
        boxes.forEach((box) => {
            box.checked = order.includes(box.value);
            box.disabled = !box.checked && n >= MAX;
            box.closest('tr').classList.toggle('is-picked', box.checked);
        });
        chips.replaceChildren(...order.map((id) => {
            const li = document.createElement('li');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.remove = id;
            remove.setAttribute('aria-label', `Retirer ${labels.get(id)}`);
            remove.textContent = '×';
            li.append(labels.get(id), remove);
            return li;
        }));
        if (!n) chips.append(Object.assign(document.createElement('li'), { className: 'chips-empty', textContent: 'Aucune voiture choisie' }));
        document.querySelectorAll('[data-selection]').forEach((input) => { input.value = order.join(','); });
        document.querySelectorAll('[data-keep-selection]').forEach((a) => { a.href = withSelection(a.href); });
    };

    const toggle = (id, on) => {
        order = order.filter((x) => x !== id);
        if (on && order.length < MAX) order.push(id);
        hidden().filter((input) => input.value === id && !on).forEach((input) => input.remove());
        render();
    };

    boxes.forEach((box) => box.addEventListener('change', () => toggle(box.value, box.checked)));
    chips.addEventListener('click', (event) => {
        const id = event.target.closest('[data-remove]')?.dataset.remove;
        if (id) toggle(id, false);
    });
    // Toute la ligne coche la case, sauf ses liens
    form.addEventListener('click', (event) => {
        const row = event.target.closest('[data-row]');
        if (!row || event.target.closest('a, input, label')) return;
        const box = row.querySelector('input[type=checkbox]');
        if (!box.disabled) toggle(box.value, !box.checked);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        location.href = `${form.action}?v=${order.join(',')}`;
    });
    render();
}

// Filtres et tri appliqués dès qu'on les change
document.querySelectorAll('[data-filters], [data-sort]').forEach((filters) => {
    filters.querySelectorAll('[data-filters-submit]').forEach((button) => { button.hidden = true; });
    filters.addEventListener('change', (event) => {
        if (event.target.matches('select, input[type=number]')) filters.requestSubmit();
    });
});

/* ---------- Comparaison : copier le lien partageable ---------- */
const copy = document.querySelector('[data-share-copy]');
copy?.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(copy.dataset.url);
        copy.textContent = 'Lien copié';
    } catch {
        prompt('Copiez ce lien :', copy.dataset.url);
    }
});

/* ---------- Accueil : le duel en direct ---------- */
const CRITERIA = [
    ['price', 'Prix', '€', false],
    ['power', 'Puissance', 'ch', true],
    ['consumption', 'Consommation', null, false],
    ['trunk', 'Coffre', 'L', true],
    ['co2', 'Émissions CO₂', 'g/km', false],
];
const number = (value, decimals) => value.toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

const data = document.getElementById('cars');
const picker = document.querySelector('[data-picker]');
if (data && picker) {
    const cars = new Map(JSON.parse(data.textContent).map((car) => [String(car.id), car]));
    const selects = [...picker.querySelectorAll('select')];
    const duel = document.querySelector('[data-stage="duel"]');
    const head = duel.querySelector('[data-duel-head]');
    const rows = duel.querySelector('[data-duel-rows]');
    const link = duel.querySelector('[data-duel-link]');

    const cell = (text, unit, win) => {
        const dd = document.createElement('dd');
        if (win) dd.className = 'win';
        dd.append(`${text} `, Object.assign(document.createElement('span'), { className: 'unit', textContent: unit }));
        return dd;
    };

    // Même règle que App\Compare\Comparator : meilleur selon le sens, consommation à unité égale
    const draw = (a, b) => {
        head.replaceChildren(...[a, b].map((car) => {
            const p = document.createElement('p');
            p.append(Object.assign(document.createElement('span'), { className: 'duel-brand', textContent: car.brand }),
                ` ${car.model} `, Object.assign(document.createElement('span'), { className: 'duel-version', textContent: car.name }));
            return p;
        }));
        rows.replaceChildren(...CRITERIA.map(([key, label, unit, higher]) => {
            const [ua, ub] = [unit ?? a.unit, unit ?? b.unit];
            const best = higher ? Math.max(a[key], b[key]) : Math.min(a[key], b[key]);
            const ranked = ua === ub;
            const decimals = key === 'consumption' ? 1 : 0;
            const row = document.createElement('div');
            row.className = 'duel-row';
            row.append(
                cell(number(a[key], decimals), ua, ranked && a[key] === best),
                Object.assign(document.createElement('dt'), { textContent: label }),
                cell(number(b[key], decimals), ub, ranked && b[key] === best),
            );
            return row;
        }));
        link.href = `${picker.action}?v=${a.id},${b.id}`;
    };

    const show = (a, b) => {
        if (reduced) return draw(a, b);
        duel.classList.add('is-swapping');
        setTimeout(() => { draw(a, b); duel.classList.remove('is-swapping'); }, 400);
    };

    selects.forEach((select) => select.addEventListener('change', () => {
        clearInterval(cycle);
        show(cars.get(selects[0].value), cars.get(selects[1].value));
    }));

    // Tant que personne n'y touche, le duel change tout seul : trois affiches tirées du catalogue
    const list = [...cars.values()];
    const by = (key, dir) => [...list].sort((x, y) => dir * (x[key] - y[key]));
    const duels = [
        selects.map((s) => cars.get(s.value)),
        [by('price', 1)[0], by('price', 1).find((c) => c.unit !== by('price', 1)[0].unit)],
        [by('power', -1)[0], by('trunk', -1)[0]],
    ].filter(([a, b]) => a && b && a.id !== b.id);
    let turn = 0;
    const cycle = reduced ? null : setInterval(() => {
        if (document.hidden) return;
        turn = (turn + 1) % duels.length;
        const [a, b] = duels[turn];
        selects[0].value = a.id;
        selects[1].value = b.id;
        show(a, b);
    }, 6000);
}

/* ---------- Accueil : le panneau fixe suit la colonne de texte ---------- */
const panels = [...document.querySelectorAll('[data-stage]')];
const steps = [...document.querySelectorAll('[data-stage-step]')];
if (panels.length && steps.length) {
    const mini = document.querySelector('.mini');
    const activate = (step) => {
        panels.forEach((panel) => panel.classList.toggle('is-shown', panel.dataset.stage.split(' ').includes(step)));
        mini.dataset.state = step;
        steps.forEach((s) => s.classList.toggle('is-active', s.dataset.stageStep === step));
    };
    const observer = new IntersectionObserver((entries) => {
        entries.filter((e) => e.isIntersecting).forEach((e) => activate(e.target.dataset.stageStep));
    }, { rootMargin: '-45% 0px -45% 0px' });
    steps.forEach((step) => observer.observe(step));
}

/* ---------- Entrées : chaque élément monte une fois quand il arrive à l'écran ---------- */
const reveals = document.querySelectorAll('[data-reveal]');
if (reveals.length && !reduced && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('js-reveal');
    const seen = new IntersectionObserver((entries) => {
        entries.filter((e) => e.isIntersecting).forEach((e) => {
            const siblings = [...e.target.parentElement.querySelectorAll(':scope > [data-reveal]')];
            e.target.style.transitionDelay = `${Math.max(0, siblings.indexOf(e.target)) * 80}ms`;
            e.target.classList.add('is-in');
            seen.unobserve(e.target);
        });
    }, { rootMargin: '0px 0px -10% 0px' });
    reveals.forEach((el) => seen.observe(el));
}
