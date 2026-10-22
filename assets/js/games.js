// Jeux du jour : une partie par jeu et par jour, retenue sur l'appareil avec la série de chaque jeu.
// Les réponses viennent du serveur au moment de jouer : la page ne les contient pas.
import { autocomplete, matcher } from './autocomplete.js';

const STORAGE_KEY = 'visavis:jeux';
const page = document.querySelector('[data-game]');
const today = page?.dataset.date;

function load() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
    } catch {
        return {};
    }
}

function day() {
    return load()[today] ?? {};
}

function save(game, state) {
    const all = load();
    all[today] = { ...(all[today] ?? {}), [game]: state };
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    } catch {
        // stockage indisponible (navigation privée) : on joue sans mémoire
    }
}

/** Jours d'affilée où ce jeu a été terminé, jusqu'à aujourd'hui (ou hier si la partie du jour reste à jouer) */
function streak(game) {
    const all = load();
    const date = new Date(`${today}T12:00:00Z`);
    if (!all[today]?.[game]?.done) date.setUTCDate(date.getUTCDate() - 1);
    let count = 0;
    while (all[date.toISOString().slice(0, 10)]?.[game]?.done) {
        count += 1;
        date.setUTCDate(date.getUTCDate() - 1);
    }
    return count;
}

function showStreaks() {
    document.querySelectorAll('[data-streak]').forEach((badge) => {
        const count = streak(badge.dataset.streak);
        badge.hidden = count < 2;
        badge.textContent = `${count} jours d'affilée`;
    });
}

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message ?? 'Une erreur est survenue, réessayez.');
    return data;
}

/* ---------- À l'aveugle ---------- */
function setupBlind(section) {
    const questions = JSON.parse(section.querySelector('[data-questions]').textContent);
    const buttons = [...section.querySelectorAll('[data-choice]')];
    const step = section.querySelector('[data-blind-step]');
    const question = section.querySelector('[data-blind-question]');
    const result = section.querySelector('[data-blind-result]');
    const next = section.querySelector('[data-blind-next]');
    const compare = section.querySelector('[data-blind-compare]');
    let state = day().aveugle ?? { answers: [] };

    const reset = () => buttons.forEach((b) => {
        b.disabled = false;
        b.classList.remove('is-right', 'is-wrong', 'is-revealed');
        b.querySelector('[data-value]').textContent = '';
    });

    const ask = (index) => {
        reset();
        step.textContent = `Question ${index + 1} sur ${questions.length}`;
        question.textContent = questions[index];
        next.hidden = true;
    };

    const reveal = (index) => {
        const answer = state.answers[index];
        step.textContent = `Question ${index + 1} sur ${questions.length}`;
        question.textContent = questions[index];
        buttons.forEach((b, i) => {
            b.disabled = true;
            b.classList.add('is-revealed');
            b.classList.toggle('is-right', i === answer.answer);
            b.classList.toggle('is-wrong', i === answer.choice && !answer.correct);
            b.querySelector('[data-value]').textContent = answer.values[i];
        });
        const score = state.answers.filter((a) => a.correct).length;
        if (state.done) {
            result.textContent = `${answer.correct ? 'Bien vu.' : 'Raté.'} Score du jour : ${score} sur ${questions.length}.`;
            compare.href = state.compare;
            compare.hidden = !state.compare;
            next.hidden = true;
        } else {
            result.textContent = answer.correct ? 'Bien vu.' : 'Raté : la bonne réponse est en rose.';
            next.hidden = false;
        }
    };

    buttons.forEach((button) => button.addEventListener('click', async () => {
        const index = state.answers.length;
        buttons.forEach((b) => { b.disabled = true; });
        try {
            const data = await post('/jeu/aveugle', { question: index, choice: Number(button.dataset.choice) });
            state.answers.push({ choice: Number(button.dataset.choice), ...data });
            state.done = state.answers.length === questions.length;
            if (data.compare) state.compare = data.compare;
            save('aveugle', state);
            reveal(index);
            if (state.done) {
                showStreaks();
                showShare();
            }
        } catch (error) {
            result.textContent = error.message;
            buttons.forEach((b) => { b.disabled = false; });
        }
    }));
    next.addEventListener('click', () => {
        result.textContent = '';
        ask(state.answers.length);
        buttons[0].focus();
    });

    if (state.done) reveal(questions.length - 1);
    else if (state.answers.length) reveal(state.answers.length - 1);
    else ask(0);
}

/* ---------- Devine la voiture ---------- */
// Ce que la voiture à trouver est, par rapport à l'essai
const ARROWS = {
    price: { up: '↑ plus chère', down: '↓ moins chère', same: '= même prix' },
    power: { up: '↑ plus puissante', down: '↓ moins puissante', same: '= même puissance' },
};

