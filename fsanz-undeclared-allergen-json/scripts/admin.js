(function () {
    'use strict';

    var template = document.getElementById('fsanz-alerts-template');
    var record = document.getElementById('fsanz-alerts-record');
    var frame = document.getElementById('fsanz-alerts-preview');
    var status = document.getElementById('fsanz-alerts-preview-status');
    var search = document.getElementById('fsanz-alerts-token-search');
    var featuredImage = document.getElementById('fsanz-alerts-featured-image');
    var recordNav = document.getElementById('fsanz-alerts-record-nav');
    var previous = document.getElementById('fsanz-alerts-previous');
    var next = document.getElementById('fsanz-alerts-next');
    var recordTitle = document.getElementById('fsanz-alerts-record-title');
    var recordPosition = document.getElementById('fsanz-alerts-record-position');
    var addNow = document.getElementById('fsanz-alerts-add-now');
    var editDraft = document.getElementById('fsanz-alerts-edit-draft');
    var missingCount = document.getElementById('fsanz-alerts-missing-count');
    var importNext = document.getElementById('fsanz-alerts-import-next');
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.fsanz-alerts-tab'));
    var automation = document.getElementById('fsanz-alerts-automation');
    if (automation) {
        automation.querySelector('input[type="checkbox"]').addEventListener('change', function () {
            automation.submit();
        });
    }
    if (!template || !record || !frame || !status || !recordNav) {
        return;
    }

    var titles = JSON.parse(recordNav.getAttribute('data-titles'));
    var timer = null;
    var controller = null;
    var importing = false;
    var savedTemplateValue = template.value;
    var savedImageToken = featuredImage ? featuredImage.value : '';

    function hasUnsavedChanges() {
        return template.value !== savedTemplateValue || (featuredImage && featuredImage.value !== savedImageToken);
    }

    function updatePreview() {
        if (importing) {
            return;
        }
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        status.textContent = 'Updating preview…';
        addNow.hidden = true;
        editDraft.hidden = true;
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
            addNow.hidden = !result.data.missing;
            var canImport = result.data.template_saved && !hasUnsavedChanges() && !result.data.unknown.length;
            addNow.disabled = !canImport;
            addNow.title = canImport ? '' : 'Save a valid HTML merge template first';
            status.textContent = result.data.unknown.length
                ? 'Unknown tokens: ' + result.data.unknown.join(', ')
                : (canImport ? 'Preview updated.' : 'Preview updated. Save the template before importing.');
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
    if (featuredImage) {
        featuredImage.addEventListener('change', function () {
            addNow.disabled = true;
            queuePreview();
        });
    }

    function moveRecord(offset) {
        var index = Number(record.value) + offset;
        if (index < 0 || index >= titles.length) {
            return;
        }
        record.value = String(index);
        recordTitle.textContent = titles[index];
        recordPosition.textContent = (index + 1) + ' of ' + titles.length;
        previous.disabled = index === 0;
        next.disabled = index === titles.length - 1;
        updatePreview();
    }

    previous.addEventListener('click', function () { moveRecord(-1); });
    next.addEventListener('click', function () { moveRecord(1); });
    addNow.addEventListener('click', function () {
        if (controller) {
            controller.abort();
        }
        importing = true;
        addNow.disabled = true;
        previous.disabled = true;
        next.disabled = true;
        status.textContent = 'Creating draft…';
        var data = new FormData();
        data.append('action', 'fsanz_alerts_import_record');
        data.append('nonce', FSANZAlertsDesigner.nonce);
        data.append('record', record.value);
        fetch(FSANZAlertsDesigner.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        }).then(function (response) { return response.json(); }).then(function (result) {
            if (!result.success) {
                throw new Error(result.data && result.data.message ? result.data.message : 'Import failed.');
            }
            addNow.hidden = true;
            editDraft.href = result.data.edit_url;
            editDraft.hidden = false;
            if (missingCount) {
                var remaining = Math.max(0, Number(missingCount.textContent) - 1);
                missingCount.textContent = String(remaining);
                if (importNext && remaining === 0) {
                    importNext.disabled = true;
                }
            }
            status.textContent = result.data.message;
        }).catch(function (error) {
            addNow.disabled = false;
            status.textContent = error.message;
        }).finally(function () {
            importing = false;
            previous.disabled = Number(record.value) === 0;
            next.disabled = Number(record.value) === titles.length - 1;
        });
    });

    function activateTab(tab, moveFocus) {
        tabs.forEach(function (candidate) {
            var active = candidate === tab;
            candidate.setAttribute('aria-selected', active ? 'true' : 'false');
            candidate.tabIndex = active ? 0 : -1;
            document.getElementById(candidate.getAttribute('aria-controls')).hidden = !active;
        });
        if (moveFocus) {
            tab.focus();
        }
        if (tab.id === 'fsanz-alerts-tab-preview') {
            updatePreview();
        }
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activateTab(tab, false); });
        tab.addEventListener('keydown', function (event) {
            var next = index;
            if (event.key === 'ArrowRight') { next = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { next = (index + tabs.length - 1) % tabs.length; }
            else if (event.key === 'Home') { next = 0; }
            else if (event.key === 'End') { next = tabs.length - 1; }
            else { return; }
            event.preventDefault();
            activateTab(tabs[next], true);
        });
    });

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
