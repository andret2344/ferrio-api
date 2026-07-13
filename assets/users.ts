import * as bootstrap from 'bootstrap';
import {liftModal} from './modalStack';
import {initUserIdCopy} from './userIdCopy';

interface BootstrapModalEvent extends Event {
    readonly relatedTarget: HTMLElement | null;
}

export interface UserUpdatedDetail {
    readonly userId: string;
    readonly banned: boolean;
    readonly reason: string;
    readonly deletedReports: number;
}

export const USER_UPDATED_EVENT = 'ferrio:user-updated';

interface ReportCounts {
    readonly total: number;
    readonly pending: number;
}

async function postJson(url: string, token: string, body: Record<string, unknown>): Promise<any> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({...body, _token: token}),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.error || `HTTP ${response.status}`);
    }
    return payload;
}

async function fetchReportCounts(url: string, userId: string): Promise<ReportCounts> {
    const response = await fetch(`${url}?user_id=${encodeURIComponent(userId)}`, {
        headers: {'Accept': 'application/json'},
    });
    if (!response.ok) {
        return {total: 0, pending: 0};
    }
    const payload = await response.json();
    return {total: Number(payload.total ?? 0), pending: Number(payload.pending ?? 0)};
}

function showError(box: HTMLElement, message: string): void {
    box.textContent = message;
    box.classList.remove('d-none');
}

function clearError(box: HTMLElement): void {
    box.textContent = '';
    box.classList.add('d-none');
}

// The users pages render ban state, counters and tiles server-side, so a reload is the honest way to
// show the new state. The reports pages patch themselves in place instead (see reports.ts) so an
// open moderation flow is not thrown away.
function announce(detail: UserUpdatedDetail): void {
    if (document.querySelector('.users-page')) {
        window.location.reload();
        return;
    }
    document.dispatchEvent(new CustomEvent<UserUpdatedDetail>(USER_UPDATED_EVENT, {detail}));
}

function setCount(modalEl: HTMLElement, role: string, value: number): void {
    const el = modalEl.querySelector<HTMLElement>(`[data-role="${role}"]`);
    if (el) {
        el.textContent = `(${value})`;
    }
}

function initBanModal(): void {
    const modalEl = document.getElementById('banUserModal');
    if (!modalEl) {
        return;
    }
    const banUrl = modalEl.dataset.banUrl ?? '';
    const countsUrl = modalEl.dataset.countsUrl ?? '';
    const token = modalEl.dataset.csrfToken ?? '';
    const userIdInput = document.getElementById('banUserId') as HTMLInputElement;
    const reasonInput = document.getElementById('banReason') as HTMLTextAreaElement;
    const errorBox = document.getElementById('banError') as HTMLDivElement;
    const submitBtn = document.getElementById('banSubmit') as HTMLButtonElement;

    liftModal(modalEl);

    const refreshSubmit = () => {
        submitBtn.disabled = userIdInput.value.trim() === '' || reasonInput.value.trim() === '';
    };

    const refreshCounts = async () => {
        const userId = userIdInput.value.trim();
        if (!userId) {
            setCount(modalEl, 'ban-count-pending', 0);
            setCount(modalEl, 'ban-count-all', 0);
            return;
        }
        const counts = await fetchReportCounts(countsUrl, userId);
        setCount(modalEl, 'ban-count-pending', counts.pending);
        setCount(modalEl, 'ban-count-all', counts.total);
    };

    modalEl.addEventListener('show.bs.modal', event => {
        const trigger = (event as BootstrapModalEvent).relatedTarget;
        const userId = trigger?.dataset.userId ?? '';
        const manual = trigger?.dataset.manual === '1';
        userIdInput.value = userId;
        userIdInput.readOnly = !manual;
        reasonInput.value = trigger?.dataset.banReason ?? '';
        (document.getElementById('banScopeNone') as HTMLInputElement).checked = true;
        clearError(errorBox);
        refreshSubmit();
        void refreshCounts();
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        if (userIdInput.readOnly) {
            reasonInput.focus();
        } else {
            userIdInput.focus();
        }
    });

    userIdInput.addEventListener('input', refreshSubmit);
    userIdInput.addEventListener('change', () => void refreshCounts());
    reasonInput.addEventListener('input', refreshSubmit);

    submitBtn.addEventListener('click', async () => {
        clearError(errorBox);
        submitBtn.disabled = true;
        const userId = userIdInput.value.trim();
        const scope = modalEl.querySelector<HTMLInputElement>('input[name="ban_delete_reports"]:checked')?.value ?? 'none';
        try {
            const payload = await postJson(banUrl, token, {
                user_id: userId,
                reason: reasonInput.value.trim(),
                delete_reports: scope,
            });
            bootstrap.Modal.getInstance(modalEl)?.hide();
            announce({
                userId: payload.user_id,
                banned: true,
                reason: payload.reason,
                deletedReports: Number(payload.deleted_reports ?? 0),
            });
        } catch (err) {
            showError(errorBox, (err as Error).message || 'Request failed');
        } finally {
            refreshSubmit();
        }
    });
}

