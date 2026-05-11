import * as bootstrap from 'bootstrap';
import axios from 'axios';
import {
    attachGenerateHandler,
    HolidayEditPayload,
    HolidayUpdatePayload,
    initHolidayEditModal,
    onHolidayEditSaved,
    openHolidayEditModal,
} from './holidayEditModal';

interface CreatePayload {
    success: boolean;
    month: number;
    message: string;
}

function showToast(type: 'success' | 'danger', message: string): void {
    const container = document.getElementById('toast-container');
    if (!container) {
        return;
    }
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, {delay: 3000});
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function setButtonLoading(btn: HTMLButtonElement, loading: boolean, originalIconClass: string): void {
    const icon = btn.querySelector('i');
    if (loading) {
        btn.disabled = true;
        if (icon) {
            icon.className = 'bi bi-arrow-repeat spin-icon';
        }
    } else {
        btn.disabled = false;
        if (icon) {
            icon.className = originalIconClass;
        }
    }
}

function nl2br(str: string): string {
    const escaped = str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    return escaped.replace(/\r\n|\r|\n/g, '<br>');
}

function initCreateForm(currentMonth: number): void {
    const createForm = document.getElementById('create') as HTMLFormElement | null;
    const createBtn = document.querySelector<HTMLButtonElement>('button[type="submit"][form="create"]');
    if (!createForm || !createBtn) {
        return;
    }

    const createIcon = createBtn.querySelector('i');
    const createIconClass = createIcon?.className ?? 'bi bi-plus-lg';

    createForm.addEventListener('submit', async event => {
        event.preventDefault();
        setButtonLoading(createBtn, true, createIconClass);
        try {
            const res = await axios.post<CreatePayload>('/admin/api/holiday', new FormData(createForm));
            if (res.data.success) {
                if (res.data.month === currentMonth) {
                    window.location.reload();
                } else {
                    showToast('success', res.data.message);
                    createForm.reset();
                }
            }
        } catch (err: unknown) {
            const errors = (err as { response?: { data?: { errors?: string[] } } }).response?.data?.errors;
            showToast('danger', errors ? errors.join(', ') : 'Failed to create holiday.');
        } finally {
            setButtonLoading(createBtn, false, createIconClass);
        }
    });
}

function initInlineGenerateButtons(): void {
    document.querySelectorAll<HTMLButtonElement>('.btn-generate-desc[data-target]').forEach(btn => {
        if (btn.id === 'edit-description-ai') {
            return;
        }
        attachGenerateHandler(btn, {
            type: 'description_pl',
            target: () => document.getElementById(btn.dataset.target!) as HTMLTextAreaElement | null,
            name: () => (document.getElementById(btn.dataset.nameSource ?? '') as HTMLTextAreaElement | null)?.value ?? '',
            month: () => btn.dataset.monthSource
                ? (document.getElementById(btn.dataset.monthSource) as HTMLInputElement | null)?.value
                : btn.dataset.month,
            day: () => btn.dataset.daySource
                ? (document.getElementById(btn.dataset.daySource) as HTMLInputElement | null)?.value
                : btn.dataset.day,
        });
    });
}

function initRowClickTriggers(): void {
    const modalEl = document.getElementById('holidayEditModal');
    if (!modalEl) {
        return;
    }

    document.querySelectorAll<HTMLElement>('.tr-holiday-clickable').forEach(row => {
        const open = () => {
            let tagIds: number[] = [];
            try {
                tagIds = JSON.parse(row.dataset.tags || '[]');
            } catch {
                tagIds = [];
            }
            const payload: HolidayEditPayload = {
                id: row.dataset.id ?? '',
                name: row.dataset.name ?? '',
                description: row.dataset.description ?? '',
                day: row.dataset.day ?? '',
                month: row.dataset.month ?? '',
                country: row.dataset.country ?? '',
                mature: row.dataset.mature === '1',
                tags: tagIds,
            };
            openHolidayEditModal(payload);
        };
        row.addEventListener('click', event => {
            if ((event.target as HTMLElement).closest('a, button, input, select, textarea')) {
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

function applyRowUpdate(payload: HolidayUpdatePayload): void {
    const row = document.querySelector<HTMLElement>(`tr.tr-holiday[data-row-id="${payload.id}"]`);
    if (!row) {
        return;
    }

    const nameCell = row.querySelector<HTMLElement>('[data-role="name-cell"]');
    const descCell = row.querySelector<HTMLElement>('[data-role="description-cell"] .holiday-description');
    const monthCell = row.querySelector<HTMLElement>('[data-role="month-cell"]');
    const dayCell = row.querySelector<HTMLElement>('[data-role="day-cell"]');
    const countryCell = row.querySelector<HTMLElement>('[data-role="country-cell"]');
    const matureCell = row.querySelector<HTMLElement>('[data-role="mature-cell"]');

    if (nameCell) {
        nameCell.textContent = payload.name ?? '';
    }
    if (descCell) {
        descCell.innerHTML = nl2br(payload.description ?? '');
    }
    if (monthCell) {
        monthCell.textContent = String(payload.month);
    }
    if (dayCell) {
        dayCell.textContent = String(payload.day);
    }
    if (countryCell) {
        countryCell.dataset.country = payload.countryCode ?? '';
        countryCell.textContent = payload.countryName
            ? `${payload.countryCode} - ${payload.countryName}`
            : 'International';
    }
    if (matureCell) {
        matureCell.dataset.mature = payload.mature ? '1' : '0';
        matureCell.textContent = payload.mature ? 'Yes' : 'No';
    }
    row.dataset.name = payload.name ?? '';
    row.dataset.description = payload.description ?? '';
    row.dataset.month = String(payload.month);
    row.dataset.day = String(payload.day);
    row.dataset.country = payload.countryCode ?? '';
    row.dataset.mature = payload.mature ? '1' : '0';
    row.dataset.tags = JSON.stringify(payload.tags ?? []);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector<HTMLElement>('[data-page="admin-create"]');
    if (!root) {
        return;
    }
    const currentMonth = parseInt(root.dataset.currentMonth ?? '0');
    initCreateForm(currentMonth);
    initInlineGenerateButtons();
    initHolidayEditModal();
    onHolidayEditSaved(payload => {
        applyRowUpdate(payload);
        showToast('success', 'Holiday updated.');
    });
    initRowClickTriggers();
});
