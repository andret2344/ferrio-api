export {};

function init(): void {
    document.querySelectorAll<HTMLFormElement>('.language-delete-form').forEach(form => {
        const expectedName = form.dataset.expectedName ?? '';
        const input = form.querySelector<HTMLInputElement>('.language-delete-confirm');
        const submit = form.querySelector<HTMLButtonElement>('.language-delete-submit');
        if (!input || !submit) {
            return;
        }

        function update(): void {
            const value = input!.value.trim();
            submit!.disabled = value !== expectedName && value !== 'DELETE';
        }

        input.addEventListener('input', update);

        const modal = form.closest<HTMLElement>('.modal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', () => {
                input.value = '';
                submit.disabled = true;
            });
            modal.addEventListener('shown.bs.modal', () => {
                input.focus();
            });
        }

        form.addEventListener('submit', event => {
            const value = input.value.trim();
            if (value !== expectedName && value !== 'DELETE') {
                event.preventDefault();
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
