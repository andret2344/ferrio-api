import * as bootstrap from 'bootstrap';

function initTabHashSync(): void {
	const tabsContainer = document.getElementById('reportsTabs');
	if (!tabsContainer) return;

	const tabIds = ['fixed-suggestions', 'floating-suggestions', 'fixed-errors', 'floating-errors'];
	const setHash = (id: string) => history.replaceState(null, '', '#' + id);

	const raw = window.location.hash.replace(/^#/, '');
	const initial = tabIds.includes(raw) ? raw : tabIds[0];

	if (raw !== initial) {
		setHash(initial);
	}

	if (initial !== tabIds[0]) {
		const trigger = document.querySelector<HTMLElement>('[data-bs-target="#' + initial + '"]');
		if (trigger) {
			trigger.click();
			setHash(initial);
		}
	}

	tabsContainer.querySelectorAll<HTMLElement>('[data-bs-toggle="tab"]').forEach((btn) => {
		btn.addEventListener('shown.bs.tab', (event) => {
			const target = (event.target as HTMLElement).getAttribute('data-bs-target') || '';
			setHash(target.replace(/^#/, ''));
		});
	});
}

function initModerateModal(): void {
	const modalEl = document.getElementById('moderateModal');
	if (!modalEl) return;

	const moderateUrl = modalEl.dataset.moderateUrl || '';
	const kindInput = document.getElementById('moderateKind') as HTMLInputElement;
	const idInput = document.getElementById('moderateId') as HTMLInputElement;
	const stateInput = document.getElementById('moderateState') as HTMLInputElement;
	const stateToggleBadge = modalEl.querySelector<HTMLElement>('[data-role="state-toggle-badge"]');
	const stateMenu = modalEl.querySelector<HTMLElement>('.state-dropdown-menu');
	const commentInput = document.getElementById('moderateComment') as HTMLTextAreaElement;
	const errorBox = document.getElementById('moderateError') as HTMLDivElement;
	const saveBtn = document.getElementById('moderateSave') as HTMLButtonElement;

	const setState = (value: string) => {
		stateInput.value = value;
		if (stateToggleBadge) {
			stateToggleBadge.textContent = value;
			stateToggleBadge.className = 'badge report-state report-state-' + value.toLowerCase();
			stateToggleBadge.dataset.role = 'state-toggle-badge';
		}
	};

	stateMenu?.querySelectorAll<HTMLElement>('.dropdown-item').forEach((item) => {
		item.addEventListener('click', () => {
			const value = item.dataset.value || '';
			setState(value);
		});
	});

	modalEl.addEventListener('show.bs.modal', (event: Event) => {
		const trigger = (event as unknown as { relatedTarget: HTMLElement }).relatedTarget;
		if (!trigger) return;
		kindInput.value = trigger.dataset.kind || '';
		idInput.value = trigger.dataset.id || '';
		setState(trigger.dataset.state || '');
		commentInput.value = trigger.dataset.comment || '';
		errorBox.classList.add('d-none');
		errorBox.textContent = '';
	});

	saveBtn.addEventListener('click', async () => {
		errorBox.classList.add('d-none');
		errorBox.textContent = '';
		saveBtn.disabled = true;

		try {
			const response = await fetch(moderateUrl, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					kind: kindInput.value,
					id: Number(idInput.value),
					report_state: stateInput.value,
					comment: commentInput.value,
				}),
			});

			if (!response.ok) {
				const payload = await response.json().catch(() => ({}));
				throw new Error(payload.error || ('HTTP ' + response.status));
			}

			const payload = await response.json();
			updateRow(kindInput.value, idInput.value, payload.report_state, payload.comment);
			bootstrap.Modal.getInstance(modalEl)?.hide();
		} catch (err) {
			errorBox.textContent = (err as Error).message || 'Request failed';
			errorBox.classList.remove('d-none');
		} finally {
			saveBtn.disabled = false;
		}
	});
}

function createCommentPopover(el: HTMLElement): void {
	const existing = bootstrap.Popover.getInstance(el);
	if (existing) existing.dispose();
	new bootstrap.Popover(el, {trigger: 'focus', placement: 'top'});
}

function initCommentPopovers(): void {
	document.querySelectorAll<HTMLElement>('[data-role="comment-popover"]').forEach((el) => {
		if (!el.classList.contains('d-none')) {
			createCommentPopover(el);
		}
	});
}

function updateRow(kind: string, id: string, state: string, comment: string | null): void {
	const row = document.querySelector<HTMLElement>(
		'tr[data-report-row][data-kind="' + kind + '"][data-id="' + id + '"]'
	);
	if (!row) return;

	const badge = row.querySelector<HTMLElement>('[data-role="state-badge"]');
	if (badge) {
		badge.textContent = state;
		badge.className = 'badge report-state report-state-' + state.toLowerCase();
		badge.dataset.role = 'state-badge';
	}

	const moderateBtn = row.querySelector<HTMLElement>('.btn-moderate');
	if (moderateBtn) {
		moderateBtn.dataset.state = state;
		moderateBtn.dataset.comment = comment ?? '';
	}

	const commentBtn = row.querySelector<HTMLElement>('[data-role="comment-popover"]');
	if (commentBtn) {
		bootstrap.Popover.getInstance(commentBtn)?.dispose();
		if (comment && comment.trim() !== '') {
			commentBtn.setAttribute('data-bs-content', comment);
			commentBtn.classList.remove('d-none');
			createCommentPopover(commentBtn);
		} else {
			commentBtn.setAttribute('data-bs-content', '');
			commentBtn.classList.add('d-none');
		}
	}
}

document.addEventListener('DOMContentLoaded', () => {
	initTabHashSync();
	initModerateModal();
	initCommentPopovers();
});
