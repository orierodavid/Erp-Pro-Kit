/**
 * All PMS interactive behavior lives here rather than in inline
 * <script> blocks inside the view files.
 *
 * This matters for a concrete reason: this file is enqueued with
 * 'wp-api-fetch' as a dependency and loaded in the footer, so
 * wp.apiFetch is guaranteed to exist by the time any of this runs.
 * Inline scripts in the page body execute BEFORE the footer loads,
 * so they hit an undefined wp.apiFetch — which silently breaks
 * everything after it in the same script block.
 *
 * Everything below is defensive about elements not existing, since
 * the same file loads on every PMS screen.
 */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /**
     * Task form: show/hide the address + geofence fields depending on
     * whether Work mode is "location_based".
     */
    function initWorkModeToggle() {
        var workModeSelect = document.getElementById('work_mode');
        var locationFields = document.getElementById('pms-location-fields');

        if (!workModeSelect || !locationFields) {
            return;
        }

        function toggle() {
            locationFields.style.display = workModeSelect.value === 'location_based' ? '' : 'none';
        }

        workModeSelect.addEventListener('change', toggle);
        toggle(); // apply on load, not just on change
    }

    /**
     * Task form: geocode the typed address via our REST endpoint and
     * stash the resolved coordinates in the hidden lat/lng inputs.
     */
    function initAddressVerification() {
        var verifyBtn = document.getElementById('pms-verify-location');
        var addressInput = document.getElementById('address');
        var latInput = document.getElementById('latitude');
        var lngInput = document.getElementById('longitude');
        var resultEl = document.getElementById('pms-location-result');

        if (!verifyBtn || !addressInput || !latInput || !lngInput) {
            return;
        }

        verifyBtn.addEventListener('click', function () {
            var address = addressInput.value.trim();

            if (!address) {
                if (resultEl) resultEl.textContent = 'Enter an address first.';
                return;
            }

            verifyBtn.disabled = true;
            if (resultEl) resultEl.textContent = 'Verifying…';

            wp.apiFetch({ path: '/pms/v1/geocode?address=' + encodeURIComponent(address) })
                .then(function (result) {
                    latInput.value = result.lat;
                    lngInput.value = result.lng;
                    if (resultEl) {
                        resultEl.innerHTML = '<span class="dashicons dashicons-yes" style="color:#4caf50;"></span> ' + result.display_name;
                    }
                })
                .catch(function (err) {
                    // Deliberately does NOT clear the coordinate inputs —
                    // the user may have entered them manually precisely
                    // because the address couldn't be geocoded.
                    if (resultEl) {
                        resultEl.textContent = (err.message || 'Could not verify that address.')
                            + ' You can enter the coordinates manually below instead.';
                    }
                })
                .finally(function () {
                    verifyBtn.disabled = false;
                });
        });
    }

    /**
     * Start-task buttons. Handles both the single button on the
     * My Tasks detail screen and the per-row buttons on the frontend
     * shortcode — they share the same data attributes.
     */
    function initStartTaskButtons() {
        var buttons = document.querySelectorAll('.pms-start-task-btn, #pms-start-task-btn');

        Array.prototype.forEach.call(buttons, function (btn) {
            btn.addEventListener('click', function () {
                var taskId = btn.dataset.taskId;
                var isLocationBased = btn.dataset.locationBased === '1';

                // The detail screen has one shared note element; the
                // frontend list has one per task row.
                var noteEl = document.querySelector('.pms-location-note[data-note-for="' + taskId + '"]')
                    || document.getElementById('pms-start-note');

                function doStart(lat, lng) {
                    btn.disabled = true;
                    var body = {};

                    if (lat !== undefined) {
                        body.lat = lat;
                        body.lng = lng;
                    }

                    wp.apiFetch({
                        path: '/pms/v1/tasks/' + taskId + '/start',
                        method: 'POST',
                        data: body
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (err) {
                        btn.disabled = false;
                        if (noteEl) noteEl.textContent = err.message || 'Could not start this task.';
                    });
                }

                if (!isLocationBased) {
                    doStart();
                    return;
                }

                if (!navigator.geolocation) {
                    if (noteEl) noteEl.textContent = 'Your browser does not support location access.';
                    return;
                }

                btn.disabled = true;
                if (noteEl) noteEl.textContent = 'Getting your location…';

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        doStart(pos.coords.latitude, pos.coords.longitude);
                    },
                    function () {
                        btn.disabled = false;
                        if (noteEl) noteEl.textContent = 'Location access was denied — it is required to start this task.';
                    },
                    { timeout: 8000 }
                );
            });
        });
    }


    function initAttendanceButtons() {
        var buttons = document.querySelectorAll('.pms-attendance-btn');

        Array.prototype.forEach.call(buttons, function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.dataset.attendanceAction;
                var note = document.querySelector('.pms-attendance-note');
                var buttonsAll = document.querySelectorAll('.pms-attendance-btn');

                function send(path, data) {
                    Array.prototype.forEach.call(buttonsAll, function (item) { item.disabled = true; });
                    if (note) note.textContent = 'Saving…';

                    wp.apiFetch({
                        path: path,
                        method: 'POST',
                        data: data || {}
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (err) {
                        Array.prototype.forEach.call(buttonsAll, function (item) { item.disabled = false; });
                        if (note) note.textContent = err.message || 'Could not update attendance.';
                    });
                }

                if (action === 'clock-out') {
                    send('/pms/v1/attendance/clock-out');
                    return;
                }

                if (!navigator.geolocation) {
                    send('/pms/v1/attendance/clock-in');
                    return;
                }

                if (note) note.textContent = 'Getting your location…';
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        send('/pms/v1/attendance/clock-in', {
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude
                        });
                    },
                    function () {
                        // Location is optional for attendance; clock in without it.
                        send('/pms/v1/attendance/clock-in');
                    },
                    { timeout: 8000 }
                );
            });
        });
    }

    onReady(function () {
        initWorkModeToggle();
        initAddressVerification();
        initStartTaskButtons();
        initAttendanceButtons();
    });
})();
