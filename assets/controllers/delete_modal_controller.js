import { Controller } from '@hotwired/stimulus';

/*
 * Delete confirmation modal (components/modal/_delete.html.twig).
 *
 * The trigger button (macro `delete_button`) opens the modal through
 * Bootstrap (`data-bs-toggle="modal"`) and carries:
 *   data-gm-delete-url     form action (resource delete route, POST)
 *   data-gm-delete-token   CSRF token (`delete-<id>`), posted as `_token`
 *   data-gm-delete-label   item label inserted in the message
 *
 * Values: message (with a `__label__` placeholder), defaultMessage.
 */
export default class extends Controller {
    static targets = ['form', 'token', 'message'];
    static values = { message: String, defaultMessage: String };

    connect() {
        this.onShow = (event) => this.prepare(event.relatedTarget);
        this.element.addEventListener('show.bs.modal', this.onShow);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.onShow);
    }

    prepare(trigger) {
        if (!trigger) {
            return;
        }

        const { gmDeleteUrl: url = '#', gmDeleteToken: token = '', gmDeleteLabel: label = '' } = trigger.dataset;

        this.formTarget.action = url;
        this.tokenTarget.value = token;

        if (this.hasMessageTarget) {
            this.messageTarget.textContent = label && this.messageValue
                ? this.messageValue.replace('__label__', label)
                : this.defaultMessageValue;
        }
    }
}
