import { Controller } from '@hotwired/stimulus';

/*
 * List page size selector: reloads the list with `itemsPerPage=<value>`,
 * back to the first page.
 *
 *   <select data-controller="gm-items-per-page" data-action="gm-items-per-page#change">
 */
export default class extends Controller {
    change() {
        const url = new URL(window.location.href);

        url.searchParams.set('itemsPerPage', this.element.value);
        url.searchParams.delete('page');

        window.location.assign(url.toString());
    }
}
