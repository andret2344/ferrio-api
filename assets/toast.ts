import * as bootstrap from 'bootstrap';

type ToastType = 'success' | 'danger';

/**
 * Shows a transient snackbar. Reuses Bootstrap's Toast (already bundled) and creates its own
 * fixed container on first use, so callers do not need a `#toast-container` in their template.
 */
export function showToast(type: ToastType, message: string): void {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class='d-flex'><div class='toast-body'>${message}</div><button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast'></button></div>`;
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, {delay: 3000});
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}
