import * as bootstrap from 'bootstrap';
import axios from 'axios';

interface UpdatePayload {
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

interface CreatePayload {
	success: boolean;
	month: number;
	message: string;
}

interface GenerateFields {
	type: 'description_pl' | 'description' | 'name';
	language?: string;
	target: () => HTMLTextAreaElement | HTMLInputElement | null;
	name: () => string;
	month: () => string | undefined;
	day: () => string | undefined;
}

function showToast(type: 'success' | 'danger', message: string): void {
	const container = document.getElementById('toast-container');
	if (!container) return;
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
		if (icon) icon.className = 'bi bi-arrow-repeat spin-icon';
	} else {
		btn.disabled = false;
		if (icon) icon.className = originalIconClass;
	}
}

function nl2br(str: string): string {
	const escaped = str
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
	return escaped.replace(/\r\n|\r|\n/g, '<br>');
}

function attachGenerateHandler(btn: HTMLButtonElement, fields: GenerateFields): void {
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
		if (icon) icon.className = 'bi bi-arrow-repeat spin-icon';
		btn.disabled = true;

		try {
			const payload: Record<string, unknown> = {
				type: fields.type,
				day: parseInt(day),
				month: parseInt(month),
				name: name || undefined,
			};
			if (fields.language) payload.language = fields.language;
			const res = await axios.post('/admin/api/generate', payload);
			if (res.data.error) {
				alert(res.data.error);
			} else {
				target.value = res.data.result;
			}
		} catch {
			alert('Failed to generate.');
		} finally {
			if (icon) icon.className = originalClass;
			btn.disabled = false;
		}
	});
}

function initCreateForm(currentMonth: number): void {
	const createForm = document.getElementById('create') as HTMLFormElement | null;
	const createBtn = document.querySelector<HTMLButtonElement>('button[type="submit"][form="create"]');
	if (!createForm || !createBtn) return;

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

		if (!this.menuEl) return;
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

function initEditModal(): void {
	const modalEl = document.getElementById('holidayEditModal');
	if (!modalEl) return;

	const updateUrl = modalEl.dataset.updateUrl ?? '';
	const csrfToken = modalEl.dataset.csrfToken ?? '';

	const idInput = document.getElementById('edit-metadata-id') as HTMLInputElement;
	const nameInput = document.getElementById('edit-name') as HTMLTextAreaElement;
	const descInput = document.getElementById('edit-description') as HTMLTextAreaElement;
	const dayInput = document.getElementById('edit-day') as HTMLInputElement;
	const monthInput = document.getElementById('edit-month') as HTMLInputElement;
	const countrySelect = document.getElementById('edit-country') as HTMLSelectElement;
	const matureInput = document.getElementById('edit-mature') as HTMLInputElement;
	const tagsRoot = document.getElementById('edit-tags') as HTMLElement;
	const errorBox = document.getElementById('edit-error') as HTMLDivElement;
	const acceptBtn = document.getElementById('edit-accept') as HTMLButtonElement;
	const acceptIconClass = acceptBtn.querySelector('i')?.className ?? 'bi bi-check-lg me-1';

	const tagPicker = new TagPicker(tagsRoot);

	document.querySelectorAll<HTMLElement>('.tr-holiday-clickable').forEach(row => {
		const open = () => bootstrap.Modal.getOrCreateInstance(modalEl).show(row);
		row.addEventListener('click', event => {
			if ((event.target as HTMLElement).closest('a, button, input, select, textarea')) return;
			open();
		});
		row.addEventListener('keydown', event => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				open();
			}
		});
	});

	modalEl.addEventListener('show.bs.modal', event => {
		const trigger = (event as unknown as { relatedTarget: HTMLElement | null }).relatedTarget;
		if (!trigger) return;
		errorBox.classList.add('d-none');
		errorBox.textContent = '';
		idInput.value = trigger.dataset.id ?? '';
		nameInput.value = trigger.dataset.name ?? '';
		descInput.value = trigger.dataset.description ?? '';
		dayInput.value = trigger.dataset.day ?? '';
		monthInput.value = trigger.dataset.month ?? '';
		countrySelect.value = trigger.dataset.country || 'null';
		matureInput.checked = trigger.dataset.mature === '1';
		let tagIds: number[] = [];
		try {
			tagIds = JSON.parse(trigger.dataset.tags || '[]');
		} catch {
			tagIds = [];
		}
		tagPicker.setSelected(tagIds);
	});

	const nameAi = document.getElementById('edit-name-ai') as HTMLButtonElement | null;
	if (nameAi) {
		attachGenerateHandler(nameAi, {
			type: 'name',
			language: 'polski',
			target: () => nameInput,
			name: () => nameInput.value,
			month: () => monthInput.value,
			day: () => dayInput.value,
		});
	}
	const descAi = document.getElementById('edit-description-ai') as HTMLButtonElement | null;
	if (descAi) {
		attachGenerateHandler(descAi, {
			type: 'description_pl',
			target: () => descInput,
			name: () => nameInput.value,
			month: () => monthInput.value,
			day: () => dayInput.value,
		});
	}

	acceptBtn.addEventListener('click', async () => {
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
			const res = await axios.post<UpdatePayload>(updateUrl, data);
			if (res.data.success) {
				applyRowUpdate(res.data);
				bootstrap.Modal.getInstance(modalEl)?.hide();
				showToast('success', 'Holiday updated.');
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

function applyRowUpdate(payload: UpdatePayload): void {
	const row = document.querySelector<HTMLElement>(`tr.tr-holiday[data-row-id="${payload.id}"]`);
	if (!row) return;

	const nameCell = row.querySelector<HTMLElement>('[data-role="name-cell"]');
	const descCell = row.querySelector<HTMLElement>('[data-role="description-cell"] .holiday-description');
	const monthCell = row.querySelector<HTMLElement>('[data-role="month-cell"]');
	const dayCell = row.querySelector<HTMLElement>('[data-role="day-cell"]');
	const countryCell = row.querySelector<HTMLElement>('[data-role="country-cell"]');
	const matureCell = row.querySelector<HTMLElement>('[data-role="mature-cell"]');

	if (nameCell) nameCell.textContent = payload.name ?? '';
	if (descCell) descCell.innerHTML = nl2br(payload.description ?? '');
	if (monthCell) monthCell.textContent = String(payload.month);
	if (dayCell) dayCell.textContent = String(payload.day);
	if (countryCell) {
		countryCell.dataset.country = payload.countryCode ?? '';
		countryCell.textContent = payload.countryName
			? `${payload.countryCode} - ${payload.countryName}`
			: 'International';
	}
	if (matureCell) {
		matureCell.dataset.mature = payload.mature ? '1' : '0';
		matureCell.textContent = payload.mature ? 'Yes' : 'No';
	}
	row.dataset.name = payload.name ?? '';
	row.dataset.description = payload.description ?? '';
	row.dataset.month = String(payload.month);
	row.dataset.day = String(payload.day);
	row.dataset.country = payload.countryCode ?? '';
	row.dataset.mature = payload.mature ? '1' : '0';
	row.dataset.tags = JSON.stringify(payload.tags ?? []);
}

document.addEventListener('DOMContentLoaded', () => {
	const root = document.querySelector<HTMLElement>('[data-page="admin-create"]');
	if (!root) return;
	const currentMonth = parseInt(root.dataset.currentMonth ?? '0');
	initCreateForm(currentMonth);
	initInlineGenerateButtons();
	initEditModal();
});
