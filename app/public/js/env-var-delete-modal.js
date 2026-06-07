(function () {
    var modal = document.getElementById('tw-env-var-delete-modal');
    if (!modal) { return; }

    modal.addEventListener('show.bs.modal', function (event) {
        var trigger   = event.relatedTarget;
        var deleteUrl = trigger.getAttribute('data-delete-url');
        var varKey    = trigger.getAttribute('data-var-key');

        document.getElementById('tw-env-var-delete-key').textContent = varKey;

        var confirmBtn = document.getElementById('tw-env-var-delete-confirm');
        confirmBtn.setAttribute('hx-post', deleteUrl);
        htmx.process(confirmBtn);
    });
}());
