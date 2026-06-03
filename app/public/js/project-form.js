(function () {
    function filterPrimaryServer() {
        var select = document.getElementById('serverId');
        if (!select) return;
        var primaryId = select.value;

        document.querySelectorAll('#tw-swarm-section [data-server-id]').forEach(function (row) {
            var hide = primaryId && row.dataset.serverId === primaryId;
            row.style.display = hide ? 'none' : '';
            if (hide) {
                row.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
                row.querySelectorAll('input[type="radio"]').forEach(function (rb) { rb.checked = false; });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        filterPrimaryServer();
        var select = document.getElementById('serverId');
        if (select) {
            select.addEventListener('change', filterPrimaryServer);
        }
    });
}());
