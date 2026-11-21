const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

function request(url, { method = 'GET', body, headers = {} } = {}) {
    return fetch(url, {
        method,
        body,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, ...headers },
    });
}

function htmlToElement(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();

    return template.content.firstElementChild;
}

/**
 * Capture: save without reloading and prepend the new card to the inbox.
 */
function initCapture() {
    const form = document.getElementById('capture-form');
    if (!form) return;

    const textarea = form.querySelector('textarea');
    const feedback = form.querySelector('[data-capture-feedback]');

    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!textarea.value.trim()) return;

        const response = await request(form.action, { method: 'POST', body: new FormData(form) });

        if (!response.ok) {
            feedback.textContent = 'Saving failed.';
            return;
        }

        const { html } = await response.json();
        const list = document.querySelector('[data-item-list]');
        list.querySelector('[data-empty]')?.remove();
        list.prepend(htmlToElement(html));

        form.reset();
        textarea.focus();
        feedback.textContent = 'Saved, the AI is on it…';
        setTimeout(() => (feedback.textContent = ''), 3000);
    });
}

/**
 * Search: debounce input and swap the list with server-rendered results.
 */
function initSearch() {
    const form = document.getElementById('search-form');
    const target = document.getElementById('item-list');
    if (!form || !target) return;

    let timer;
    let controller;

    const run = async () => {
        const params = new URLSearchParams(new FormData(form));
        for (const [key, value] of [...params]) {
            if (!value) params.delete(key);
        }

        const url = `${form.action}?${params}`;
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(url, { headers: { 'X-Fragment': '1' }, signal: controller.signal });
            target.innerHTML = await response.text();
            history.replaceState(null, '', params.size ? url : form.action);
        } catch (error) {
            if (error.name !== 'AbortError') throw error;
        }
    };

    form.addEventListener('input', (event) => {
        clearTimeout(timer);
        timer = setTimeout(run, event.target.type === 'search' ? 300 : 0);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        run();
    });
}

/**
 * Poll cards that are still being processed and swap them when the AI is done.
 */
function pollProcessingItems() {
    document.querySelectorAll('[data-item-card][data-in-progress]').forEach(async (card) => {
        const response = await request(`/items/${card.dataset.itemCard}`);
        if (!response.ok) return;

        const { html, in_progress } = await response.json();
        if (!in_progress) card.replaceWith(htmlToElement(html));
    });
}

/**
 * Toggle actions done/open without a reload.
 */
function initActionToggles() {
    document.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-toggle-action]');
        if (!checkbox) return;

        const response = await request(checkbox.dataset.toggleAction, { method: 'PATCH' });
        if (!response.ok) {
            checkbox.checked = !checkbox.checked;
            return;
        }

        const { done } = await response.json();
        checkbox.checked = done;
        checkbox.parentElement.querySelector('[data-action-text]')?.classList.toggle('line-through', done);
        checkbox.parentElement.querySelector('[data-action-text]')?.classList.toggle('text-ink-400', done);
    });
}

/**
 * Ask: show a thinking state while the server retrieves notes and the AI answers.
 */
function initAsk() {
    const form = document.querySelector('[data-ask-form]');
    if (!form) return;

    form.addEventListener('submit', () => {
        form.querySelector('button').disabled = true;
        form.querySelector('[data-ask-loading]')?.classList.remove('hidden');
    });
}

/**
 * Pixel offset of the caret inside a textarea, measured with a hidden mirror element.
 */
function caretTop(textarea) {
    const style = getComputedStyle(textarea);
    const mirror = document.createElement('div');
    for (const property of ['boxSizing', 'width', 'paddingTop', 'paddingRight', 'paddingLeft', 'borderTopWidth', 'borderLeftWidth', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'tabSize']) {
        mirror.style[property] = style[property];
    }
    Object.assign(mirror.style, { position: 'absolute', visibility: 'hidden', whiteSpace: 'pre-wrap', overflowWrap: 'break-word', top: '0', left: '-9999px' });
    mirror.textContent = textarea.value.slice(0, textarea.selectionStart);
    const marker = document.createElement('span');
    marker.textContent = '\u200b';
    mirror.append(marker);
    document.body.append(mirror);

    const top = marker.offsetTop + marker.offsetHeight - textarea.scrollTop;
    mirror.remove();

    return Math.max(0, Math.min(top, textarea.clientHeight));
}

/**
 * Wiki-links: typing [[ in a note suggests existing notes and inserts [[#id|title]],
 * which keeps working when the AI later renames the target note.
 */
