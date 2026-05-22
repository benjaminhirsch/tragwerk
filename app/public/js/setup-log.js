(function () {
    function getLogPre() {
        var w = document.getElementById('tw-setup-log');
        return w ? w.querySelector('pre') : null;
    }

    document.body.addEventListener('htmx:beforeSwap', function (e) {
        if (e.detail.target.id !== 'tw-setup-log') return;
        var pre = getLogPre();
        window.__logAutoScroll = pre
            ? (pre.scrollHeight - pre.scrollTop - pre.clientHeight) < 100
            : true;
    });

    document.body.addEventListener('htmx:afterSwap', function (e) {
        if (e.detail.target.id !== 'tw-setup-log') return;
        if (window.__logAutoScroll === false) return;
        var pre = getLogPre();
        if (pre) pre.scrollTop = pre.scrollHeight;
    });
}());
