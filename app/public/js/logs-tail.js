(function () {
    'use strict';

    // Auto-follow the live container log tail.
    // #log-tail is swapped (innerHTML) every 10s / on sse:message, which recreates
    // .term-body and resets its scroll position. We keep following the tail only while
    // the user is already near the bottom, so scrolling up to read earlier output is
    // not interrupted.
    var BOTTOM_THRESHOLD = 48;
    var follow = true;

    function termBody() {
        var tail = document.getElementById('log-tail');
        return tail ? tail.querySelector('.term-body') : null;
    }

    function scrollToBottom() {
        var body = termBody();
        if (body) {
            body.scrollTop = body.scrollHeight;
        }
    }

    function isTailSwap(evt) {
        var t = evt.detail && evt.detail.target;
        return !!t && t.id === 'log-tail';
    }

    document.body.addEventListener('htmx:beforeSwap', function (evt) {
        if (!isTailSwap(evt)) {
            return;
        }
        var body = termBody();
        follow = body
            ? (body.scrollHeight - body.scrollTop - body.clientHeight) <= BOTTOM_THRESHOLD
            : true;
    });

    document.body.addEventListener('htmx:afterSwap', function (evt) {
        if (isTailSwap(evt) && follow) {
            scrollToBottom();
        }
    });

    document.addEventListener('DOMContentLoaded', scrollToBottom);
})();
