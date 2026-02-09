/**
 * Bundle 45 → gads 24 (offline conversions) + sheet 32 (CRM).
 * Тіло: click_id, gclid, utm_*, is_direct, event, value, currency, time, order.
 * 4 статуси: new_lead, lead_qualified, lead_payment, purchase.
 */
(function () {
    if (!window.dataLayer) window.dataLayer = [];

    var GADS_ACCOUNT_ID = 24;
    var SHEET_ACCOUNT_ID = 32;
    var BASE_URL = 'https://api.median-grp.com/partners';
    var UTM_PREFIX = 'median_grp_';

    var leadEvents = ['wpcf7successfulsubmit', 'formSubmit', 'otpravka_formi2024'];
    var statusEvents = ['new_lead', 'lead_qualified', 'lead_payment', 'purchase'];

    function getUtm(key) {
        try {
            return localStorage.getItem(UTM_PREFIX + key) || '';
        } catch (e) {
            return '';
        }
    }

    function buildPayload(conversionEvent, total, order) {
        var utils = window.MedianGRPUtils;
        if (!utils) return null;

        var clickId = utils.getUserId() || utils.getCookie('gads_user_id') || '';
        var gclid = utils.getCookie('gclid') || '';
        var isDirect = (!gclid && !getUtm('utm_source')) ? '1' : '0';

        var time = new Date().toISOString().slice(0, 19).replace('T', ' ');
        var payload = {
            click_id: clickId,
            gclid: gclid,
            utm_source: getUtm('utm_source'),
            utm_medium: getUtm('utm_medium'),
            utm_campaign: getUtm('utm_campaign'),
            utm_content: getUtm('utm_content'),
            utm_term: getUtm('utm_term'),
            is_direct: isDirect,
            event: conversionEvent || 'new_lead',
            value: typeof total === 'number' ? total : (parseFloat(total, 10) || 0),
            currency: 'UAH',
            time: time,
            order: order || ''
        };
        return payload;
    }

    function objectToFormData(obj) {
        var formData = new FormData();
        var utils = window.MedianGRPUtils;
        if (utils && typeof utils.jsonToFormData === 'function') {
            return utils.jsonToFormData(utils.filterObject ? utils.filterObject(obj) : obj);
        }
        Object.keys(obj).forEach(function (key) {
            var v = obj[key];
            formData.append(key, v === null || v === undefined ? '' : v);
        });
        return formData;
    }

    function sendToGadsAndSheet(payload) {
        if (!payload) return;

        var gadsUrl = BASE_URL + '/gads-conversions/' + GADS_ACCOUNT_ID;
        var sheetUrl = BASE_URL + '/google-sheets/' + SHEET_ACCOUNT_ID;

        Promise.all([
            fetch(gadsUrl, { body: objectToFormData(payload), method: 'POST', mode: 'cors' }),
            fetch(sheetUrl, { body: objectToFormData(payload), method: 'POST', mode: 'cors' })
        ]).then(function () {
            if (window.MedianGRPUtils && window.MedianGRPUtils.pushLogEvent) {
                window.MedianGRPUtils.pushLogEvent({
                    data: payload,
                    status: true,
                    send: true,
                    source: 'bundle45'
                });
            }
        }).catch(function () {
            if (window.MedianGRPUtils && window.MedianGRPUtils.pushLogEvent) {
                window.MedianGRPUtils.pushLogEvent({
                    data: payload,
                    status: false,
                    send: true,
                    source: 'bundle45'
                });
            }
        });
    }

    var leadDedupe = {};
    var lastLeadTime = 0;

    function onDataLayerEvent(ev) {
        if (!ev || !ev.event) return;

        var conversionEvent = 'new_lead';
        var total = 0;
        var order = '';

        if (ev.event === 'gads_offline_lead') {
            conversionEvent = statusEvents.indexOf(ev.conversion_event) >= 0
                ? ev.conversion_event
                : (ev.conversion_event || 'new_lead');
            total = ev.total != null ? ev.total : 0;
            order = ev.order || ev.nomier_zamovliennia || '';
        } else if (leadEvents.indexOf(ev.event) >= 0) {
            var now = Date.now();
            if (now - lastLeadTime < 5000) return;
            var key = 'lead_' + ev.event + '_' + (window.location.pathname || '');
            if (leadDedupe[key] && (now - leadDedupe[key] < 10000)) return;
            lastLeadTime = now;
            leadDedupe[key] = now;
            total = ev.total != null ? ev.total : 0;
            order = ev.order || ev.nomier_zamovliennia || '';
        } else {
            return;
        }

        var payload = buildPayload(conversionEvent, total, order);
        sendToGadsAndSheet(payload);
    }

    (function subscribeDataLayer(callback) {
        var originalPush = window.dataLayer.push;
        var dedupeWindow = 2000;
        var recent = {};

        function eventKey(ev) {
            try {
                return (ev.event || '') + '|' + JSON.stringify(ev);
            } catch (e) {
                return ev.event || 'no-event';
            }
        }

        window.dataLayer.push = function () {
            var ev = arguments[0];
            var key = eventKey(ev);
            var now = Date.now();
            if (recent[key] && (now - recent[key] <= dedupeWindow)) return;
            recent[key] = now;
            var result = originalPush.apply(window.dataLayer, arguments);
            try {
                callback(ev);
            } catch (e) {}
            return result;
        };
    })(onDataLayerEvent);

    document.addEventListener('wpcf7mailsent', function () {
        window.dataLayer.push({ event: 'wpcf7successfulsubmit' });
    });

    document.addEventListener('submit', function (e) {
        if (!e.target || !e.target.classList || !e.target.classList.contains('wpcf7-form')) {
            window.dataLayer.push({ event: 'formSubmit' });
        }
    });
})();
