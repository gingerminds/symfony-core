import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/*
 * Drag & drop reordering of a tree (crud/list_tree.html.twig).
 *
 *   <div data-controller="gm-sortable-tree"
 *        data-gm-sortable-tree-url-value="/admin/categories/reorder"
 *        data-gm-sortable-tree-csrf-token-value="...">       (optional, sent as X-CSRF-Token)
 *     <div class="sortable-level" data-parent-id="">         (empty = root level)
 *       <div class="sortable-item" data-item-id="3"><i class="drag-handle"></i>...
 *         <div class="sortable-level" data-parent-id="3">...</div>
 *       </div>
 *     </div>
 *   </div>
 *
 * One Sortable per `.sortable-level`; items are dragged by `.drag-handle`
 * (whole item when `handle` value is empty) within their level. On drop:
 * POST {ids: [ordered ids of the level], parent_id: <level parent id|null>}
 * as JSON to the url value. Dispatches `gm-sortable-tree:reordered` (detail:
 * {ids, parentId, response}); reloads the page when the request fails.
 */
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        handle: { type: String, default: '.drag-handle' },
    };

    connect() {
        this.sortables = [...this.element.querySelectorAll('.sortable-level')].map((level) => Sortable.create(level, {
            animation: 150,
            handle: this.handleValue || undefined,
            draggable: '.sortable-item',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: (event) => {
                if (event.from === event.to && event.oldIndex === event.newIndex) {
                    return;
                }

                this.save(level);
            },
        }));
    }

    disconnect() {
        this.sortables?.forEach((sortable) => sortable.destroy());
        this.sortables = [];
    }

    async save(level) {
        const ids = [...level.querySelectorAll(':scope > .sortable-item')].map((item) => this.#parseId(item.dataset.itemId));
        const parentId = '' === (level.dataset.parentId ?? '') ? null : this.#parseId(level.dataset.parentId);
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (this.csrfTokenValue) {
            headers['X-CSRF-Token'] = this.csrfTokenValue;
        }

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers,
                body: JSON.stringify({ ids, parent_id: parentId }),
            });

            if (!response.ok) {
                throw new Error(`Reorder failed (${response.status})`);
            }

            this.dispatch('reordered', { detail: { ids, parentId, response } });
        } catch (error) {
            console.error(error);
            window.location.reload();
        }
    }

    #parseId(value) {
        return /^\d+$/.test(value) ? parseInt(value, 10) : value;
    }
}