function initUnbanModal(): void {
    const modalEl = document.getElementById('unbanUserModal');
    if (!modalEl) {
        return;
    }
    const unbanUrl = modalEl.dataset.unbanUrl ?? '';
    const token = modalEl.dataset.csrfToken ?? '';
    const userIdEl = document.getElementById('unbanUserId') as HTMLElement;
    const errorBox = document.getElementById('unbanError') as HTMLDivElement;
    const submitBtn = document.getElementById('unbanSubmit') as HTMLButtonElement;

    liftModal(modalEl);

    let userId = '';
    modalEl.addEventListener('show.bs.modal', event => {
        const trigger = (event as BootstrapModalEvent).relatedTarget;
        userId = trigger?.dataset.userId ?? '';
        userIdEl.textContent = userId;
        clearError(errorBox);
        submitBtn.disabled = userId === '';
    });

    submitBtn.addEventListener('click', async () => {
        clearError(errorBox);
        submitBtn.disabled = true;
        try {
            await postJson(unbanUrl, token, {user_id: userId});
            bootstrap.Modal.getInstance(modalEl)?.hide();
            announce({userId: userId, banned: false, reason: '', deletedReports: 0});
        } catch (err) {
            showError(errorBox, (err as Error).message || 'Request failed');
        } finally {
            submitBtn.disabled = false;
        }
    });
}

function initDeleteReportsModal(): void {
    const modalEl = document.getElementById('deleteUserReportsModal');
    if (!modalEl) {
        return;
    }
    const deleteUrl = modalEl.dataset.deleteUrl ?? '';
    const token = modalEl.dataset.csrfToken ?? '';
    const summaryEl = document.getElementById('deleteUserReportsSummary') as HTMLElement;
    const errorBox = document.getElementById('deleteUserReportsError') as HTMLDivElement;
    const submitBtn = document.getElementById('deleteUserReportsSubmit') as HTMLButtonElement;

    liftModal(modalEl);

    let userId = '';
    modalEl.addEventListener('show.bs.modal', event => {
        const trigger = (event as BootstrapModalEvent).relatedTarget;
        userId = trigger?.dataset.userId ?? '';
        const label = trigger?.dataset.userLabel || userId;
        summaryEl.textContent = `Delete the reports of ${label}.`;

        const row = document.querySelector<HTMLElement>(`tr[data-user-row="${userId}"]`);
        const counts = row?.querySelector<HTMLElement>('[data-role="report-counts"]');
        setCount(modalEl, 'delete-count-pending', Number(counts?.dataset.pending ?? 0));
        setCount(modalEl, 'delete-count-all', Number(counts?.dataset.total ?? 0));

        (document.getElementById('deleteScopePending') as HTMLInputElement).checked = true;
        clearError(errorBox);
        submitBtn.disabled = userId === '';
    });

    submitBtn.addEventListener('click', async () => {
        clearError(errorBox);
        submitBtn.disabled = true;
        const scope = modalEl.querySelector<HTMLInputElement>('input[name="delete_reports_scope"]:checked')?.value ?? 'pending';
        try {
            const payload = await postJson(deleteUrl, token, {user_id: userId, scope: scope});
            bootstrap.Modal.getInstance(modalEl)?.hide();
            announce({
                userId: userId,
                banned: true,
                reason: '',
                deletedReports: Number(payload.deleted_reports ?? 0),
            });
        } catch (err) {
            showError(errorBox, (err as Error).message || 'Request failed');
        } finally {
            submitBtn.disabled = false;
        }
    });
}

function initTriggers(): void {
    const banModal = document.getElementById('banUserModal');
    const unbanModal = document.getElementById('unbanUserModal');
    const deleteModal = document.getElementById('deleteUserReportsModal');
    const wiring: ReadonlyArray<readonly [string, HTMLElement | null]> = [
        ['ban-open', banModal],
        ['unban-open', unbanModal],
        ['delete-reports-open', deleteModal],
    ];
    wiring.forEach(([role, modalEl]) => {
        if (!modalEl) {
            return;
        }
        document.querySelectorAll<HTMLElement>(`[data-role="${role}"]`).forEach(trigger => {
            trigger.addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(modalEl).show(trigger));
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('banUserModal')) {
        return;
    }
    initBanModal();
    initUnbanModal();
    initDeleteReportsModal();
    initTriggers();
    if (document.querySelector('.users-page')) {
        initUserIdCopy();
    }
});
