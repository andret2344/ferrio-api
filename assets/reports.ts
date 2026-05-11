import * as bootstrap from 'bootstrap';
import {HolidayEditPayload, initHolidayEditModal, onHolidayEditSaved, openHolidayEditModal,} from './holidayEditModal';

function setHash(id: string): void {
    return history.replaceState(null, '', `#${id}`);
}

function initTabHashSync(): void {
    const tabsContainer = document.getElementById('reportsTabs');
    if (!tabsContainer) {
        return;
    }

    const tabIds = ['fixed-suggestions', 'floating-suggestions', 'fixed-errors', 'floating-errors'];

    const raw = globalThis.location.hash.replace(/^#/, '');
    const initial = tabIds.includes(raw) ? raw : tabIds[0];

    if (raw !== initial) {
        setHash(initial);
    }

    if (initial !== tabIds[0]) {
        const trigger = document.querySelector<HTMLElement>(`[data-bs-target="#${initial}"]`);
        if (trigger) {
            trigger.click();
            setHash(initial);
        }
    }

    tabsContainer.querySelectorAll<HTMLElement>('[data-bs-toggle="tab"]').forEach(btn =>
        btn.addEventListener('shown.bs.tab', event => {
            const target = (event.target as HTMLElement).dataset.bsTarget || '';
            setHash(target.replace(/^#/, ''));
        }));
}

let activeRow: HTMLElement | null = null;
let pendingEditPayload: HolidayEditPayload | null = null;

function setReferredHoliday(row: HTMLElement | null): void {
    const card = document.getElementById('referredHolidayCard');
    const nameEl = document.getElementById('referredHolidayName');
    const descEl = document.getElementById('referredHolidayDescription');
    const emptyEl = document.getElementById('referredHolidayEmpty');
    const hintEl = document.getElementById('referredHolidayEditHint');
    const contentEl = document.getElementById('referredHolidayContent');
    if (!card || !nameEl || !descEl || !emptyEl || !hintEl || !contentEl) {
        return;
    }

    const name = row?.dataset.referredName ?? '';
    const description = row?.dataset.referredDescription ?? '';
    const canEdit = row?.dataset.canEdit === '1';

    nameEl.textContent = name;
    descEl.textContent = description;

    if (!name && !description) {
        emptyEl.classList.remove('d-none');
        nameEl.classList.add('d-none');
        descEl.classList.add('d-none');
    } else {
        emptyEl.classList.add('d-none');
        nameEl.classList.remove('d-none');
        descEl.classList.remove('d-none');
    }

    pendingEditPayload = null;
    if (canEdit && row?.dataset.editPayload) {
        try {
            pendingEditPayload = JSON.parse(row.dataset.editPayload) as HolidayEditPayload;
        } catch {
            pendingEditPayload = null;
        }
    }

    if (pendingEditPayload) {
        contentEl.classList.add('clickable');
        contentEl.setAttribute('role', 'button');
        hintEl.classList.remove('d-none');
    } else {
        contentEl.classList.remove('clickable');
        contentEl.removeAttribute('role');
        hintEl.classList.add('d-none');
    }
}

function initModerateModal(): void {
    const modalEl = document.getElementById('moderateModal');
    if (!modalEl) {
        return;
    }

    const moderateUrl = modalEl.dataset.moderateUrl || '';
    const deleteUrl = modalEl.dataset.deleteUrl || '';
    const kindInput = document.getElementById('moderateKind') as HTMLInputElement;
    const idInput = document.getElementById('moderateId') as HTMLInputElement;
    const stateInput = document.getElementById('moderateState') as HTMLInputElement;
    const stateToggleBadge = modalEl.querySelector<HTMLElement>('[data-role="state-toggle-badge"]');
    const stateMenu = modalEl.querySelector<HTMLElement>('.state-dropdown-menu');
    const commentInput = document.getElementById('moderateComment') as HTMLTextAreaElement;
    const holidayIdInput = document.getElementById('moderateHolidayId') as HTMLInputElement;
    const errorBox = document.getElementById('moderateError') as HTMLDivElement;
    const saveBtn = document.getElementById('moderateSave') as HTMLButtonElement;
    const deleteBtn = document.getElementById('moderateDelete') as HTMLButtonElement;
    const referredContent = document.getElementById('referredHolidayContent') as HTMLElement | null;

    const setState = (value: string) => {
        stateInput.value = value;
        if (stateToggleBadge) {
            stateToggleBadge.textContent = value;
            stateToggleBadge.className = `badge report-state report-state-${value.toLowerCase()}`;
            stateToggleBadge.dataset.role = 'state-toggle-badge';
        }
    };

    stateMenu?.querySelectorAll<HTMLElement>('.dropdown-item').forEach(item =>
        item.addEventListener('click', () => {
            const value = item.dataset.value || '';
            setState(value);
        }));

    modalEl.addEventListener('show.bs.modal', (event: Event) => {
        const trigger = (event as unknown as { relatedTarget: HTMLElement }).relatedTarget;
        if (!trigger) {
            return;
        }
        activeRow = trigger;
        kindInput.value = trigger.dataset.kind || '';
        idInput.value = trigger.dataset.id || '';
        setState(trigger.dataset.state || '');
        commentInput.value = trigger.dataset.comment || '';
        holidayIdInput.value = trigger.dataset.holidayId || '';
        holidayIdInput.classList.remove('is-invalid');
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        setReferredHoliday(trigger);
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        activeRow = null;
        pendingEditPayload = null;
    });

    if (referredContent) {
        const openEdit = () => {
            if (!pendingEditPayload) {
                return;
            }
            openHolidayEditModal(pendingEditPayload);
        };
        referredContent.addEventListener('click', openEdit);
        referredContent.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openEdit();
            }
        });
    }

    holidayIdInput.addEventListener('input', () => holidayIdInput.classList.remove('is-invalid'));

    deleteBtn.addEventListener('click', async () => {
        if (!confirm('Delete this report? This cannot be undone.')) {
            return;
        }
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        deleteBtn.disabled = true;
        saveBtn.disabled = true;

        try {
            const response = await fetch(deleteUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    kind: kindInput.value,
                    id: Number(idInput.value),
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.error || `HTTP ${response.status}`);
            }

            removeRow(kindInput.value, idInput.value);
            bootstrap.Modal.getInstance(modalEl)?.hide();
        } catch (err) {
            errorBox.textContent = (err as Error).message || 'Request failed';
            errorBox.classList.remove('d-none');
        } finally {
            deleteBtn.disabled = false;
            saveBtn.disabled = false;
        }
    });

    saveBtn.addEventListener('click', async () => {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        holidayIdInput.classList.remove('is-invalid');
        saveBtn.disabled = true;

        try {
            const response = await fetch(moderateUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    kind: kindInput.value,
                    id: Number(idInput.value),
                    report_state: stateInput.value,
                    comment: commentInput.value,
                    holiday_id: holidayIdInput.value,
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                if (payload.field === 'holiday_id') {
                    holidayIdInput.classList.add('is-invalid');
                }
                throw new Error(payload.error || `HTTP ${response.status}`);
            }

            const payload = await response.json();
            updateRow(kindInput.value, idInput.value, payload.report_state, payload.comment, payload.holiday_id);
            bootstrap.Modal.getInstance(modalEl)?.hide();
        } catch (err) {
            errorBox.textContent = (err as Error).message || 'Request failed';
            errorBox.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });
}

