import { Controller } from '@hotwired/stimulus';

/*
 * Admin sidebar collapse-to-icons toggle, remembered in localStorage.
 *
 *   <nav class="sidebar" data-controller="gm-sidebar">
 *     <button data-action="gm-sidebar#toggle">
 *
 * Values: storageKey (default `gm-sidebar-collapsed`).
 */
export default class extends Controller {
    static values = { storageKey: { type: String, default: 'gm-sidebar-collapsed' } };

    connect() {
        if ('1' === this.#read()) {
            this.element.classList.add('collapsed');
        }
    }

    toggle() {
        const collapsed = this.element.classList.toggle('collapsed');

        try {
            window.localStorage.setItem(this.storageKeyValue, collapsed ? '1' : '0');
        } catch {
            // storage unavailable (private mode...): the state is not remembered
        }
    }

    #read() {
        try {
            return window.localStorage.getItem(this.storageKeyValue);
        } catch {
            return null;
        }
    }
}
