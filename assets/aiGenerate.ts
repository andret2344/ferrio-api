import axios from 'axios';

interface GenerateFields {
    readonly type: 'description_pl' | 'description' | 'name';
    readonly language?: string;
    readonly target: () => HTMLTextAreaElement | HTMLInputElement | null;
    readonly name: () => string;
    readonly month: () => string | undefined;
    readonly day: () => string | undefined;
    readonly country?: () => string | undefined;
    readonly enabled?: () => boolean;
    readonly onGenerated?: () => void;
}

export interface GenerateHandle {
    readonly refresh: () => void;
}

export function attachGenerateHandler(btn: HTMLButtonElement, fields: GenerateFields): GenerateHandle {
    const refresh = (): void => {
        if (fields.enabled) {
            btn.disabled = !fields.enabled();
        }
    };
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
                day: Number.parseInt(day),
                month: Number.parseInt(month),
                name: name || undefined,
            };
            if (fields.language) {
                payload.language = fields.language;
            }
            if (fields.country) {
                const country = fields.country();
                if (country && country !== 'null') {
                    payload.country = country;
                }
            }
            const res = await axios.post('/admin/api/generate', payload);
            if (res.data.error) {
                alert(res.data.error);
            } else {
                target.value = res.data.result;
                target.dispatchEvent(new Event('input', {bubbles: true}));
                if (fields.onGenerated) {
                    fields.onGenerated();
                }
            }
        } catch {
            alert('Failed to generate.');
        } finally {
            if (icon) {
                icon.className = originalClass;
            }
            refresh();
        }
    });
    refresh();
    return {refresh};
}
