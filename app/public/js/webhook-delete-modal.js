(function () {
    var modal = document.getElementById('tw-webhook-delete-modal');
    if (!modal) { return; }

    modal.addEventListener('show.bs.modal', function (event) {
        var trigger    = event.relatedTarget;
        var deleteUrl  = trigger.getAttribute('data-delete-url');
        var forgeLabel = trigger.getAttribute('data-forge-label');

        document.getElementById('tw-webhook-delete-forge').textContent = forgeLabel;

        var confirmBtn = document.getElementById('tw-webhook-delete-confirm');
        confirmBtn.setAttribute('hx-post', deleteUrl);
        confirmBtn.setAttribute('hx-target', '#webhook-list');
        confirmBtn.setAttribute('hx-swap', 'outerHTML');
        confirmBtn.setAttribute('hx-include', '#tw-csrf-token');
        htmx.process(confirmBtn);
    });
}());
