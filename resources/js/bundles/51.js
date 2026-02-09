/**
 * Bundle 51 — Google Ads offline conversion Lead.
 * Lead: при сабміті форми #checkoutForm.
 */

    if (!window.dataLayer) window.dataLayer = [];

    var DEDUPE_MS = 8000;
    var leadSentAt = 0;

    function sendLead() {
        if (typeof window.GAdsConversion === 'undefined' || typeof window.GAdsConversion.event !== 'function') return;
        var now = Date.now();
        if (now - leadSentAt < DEDUPE_MS) return;
        leadSentAt = now;
        try {
            window.GAdsConversion.event('lead');
        } catch (e) {}
    }

    function attach() {
        var form = document.getElementById('checkoutForm');
        if (!form) return;
        form.addEventListener('submit', function () {
            sendLead();
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }

