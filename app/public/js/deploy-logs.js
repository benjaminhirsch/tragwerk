(function () {
    'use strict';

    // Note: the filter segment (All / Builds / Deploys) is fully server-side —
    // each button reloads the panel via HTMX and the active state is rendered
    // server-side. No JS involved there.

    // Active highlight on log selection
    document.addEventListener('click', function (e) {
        var item = e.target.closest('#log-items .log-item');
        if (!item) {
            return;
        }
        document.querySelectorAll('#log-items .log-item').forEach(function (i) {
            i.classList.remove('active');
        });
        item.classList.add('active');
    });

    function terminalText() {
        var body = document.getElementById('term-body');
        return body ? body.innerText : '';
    }

    function terminalName() {
        var title = document.getElementById('term-title');
        return (title ? title.textContent.trim() : 'log') || 'log';
    }

    // Copy terminal output
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#copy-btn');
        if (!btn || !navigator.clipboard) {
            return;
        }
        navigator.clipboard.writeText(terminalText());
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-check2';
            setTimeout(function () {
                icon.className = 'bi bi-clipboard';
            }, 1200);
        }
    });

    // Download terminal output
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#dl-btn');
        if (!btn) {
            return;
        }
        var blob = new Blob([terminalText()], { type: 'text/plain' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = terminalName();
        a.click();
        URL.revokeObjectURL(a.href);
    });
})();
