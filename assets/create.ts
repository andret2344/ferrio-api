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
    initRowNavigation();
});