function initRowTriggers(): void {
    const modalEl = document.getElementById('moderateModal');
    if (!modalEl) {
        return;
    }
    document.querySelectorAll<HTMLElement>('tr.report-row').forEach(row => {
        const open = () => bootstrap.Modal.getOrCreateInstance(modalEl).show(row);
        row.addEventListener('click', event => {
            if ((event.target as HTMLElement).closest('a, button, input, select, textarea, [data-role="comment-popover"]')) {
                return;
            }
            open();
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    });
}

function createCommentPopover(el: HTMLElement): void {
    const existing = bootstrap.Popover.getInstance(el);
    if (existing) {
        existing.dispose();
    }
    new bootstrap.Popover(el, {trigger: 'focus', placement: 'top'});
}

function initCommentPopovers(): void {
    document.querySelectorAll<HTMLElement>('[data-role="comment-popover"]').forEach(el => {
        if (!el.classList.contains('d-none')) {
            createCommentPopover(el);
        }
    });
}

function updateRow(kind: string, id: string, state: string, comment: string | null, holidayId: number | null): void {
    const row = document.querySelector<HTMLElement>(
        `tr.report-row[data-kind="${kind}"][data-id="${id}"]`
    );
    if (!row) {
        return;
    }

    const badge = row.querySelector<HTMLElement>('[data-role="state-badge"]');
    if (badge) {
        badge.textContent = state;
        badge.className = `badge report-state report-state-${state.toLowerCase()}`;
        badge.dataset.role = 'state-badge';
    }

    row.dataset.state = state;
    row.dataset.comment = comment ?? '';
    row.dataset.holidayId = holidayId == null ? '' : String(holidayId);

    const commentBtn = row.querySelector<HTMLElement>('[data-role="comment-popover"]');
    if (commentBtn) {
        bootstrap.Popover.getInstance(commentBtn)?.dispose();
        if (comment && comment.trim() !== '') {
            commentBtn.dataset.bsContent = comment;
            commentBtn.classList.remove('d-none');
            createCommentPopover(commentBtn);
        } else {
            commentBtn.dataset.bsContent = '';
            commentBtn.classList.add('d-none');
        }
    }
}

function removeRow(kind: string, id: string): void {
    const row = document.querySelector<HTMLElement>(
        `tr.report-row[data-kind="${kind}"][data-id="${id}"]`
    );
    if (!row) {
        return;
    }

    const pane = row.closest<HTMLElement>('.tab-pane');
    const commentBtn = row.querySelector<HTMLElement>('[data-role="comment-popover"]');
    if (commentBtn) {
        bootstrap.Popover.getInstance(commentBtn)?.dispose();
    }
    row.remove();

    if (pane) {
        const remaining = pane.querySelectorAll('tr.report-row').length;
        const counter = pane.querySelector<HTMLElement>('.report-count');
        if (counter) {
            counter.textContent = `${remaining} ${remaining === 1 ? 'entry' : 'entries'}`;
        }
        const paneId = pane.id;
        const tabBadge = document.querySelector<HTMLElement>(`[data-bs-target="#${paneId}"] .badge`);
        if (tabBadge) {
            tabBadge.textContent = String(remaining);
        }
    }
}

function refreshReferredHolidayFromEdit(payload: {
    id: number;
    name: string;
    description: string | null;
    month: number;
    day: number;
    countryCode: string | null;
    mature: boolean;
    tags: number[];
}): void {
    document.querySelectorAll<HTMLElement>('tr.report-row').forEach(row => {
        const isFixed = row.dataset.kind?.startsWith('fixed_');
        if (!isFixed) {
            return;
        }
        const editPayloadRaw = row.dataset.editPayload;
        if (!editPayloadRaw) {
            return;
        }
        try {
            const data = JSON.parse(editPayloadRaw) as HolidayEditPayload;
            if (Number(data.id) !== payload.id) {
                return;
            }
            const next: HolidayEditPayload = {
                id: payload.id,
                name: payload.name,
                description: payload.description ?? '',
                day: payload.day,
                month: payload.month,
                country: payload.countryCode ?? '',
                mature: payload.mature,
                tags: payload.tags ?? [],
            };
            row.dataset.editPayload = JSON.stringify(next);
            const isErrorRow = row.dataset.kind === 'fixed_error';
            if (isErrorRow) {
                row.dataset.referredName = payload.name;
                row.dataset.referredDescription = payload.description ?? '';
            }
        } catch {
            // ignore
        }
    });

    if (activeRow) {
        setReferredHoliday(activeRow);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('reportsTabs')) {
        return;
    }
    initTabHashSync();
    initModerateModal();
    initRowTriggers();
    initCommentPopovers();
    initHolidayEditModal();
    onHolidayEditSaved(refreshReferredHolidayFromEdit);
});
