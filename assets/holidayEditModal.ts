import * as bootstrap from 'bootstrap';
import axios from 'axios';

export interface HolidayEditPayload {
    id: string | number;
    name: string;
    description: string;
    day: string | number;
    month: string | number;
    country: string;
    mature: boolean;
    tags: number[];
}

export interface HolidayUpdatePayload {
    success: boolean;
    id: number;
    month: number;
    day: number;
    name: string;
    description: string | null;
    countryCode: string | null;
    countryName: string | null;
    mature: boolean;
    tags: number[];
}

interface TagDefinition {
    id: number;
    slug: string;
    name: string;
}

class TagPicker {
    private readonly all: TagDefinition[];
    private readonly byId: Map<number, TagDefinition>;
    private selected: Set<number> = new Set();
    private readonly chipsEl: HTMLElement;
    private readonly menuEl: HTMLElement | null;

    constructor(root: HTMLElement) {
        try {
            this.all = JSON.parse(root.dataset.tags ?? '[]');
        } catch {
            this.all = [];
        }
        this.byId = new Map(this.all.map(t => [t.id, t]));
        this.chipsEl = root.querySelector<HTMLElement>('#edit-tag-chips')!;
        this.menuEl = root.querySelector<HTMLElement>('#edit-tag-menu');
    }

    setSelected(ids: number[]): void {
        this.selected = new Set(ids.filter(id => this.byId.has(id)));
        this.render();
    }

    getSelected(): number[] {
        return [...this.selected];
    }

    private add(id: number): void {
        this.selected.add(id);
        this.render();
    }

    private remove(id: number): void {
        this.selected.delete(id);
        this.render();
    }

    private render(): void {
        this.chipsEl.replaceChildren();
        [...this.selected]
            .map(id => this.byId.get(id)!)
            .sort((a, b) => a.name.localeCompare(b.name))
            .forEach(tag => this.chipsEl.appendChild(this.buildChip(tag)));

        if (!this.menuEl) {
            return;
        }
        this.menuEl.replaceChildren();
        const available = this.all
            .filter(t => !this.selected.has(t.id))
            .sort((a, b) => a.name.localeCompare(b.name));
        if (available.length === 0) {
            const li = document.createElement('li');
            li.className = 'tag-picker-empty';
            li.textContent = 'All tags selected';
            this.menuEl.appendChild(li);
            return;
        }
        available.forEach(tag => this.menuEl!.appendChild(this.buildMenuItem(tag)));
    }

    private buildChip(tag: TagDefinition): HTMLElement {
        const chip = document.createElement('span');
        chip.className = 'tag-picker-chip';
        chip.title = tag.slug;

        const icon = document.createElement('i');
        icon.className = 'bi bi-tag-fill tag-picker-chip-icon';
        chip.appendChild(icon);

        const label = document.createElement('span');
        label.className = 'tag-picker-chip-label';
        label.textContent = tag.name;
        chip.appendChild(label);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'tag-picker-chip-remove';
        remove.setAttribute('aria-label', 'Remove tag');
        remove.innerHTML = '<i class="bi bi-x-lg"></i>';
        remove.addEventListener('click', () => this.remove(tag.id));
        chip.appendChild(remove);

        return chip;
    }

    private buildMenuItem(tag: TagDefinition): HTMLElement {
        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dropdown-item tag-picker-menu-item';
        btn.title = tag.slug;
        btn.innerHTML = '<i class="bi bi-tag"></i><span></span>';
        btn.querySelector('span')!.textContent = tag.name;
        btn.addEventListener('click', () => this.add(tag.id));
        li.appendChild(btn);
        return li;
    }
}

interface GenerateFields {
    type: 'description_pl' | 'description' | 'name';
    language?: string;
    target: () => HTMLTextAreaElement | HTMLInputElement | null;
    name: () => string;
    month: () => string | undefined;
    day: () => string | undefined;
}

export function attachGenerateHandler(btn: HTMLButtonElement, fields: GenerateFields): void {
    btn.addEventListener('click', async () => {
        const target = fields.target();
        const name = (fields.name() || '').trim();
        const month = fields.month();
        const day = fields.day();

        if (!target || !name || !month || !day) {
            alert('Fill in day, month and name first.');
            return;
        }

        const icon = btn.querySelector('i');
        const originalClass = icon?.className ?? '';
        if (icon) {
            icon.className = 'bi bi-arrow-repeat spin-icon';
        }
        btn.disabled = true;

        try {
            const payload: Record<string, unknown> = {
                type: fields.type,
                day: parseInt(day),
                month: parseInt(month),
                name: name || undefined,
            };
            if (fields.language) {
                payload.language = fields.language;
            }
            const res = await axios.post('/admin/api/generate', payload);
            if (res.data.error) {
                alert(res.data.error);
            } else {
                target.value = res.data.result;
            }
        } catch {
            alert('Failed to generate.');
        } finally {
            if (icon) {
                icon.className = originalClass;
            }
            btn.disabled = false;
        }
    });
}

