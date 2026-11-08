import * as bootstrap from 'bootstrap';
import {showToast} from './toast';

/**
 * The holiday import page (/admin/import) and its blacklist (/admin/import/blacklist).
 *
 * Every tile action answers with the tile re-rendered by the server (`html`), or `html: null` when the
 * tile is gone, and this file only swaps it in place - how a tile looks is decided in
 * templates/admin/import/_tile.html.twig alone.
 */

interface TileResponse {
    readonly html: string | null;
}

async function postJson(url: string, token: string, body: Record<string, unknown>): Promise<any> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify({...body, _token: token}),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.error || `HTTP ${response.status}`);
    }
    return payload;
}

function updateCount(): void {
    const count = document.querySelector<HTMLElement>('[data-import-count]');
    if (count) {
        count.textContent = `${document.querySelectorAll('[data-import-tile]').length}`;
    }
}

function replaceTile(tile: HTMLElement, html: string | null): void {
    if (html === null) {
        tile.remove();
        updateCount();
        return;
    }
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const fresh = template.content.firstElementChild;
    if (fresh) {
        tile.replaceWith(fresh);
    }
}

function setBusy(tile: HTMLElement, busy: boolean): void {
    tile.classList.toggle('import-tile-busy', busy);
    tile.querySelectorAll<HTMLButtonElement>('button').forEach(button => {
        button.disabled = busy;
    });
}

async function runAction(tile: HTMLElement, url: string, token: string, body: Record<string, unknown>): Promise<void> {
    setBusy(tile, true);
    try {
        const payload: TileResponse = await postJson(url, token, body);
        replaceTile(tile, payload.html);
    } catch (error) {
        setBusy(tile, false);
        showToast('danger', error instanceof Error ? error.message : 'Request failed.');
    }
}

async function verify(tile: HTMLElement, button: HTMLButtonElement, token: string): Promise<void> {
    // A searched answer takes a while; the spinner says the click registered.
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking…';
    await runAction(tile, tile.dataset.verifyUrl ?? '', token, {});
}

/**
 * "Add" opens the create form in a new tab. When the admin comes back, the tiles they added from are
 * asked for again, so one whose holiday now exists disappears without a reload.
 */
function refreshOnReturn(pending: Set<HTMLElement>): void {
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState !== 'visible' || pending.size === 0) {
            return;
        }
        const tiles = [...pending];
        pending.clear();
        for (const tile of tiles) {
            if (!tile.isConnected) {
                continue;
            }
            const response = await fetch(tile.dataset.showUrl ?? '', {headers: {'Accept': 'application/json'}});
            if (response.ok) {
                const payload: TileResponse = await response.json();
                replaceTile(tile, payload.html);
            }
        }
    });
}

function initRejectModal(token: string): (tile: HTMLElement) => void {
    const modalEl = document.getElementById('importRejectModal');
    if (!modalEl) {
        return () => undefined;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const name = modalEl.querySelector<HTMLElement>('[data-role="reject-name"]')!;
    const comment = modalEl.querySelector<HTMLTextAreaElement>('#import-reject-comment')!;
    const hint = modalEl.querySelector<HTMLElement>('[data-role="comment-hint"]')!;
    const errorBox = modalEl.querySelector<HTMLElement>('[data-role="reject-error"]')!;
    const confirm = modalEl.querySelector<HTMLButtonElement>('[data-role="reject-confirm"]')!;
    const reasons = [...modalEl.querySelectorAll<HTMLInputElement>('input[name="import-reject-reason"]')];
    let current: HTMLElement | null = null;

    function selected(): HTMLInputElement | undefined {
        return reasons.find(reason => reason.checked);
    }

    function refresh(): void {
        const requires = selected()?.dataset.requiresComment === '1';
        hint.textContent = requires ? '(required)' : '(optional)';
        confirm.disabled = requires && comment.value.trim() === '';
    }

    reasons.forEach(reason => reason.addEventListener('change', refresh));
    comment.addEventListener('input', refresh);

    confirm.addEventListener('click', async () => {
        if (!current) {
            return;
        }
        confirm.disabled = true;
        errorBox.classList.add('d-none');
        try {
            const payload: TileResponse = await postJson(current.dataset.rejectUrl ?? '', token, {
                reason: selected()?.value ?? '',
                comment: comment.value,
            });
            replaceTile(current, payload.html);
            modal.hide();
        } catch (error) {
            errorBox.textContent = error instanceof Error ? error.message : 'Request failed.';
            errorBox.classList.remove('d-none');
            refresh();
        }
    });

    return (tile: HTMLElement) => {
        current = tile;
        name.textContent = `“${tile.dataset.name ?? ''}”`;
        comment.value = '';
        if (reasons.length > 0) {
            reasons[0].checked = true;
        }
        errorBox.classList.add('d-none');
        refresh();
        modal.show();
    };
}

function initImportPage(page: HTMLElement): void {
    const token = page.dataset.csrfToken ?? '';
    const openReject = initRejectModal(token);
    const pendingAdds = new Set<HTMLElement>();
    refreshOnReturn(pendingAdds);

    page.addEventListener('click', event => {
        const target = event.target as HTMLElement;
        const tile = target.closest<HTMLElement>('[data-import-tile]');
        if (!tile) {
            return;
        }
        const link = target.closest<HTMLAnchorElement>('a[href*="/holiday/"][href*="/new"]');
        if (link) {
            pendingAdds.add(tile);
            return;
        }
        const button = target.closest<HTMLButtonElement>('[data-import-action]');
        if (!button) {
            return;
        }
        switch (button.dataset.importAction) {
            case 'remove':
                void runAction(tile, tile.dataset.removeUrl ?? '', token, {});
                break;
            case 'ignore':
                void runAction(tile, tile.dataset.ignoreUrl ?? '', token, {});
                break;
            case 'link':
                void runAction(tile, tile.dataset.linkUrl ?? '', token, {
                    kind: button.dataset.kind ?? '',
                    metadata_id: Number(button.dataset.metadataId ?? 0),
                });
                break;
            case 'verify':
                void verify(tile, button, token);
                break;
            case 'reject':
                openReject(tile);
                break;
        }
    });
}

function initBlacklistPage(page: HTMLElement): void {
    const token = page.dataset.csrfToken ?? '';
    page.addEventListener('click', async event => {
        const button = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-restore-url]');
        if (!button) {
            return;
        }
        button.disabled = true;
        try {
            await postJson(button.dataset.restoreUrl ?? '', token, {});
            button.closest('[data-blacklist-row]')?.remove();
            showToast('success', 'Restored - it can come back as a tile on the next paste.');
        } catch (error) {
            button.disabled = false;
            showToast('danger', error instanceof Error ? error.message : 'Request failed.');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const importPage = document.querySelector<HTMLElement>('[data-import-page]');
    if (importPage) {
        initImportPage(importPage);
    }
    const blacklistPage = document.querySelector<HTMLElement>('[data-import-blacklist]');
    if (blacklistPage) {
        initBlacklistPage(blacklistPage);
    }
});
