/* ============================================================
   Tragwerk — Configuration · per-application tab switching
   Toggles .cfg-app-panel visibility from the .tw-seg tab bar.
   ============================================================ */
(function () {
    'use strict';

    /* Event delegation so this keeps working after HTMX swaps. */
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('#cfg-app-seg [data-app-target]');
        if (btn === null) {
            return;
        }

        var seg = btn.closest('#cfg-app-seg');
        var targetId = btn.getAttribute('data-app-target');

        seg.querySelectorAll('[data-app-target]').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });

        document.querySelectorAll('.cfg-app-panel').forEach(function (panel) {
            panel.hidden = panel.id !== targetId;
        });
    });
})();