let initialized = false;
let modalEl: HTMLElement | null = null;
let tagPicker: TagPicker | null = null;
let idInput: HTMLInputElement | null = null;
let nameInput: HTMLTextAreaElement | null = null;
let descInput: HTMLTextAreaElement | null = null;
let dayInput: HTMLInputElement | null = null;
let monthInput: HTMLInputElement | null = null;
let countrySelect: HTMLSelectElement | null = null;
let matureInput: HTMLInputElement | null = null;
let errorBox: HTMLDivElement | null = null;
let acceptBtn: HTMLButtonElement | null = null;

const savedListeners: Array<(p: HolidayUpdatePayload) => void> = [];

export function onHolidayEditSaved(fn: (p: HolidayUpdatePayload) => void): void {
    savedListeners.push(fn);
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

export function initHolidayEditModal(): void {
    if (initialized) {
        return;
    }
    modalEl = document.getElementById('holidayEditModal');
    if (!modalEl) {
        return;
    }
    initialized = true;

    const updateUrl = modalEl.dataset.updateUrl ?? '';
    const csrfToken = modalEl.dataset.csrfToken ?? '';

    idInput = document.getElementById('edit-metadata-id') as HTMLInputElement;
    nameInput = document.getElementById('edit-name') as HTMLTextAreaElement;
    descInput = document.getElementById('edit-description') as HTMLTextAreaElement;
    dayInput = document.getElementById('edit-day') as HTMLInputElement;
    monthInput = document.getElementById('edit-month') as HTMLInputElement;
    countrySelect = document.getElementById('edit-country') as HTMLSelectElement;
    matureInput = document.getElementById('edit-mature') as HTMLInputElement;
    const tagsRoot = document.getElementById('edit-tags') as HTMLElement;
    errorBox = document.getElementById('edit-error') as HTMLDivElement;
    acceptBtn = document.getElementById('edit-accept') as HTMLButtonElement;
    const acceptIconClass = acceptBtn.querySelector('i')?.className ?? 'bi bi-check-lg me-1';

    tagPicker = new TagPicker(tagsRoot);

    const descAi = document.getElementById('edit-description-ai') as HTMLButtonElement | null;
    if (descAi) {
        attachGenerateHandler(descAi, {
            type: 'description_pl',
            target: () => descInput,
            name: () => nameInput?.value ?? '',
            month: () => monthInput?.value,
            day: () => dayInput?.value,
        });
    }

    acceptBtn.addEventListener('click', async () => {
        if (!modalEl || !idInput || !nameInput || !descInput || !dayInput || !monthInput || !countrySelect || !matureInput || !errorBox || !tagPicker || !acceptBtn) {
            return;
        }
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        setButtonLoading(acceptBtn, true, acceptIconClass);

        const data = new FormData();
        data.append('metadata_id', idInput.value);
        data.append('name', nameInput.value);
        data.append('description', descInput.value);
        data.append('day', dayInput.value);
        data.append('month', monthInput.value);
        data.append('country', countrySelect.value);
        if (matureInput.checked) {
            data.append('mature', '1');
        }
        tagPicker.getSelected().forEach(id => data.append('tags[]', String(id)));
        data.append('_token', csrfToken);

        try {
            const res = await axios.post<HolidayUpdatePayload>(updateUrl, data);
            if (res.data.success) {
                savedListeners.forEach(fn => fn(res.data));
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }
        } catch (err: unknown) {
            const errors = (err as { response?: { data?: { errors?: string[] } } }).response?.data?.errors;
            errorBox.textContent = errors ? errors.join(', ') : 'Failed to update holiday.';
            errorBox.classList.remove('d-none');
        } finally {
            setButtonLoading(acceptBtn, false, acceptIconClass);
        }
    });
}

export function openHolidayEditModal(data: HolidayEditPayload): void {
    initHolidayEditModal();
    if (!modalEl || !idInput || !nameInput || !descInput || !dayInput || !monthInput || !countrySelect || !matureInput || !errorBox || !tagPicker) {
        return;
    }
    errorBox.classList.add('d-none');
    errorBox.textContent = '';
    idInput.value = String(data.id);
    nameInput.value = data.name ?? '';
    descInput.value = data.description ?? '';
    dayInput.value = String(data.day ?? '');
    monthInput.value = String(data.month ?? '');
    countrySelect.value = data.country || 'null';
    matureInput.checked = !!data.mature;
    tagPicker.setSelected(data.tags ?? []);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}
