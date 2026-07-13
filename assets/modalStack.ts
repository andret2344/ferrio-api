// Bootstrap does not raise the z-index of a modal opened on top of another one, so the second
// modal's backdrop falls behind the first (the page dims twice and the top modal never dims).
// Lift the modal and its backdrop above whatever is already open.
export function liftModal(modalEl: HTMLElement): void {
    modalEl.addEventListener('show.bs.modal', () => {
        const openCount = document.querySelectorAll('.modal.show').length;
        const zIndex = 1055 + (openCount + 1) * 20;
        modalEl.style.zIndex = String(zIndex);
        globalThis.setTimeout(() => {
            const backdrops = document.querySelectorAll<HTMLElement>('.modal-backdrop');
            const top = backdrops[backdrops.length - 1];
            if (top) {
                top.style.zIndex = String(zIndex - 10);
            }
        }, 0);
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
        modalEl.style.zIndex = '';
    });
}
