import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation modal (components/modal/_confirm.html.twig): when opened by a
 * trigger holding `data-gm-confirm-url`, the confirm button points to it.
 */
export default class extends Controller {
    static targets = ['confirm'];

    connect() {
        this.onShow = (event) => {
            const url = event.relatedTarget?.dataset.gmConfirmUrl;

            if (url && this.hasConfirmTarget) {
                this.confirmTarget.href = url;
            }
        };
        this.element.addEventListener('show.bs.modal', this.onShow);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.onShow);
    }
}
