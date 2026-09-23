import { Controller } from '@hotwired/stimulus';

/*
 * Show / hide a password input.
 *
 *   <div data-controller="gm-password-toggle">
 *     <input type="password" data-gm-password-toggle-target="input">
 *     <button type="button" data-action="gm-password-toggle#toggle"><i class="bi bi-eye" data-gm-password-toggle-target="icon"></i></button>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'icon'];

    toggle() {
        const show = 'password' === this.inputTarget.type;

        this.inputTarget.type = show ? 'text' : 'password';

        if (this.hasIconTarget) {
            this.iconTarget.classList.toggle('bi-eye', !show);
            this.iconTarget.classList.toggle('bi-eye-slash', show);
        }
    }
}
