// Champ à suggestions accessible (combobox) : flèches, Entrée, Échap, clic. `search(q)` rend une liste d'objets { id, label }.
export function autocomplete(input, list, search, onPick) {
    let items = [];
    let active = -1;
    let request = 0;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const highlight = (index) => {
        active = index;
        [...list.children].forEach((li, i) => li.setAttribute('aria-selected', String(i === index)));
        if (index >= 0) input.setAttribute('aria-activedescendant', list.children[index].id);
    };

    const pick = (item) => {
        close();
        onPick(item);
    };

    const render = () => {
        list.replaceChildren(...items.map((item, i) => {
            const li = document.createElement('li');
            li.id = `${list.id}-${i}`;
            li.role = 'option';
            li.textContent = item.label;
            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                pick(item);
            });
            return li;
        }));
        list.hidden = !items.length;
        input.setAttribute('aria-expanded', String(!!items.length));
        highlight(items.length ? 0 : -1);
    };

    input.addEventListener('input', async () => {
        const id = ++request;
        const found = await search(input.value.trim());
        if (id === request) {
            items = found;
            render();
        }
    });
    input.addEventListener('keydown', (event) => {
        if (list.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            pick(items[active]);
        } else if (event.key === 'Escape') {
            close();
        }
    });
    input.addEventListener('blur', close);
}

/** Tous les mots tapés doivent apparaître dans le libellé, accents et casse ignorés */
export function matcher(entries, limit = 8) {
    const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const indexed = entries.map((entry) => [fold(entry.label), entry]);
    return (query) => {
        const words = fold(query).split(/\s+/).filter(Boolean);
        if (!words.length) return [];
        return indexed.filter(([label]) => words.every((w) => label.includes(w))).slice(0, limit).map(([, entry]) => entry);
    };
}