function setupGuess(section) {
    const models = JSON.parse(section.querySelector('[data-models]').textContent);
    const form = section.querySelector('[data-guess-form]');
    const input = section.querySelector('[data-guess-input]');
    const triesLeft = section.querySelector('[data-tries-left]');
    const clues = section.querySelector('[data-clues]');
    const table = section.querySelector('[data-guesses]');
    const result = section.querySelector('[data-guess-result]');
    const tries = 6;
    const find = matcher(models);
    let picked = null;
    let state = day().devine ?? { guesses: [] };
    const photo = section.querySelector('[data-mystery-photo]');
    const credit = section.querySelector('[data-mystery-credit]');

    // Plus nette à chaque erreur, mais jamais tout à fait avant la fin : la vignette grandit, le flou baisse
    const BLUR = [16, 11, 10, 7, 6, 4, 0];
    const showPhoto = () => {
        if (!photo) return;
        const level = state.done ? 6 : Math.min(state.guesses.length, 5);
        photo.src = `${photo.dataset.src}${level}`;
        photo.style.setProperty('--blur', `${BLUR[level]}px`);
        photo.classList.toggle('is-sharp', level === 6);
        if (state.done && state.answer?.credit) {
            credit.replaceChildren('Photo : ', Object.assign(document.createElement('a'), {
                href: state.answer.credit.page, textContent: state.answer.credit.text, target: '_blank', rel: 'noopener',
            }), ', via Wikimedia Commons');
            credit.hidden = false;
        }
    };

    autocomplete(input, section.querySelector('.combo-list'), async (q) => find(q), (item) => {
        picked = item;
        input.value = item.label;
    });
    input.addEventListener('input', () => { picked = null; });

    const cell = (text, good) => {
        const td = document.createElement('td');
        td.textContent = text;
        if (good !== undefined) td.className = good ? 'is-right' : 'is-wrong';
        return td;
    };

    const render = () => {
        showPhoto();
        if (state.clues) {
            clues.replaceChildren(...state.clues.map((clue) => {
                const div = document.createElement('div');
                div.append(Object.assign(document.createElement('dt'), { textContent: clue.label }),
                    Object.assign(document.createElement('dd'), { textContent: clue.value }));
                return div;
            }));
        }
        table.hidden = !state.guesses.length;
        table.tBodies[0].replaceChildren(...state.guesses.map((row) => {
            const tr = document.createElement('tr');
            const th = Object.assign(document.createElement('th'), { scope: 'row', textContent: row.label });
            tr.append(th, cell(row.brand ? 'Oui' : 'Non', row.brand), cell(row.body ? 'Oui' : 'Non', row.body),
                cell(ARROWS.price[row.price], row.price === 'same'), cell(ARROWS.power[row.power], row.power === 'same'));
            return tr;
        }));
        const left = tries - state.guesses.length;
        triesLeft.textContent = `(${left} essai${left > 1 ? 's' : ''})`;
        if (state.done) {
            form.hidden = true;
            result.replaceChildren(
                state.won ? `Trouvé en ${state.guesses.length} essai${state.guesses.length > 1 ? 's' : ''} : ` : 'Perdu. C\'était ',
                Object.assign(document.createElement('a'), { href: state.answer.url, textContent: state.answer.label }),
                '.',
            );
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const guess = picked ?? find(input.value)[0];
        if (!guess) {
            result.textContent = 'Choisissez un modèle dans la liste.';
            return;
        }
        if (state.guesses.some((row) => row.label === guess.label)) {
            result.textContent = 'Déjà proposé.';
            return;
        }
        try {
            const data = await post('/jeu/devine', { model: guess.id, wrong: state.guesses.length });
            state.guesses.push(data.row);
            if (data.clues.length) state.clues = data.clues;
            state.won = data.correct;
            state.done = !!data.answer;
            state.answer = data.answer;
            save('devine', state);
            input.value = '';
            picked = null;
            result.textContent = data.correct ? '' : (state.done ? '' : 'Ce n\'est pas elle. Nouvel indice dévoilé.');
            render();
            if (state.done) {
                showStreaks();
                showShare();
            } else {
                input.focus();
            }
        } catch (error) {
            result.textContent = error.message;
        }
    });

    render();
}

/* ---------- Partage ---------- */
function shareText() {
    const { aveugle, devine } = day();
    const lines = [`Vis-à-vis · jeux du ${today.split('-').reverse().slice(0, 2).join('/')}`];
    if (aveugle?.done) {
        lines.push(`À l'aveugle : ${aveugle.answers.map((a) => (a.correct ? '🟩' : '🟥')).join('')} ${aveugle.answers.filter((a) => a.correct).length}/3`); // taste-skill-ignore : uniquement dans le texte partagé, jamais sur la page
    }
    if (devine?.done) {
        lines.push(`Devine la voiture : ${devine.won ? `${devine.guesses.length}/6` : 'X/6'}`);
    }
    lines.push(`${location.origin}/jeu`);
    return lines.join('\n');
}

function showShare() {
    const box = document.querySelector('[data-share]');
    const { aveugle, devine } = day();
    if (!aveugle?.done && !devine?.done) return;
    // Sur la page, le score en mots ; les carrés de couleur ne vont que dans le texte partagé
    const parts = [];
    if (aveugle?.done) parts.push(`À l'aveugle : ${aveugle.answers.filter((a) => a.correct).length} sur 3`);
    if (devine?.done) parts.push(`Devine la voiture : ${devine.won ? `trouvée en ${devine.guesses.length} essais` : 'pas trouvée'}`);
    box.querySelector('[data-share-text]').textContent = parts.join(' · ');
    box.hidden = false;
}

if (page) {
    setupBlind(document.querySelector('[data-blind]'));
    setupGuess(document.querySelector('[data-guess]'));
    showStreaks();
    showShare();
    const button = document.querySelector('[data-share-button]');
    button.addEventListener('click', async () => {
        const text = shareText();
        try {
            if (navigator.share) {
                await navigator.share({ text });
                return;
            }
            await navigator.clipboard.writeText(text);
            button.textContent = 'Copié';
        } catch {
            // partage annulé
        }
    });
}
