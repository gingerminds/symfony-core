# Turbo

The admin panel runs with [Turbo](https://turbo.hotwired.dev/) (`symfony/ux-turbo`, a dependency
of the bundle), enabled by the `turbo-core` controller of the project's `assets/controllers.json`.
Turbo Drive turns every link click and form submission into a `fetch`: it swaps the `<body>`,
merges the `<head>` and pushes the URL to the history, so pages change without a full reload.

Laravel's Blade + Vite/jQuery stack has no equivalent: every click is a full page load there.
Most of what follows is the consequence of a page that is **not reloaded** between two
navigations.

The bundle uses Turbo Drive only: no `<turbo-frame>`, no Turbo Streams, no Mercure.

## JavaScript runs once

The JS modules are evaluated on the first page only. `DOMContentLoaded`, `load` and inline
`<script>` initialisation code do not run again after a Turbo navigation, so code like this
works on the first page and silently does nothing on the next ones:

```js
// Wrong: runs once, never on pages reached through Turbo
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
});
```

Write a Stimulus controller instead: `connect()` runs every time its element enters the page,
`disconnect()` every time it leaves, whatever the navigation. The bundle's `gm-*` controllers
work this way; register project controllers in `assets/controllers/`.

```js
// assets/controllers/tooltip_controller.js — <span data-controller="tooltip" data-bs-title="...">
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.tooltip = new bootstrap.Tooltip(this.element);
    }

    disconnect() {
        this.tooltip.dispose();
    }
}
```

For code that cannot be a controller (analytics, third-party widgets), listen to `turbo:load`,
fired after the first load and after every visit.

Global state survives navigations too: a `setInterval`, a listener on `window` or a variable set
on one page are still there on the next one. Clean them up in `disconnect()`.

## Forms

Turbo submits forms with `fetch` and only renders the response when it follows these rules:

| Outcome | Response | Laravel equivalent |
|---|---|---|
| Success | redirect (`redirectToRoute()`) | `redirect()->route()` |
| Validation errors | the form page with status **422** | `back()->withErrors()` (redirect) |
| Error page | 4xx/5xx, rendered as is | — |

A form re-rendered with a **200** is ignored by Turbo: the page does not change and the user sees
no error. `AbstractCrudController` already answers 422 on an invalid form; a custom action must
do the same:

```php
return $this->render('admin/import.html.twig', ['form' => $form], new Response(
    status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
));
```

Add `data-turbo="false"` to a form whose response breaks these rules (file download, external
redirect, a response handled by the firewall). The login form does it.

## GET links must not change data

Turbo 8 prefetches a link when the pointer stays on it (~100 ms). Any `GET` URL can therefore
be requested without being clicked. An action that changes data (delete, toggle, send...) must be
a `POST` form with a CSRF token, as the generated `delete` route is. Prefetch requests carry the
`X-Sec-Purpose: prefetch` header and show up in the profiler.

Disable prefetch for the whole document with `<meta name="turbo-prefetch" content="false">`, or
for one link or container with `data-turbo-prefetch="false"`.

## Page cache and back button

Turbo keeps a snapshot of the visited pages and shows it immediately on back/forward, before the
fresh page arrives. A snapshot is taken as the page is left, in its current state: an open modal,
a dropdown or a half-filled field can reappear. Reset such state on `turbo:before-cache`, or opt a
page out with `<meta name="turbo-cache-control" content="no-cache">`.

## `<head>` and assets

On each visit Turbo merges the new `<head>`: `<title>`, `<meta>` and `<link rel="prev|next">` are
updated, scripts already loaded are not executed again. After a deployment, open tabs keep the old
JS/CSS until a full reload; mark the asset tags with `data-turbo-track="reload"` to force it when
they change.

## Opting out

- One link or form: `data-turbo="false"` (also on a container, for everything inside).
- The whole project: `"enabled": false` on `turbo-core` in `assets/controllers.json`. The admin
  keeps working with full page loads; nothing in the bundle depends on Turbo.

## Debugging

- Navigations appear as `fetch` requests in the browser's network tab, not as `document`.
- The web debug toolbar is excluded from Turbo (`data-turbo="false"`): its links do full page loads.
- When a click "does nothing", check the response status of the request first (form answered 200,
  redirect to another domain), then the browser console.
- `turbo:*` events (`turbo:visit`, `turbo:submit-end`, `turbo:fetch-request-error`...) can be
  logged to follow a navigation.