function initWikiLinks() {
    document.querySelectorAll('[data-wiki-input]').forEach((textarea) => {
        const menu = document.createElement('ul');
        menu.className = 'wiki-menu hidden';
        textarea.insertAdjacentElement('afterend', menu);
        textarea.parentElement.classList.add('relative');

        let results = [];
        let active = 0;
        let timer;

        const openQuery = () => {
            const before = textarea.value.slice(0, textarea.selectionStart);
            const match = before.match(/\[\[([^\[\]|\n]*)$/);

            return match ? { query: match[1], start: before.length - match[0].length } : null;
        };

        const close = () => {
            menu.classList.add('hidden');
            results = [];
        };

        const render = () => {
            menu.replaceChildren(
                ...results.map((item, index) => {
                    const option = document.createElement('li');
                    option.textContent = item.title;
                    option.className = index === active ? 'is-active' : '';
                    option.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        insert(item);
                    });

                    return option;
                }),
            );
            menu.style.top = `${textarea.offsetTop + caretTop(textarea) + 4}px`;
            menu.classList.toggle('hidden', results.length === 0);
        };

        const insert = (item) => {
            const open = openQuery();
            if (!open) return close();

            const title = item.title.replace(/[\[\]|]/g, '');
            const link = `[[#${item.id}|${title}]]`;
            const after = textarea.value.slice(textarea.selectionStart).replace(/^[^\[\]\n]*\]\]/, '');
            textarea.value = textarea.value.slice(0, open.start) + link + after;

            const caret = open.start + link.length;
            textarea.setSelectionRange(caret, caret);
            textarea.focus();
            close();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const lookup = async () => {
            const open = openQuery();
            if (!open) return close();

            const params = new URLSearchParams({ q: open.query });
            if (textarea.dataset.wikiExcept) params.set('except', textarea.dataset.wikiExcept);

            const response = await request(`/items/lookup?${params}`);
            if (!response.ok) return close();

            results = await response.json();
            active = 0;
            render();
        };

        textarea.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(lookup, 150);
        });
        textarea.addEventListener('blur', close);
        textarea.addEventListener('keydown', (event) => {
            if (results.length === 0) return;

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length;
                render();
            } else if (event.key === 'Enter' && !event.metaKey && !event.ctrlKey) {
                event.preventDefault();
                insert(results[active]);
            } else if (event.key === 'Escape') {
                close();
            }
        });
    });
}

/**
 * Writing view: preview tab, autosave for existing notes, a local draft for new ones, and ⌘S.
 */
function initWriting() {
    const form = document.querySelector('[data-write-form]');
    if (!form) return;

    const body = form.querySelector('[data-write-body]');
    const preview = form.querySelector('[data-write-preview]');
    const status = form.querySelector('[data-write-status]');
    const draftKey = form.dataset.draftKey;
    const fields = [form.elements.title, body];
    let timer;
    let dirty = false;

    const storage = {
        read() {
            try {
                return JSON.parse(localStorage.getItem(draftKey) ?? 'null');
            } catch {
                return null;
            }
        },
        write(value) {
            try {
                value ? localStorage.setItem(draftKey, JSON.stringify(value)) : localStorage.removeItem(draftKey);
            } catch {
                // Private mode or blocked storage: the draft just isn't kept.
            }
        },
    };

    if (draftKey && !body.value) {
        const draft = storage.read();
        if (draft?.body) {
            form.elements.title.value = draft.title ?? '';
            body.value = draft.body;
            status.textContent = 'Draft restored';
        }
    }

    const autosave = async () => {
        clearTimeout(timer);
        if (!dirty || !body.value.trim()) return;

        status.textContent = 'Saving…';
        const response = await request(form.action, { method: 'POST', body: new FormData(form) });
        if (!response.ok) {
            status.textContent = 'Not saved';
            return;
        }

        dirty = false;
        const { saved_at } = await response.json();
        status.textContent = `Saved at ${saved_at}`;
    };

    fields.forEach((field) =>
        field.addEventListener('input', () => {
            dirty = true;
            if (draftKey) {
                storage.write({ title: form.elements.title.value, body: body.value });
            } else {
                status.textContent = 'Unsaved changes';
                clearTimeout(timer);
                timer = setTimeout(autosave, 1500);
            }
        }),
    );

    form.addEventListener('keydown', (event) => {
        if (event.key === 's' && (event.metaKey || event.ctrlKey)) {
            event.preventDefault();
            form.dataset.autosave !== undefined ? autosave() : form.requestSubmit();
        }
    });

    form.addEventListener('submit', () => {
        dirty = false;
        if (draftKey) storage.write(null);
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty && form.dataset.autosave !== undefined) event.preventDefault();
    });

    form.querySelectorAll('[data-write-tab]').forEach((tab) =>
        tab.addEventListener('click', async () => {
            const showPreview = tab.dataset.writeTab === 'preview';
            form.querySelectorAll('[data-write-tab]').forEach((other) => other.classList.toggle('is-active', other === tab));
            body.classList.toggle('hidden', showPreview);
            preview.classList.toggle('hidden', !showPreview);

            if (!showPreview) {
                body.focus();
                return;
            }

            const data = new FormData();
            data.set('body', body.value);
            const response = await request(preview.dataset.previewUrl, { method: 'POST', body: data, headers: { Accept: 'text/html' } });
            preview.innerHTML = response.ok ? await response.text() : '<p>The preview could not be loaded.</p>';
        }),
    );
}

/**
 * Note page: reload once the AI has finished, so the new title, text and links show up.
 */
async function reloadWhenProcessed() {
    const banner = document.querySelector('[data-reload-when-processed]');
    if (!banner) return;

    const response = await request(banner.dataset.reloadWhenProcessed);
    if (!response.ok) return;

    const { in_progress } = await response.json();
    if (!in_progress) window.location.reload();
}

/**
 * Dropdown menus built on <details>: close on a click outside or on Escape.
 */
function initDropdowns() {
    const closeAll = (except) =>
        document.querySelectorAll('details[data-item-menu][open]').forEach((menu) => menu !== except && (menu.open = false));

    document.addEventListener('click', (event) => closeAll(event.target.closest('details[data-item-menu]')));
    document.addEventListener('keydown', (event) => event.key === 'Escape' && closeAll(null));
}

initCapture();
initDropdowns();
initAsk();
initSearch();
initActionToggles();
initWikiLinks();
initWriting();
setInterval(pollProcessingItems, 2500);
setInterval(reloadWhenProcessed, 2500);
