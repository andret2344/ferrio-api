const STORAGE_KEY = 'adminSidebarCollapsed';
const GROUP_STORAGE_PREFIX = 'adminSidebarGroup:';

function applyState(collapsed: boolean): void {
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
    try {
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch (e) {
        // ignore — non-persistent fallback
    }
}

function isGroupCollapsedInStorage(name: string): boolean {
    try {
        return localStorage.getItem(`${GROUP_STORAGE_PREFIX}${name}`) === '0';
    } catch {
        return false;
    }
}

function applyGroupState(group: HTMLElement, button: HTMLElement, expanded: boolean): void {
    group.classList.toggle('sidebar-group-collapsed', !expanded);
    button.setAttribute('aria-expanded', String(expanded));
}

function initGroups(): void {
    document.querySelectorAll<HTMLButtonElement>('[data-sidebar-group]').forEach(button => {
        const group = button.closest<HTMLElement>('.sidebar-group');
        const name = button.dataset.sidebarGroup;
        if (!group || !name) {
            return;
        }
        // The group holding the current page always starts open, whatever was stored.
        if (!group.classList.contains('sidebar-group-active') && isGroupCollapsedInStorage(name)) {
            applyGroupState(group, button, false);
        }
        button.addEventListener('click', () => {
            const expanded = group.classList.contains('sidebar-group-collapsed');
            applyGroupState(group, button, expanded);
            try {
                localStorage.setItem(`${GROUP_STORAGE_PREFIX}${name}`, expanded ? '1' : '0');
            } catch {
                // ignore — non-persistent fallback
            }
        });
    });
}

function init(): void {
    initGroups();
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
