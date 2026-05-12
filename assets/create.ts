import * as bootstrap from 'bootstrap';
import axios from 'axios';
import {attachGenerateHandler} from './aiGenerate';

interface CreatePayload {
    readonly success: boolean;
    readonly month: number;
    readonly message: string;
    readonly id: number;
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

function initRowNavigation(): void {
    document.querySelectorAll<HTMLElement>('.tr-holiday-clickable[data-href]').forEach(row => {
        const href = row.dataset.href!;
        row.addEventListener('click', event => {
            if ((event.target as HTMLElement).closest('a, button, input, select, textarea')) {
                return;
            }
            window.location.assign(href);
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                window.location.assign(href);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const createRoot = document.querySelector<HTMLElement>('[data-page="admin-create"]');
    if (createRoot) {
        const currentMonth = parseInt(createRoot.dataset.currentMonth ?? '0');
        initCreateForm(currentMonth);
        initInlineGenerateButtons();
    }
    initRowNavigation();
});
