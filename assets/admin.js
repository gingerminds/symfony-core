/*
 * Gingerminds core admin entrypoint (importmap entry `gingerminds-core-admin`,
 * logical path `gingerminds-core/admin.js`).
 *
 * - starts the Stimulus application of the project (@symfony/stimulus-bundle:
 *   controllers from assets/controllers.json, e.g. UX Autocomplete, and
 *   assets/controllers/) and registers the bundle's own `gm-*` controllers;
 * - loads Bootstrap JS (exposed as window.bootstrap), Bootstrap Icons and
 *   the Tom Select Bootstrap 5 theme.
 *
 * The admin CSS (assets/styles/admin.scss) is compiled by sass-bundle and
 * linked by the layouts.
 */
import { startStimulusApp } from '@symfony/stimulus-bundle';
import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'tom-select/dist/css/tom-select.bootstrap5.css';

import CheckAllController from './controllers/check_all_controller.js';
import ConfirmModalController from './controllers/confirm_modal_controller.js';
import ContributorFieldsController from './controllers/contributor_fields_controller.js';
import DeleteModalController from './controllers/delete_modal_controller.js';
import ItemsPerPageController from './controllers/items_per_page_controller.js';
import PasswordToggleController from './controllers/password_toggle_controller.js';
import SidebarController from './controllers/sidebar_controller.js';
import SortableTreeController from './controllers/sortable_tree_controller.js';

window.bootstrap = bootstrap;

const app = startStimulusApp();

app.register('gm-check-all', CheckAllController);
app.register('gm-confirm-modal', ConfirmModalController);
app.register('gm-contributor-fields', ContributorFieldsController);
app.register('gm-delete-modal', DeleteModalController);
app.register('gm-items-per-page', ItemsPerPageController);
app.register('gm-password-toggle', PasswordToggleController);
app.register('gm-sidebar', SidebarController);
app.register('gm-sortable-tree', SortableTreeController);

export { app };
