(function () {
    var tabMap = {};

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
        var target = btn.getAttribute('data-bs-target');
        if (!target) return;
        var hash = target.replace(/^#tab-/, '');
        tabMap[hash] = btn;

        btn.addEventListener('shown.bs.tab', function () {
            history.replaceState(null, '', window.location.pathname + '#' + hash);
        });
    });

    var initialHash = window.location.hash.replace(/^#/, '');
    if (initialHash && tabMap[initialHash]) {
        document.addEventListener('DOMContentLoaded', function () {
            bootstrap.Tab.getOrCreateInstance(tabMap[initialHash]).show();
        });
    }
}());
