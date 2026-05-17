import * as bootstrap from 'bootstrap';
import axios from 'axios';
import {attachGenerateHandler, GenerateHandle} from './aiGenerate';

type Kind = 'fixed' | 'floating';

interface TranslationEntry {
    readonly name: string;
    readonly description: string | null;
}

interface LanguageDef {
    readonly code: string;
    readonly name: string;
}

interface TagDefinition {
    readonly id: number;
    readonly slug: string;
    readonly name: string;
}

interface TranslationSection {
    readonly code: string;
    readonly el: HTMLElement;
    readonly nameInput: HTMLInputElement;
    readonly descInput: HTMLTextAreaElement;
    readonly originalName: string;
    readonly originalDesc: string;
    readonly existedOnServer: boolean;
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

class TagPicker {
    private readonly all: TagDefinition[];
    private readonly byId: Map<number, TagDefinition>;
    private readonly selected: Set<number>;
    private readonly chipsEl: HTMLElement;
    private readonly menuEl: HTMLElement | null;
    private onChange: () => void = (): any => undefined;

    constructor(root: HTMLElement, tags: TagDefinition[]) {
        this.all = tags;
        this.byId = new Map(this.all.map(t => [t.id, t]));
        const initial: number[] = JSON.parse(root.dataset.selected ?? '[]');
        this.selected = new Set(initial.filter(id => this.byId.has(id)));
        this.chipsEl = root.querySelector<HTMLElement>('#meta-tag-chips')!;
        this.menuEl = root.querySelector<HTMLElement>('#meta-tag-menu');
        this.render();
    }

    setOnChange(fn: () => void): void {
        this.onChange = fn;
    }

    getSelected(): number[] {
        return [...this.selected].sort((a, b) => a - b);
    }

    private add(id: number): void {
        this.selected.add(id);
        this.render();
        this.onChange();
    }

