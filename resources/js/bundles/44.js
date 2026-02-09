if (!window.dataLayer) window.dataLayer = [];

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
        console.log("[FB LEAD SENT]", event.event, window.location.pathname);

        setTimeout(() => {
            if (leadDedupe.has(eventKey) && (Date.now() - leadDedupe.get(eventKey) > 10000)) {
                leadDedupe.delete(eventKey);
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

