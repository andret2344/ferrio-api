const STORAGE_KEY = 'adminSidebarCollapsed';

function applyState(collapsed: boolean): void {
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
    try {
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch (e) {
        // ignore — non-persistent fallback
    }
}

function init(): void {
    const toggle = document.querySelector<HTMLButtonElement>('[data-sidebar-toggle]');
    if (!toggle) {
        return;
    }
    toggle.addEventListener('click', () => {
        const collapsed = !document.documentElement.classList.contains('sidebar-collapsed');
        applyState(collapsed);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
