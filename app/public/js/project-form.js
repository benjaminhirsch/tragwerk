(function () {
    function getCheckedSwarmIds() {
        var ids = {};
        document.querySelectorAll('#tw-swarm-section [data-server-id]').forEach(function (row) {
            var hasChecked = false;
            row.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (cb.checked) { hasChecked = true; }
            });
            if (hasChecked) { ids[row.dataset.serverId] = true; }
        });
        return ids;
    }

    function filterSwarmNodesFromPrimary() {
        var select = document.getElementById('serverId');
        if (!select) { return; }
        var checkedIds = getCheckedSwarmIds();
        var currentVal = select.value;

        select.querySelectorAll('option').forEach(function (opt) {
            if (!opt.value) { return; }
            opt.disabled = !!checkedIds[opt.value];
        });

        if (checkedIds[currentVal]) {
            select.value = '';
        }
    }

    function filterPrimaryServer() {
        var select = document.getElementById('serverId');
        if (!select) { return; }
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
        filterSwarmNodesFromPrimary();

        var select = document.getElementById('serverId');
        if (select) {
            select.addEventListener('change', filterPrimaryServer);
        }

        document.querySelectorAll('#tw-swarm-section input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', filterSwarmNodesFromPrimary);
        });
    });
}());
