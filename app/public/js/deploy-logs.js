(function () {
    'use strict';

    // Note: the filter segment (All / Builds / Deploys) is fully server-side —
    // each button reloads the panel via HTMX and the active state is rendered
    // server-side. No JS involved there.

    // Active highlight on log selection. The list (#log-items) is periodically swapped by
    // SSE/polling, which drops the JS-applied class — so we remember the selected entry by its
    // (unique) hx-get URL and re-apply the active state after each swap.
    var selectedKey = null;

    // On a deep link (?selected=…) the server renders the active item; adopt it
    // so periodic SSE/poll swaps of #log-items keep the highlight.
    var initiallyActive = document.querySelector('#log-items .log-item.active');
    if (initiallyActive) {
        selectedKey = initiallyActive.getAttribute('hx-get');
    }

    function markActive() {
        document.querySelectorAll('#log-items .log-item').forEach(function (i) {
            i.classList.toggle('active', selectedKey !== null && i.getAttribute('hx-get') === selectedKey);
        });
    }

    document.addEventListener('click', function (e) {
        var item = e.target.closest('#log-items .log-item');
        if (!item) {
            return;
        }
        selectedKey = item.getAttribute('hx-get');
        markActive();
    });

    document.body.addEventListener('htmx:afterSwap', function (evt) {
        var t = evt.detail && evt.detail.target;
        if (t && (t.id === 'log-items' || t.querySelector('#log-items') !== null || t.closest('#log-items') !== null)) {
            markActive();
        }
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

    // Auto-follow the terminal output on live updates.
    // The whole #tw-terminal is swapped (outerHTML) on each SSE/poll update, which resets the
    // scroll position. We keep following the tail only while the user is already near the bottom,
    // so scrolling up to read earlier output is not interrupted.
    var BOTTOM_THRESHOLD = 48;
    var follow = true;

    function scrollToBottom() {
        var body = document.getElementById('term-body');
        if (body) {
            body.scrollTop = body.scrollHeight;
        }
    }

    function isTerminalSwap(evt) {
        var t = evt.detail && evt.detail.target;
        return !!t && (t.id === 'tw-terminal' || t.querySelector('#term-body') !== null);
    }

    document.body.addEventListener('htmx:beforeSwap', function (evt) {
        if (!isTerminalSwap(evt)) {
            return;
        }
        var body = document.getElementById('term-body');
        if (body) {
            follow = (body.scrollHeight - body.scrollTop - body.clientHeight) <= BOTTOM_THRESHOLD;
        }
    });

    document.body.addEventListener('htmx:afterSwap', function (evt) {
        if (isTerminalSwap(evt) && follow) {
            scrollToBottom();
        }
    });

    document.addEventListener('DOMContentLoaded', scrollToBottom);
})();
