import * as bootstrap from 'bootstrap';

interface BootstrapModalEvent extends Event {
    readonly relatedTarget: HTMLElement | null;
}

interface PlatformMeta {
    readonly label: string;
    readonly icon: string;
}

const PLATFORM_META: Record<string, PlatformMeta> = {
    android: {label: 'Android', icon: 'bi-android2'},
    ios: {label: 'iOS', icon: 'bi-apple'},
    web: {label: 'Web', icon: 'bi-globe'},
    api: {label: 'API', icon: 'bi-braces'},
};

let pendingDetailHref: string | null = null;

function setReportField(id: string, value: string): void {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    if (value) {
        el.textContent = value;
        el.classList.remove('report-content-empty');
    } else {
        el.textContent = '—';
        el.classList.add('report-content-empty');
    }
}

function setReportHtml(id: string, html: string): void {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    if (html) {
        el.innerHTML = html;
        el.classList.remove('report-content-empty');
    } else {
        el.textContent = '—';
        el.classList.add('report-content-empty');
    }
}

function formatReportType(raw: string): string {
    if (!raw) {
        return '';
    }
    const spaced = raw.toLowerCase().replace(/_/g, ' ');
    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function platformBadgeHtml(raw: string): string {
    if (!raw) {
        return '';
    }
    const meta = PLATFORM_META[raw];
    if (!meta) {
        return `<span class='badge platform-badge platform-icon-unknown'><i class='bi bi-question-circle me-1'></i>${escapeHtml(raw)}</span>`;
    }
    return `<span class='badge platform-badge platform-icon-${raw}'><i class='bi ${meta.icon} me-1'></i>${escapeHtml(meta.label)}</span>`;
}

function flagEmoji(iso: string): string {
    if (!iso || iso.length !== 2) {
        return '';
    }
    const A = 0x1F1E6;
    const base = 'A'.charCodeAt(0);
    const codes = iso.toUpperCase().split('').map(c => A + c.charCodeAt(0) - base);
    if (codes.some(code => code < 0x1F1E6 || code > 0x1F1FF)) {
        return '';
    }
    return String.fromCodePoint(...codes);
}

function deviceCountryHtml(iso: string): string {
    if (!iso) {
        return '';
    }
    const flag = flagEmoji(iso);
    const label = escapeHtml(iso.toUpperCase());
    return flag ? `<span class='device-country-flag' aria-hidden='true'>${flag}</span> ${label}` : label;
}

function formatUserLine(name: string, email: string): string {
    if (name && email) {
        return `${name} (${email})`;
    }
    return name || email || '';
}

function populateReportMeta(row: HTMLElement, prefix: 'suggestionReport' | 'errorReport'): void {
    setReportField(`${prefix}Datetime`, row.dataset.reportDatetime ?? '');
    setReportField(`${prefix}User`, formatUserLine(row.dataset.reportUserName ?? '', row.dataset.reportUserEmail ?? ''));
    setReportField(`${prefix}UserId`, row.dataset.reportUserId ?? '');
    setReportHtml(`${prefix}Platform`, platformBadgeHtml(row.dataset.platform ?? ''));
    setReportField(`${prefix}Device`, row.dataset.realDevice ?? '');
    setReportHtml(`${prefix}DeviceCountry`, deviceCountryHtml(row.dataset.deviceCountry ?? ''));
}

function populateSuggestionContent(row: HTMLElement): void {
    setReportField('suggestionReportName', row.dataset.reportName ?? '');
    setReportField('suggestionReportDate', row.dataset.reportDate ?? '');
    setReportField('suggestionReportCountry', row.dataset.reportCountry ?? '');
    setReportField('suggestionReportDescription', row.dataset.reportDescription ?? '');
    populateReportMeta(row, 'suggestionReport');
}

function populateErrorContent(row: HTMLElement): void {
    setReportField('errorReportType', formatReportType(row.dataset.reportType ?? ''));
    setReportField('errorReportLanguage', row.dataset.reportLanguage ?? '');
    setReportField('errorReportDescription', row.dataset.reportDescription ?? '');
    populateReportMeta(row, 'errorReport');
}

function setReferredHoliday(row: HTMLElement | null): void {
    const card = document.getElementById('referredHolidayCard');
    const nameEl = document.getElementById('referredHolidayName');
    const descEl = document.getElementById('referredHolidayDescription');
    const emptyEl = document.getElementById('referredHolidayEmpty');
    const contentEl = document.getElementById('referredHolidayContent');
    if (!card || !nameEl || !descEl || !emptyEl || !contentEl) {
        return;
    }

    const name = row?.dataset.referredName ?? '';
    const description = row?.dataset.referredDescription ?? '';
    pendingDetailHref = row?.dataset.detailHref || null;

    nameEl.textContent = name;
    descEl.textContent = description;

    const isEmpty = !name && !description;
    contentEl.classList.toggle('d-none', isEmpty);
    emptyEl.classList.toggle('d-none', !isEmpty);
    descEl.classList.toggle('d-none', !description);

    if (pendingDetailHref) {
        contentEl.classList.add('clickable');
        contentEl.setAttribute('role', 'button');
    } else {
        contentEl.classList.remove('clickable');
        contentEl.removeAttribute('role');
    }
}

function makeStateSetter(modalEl: HTMLElement, hiddenInput: HTMLInputElement): (value: string) => void {
    const toggleBadge = modalEl.querySelector<HTMLElement>('[data-role="state-toggle-badge"]');
    return (value: string) => {
        hiddenInput.value = value;
        if (toggleBadge) {
            toggleBadge.textContent = value;
            toggleBadge.className = `badge report-state report-state-${value.toLowerCase()}`;
            toggleBadge.dataset.role = 'state-toggle-badge';
        }
    };
}

function bindStateMenu(modalEl: HTMLElement, setState: (value: string) => void, onChange?: () => void): void {
    const stateMenu = modalEl.querySelector<HTMLElement>('.state-dropdown-menu');
    stateMenu?.querySelectorAll<HTMLElement>('.dropdown-item').forEach(item =>
        item.addEventListener('click', () => {
            setState(item.dataset.value || '');
            onChange?.();
        }));
}

interface ModerationModalOptions {
    readonly modalId: string;
    readonly kindInputId: string;
    readonly idInputId: string;
    readonly stateInputId: string;
    readonly commentInputId: string;
    readonly errorBoxId: string;
    readonly saveBtnId: string;
    readonly deleteBtnId: string;
    readonly holidayIdInputId?: string;
    readonly onShow?: (trigger: HTMLElement) => void;
    readonly onHide?: () => void;
}

function initModerationModal(opts: ModerationModalOptions): void {
    const modalEl = document.getElementById(opts.modalId);
    if (!modalEl) {
        return;
    }

    const moderateUrl = modalEl.dataset.moderateUrl || '';
    const deleteUrl = modalEl.dataset.deleteUrl || '';
    const kindInput = document.getElementById(opts.kindInputId) as HTMLInputElement;
    const idInput = document.getElementById(opts.idInputId) as HTMLInputElement;
    const stateInput = document.getElementById(opts.stateInputId) as HTMLInputElement;
    const commentInput = document.getElementById(opts.commentInputId) as HTMLTextAreaElement;
    const errorBox = document.getElementById(opts.errorBoxId) as HTMLDivElement;
    const saveBtn = document.getElementById(opts.saveBtnId) as HTMLButtonElement;
    const deleteBtn = document.getElementById(opts.deleteBtnId) as HTMLButtonElement;
    const holidayIdInput = opts.holidayIdInputId
        ? document.getElementById(opts.holidayIdInputId) as HTMLInputElement
        : null;

    let initialState = '';
    let initialComment = '';
    let initialHolidayId = '';
    const refreshDirty = () => {
        const dirty = stateInput.value !== initialState
            || commentInput.value !== initialComment
            || (holidayIdInput !== null && holidayIdInput.value !== initialHolidayId);
        saveBtn.disabled = !dirty;
    };

    const setState = makeStateSetter(modalEl, stateInput);
    bindStateMenu(modalEl, setState, refreshDirty);

    modalEl.addEventListener('show.bs.modal', event => {
        const trigger = (event as BootstrapModalEvent).relatedTarget;
        if (!trigger) {
            return;
        }
        kindInput.value = trigger.dataset.kind || '';
        idInput.value = trigger.dataset.id || '';
        setState(trigger.dataset.state || '');
        commentInput.value = trigger.dataset.comment || '';
        if (holidayIdInput) {
            holidayIdInput.value = trigger.dataset.holidayId || '';
            holidayIdInput.classList.remove('is-invalid');
        }
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        opts.onShow?.(trigger);
        initialState = stateInput.value;
        initialComment = commentInput.value;
        initialHolidayId = holidayIdInput?.value ?? '';
        saveBtn.disabled = true;
    });

    if (opts.onHide) {
        modalEl.addEventListener('hidden.bs.modal', opts.onHide);
    }

    commentInput.addEventListener('input', refreshDirty);
    holidayIdInput?.addEventListener('input', () => {
        holidayIdInput.classList.remove('is-invalid');
        refreshDirty();
    });

    deleteBtn.addEventListener('click', () =>
        deleteReport(modalEl, deleteUrl, kindInput, idInput, errorBox, saveBtn, deleteBtn));

    saveBtn.addEventListener('click', async () => {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        holidayIdInput?.classList.remove('is-invalid');
        saveBtn.disabled = true;

        try {
            const body: Record<string, unknown> = {
                kind: kindInput.value,
                id: Number(idInput.value),
                report_state: stateInput.value,
                comment: commentInput.value,
            };
            if (holidayIdInput) {
                body.holiday_id = holidayIdInput.value;
            }
            const response = await fetch(moderateUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                if (payload.field === 'holiday_id' && holidayIdInput) {
                    holidayIdInput.classList.add('is-invalid');
                }
                throw new Error(payload.error || `HTTP ${response.status}`);
            }

            const payload = await response.json();
            updateRow(kindInput.value, idInput.value, payload.report_state, payload.comment, payload.holiday_id ?? null);
            bootstrap.Modal.getInstance(modalEl)?.hide();
        } catch (err) {
            errorBox.textContent = (err as Error).message || 'Request failed';
            errorBox.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });
}

function initSuggestionModal(): void {
    initModerationModal({
        modalId: 'moderateSuggestionModal',
        kindInputId: 'suggestionKind',
        idInputId: 'suggestionId',
        stateInputId: 'suggestionState',
        commentInputId: 'suggestionComment',
        holidayIdInputId: 'suggestionHolidayId',
        errorBoxId: 'suggestionError',
        saveBtnId: 'suggestionSave',
        deleteBtnId: 'suggestionDelete',
        onShow: trigger => {
            populateSuggestionContent(trigger);
        },
    });
}

function initErrorModal(): void {
    const referredContent = document.getElementById('referredHolidayContent');
    if (referredContent) {
        const openDetail = () => {
            if (!pendingDetailHref) {
                return;
            }
            window.location.assign(pendingDetailHref);
        };
        referredContent.addEventListener('click', openDetail);
        referredContent.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDetail();
            }
        });
    }

    initModerationModal({
        modalId: 'moderateErrorModal',
        kindInputId: 'errorKind',
        idInputId: 'errorId',
        stateInputId: 'errorState',
        commentInputId: 'errorComment',
        errorBoxId: 'errorError',
        saveBtnId: 'errorSave',
        deleteBtnId: 'errorDelete',
        onShow: trigger => {
            setReferredHoliday(trigger);
            populateErrorContent(trigger);
        },
        onHide: () => {
            pendingDetailHref = null;
        },
    });
}

