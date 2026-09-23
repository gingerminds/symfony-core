import { Controller } from '@hotwired/stimulus';

/*
 * "Check all" switches of grouped checkboxes (role permissions).
 *
 *   <div data-controller="gm-check-all">
 *     <input type="checkbox" data-gm-check-all-target="all" data-action="change->gm-check-all#toggleAll">
 *     <input type="checkbox" data-gm-check-all-target="group" data-group="users" data-action="change->gm-check-all#toggleGroup">
 *     <input type="checkbox" data-gm-check-all-target="item" data-group="users" data-action="change->gm-check-all#refresh">
 *   </div>
 *
 * Group / global switches reflect the items state (checked / indeterminate).
 */
export default class extends Controller {
    static targets = ['all', 'group', 'item'];

    connect() {
        this.refresh();
    }

    toggleAll(event) {
        this.itemTargets.forEach((item) => { item.checked = event.target.checked; });
        this.refresh();
    }

    toggleGroup(event) {
        const group = event.target.dataset.group;
        this.itemsOf(group).forEach((item) => { item.checked = event.target.checked; });
        this.refresh();
    }

    refresh() {
        this.groupTargets.forEach((switchElement) => {
            this.#sync(switchElement, this.itemsOf(switchElement.dataset.group));
        });

        if (this.hasAllTarget) {
            this.#sync(this.allTarget, this.itemTargets);
        }
    }

    itemsOf(group) {
        return this.itemTargets.filter((item) => item.dataset.group === group);
    }

    #sync(switchElement, items) {
        const checked = items.filter((item) => item.checked).length;
        switchElement.checked = items.length > 0 && checked === items.length;
        switchElement.indeterminate = checked > 0 && checked < items.length;
    }
}
