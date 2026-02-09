if (!window.dataLayer) window.dataLayer = [];

const isPrikhodko = /prikhodko\.com\.ua/.test(location.hostname);

(function subscribeToDataLayerSafe(callback) {
    const originalPush = window.dataLayer.push;
    const dedupeWindow = 2000;
    const recent = new Map();

    function cleanRecent() {
        const now = Date.now();
        for (const [k, t] of recent.entries()) {
            if (now - t > dedupeWindow) recent.delete(k);
        }
    }

    function eventKey(ev) {
        try {
            return ev.event + "|" + JSON.stringify(ev);
        } catch {
            return ev.event || "no-event";
        }
    }

    window.dataLayer.push = function () {
        const ev = arguments[0];
        cleanRecent();
        const key = eventKey(ev);
        const now = Date.now();

        if (recent.has(key) && (now - recent.get(key) <= dedupeWindow)) {
            console.warn("[DEDUPED BLOCKED]", ev);
            return;
        }

        recent.set(key, now);
        const result = originalPush.apply(window.dataLayer, arguments);

        try { callback(ev); } catch {}

        return result;
    };
})(dlEvent);

const leadDedupe = new Map();
let lastLeadTime = 0;
const gadsConversionSent = new Map();

function waitForGAds(callback, maxAttempts = 100) {
    if (window.GAdsConversion && typeof window.GAdsConversion.event === 'function') {
        console.log("[GADS CONVERSION] GAdsConversion found, calling callback");
        callback();
    } else if (maxAttempts > 0) {
        if (maxAttempts === 100 || maxAttempts % 20 === 0) {
            console.log("[GADS CONVERSION] Waiting for GAdsConversion to load...", "Attempts left:", maxAttempts);
            console.log("[GADS CONVERSION] window.GAdsConversion:", window.GAdsConversion);
            console.log("[GADS CONVERSION] window.MedianGRPUtils:", window.MedianGRPUtils);
        }
        setTimeout(() => waitForGAds(callback, maxAttempts - 1), 100);
    } else {
        console.warn("[GADS CONVERSION] GAdsConversion not available after waiting (10 seconds)");
        console.warn("[GADS CONVERSION] window.GAdsConversion:", window.GAdsConversion);
        console.warn("[GADS CONVERSION] window.MedianGRPUtils:", window.MedianGRPUtils);
        console.warn("[GADS CONVERSION] window.sessionStorage.getItem('median-events-gads'):", window.sessionStorage.getItem('median-events-gads'));
    }
}

function dlEvent(event) {
    if (!event?.event) return;

    const leadEvents = ["wpcf7successfulsubmit", "formSubmit", "otpravka_formi2024"];

    if (leadEvents.includes(event.event)) {
        const now = Date.now();

        if (now - lastLeadTime < 5000) {
            console.log("[FB LEAD BLOCKED - time check]", event.event);
            return;
        }

        const eventKey = `lead_${event.event}_${window.location.pathname}`;
        if (leadDedupe.has(eventKey) && (now - leadDedupe.get(eventKey) < 10000)) {
            console.log("[FB LEAD BLOCKED - event key]", event.event);
            return;
        }

        lastLeadTime = now;
        leadDedupe.set(eventKey, now);

        FbEvents.Lead();
        // Офлайн конверсія для Google Ads
        const gadsEventKey = `gads_lead_${event.event}_${window.location.pathname}`;
        if (gadsConversionSent.has(gadsEventKey) && (now - gadsConversionSent.get(gadsEventKey) < 10000)) {
            console.log("[GADS CONVERSION] Already sent, skipping duplicate");
        } else {
            console.log("[GADS CONVERSION] Attempting to send lead event");
            gadsConversionSent.set(gadsEventKey, now);
            waitForGAds(() => {
                try {
                    window.GAdsConversion.event('lead');
                    console.log("[GADS CONVERSION] Lead event sent successfully");
                } catch (error) {
                    console.error("[GADS CONVERSION] Error sending lead event:", error);
                    gadsConversionSent.delete(gadsEventKey);
                }
            });
        }
        console.log("[FB LEAD SENT]", event.event, window.location.pathname);

        setTimeout(() => {
            if (leadDedupe.has(eventKey) && (Date.now() - leadDedupe.get(eventKey) > 10000)) {
                leadDedupe.delete(eventKey);
            }
            if (gadsConversionSent.has(gadsEventKey) && (Date.now() - gadsConversionSent.get(gadsEventKey) > 10000)) {
                gadsConversionSent.delete(gadsEventKey);
            }
        }, 15000);

        return;
    }
}

document.addEventListener("wpcf7mailsent", () => {
    window.dataLayer.push({ event: "wpcf7successfulsubmit" });
});

document.addEventListener("submit", e => {
    if (!e.target.classList.contains("wpcf7-form")) {
        window.dataLayer.push({ event: "formSubmit" });
    }
});

if (isPrikhodko) {
    console.log("Prikhodko tracking initialized");
}