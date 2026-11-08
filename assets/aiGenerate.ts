import axios from 'axios';
import {showToast} from './toast';

export interface FixedDate {
    readonly day: number;
    readonly month: number;
}

/** A floating holiday has no date of its own; the server reads the rule and resolves this year's. */
export interface FloatingRule {
    readonly algorithm: string;
    readonly algorithm_args: Readonly<Record<string, unknown>>;
}

export type GenerateDate = FixedDate | FloatingRule;

interface GenerateFields {
    readonly type: 'description_pl' | 'description' | 'name';
    readonly language?: string;
    readonly target: () => HTMLTextAreaElement | HTMLInputElement | null;
    readonly name: () => string;
    /** Null while the date or the rule is incomplete or invalid. */
    readonly date: () => GenerateDate | null;
    readonly country?: () => string | undefined;
    readonly enabled?: () => boolean;
    readonly onGenerated?: () => void;
    /** The Source URL field a picked suggestion goes into. */
    readonly sourceUrl?: () => HTMLInputElement | null;
    /** Where the pages the web search returned are offered as the source. */
    readonly sourceSuggestions?: () => HTMLElement | null;
    /** Where a disagreement between the holiday's date and the sources is shown. */
    readonly dateNote?: () => HTMLElement | null;
}

export interface GenerateHandle {
    readonly refresh: () => void;
}

interface SourceSuggestion {
    readonly url: string;
    readonly title: string;
    readonly recommended: boolean;
}

function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

/**
 * Offers the pages behind a generated description under its Source URL field. Nothing is written
 * into the field until the admin picks one: the model's pick is marked, but a page it names is no
 * proof the text rests on it, so it is opened and chosen by hand. Titles and URLs come from web
 * search results, so they are set as text and attributes, never as markup.
 */
function showSourceSuggestions(container: HTMLElement | null, input: HTMLInputElement | null, sources: readonly SourceSuggestion[]): void {
    if (!container) {
        return;
    }
    container.replaceChildren();
    container.classList.toggle('d-none', !input || sources.length === 0);
    if (!input || sources.length === 0) {
        return;
    }

    const header = document.createElement('div');
    header.className = 'url-suggestions-header';
    const heading = document.createElement('span');
    heading.textContent = 'Pages the AI search found - open one and pick the source';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white';
    close.title = 'Dismiss';
    close.addEventListener('click', () => showSourceSuggestions(container, input, []));
    header.append(heading, close);
    container.append(header);

    for (const source of sources) {
        const row = document.createElement('div');
        row.className = 'url-suggestion';

        const text = document.createElement('div');
        text.className = 'url-suggestion-text';
        const title = document.createElement('span');
        title.className = 'url-suggestion-title';
        title.textContent = source.title !== '' ? source.title : hostOf(source.url);
        title.title = source.url;
        const host = document.createElement('span');
        host.className = 'url-suggestion-host';
        host.textContent = hostOf(source.url);
        text.append(title, host);
        if (source.recommended) {
            const badge = document.createElement('span');
            badge.className = 'url-suggestion-badge';
            badge.textContent = 'AI pick';
            badge.title = 'The page the model says the text relies on most';
            text.append(badge);
        }

        const visit = document.createElement('a');
        visit.className = 'btn btn-sm btn-outline-light';
        visit.href = source.url;
        visit.target = '_blank';
        visit.rel = 'noopener noreferrer';
        visit.title = 'Visit';
        const visitIcon = document.createElement('i');
        visitIcon.className = 'bi bi-box-arrow-up-right';
        visit.append(visitIcon);

        row.append(text, visit);
        if (input.value.trim() === source.url) {
            const current = document.createElement('span');
            current.className = 'url-suggestion-current';
            current.textContent = 'In use';
            row.append(current);
        } else {
            const use = document.createElement('button');
            use.type = 'button';
            use.className = 'btn btn-sm btn-outline-warning';
            use.textContent = 'Use';
            use.addEventListener('click', () => {
                input.value = source.url;
                input.dispatchEvent(new Event('input', {bubbles: true}));
                showSourceSuggestions(container, input, []);
            });
            row.append(use);
        }
        container.append(row);
    }
}

/**
 * What the search found about the date when it disagrees with the holiday's own. Shown, never
 * applied: holiday blogs disagree with each other, and the date stays the admin's decision.
 */
function showDateNote(container: HTMLElement | null, note: string | null): void {
    if (!container) {
        return;
    }
    container.replaceChildren();
    container.classList.toggle('d-none', !note);
    if (!note) {
        return;
    }
    const icon = document.createElement('i');
    icon.className = 'bi bi-calendar-x';
    const label = document.createElement('strong');
    label.textContent = 'Date check:';
    const text = document.createElement('span');
    text.textContent = note;
    container.append(icon, label, text);
}

export function attachGenerateHandler(btn: HTMLButtonElement, fields: GenerateFields): GenerateHandle {
    const refresh = (): void => {
        if (fields.enabled) {
            btn.disabled = !fields.enabled();
        }
    };
    btn.addEventListener('click', async () => {
        const target = fields.target();
        const name = (fields.name() || '').trim();
        const date = fields.date();

        if (!target || !name || !date) {
            showToast('danger', 'Fill in the date (or a valid rule) and the name first.');
            return;
        }

        const icon = btn.querySelector('i');
        const originalClass = icon?.className ?? '';
        if (icon) {
            icon.className = 'bi bi-arrow-repeat spin-icon';
        }
        btn.disabled = true;

        try {
            const payload: Record<string, unknown> = {
                ...date,
                type: fields.type,
                name,
            };
            if (fields.language) {
                payload.language = fields.language;
            }
            if (fields.country) {
                const country = fields.country();
                if (country && country !== 'null') {
                    payload.country = country;
                }
            }
            const res = await axios.post('/admin/api/generate', payload);
            if (res.data.error) {
                showToast('danger', res.data.error);
            } else {
                target.value = res.data.result;
                target.dispatchEvent(new Event('input', {bubbles: true}));
                showSourceSuggestions(fields.sourceSuggestions?.() ?? null, fields.sourceUrl?.() ?? null, res.data.sources ?? []);
                showDateNote(fields.dateNote?.() ?? null, res.data.date_note ?? null);
                if (fields.onGenerated) {
                    fields.onGenerated();
                }
            }
        } catch {
            showToast('danger', 'Failed to generate.');
        } finally {
            if (icon) {
                icon.className = originalClass;
            }
            refresh();
        }
    });
    refresh();
    return {refresh};
}
