import { Controller } from '@hotwired/stimulus';

/*
 * User form: shows the contributor fields once a contributor (or "new
 * contributor") is selected in the `contributorId` select.
 *
 *   <div data-controller="gm-contributor-fields">
 *     <select data-gm-contributor-fields-target="select" data-action="change->gm-contributor-fields#toggle">
 *     <div data-gm-contributor-fields-target="fields">...</div>
 *   </div>
 *
 * Without select target (profile form) the fields stay visible.
 */
export default class extends Controller {
    static targets = ['select', 'fields'];

    connect() {
        this.toggle();
    }

    toggle() {
        if (!this.hasSelectTarget || !this.hasFieldsTarget) {
            return;
        }

        this.fieldsTarget.hidden = '' === this.selectTarget.value;
    }
}