async function deleteReport(
    modalEl: HTMLElement,
    deleteUrl: string,
    kindInput: HTMLInputElement,
    idInput: HTMLInputElement,
    errorBox: HTMLDivElement,
    saveBtn: HTMLButtonElement,
    deleteBtn: HTMLButtonElement,
): Promise<void> {
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
}

function initRowTriggers(): void {
    const suggestionModalEl = document.getElementById('moderateSuggestionModal');
    const errorModalEl = document.getElementById('moderateErrorModal');
    document.querySelectorAll<HTMLElement>('tr.report-row').forEach(row => {
        const kind = row.dataset.kind || '';
        const target = kind.endsWith('_error') ? errorModalEl : suggestionModalEl;
        if (!target) {
            return;
        }
        const open = () => bootstrap.Modal.getOrCreateInstance(target).show(row);
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

function refreshPaneSummary(): void {
    const pane = document.querySelector<HTMLElement>('.reports-pane');
    if (!pane) {
        return;
    }
    const rows = pane.querySelectorAll<HTMLElement>('tr.report-row');
    const total = rows.length;
    const pending = Array.from(rows).filter(row => row.dataset.state === 'REPORTED').length;

    const totalEl = pane.querySelector<HTMLElement>('[data-role="total-count"]');
    if (totalEl) {
        totalEl.textContent = String(total);
    }
    const nounEl = pane.querySelector<HTMLElement>('[data-role="total-noun"]');
    if (nounEl) {
        nounEl.textContent = total === 1 ? 'report' : 'reports';
    }

    const pendingPill = pane.querySelector<HTMLElement>('[data-role="pending-pill"]');
    const pendingCount = pane.querySelector<HTMLElement>('[data-role="pending-count"]');
    if (pendingCount) {
        pendingCount.textContent = String(pending);
    }
    pendingPill?.classList.toggle('d-none', pending === 0);
}

function initSortableHeaders(): void {
    const table = document.querySelector<HTMLTableElement>('table.reports-table');
    if (!table) {
        return;
    }
    const headers = table.querySelectorAll<HTMLTableCellElement>('thead th[data-sort]');
    headers.forEach((th, columnIndex) => {
        th.classList.add('sortable');
        let direction: 'asc' | 'desc' | null = null;
        th.addEventListener('click', () => {
            const tbody = table.tBodies[0];
            if (!tbody) {
                return;
            }
            direction = direction === 'asc' ? 'desc' : 'asc';
            headers.forEach(other => {
                if (other !== th) {
                    other.classList.remove('sort-asc', 'sort-desc');
                }
            });
            th.classList.toggle('sort-asc', direction === 'asc');
            th.classList.toggle('sort-desc', direction === 'desc');

            const rows = Array.from(tbody.querySelectorAll<HTMLTableRowElement>('tr.report-row'));
            rows.sort((a, b) => {
                const av = (a.cells[columnIndex]?.dataset.sortValue ?? a.cells[columnIndex]?.textContent ?? '').trim();
                const bv = (b.cells[columnIndex]?.dataset.sortValue ?? b.cells[columnIndex]?.textContent ?? '').trim();
                const aNum = Number(av);
                const bNum = Number(bv);
                let cmp: number;
                if (!Number.isNaN(aNum) && !Number.isNaN(bNum) && av !== '' && bv !== '') {
                    cmp = aNum - bNum;
                } else {
                    cmp = av.localeCompare(bv);
                }
                return direction === 'asc' ? cmp : -cmp;
            });
            rows.forEach(row => tbody.appendChild(row));
        });
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
    if (holidayId !== null) {
        row.dataset.holidayId = String(holidayId);
    }

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

    refreshPaneSummary();
}

function removeRow(kind: string, id: string): void {
    const row = document.querySelector<HTMLElement>(
        `tr.report-row[data-kind="${kind}"][data-id="${id}"]`
    );
    if (!row) {
        return;
    }

    const commentBtn = row.querySelector<HTMLElement>('[data-role="comment-popover"]');
    if (commentBtn) {
        bootstrap.Popover.getInstance(commentBtn)?.dispose();
    }
    row.remove();

    refreshPaneSummary();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.reports-pane')) {
        return;
    }
    initSuggestionModal();
    initErrorModal();
    initRowTriggers();
    initCommentPopovers();
    initSortableHeaders();
});
