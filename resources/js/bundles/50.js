

    if (!window.dataLayer) window.dataLayer = [];

    var DEDUPE_MS = 8000;
    var leadSentAt = 0;
    var lastEventByType = {};

    function sendGadsEvent(eventName) {
        if (typeof window.GAdsConversion === 'undefined' || typeof window.GAdsConversion.event !== 'function') return;
        var now = Date.now();
        if (lastEventByType[eventName] && (now - lastEventByType[eventName] < DEDUPE_MS)) return;
        lastEventByType[eventName] = now;
        try {
            window.GAdsConversion.event(eventName);
        } catch (e) {}
    }

    function sendLead() {
        var now = Date.now();
        if (now - leadSentAt < DEDUPE_MS) return;
        leadSentAt = now;
        sendGadsEvent('lead');
    }

    function onElementorFormSubmit(e) {
        var form = e && e.target;
        if (!form || !form.classList || !form.classList.contains('elementor-form')) return;
        sendLead();
    }

    function onClick(ev) {
        var target = ev.target && (ev.target.closest ? ev.target.closest('a') : ev.target);
        if (!target || target.tagName !== 'A') return;
        var href = (target.getAttribute('href') || '').trim();
        if (!href) return;

        if (href.indexOf('web.whatsapp.com') !== -1 || (target.classList && (target.classList.contains('chaty-whatsapp-channel') || target.classList.contains('Whatsapp-channel')))) {
            sendGadsEvent('start_message');
            return;
        }
        if (href.indexOf('tel:') === 0) {
            sendGadsEvent('click_phone');
            return;
        }
        if (href.indexOf('mailto:') === 0) {
            sendGadsEvent('click_email');
            return;
        }
    }

    function onDataLayerPush(ev) {
        if (ev && ev.event === 'elementor_form_success') sendLead();
    }

    (function subscribeDataLayer() {
        var orig = window.dataLayer.push;
        window.dataLayer.push = function () {
            var res = orig.apply(window.dataLayer, arguments);
            try {
                var arg = arguments[0];
                if (arg && typeof arg === 'object' && arg.event) onDataLayerPush(arg);
            } catch (e) {}
            return res;
        };
    })();

    function attach() {
        document.addEventListener('submit', onElementorFormSubmit, true);
        document.addEventListener('click', onClick, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }

    window.Median50SendLead = sendLead;

