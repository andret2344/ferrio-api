/**
 * Double-submits a CSRF token in a form field and a cookie, as expected by Symfony's
 * `SameOriginCsrfTokenManager` (the `stateless_token_ids` in config/packages/csrf.yaml).
 *
 * This is the second, opportunistic layer only: the primary check is the Origin/Referer
 * comparison the server performs, which keeps working without any JavaScript. Ported from the
 * upstream `csrf_protection_controller.js` recipe, minus its Stimulus and Turbo glue - this
 * project uses neither.
 *
 * The server renders the token id as a placeholder value; the code below swaps in a random token
 * and writes the id/token pair into a cookie, so the two halves must match on arrival.
 */

const NAME_CHECK = /^[-_a-zA-Z0-9]{4,22}$/;
const TOKEN_CHECK = /^[-_/+a-zA-Z0-9]{24,}$/;

const CSRF_FIELD_SELECTOR = 'input[data-controller="csrf-protection"], input[name="_csrf_token"]';

function randomToken(): string {
	const bytes = window.crypto.getRandomValues(new Uint8Array(18));
	return btoa(String.fromCharCode(...bytes));
}

function generateCsrfToken(formElement: HTMLFormElement): void {
	const csrfField = formElement.querySelector<HTMLInputElement>(CSRF_FIELD_SELECTOR);
	if (!csrfField) {
		return;
	}

	let csrfCookie = csrfField.dataset.csrfProtectionCookieValue;
	let csrfToken = csrfField.value;

	// On the first submit the field still holds the placeholder, which is the token id: keep it as
	// the cookie name and replace the field's value with a freshly generated token.
	if (!csrfCookie && NAME_CHECK.test(csrfToken)) {
		csrfCookie = csrfToken;
		csrfField.dataset.csrfProtectionCookieValue = csrfCookie;
		csrfToken = randomToken();
		csrfField.defaultValue = csrfToken;
	}
	csrfField.dispatchEvent(new Event('change', {bubbles: true}));

	if (csrfCookie && TOKEN_CHECK.test(csrfToken)) {
		const cookie = `${csrfCookie}_${csrfToken}=${csrfCookie}; path=/; samesite=strict`;
		if (window.location.protocol === 'https:') {
			document.cookie = `__Host-${cookie}; secure`;
		} else {
			document.cookie = cookie;
		}
	}
}

// Capture phase, so the cookie is written before any other submit handler can cancel or reroute
// the event.
document.addEventListener('submit', (event: SubmitEvent) => {
	if (event.target instanceof HTMLFormElement) {
		generateCsrfToken(event.target);
	}
}, true);
