// Copy-to-clipboard button for a Firebase UID. The value is read from `data-user-id` at click time,
// so callers may re-point a button at another user without rebinding it.
export function initUserIdCopy(): void {
    document.querySelectorAll<HTMLButtonElement>('.user-id-copy').forEach(button => {
        button.addEventListener('click', async () => {
            const value = button.dataset.userId ?? '';
            if (!value) {
                return;
            }
            const icon = button.querySelector<HTMLElement>('i');
            try {
                await navigator.clipboard.writeText(value);
                button.classList.add('copied');
                icon?.classList.replace('bi-clipboard', 'bi-clipboard-check');
                globalThis.setTimeout(() => {
                    button.classList.remove('copied');
                    icon?.classList.replace('bi-clipboard-check', 'bi-clipboard');
                }, 1200);
            } catch {
                // Clipboard unavailable (insecure context / denied) — silently ignore.
            }
        });
    });
}
