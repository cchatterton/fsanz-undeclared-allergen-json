(function () {
    'use strict';

    var template = document.getElementById('fsanz-alerts-template');
    var record = document.getElementById('fsanz-alerts-record');
    var frame = document.getElementById('fsanz-alerts-preview');
    var status = document.getElementById('fsanz-alerts-preview-status');
    var search = document.getElementById('fsanz-alerts-token-search');
    if (!template || !record || !frame || !status) {
        return;
    }

    var timer = null;
    var controller = null;

    function updatePreview() {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        status.textContent = 'Updating preview…';
        var data = new FormData();
        data.append('action', 'fsanz_alerts_preview');
        data.append('nonce', FSANZAlertsDesigner.nonce);
        data.append('record', record.value);
        data.append('template', template.value);

        fetch(FSANZAlertsDesigner.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
            signal: controller.signal
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (!result.success) {
                throw new Error(result.data && result.data.message ? result.data.message : 'Preview failed.');
            }
            frame.srcdoc = result.data.document;
            status.textContent = result.data.unknown.length
                ? 'Unknown tokens: ' + result.data.unknown.join(', ')
                : 'Preview updated. Save the template to use it for imports.';
        }).catch(function (error) {
            if (error.name !== 'AbortError') {
                status.textContent = error.message;
            }
        });
    }

    function queuePreview() {
        window.clearTimeout(timer);
        timer = window.setTimeout(updatePreview, 300);
    }

    template.addEventListener('input', queuePreview);
    record.addEventListener('change', updatePreview);

    document.querySelectorAll('.fsanz-alerts-token').forEach(function (button) {
        button.addEventListener('click', function () {
            var token = button.getAttribute('data-token');
            var start = template.selectionStart;
            var end = template.selectionEnd;
            template.value = template.value.slice(0, start) + token + template.value.slice(end);
            template.focus();
            template.setSelectionRange(start + token.length, start + token.length);
            queuePreview();
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            var query = search.value.toLowerCase().trim();
            document.querySelectorAll('.fsanz-alerts-token-list tbody tr').forEach(function (row) {
                row.hidden = query && row.textContent.toLowerCase().indexOf(query) === -1;
            });
        });
    }
}());
