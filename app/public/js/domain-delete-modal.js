(function () {
    var modal = document.getElementById('tw-domain-delete-modal');
    if (!modal) { return; }

    modal.addEventListener('show.bs.modal', function (event) {
        var trigger    = event.relatedTarget;
        var deleteUrl  = trigger.getAttribute('data-delete-url');
        var domainHost = trigger.getAttribute('data-domain-host');

        document.getElementById('tw-domain-delete-host').textContent = domainHost;

        var confirmBtn = document.getElementById('tw-domain-delete-confirm');
        confirmBtn.setAttribute('hx-post', deleteUrl);
        htmx.process(confirmBtn);
    });
}());
