/**
 * Selects the full value of an input when it is clicked.
 *
 * Replaces an inline onclick="this.select()" handler, which the strict CSP
 * blocks: script-src-attr is not whitelisted and falls back to script-src,
 * where no 'unsafe-inline' is allowed.
 *
 * Delegated on the document so htmx-swapped inputs are covered as well.
 */
document.addEventListener('click', (event) => {
    const element = event.target;

    if (!(element instanceof HTMLInputElement)) return;
    if (!element.hasAttribute('data-select-on-click')) return;

    element.select();
});