    private remove(id: number): void {
        this.selected.delete(id);
        this.render();
        this.onChange();
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

class DetailPage {
    private readonly root: HTMLElement;
    private readonly kind: Kind;
    private readonly id: number;
    private readonly isNew: boolean;
    private readonly saveUrl: string;
    private readonly deleteUrl: string;
    private readonly backUrl: string;
    private readonly csrfToken: string;
    private readonly targets: LanguageDef[];
    private readonly tags: TagDefinition[];
    private readonly translations: Record<string, TranslationEntry>;

    private readonly sourceName: HTMLTextAreaElement;
    private readonly sourceDesc: HTMLTextAreaElement;
    private readonly metaDay: HTMLInputElement | null;
    private readonly metaMonth: HTMLInputElement | null;
    private readonly metaCountry: HTMLSelectElement;
    private readonly metaMature: HTMLInputElement;
    private readonly metaAlgorithm: HTMLSelectElement | null;
    private readonly metaAlgorithmArgs: HTMLTextAreaElement | null;
    private readonly metaAlgorithmArgsError: HTMLElement | null;
    private readonly metaAlgorithmArgsFill: HTMLButtonElement | null;
    private readonly tagPicker: TagPicker;
    private readonly sectionsRoot: HTMLElement;
    private readonly addMenu: HTMLElement;
    private readonly template: HTMLTemplateElement;
    private readonly saveBtn: HTMLButtonElement;
    private readonly errorBox: HTMLElement;
    private readonly saveIconClass: string;

    private sections: TranslationSection[] = [];
    private readonly removedFromServer: Set<string> = new Set();
    private readonly aiHandles: GenerateHandle[] = [];

    private readonly originalSourceName: string;
    private readonly originalSourceDesc: string;
    private readonly originalDay: string;
    private readonly originalMonth: string;
    private readonly originalCountry: string;
    private readonly originalMature: boolean;
    private readonly originalTagIds: string;
    private readonly originalAlgorithm: string;
    private readonly originalAlgorithmArgs: string;

    constructor(root: HTMLElement) {
        this.root = root;
        this.kind = (root.dataset.kind ?? 'fixed') as Kind;
        this.id = Number.parseInt(root.dataset.holidayId ?? '0');
        this.isNew = root.dataset.isNew === '1';
        this.saveUrl = root.dataset.saveUrl ?? '';
        this.deleteUrl = root.dataset.deleteUrl ?? '';
        this.backUrl = root.dataset.backUrl ?? '';
        this.csrfToken = root.dataset.csrfToken ?? '';
        try {
            this.targets = JSON.parse(root.dataset.targets ?? '[]');
        } catch {
            this.targets = [];
        }
        try {
            this.tags = JSON.parse(root.dataset.tags ?? '[]');
        } catch {
            this.tags = [];
        }
        const trScript = document.getElementById('translations-data');
        try {
            this.translations = trScript?.textContent ? JSON.parse(trScript.textContent) : {};
        } catch {
            this.translations = {};
        }

        this.sourceName = document.getElementById('source-name') as HTMLTextAreaElement;
        this.sourceDesc = document.getElementById('source-description') as HTMLTextAreaElement;
        this.metaDay = document.getElementById('meta-day') as HTMLInputElement | null;
        this.metaMonth = document.getElementById('meta-month') as HTMLInputElement | null;
        this.metaCountry = document.getElementById('meta-country') as HTMLSelectElement;
        this.metaMature = document.getElementById('meta-mature') as HTMLInputElement;
        this.metaAlgorithm = document.getElementById('meta-algorithm') as HTMLSelectElement | null;
        this.metaAlgorithmArgs = document.getElementById('meta-algorithm-args') as HTMLTextAreaElement | null;
        this.metaAlgorithmArgsError = document.getElementById('meta-algorithm-args-error');
        this.metaAlgorithmArgsFill = document.getElementById('meta-algorithm-args-fill') as HTMLButtonElement | null;
        this.sectionsRoot = document.getElementById('translate-sections') as HTMLElement;
        this.addMenu = document.getElementById('translate-add-menu') as HTMLElement;
        this.template = document.getElementById('translate-section-template') as HTMLTemplateElement;
        this.saveBtn = document.getElementById('detail-save') as HTMLButtonElement;
        this.errorBox = document.getElementById('detail-error') as HTMLElement;
        this.saveIconClass = this.saveBtn.querySelector('i')?.className ?? 'bi bi-check-lg me-1';

        const tagsRoot = document.getElementById('meta-tags') as HTMLElement;
        this.tagPicker = new TagPicker(tagsRoot, this.tags);
        this.tagPicker.setOnChange(() => this.refreshSaveButton());

        this.originalSourceName = this.sourceName.value;
        this.originalSourceDesc = this.sourceDesc.value;
        this.originalDay = this.metaDay?.value ?? '';
        this.originalMonth = this.metaMonth?.value ?? '';
        this.originalCountry = this.metaCountry.value;
        this.originalMature = this.metaMature.checked;
        this.originalTagIds = JSON.stringify(this.tagPicker.getSelected());
        this.originalAlgorithm = this.metaAlgorithm?.value ?? '';
        this.originalAlgorithmArgs = this.metaAlgorithmArgs?.value ?? '';

        this.attachMetaListeners();
        this.attachSourceAi();
        this.bootstrapTranslations();
        this.refreshAddMenu();
        this.initFixedStrategyDropdowns();
        this.updateAlgorithmPlaceholder();
        this.saveBtn.addEventListener('click', () => this.save());
        this.attachDeleteHandler();
        this.refreshSaveButton();
    }

    private attachDeleteHandler(): void {
        if (this.isNew || this.deleteUrl === '') {
            return;
        }
        const deleteBtn = document.getElementById('detail-delete') as HTMLButtonElement | null;
        const confirmBtn = document.getElementById('detail-delete-confirm') as HTMLButtonElement | null;
        const modalEl = document.getElementById('delete-confirm-modal');
        if (!deleteBtn || !confirmBtn || !modalEl) {
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        deleteBtn.addEventListener('click', () => modal.show());
        const confirmIconClass = confirmBtn.querySelector('i')?.className ?? 'bi bi-trash me-1';
        confirmBtn.addEventListener('click', async () => {
            setButtonLoading(confirmBtn, true, confirmIconClass);
            try {
                const res = await axios.post<{ success: boolean; errors?: string[] }>(this.deleteUrl, {
                    _token: this.csrfToken,
                });
                if (res.data.success) {
                    globalThis.location.assign(this.backUrl);
                    return;
                }
            } catch (err: unknown) {
                const errors = (err as { response?: { data?: { errors?: string[] } } }).response?.data?.errors;
                this.errorBox.textContent = errors ? errors.join(', ') : 'Failed to delete.';
                this.errorBox.classList.remove('d-none');
            }
            setButtonLoading(confirmBtn, false, confirmIconClass);
            modal.hide();
        });
    }

    private initFixedStrategyDropdowns(): void {
        const ids = ['translate-add-btn', 'meta-tag-add'];
        for (const id of ids) {
            const el = document.getElementById(id);
            if (!el) {
                continue;
            }
            bootstrap.Dropdown.getOrCreateInstance(el, {
                popperConfig: (defaultConfig: Record<string, unknown>) => ({...defaultConfig, strategy: 'fixed'}),
            });
        }
    }

    private attachMetaListeners(): void {
        const onChange = () => {
            this.refreshSaveButton();
            this.refreshAiButtons();
        };
        if (this.metaDay) {
            this.metaDay.addEventListener('input', onChange);
        }
        if (this.metaMonth) {
            this.metaMonth.addEventListener('input', onChange);
        }
        this.metaCountry.addEventListener('change', onChange);
        this.metaMature.addEventListener('change', onChange);
        if (this.metaAlgorithm) {
            this.metaAlgorithm.addEventListener('change', () => {
                this.updateAlgorithmPlaceholder();
                onChange();
            });
        }
        if (this.metaAlgorithmArgs) {
            this.metaAlgorithmArgs.addEventListener('input', () => {
                this.refreshAlgorithmArgsFill();
                onChange();
            });
        }
        if (this.metaAlgorithmArgsFill) {
            this.metaAlgorithmArgsFill.addEventListener('click', () => this.fillAlgorithmArgsFromPlaceholder());
        }
        this.sourceName.addEventListener('input', onChange);
        this.sourceDesc.addEventListener('input', onChange);
    }

    private attachSourceAi(): void {
        const descAi = document.getElementById('source-description-ai') as HTMLButtonElement | null;
        if (descAi) {
            const handle = attachGenerateHandler(descAi, {
                type: 'description_pl',
                target: () => this.sourceDesc,
                name: () => this.sourceName.value,
                month: () => this.currentMonth(),
                day: () => this.currentDay(),
                country: () => this.metaCountry.value,
                enabled: () => this.dateOrArgsReady() && this.sourceNameReady(),
            });
            this.aiHandles.push(handle);
        }
    }

    private dateOrArgsReady(): boolean {
        if (this.kind === 'fixed') {
            const day = (this.metaDay?.value ?? '').trim();
            const month = (this.metaMonth?.value ?? '').trim();
            return day !== '' && month !== '';
        }
        return (this.metaAlgorithmArgs?.value ?? '').trim() !== '';
    }

    private sourceNameReady(): boolean {
        return this.sourceName.value.trim() !== '';
    }

    private refreshAiButtons(): void {
        for (const handle of this.aiHandles) {
            handle.refresh();
        }
    }

    private currentMonth(): string {
        return this.metaMonth?.value || '1';
    }

    private currentDay(): string {
        return this.metaDay?.value || '1';
    }

    private bootstrapTranslations(): void {
        for (const target of this.targets) {
            const entry = this.translations[target.code];
            if (entry !== undefined) {
                this.appendSection(target, entry.name ?? '', entry.description ?? '', !this.isNew);
            }
        }
    }

    private appendSection(target: LanguageDef, name: string, description: string, existedOnServer: boolean): void {
        const fragment = this.template.content.cloneNode(true) as DocumentFragment;
        const sectionEl = fragment.querySelector<HTMLElement>('.translate-section')!;
        const codeEl = sectionEl.querySelector<HTMLElement>('[data-role="lang-code"]')!;
        const nameEl = sectionEl.querySelector<HTMLElement>('[data-role="lang-name"]')!;
        const nameInput = sectionEl.querySelector<HTMLInputElement>('[data-role="name-input"]')!;
        const descInput = sectionEl.querySelector<HTMLTextAreaElement>('[data-role="description-input"]')!;
        const nameLabel = sectionEl.querySelector<HTMLLabelElement>('[data-role="name-label"]')!;
        const descLabel = sectionEl.querySelector<HTMLLabelElement>('[data-role="description-label"]')!;
        const removeBtn = sectionEl.querySelector<HTMLButtonElement>('[data-role="remove"]')!;
        const aiName = sectionEl.querySelector<HTMLButtonElement>('[data-role="ai-name"]')!;
        const aiDesc = sectionEl.querySelector<HTMLButtonElement>('[data-role="ai-description"]')!;

        sectionEl.dataset.langCode = target.code;
        codeEl.textContent = target.code.toUpperCase();
        nameEl.textContent = target.name;
        nameInput.id = `translate-${target.code}-name`;
        descInput.id = `translate-${target.code}-description`;
        nameLabel.htmlFor = nameInput.id;
        descLabel.htmlFor = descInput.id;
        nameInput.value = name;
        descInput.value = description;

        const state: TranslationSection = {
            code: target.code,
            el: sectionEl,
            nameInput,
            descInput,
            originalName: existedOnServer ? name : '',
            originalDesc: existedOnServer ? description : '',
            existedOnServer,
        };

        const onChange = () => {
            this.updateDirtyMark(state);
            this.refreshSaveButton();
            this.refreshAiButtons();
        };
        nameInput.addEventListener('input', onChange);
        descInput.addEventListener('input', onChange);
        removeBtn.addEventListener('click', () => this.removeSection(state));

        this.aiHandles.push(attachGenerateHandler(aiName, {
            type: 'name',
            language: target.name,
            target: () => nameInput,
            name: () => this.sourceName.value,
            month: () => this.currentMonth(),
            day: () => this.currentDay(),
            country: () => this.metaCountry.value,
            enabled: () => this.dateOrArgsReady() && this.sourceNameReady(),
        }), attachGenerateHandler(aiDesc, {
            type: 'description',
            language: target.name,
            target: () => descInput,
            name: () => nameInput.value.trim() || this.sourceName.value,
            month: () => this.currentMonth(),
            day: () => this.currentDay(),
            country: () => this.metaCountry.value,
            enabled: () => this.dateOrArgsReady() && nameInput.value.trim() !== '',
        }));

        this.sectionsRoot.appendChild(sectionEl);
        this.sections.push(state);
        this.updateDirtyMark(state);
    }

    private removeSection(state: TranslationSection): void {
        this.sections = this.sections.filter(s => s !== state);
        state.el.remove();
        if (state.existedOnServer) {
            this.removedFromServer.add(state.code);
        }
        this.refreshAddMenu();
        this.refreshSaveButton();
    }

    private isSectionDirty(state: TranslationSection): boolean {
        return state.nameInput.value !== state.originalName || state.descInput.value !== state.originalDesc;
    }

    private updateDirtyMark(state: TranslationSection): void {
        state.el.classList.toggle('translate-section-dirty', this.isSectionDirty(state));
    }

    private refreshAddMenu(): void {
        const present = new Set(this.sections.map(s => s.code));
        this.addMenu.replaceChildren();
        const available = this.targets.filter(t => !present.has(t.code));
        const addContainer = document.querySelector<HTMLElement>('.translate-add');
        if (addContainer) {
            addContainer.classList.toggle('d-none', available.length === 0);
        }
        if (available.length === 0) {
            return;
        }
        const addBtn = document.getElementById('translate-add-btn');
        for (const target of available) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dropdown-item translate-add-item';
            btn.innerHTML = '<span class="translate-add-item-code"></span><span class="translate-add-item-name"></span>';
            btn.querySelector('.translate-add-item-code')!.textContent = target.code.toUpperCase();
            btn.querySelector('.translate-add-item-name')!.textContent = target.name;
            btn.addEventListener('click', () => {
                this.removedFromServer.delete(target.code);
                this.appendSection(target, '', '', false);
                this.refreshAddMenu();
                this.refreshSaveButton();
                if (addBtn) {
                    bootstrap.Dropdown.getInstance(addBtn)?.hide();
                }
                this.sections[this.sections.length - 1].nameInput.focus();
            });
            li.appendChild(btn);
            this.addMenu.appendChild(li);
        }
    }

    private metaDirty(): boolean {
        if ((this.metaDay?.value ?? '') !== this.originalDay) {
            return true;
        }
        if ((this.metaMonth?.value ?? '') !== this.originalMonth) {
            return true;
        }
        if (this.metaCountry.value !== this.originalCountry) {
            return true;
        }
        if (this.metaMature.checked !== this.originalMature) {
            return true;
        }
        if (JSON.stringify(this.tagPicker.getSelected()) !== this.originalTagIds) {
            return true;
        }
        if ((this.metaAlgorithm?.value ?? '') !== this.originalAlgorithm) {
            return true;
        }
        if ((this.metaAlgorithmArgs?.value ?? '') !== this.originalAlgorithmArgs) {
            return true;
        }
        return false;
    }

    private sourceDirty(): boolean {
        return this.sourceName.value !== this.originalSourceName
            || this.sourceDesc.value !== this.originalSourceDesc;
    }

    private translationsDirty(): boolean {
        if (this.removedFromServer.size > 0) {
            return true;
        }
        return this.sections.some(s => {
            if (this.isSectionDirty(s)) {
                return true;
            }
            return !s.existedOnServer && (s.nameInput.value.trim() !== '' || s.descInput.value.trim() !== '');
        });
    }

    private hasPendingChanges(): boolean {
        if (this.isNew) {
            return this.sourceName.value.trim() !== '';
        }
        return this.metaDirty() || this.sourceDirty() || this.translationsDirty();
    }

    private refreshSaveButton(): void {
        this.saveBtn.disabled = !this.hasPendingChanges();
    }

    private updateAlgorithmPlaceholder(): void {
        if (!this.metaAlgorithm || !this.metaAlgorithmArgs) {
            return;
        }
        let examples: Record<string, string>;
        try {
            examples = JSON.parse(this.metaAlgorithm.dataset.examples ?? '{}');
        } catch {
            examples = {};
        }
        this.metaAlgorithmArgs.placeholder = examples[this.metaAlgorithm.value] ?? '';
        this.refreshAlgorithmArgsFill();
    }

    private refreshAlgorithmArgsFill(): void {
        if (!this.metaAlgorithmArgs || !this.metaAlgorithmArgsFill) {
            return;
        }
        const empty = this.metaAlgorithmArgs.value === '';
        const hasPlaceholder = (this.metaAlgorithmArgs.placeholder ?? '') !== '';
        this.metaAlgorithmArgsFill.disabled = !empty || !hasPlaceholder;
    }

    private fillAlgorithmArgsFromPlaceholder(): void {
        if (!this.metaAlgorithmArgs) {
            return;
        }
        const placeholder = this.metaAlgorithmArgs.placeholder ?? '';
        if (placeholder === '' || this.metaAlgorithmArgs.value !== '') {
            return;
        }
        this.metaAlgorithmArgs.value = placeholder;
        this.metaAlgorithmArgs.dispatchEvent(new Event('input', {bubbles: true}));
        this.metaAlgorithmArgs.focus();
    }

    private showAlgorithmArgsError(message: string): void {
        if (this.metaAlgorithmArgs) {
            this.metaAlgorithmArgs.classList.add('is-invalid');
        }
        if (this.metaAlgorithmArgsError) {
            this.metaAlgorithmArgsError.textContent = message;
        }
    }

    private clearAlgorithmArgsError(): void {
        if (this.metaAlgorithmArgs) {
            this.metaAlgorithmArgs.classList.remove('is-invalid');
        }
        if (this.metaAlgorithmArgsError) {
            this.metaAlgorithmArgsError.textContent = '';
        }
    }

    private collectTranslations(): Record<string, { name: string; description: string }> {
        const payload: Record<string, { name: string; description: string }> = {};
        for (const code of this.removedFromServer) {
            payload[code] = {name: '', description: ''};
        }
        for (const s of this.sections) {
            if (s.existedOnServer && !this.isSectionDirty(s)) {
                continue;
            }
            const name = s.nameInput.value.trim();
            const desc = s.descInput.value;
            if (!s.existedOnServer && name === '' && desc.trim() === '') {
                continue;
            }
            payload[s.code] = {name, description: desc};
        }
        return payload;
    }

    private async save(): Promise<void> {
        this.errorBox.classList.add('d-none');
        this.errorBox.textContent = '';
        setButtonLoading(this.saveBtn, true, this.saveIconClass);

        const metadata: Record<string, unknown> = {
            country: this.metaCountry.value,
            mature: this.metaMature.checked,
            tags: this.tagPicker.getSelected(),
        };
        if (this.kind === 'fixed' && this.metaDay && this.metaMonth) {
            metadata.day = Number.parseInt(this.metaDay.value);
            metadata.month = Number.parseInt(this.metaMonth.value);
        }
        if (this.kind === 'floating' && this.metaAlgorithm && this.metaAlgorithmArgs) {
            metadata.algorithm = this.metaAlgorithm.value;
            const argsRaw = this.metaAlgorithmArgs.value;
            const trimmed = argsRaw.trim();
            if (trimmed === '') {
                metadata.algorithm_args = null;
                this.clearAlgorithmArgsError();
            } else {
                try {
                    const parsed = JSON.parse(trimmed);
                    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
                        throw new SyntaxError('not an object');
                    }
                    metadata.algorithm_args = argsRaw;
                    this.clearAlgorithmArgsError();
                } catch {
                    this.showAlgorithmArgsError('Algorithm args must be a valid JSON object.');
                    setButtonLoading(this.saveBtn, false, this.saveIconClass);
                    return;
                }
            }
        }

        try {
            const res = await axios.post<{ success: boolean; errors?: string[]; redirect?: string }>(this.saveUrl, {
                _token: this.csrfToken,
                source: {name: this.sourceName.value.trim(), description: this.sourceDesc.value},
                metadata,
                translations: this.collectTranslations(),
            });
            if (res.data.success) {
                if (this.isNew && res.data.redirect) {
                    globalThis.location.assign(res.data.redirect);
                    return;
                }
                showToast('success', 'Holiday saved.');
                globalThis.location.reload();
            }
        } catch (err: unknown) {
            const errors = (err as { response?: { data?: { errors?: string[] } } }).response?.data?.errors;
            this.errorBox.textContent = errors ? errors.join(', ') : 'Failed to save.';
            this.errorBox.classList.remove('d-none');
            setButtonLoading(this.saveBtn, false, this.saveIconClass);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector<HTMLElement>('[data-page="admin-holiday-detail"]');
    if (!root) {
        return;
    }
    new DetailPage(root);
});
