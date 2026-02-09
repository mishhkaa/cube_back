var isMetaTraffic = false;

try {
    var u = new URL(window.location.href);
    var fbclidInUrl = u.searchParams.get('fbclid');
    
    if (!fbclidInUrl) {
        var fbclidFromCookie = document.cookie.match(/(?:^|;\s*)fbclid=([^;]+)/);
        if (fbclidFromCookie && fbclidFromCookie[1]) {
            u.searchParams.set('fbclid', fbclidFromCookie[1]);
            window.history.replaceState({}, '', u);
        } else if (window.location.hostname.includes('localhost') || window.location.hostname.includes('127.0.0.1')) {
            u.searchParams.set('fbclid', 'test_' + Date.now());
            window.history.replaceState({}, '', u);
        }
    }
    
    var src = (u.searchParams.get('utm_source') || '').toLowerCase();

    var ls = window.localStorage;
    var had = false;

    try { had = (ls && ls.getItem('mt_meta_traffic') === '1'); } catch(_) {}
    if (!had) had = /(?:^|;\s*)mt_meta_traffic=1\b/.test(document.cookie);

    if (src === 'facebook_ads') {
        isMetaTraffic = true;
        try { ls && ls.setItem('mt_meta_traffic', '1'); } catch(_) {}
        document.cookie = "mt_meta_traffic=1;path=/;max-age=" + (7*24*60*60);
    } else if (had) {
        isMetaTraffic = true;
    }
} catch (e) {}

function cApiEvent(event) {
    if (!event || !event.event) {
        return;
    }

    if (!event.ecommerce) {
        return;
    }

    var items = event.ecommerce.items || event.ecommerce.products || [];

    if (!items || items.length === 0) {
        return;
    }

    var first = items[0] || {};

    if (event.event === 'purchase') {
        var txId = event.ecommerce.transaction_id || event.transaction_id || ("tx-" + Date.now());
        var currency = (event.ecommerce.currency || window.fbCurrency || "PLN");
        var value = event.ecommerce.value || 0;
        var shipping = event.ecommerce.shipping || 0;
        var tax = event.ecommerce.tax || 0;
        var totalValue = value + shipping + tax;

        var isThanksPage = window.location.pathname.indexOf('/thanks') !== -1 || 
                          window.location.pathname.indexOf('/thank-you') !== -1 || 
                          window.location.pathname.indexOf('/success') !== -1;

        try {
            var purchaseData = {
                transaction_id: txId,
                items: items,
            value: value,
                shipping: shipping,
                tax: tax,
                total_value: totalValue,
            currency: currency,
                isMetaTraffic: isMetaTraffic,
                timestamp: Date.now()
            };
            localStorage.setItem('fb_purchase_data_' + txId, JSON.stringify(purchaseData));
        } catch (e) {}

        if (!isThanksPage) {
            return;
        }

        if (!window.FbEvents) {
            setTimeout(function() {
                if (window.FbEvents) {
                    cApiEvent(event);
                }
            }, 500);
            return;
        }

        try {
            if (typeof FbEvents.init === 'function') {
                var initResult = FbEvents.init();
                if (initResult && typeof initResult.then === 'function') {
                    initResult.catch(function(err) {});
                }
            }
        } catch (e) {}

        var alreadySent = sessionStorage.getItem('fb_purchase_sent_' + txId);
        if (alreadySent) {
            return;
        }

        FbEvents.setEventId(txId);

        try {
            if (window.fbq) {
                var pixelPayload = FbEvents.mapProducts(items, {
                    order_id: txId
                });
                pixelPayload.value = totalValue || pixelPayload.value || 0;

            if (isMetaTraffic) {
                    fbq("trackCustom", "UTM_Purchase", pixelPayload);
            } else {
                    fbq("track", "Purchase", pixelPayload);
                    fbq("trackCustom", "UTM_Purchase", pixelPayload);
                }
            }

            var conversionsPromise;
            try {
                var utmPayload = FbEvents.mapProducts(items, {
                    order_id: txId
                });
                utmPayload.value = totalValue || utmPayload.value || 0;
                conversionsPromise = FbEvents.CustomEvent('UTM_Purchase', utmPayload);

                if (conversionsPromise && typeof conversionsPromise.then === 'function') {
                    conversionsPromise
                        .then(function(result) {
                            try {
                                sessionStorage.setItem('fb_purchase_sent_' + txId, '1');
                            } catch (e) {}
                        })
                        .catch(function(error) {});
                } else {
                    try {
                        sessionStorage.setItem('fb_purchase_sent_' + txId, '1');
                    } catch (e) {}
                }
            } catch (conversionsError) {
                try {
                    sessionStorage.setItem('fb_purchase_sent_' + txId, '1');
                } catch (e) {}
            }

            setTimeout(function() {}, 500);

        } catch (err) {
            try {
                sessionStorage.setItem('fb_purchase_sent_' + txId, '1');
            } catch (e) {}
        }

        return;
    }

    if (event.event === 'add_to_cart' && window.FbEvents) {
        FbEvents.AddToCart(items, first.item_name);
        return;
    }

    if (event.event === 'begin_checkout' && window.FbEvents) {
        FbEvents.InitiateCheckout(items);
        return;
    }

    if (event.event === 'view_item' && window.fbq && window.FbEvents) {
        fbq('track', 'ViewContent', FbEvents.mapProducts(items, { content_name: first.item_name }));
        return;
    }
}

function subscribeToDataLayer(cb) {
    window.dataLayer = window.dataLayer || [];
    var originalPush = window.dataLayer.push.bind(window.dataLayer);

    window.dataLayer.push = function () {
        var args = Array.prototype.slice.call(arguments);
        args.forEach(function(e){
            if (e && e.event) {
                cb(e);
            }
        });
        return originalPush.apply(window.dataLayer, args);
    };

    (window.dataLayer || []).forEach(function(e){
        if (e && e.event) {
            cb(e);
        }
    });
}

function sendPurchaseFromThanksPage() {
    if (!window.FbEvents) {
        return;
    }

    try {
        if (typeof FbEvents.init === 'function') {
            var initResult = FbEvents.init();
            if (initResult && typeof initResult.then === 'function') {
                initResult.catch(function(err) {});
            }
        }
    } catch (e) {}

    var txId = null;
    var items = [];
    var value = 0;
    var shipping = 0;
    var tax = 0;
    var totalValue = 0;
    var currency = 'PLN';
    var purchaseData = null;
    var isMetaTrafficFromStorage = false;
    
    try {
        var ls = window.localStorage;
        var had = false;
        try { had = (ls && ls.getItem('mt_meta_traffic') === '1'); } catch(_) {}
        if (!had) had = /(?:^|;\s*)mt_meta_traffic=1\b/.test(document.cookie);
        isMetaTrafficFromStorage = had;
    } catch (e) {}

    try {
        var urlParams = new URLSearchParams(window.location.search);
        txId = urlParams.get('order_id') || urlParams.get('transaction_id') || urlParams.get('orderId');
    } catch (e) {}

    if (!txId) {
        try {
            var orderIdMatch = window.location.href.match(/[?&](?:order[_-]?id|transaction[_-]?id|orderId)=([^&]+)/i);
            if (orderIdMatch && orderIdMatch[1]) {
                txId = decodeURIComponent(orderIdMatch[1]);
            }
        } catch (e) {}
    }

    if (txId) {
        try {
            var storedData = localStorage.getItem('fb_purchase_data_' + txId);
            if (storedData) {
                purchaseData = JSON.parse(storedData);
                if (purchaseData && purchaseData.items) {
                    items = purchaseData.items;
                    value = purchaseData.value || 0;
                    shipping = purchaseData.shipping || 0;
                    tax = purchaseData.tax || 0;
                    totalValue = purchaseData.total_value || (value + shipping + tax);
                    currency = purchaseData.currency || 'PLN';
                    if (purchaseData.hasOwnProperty('isMetaTraffic')) {
                        isMetaTrafficFromStorage = purchaseData.isMetaTraffic;
                    }
                }
            }
        } catch (e) {}
    }

    if (!txId) {
        try {
            var keys = Object.keys(localStorage);
            for (var i = keys.length - 1; i >= 0; i--) {
                if (keys[i].indexOf('fb_purchase_data_') === 0) {
                    try {
                        var stored = JSON.parse(localStorage.getItem(keys[i]));
                        if (stored && stored.timestamp && (Date.now() - stored.timestamp) < 3600000) {
                            purchaseData = stored;
                            txId = stored.transaction_id;
                            items = stored.items || [];
                            value = stored.value || 0;
                            shipping = stored.shipping || 0;
                            tax = stored.tax || 0;
                            totalValue = stored.total_value || (value + shipping + tax);
                            currency = stored.currency || 'PLN';
                            if (stored.hasOwnProperty('isMetaTraffic')) {
                                isMetaTrafficFromStorage = stored.isMetaTraffic;
                            }
                            break;
                        }
                    } catch (e) {}
                }
            }
        } catch (e) {}
    }

    if (!txId) {
        try {
            if (window.dataLayer) {
                for (var i = window.dataLayer.length - 1; i >= 0; i--) {
                    var dlEvent = window.dataLayer[i];
                    if (dlEvent && dlEvent.ecommerce && dlEvent.ecommerce.transaction_id) {
                        txId = dlEvent.ecommerce.transaction_id;
                        items = dlEvent.ecommerce.items || dlEvent.ecommerce.products || [];
                        value = dlEvent.ecommerce.value || 0;
                        shipping = dlEvent.ecommerce.shipping || 0;
                        tax = dlEvent.ecommerce.tax || 0;
                        totalValue = value + shipping + tax;
                        currency = dlEvent.ecommerce.currency || 'PLN';
                        break;
                    }
                }
            }
        } catch (e) {}
    }

    if (!txId) {
        return;
    }

    if (items.length === 0) {
        items = [{ item_id: 'unknown', quantity: 1, price: totalValue || value }];
    }

    var alreadySent = sessionStorage.getItem('fb_purchase_sent_' + txId);
    if (alreadySent) {
        return;
    }

    FbEvents.setEventId(txId);

    try {
        if (window.fbq) {
            var pixelPayload = FbEvents.mapProducts(items, {
                order_id: txId
            });
            if (totalValue > 0) {
                pixelPayload.value = totalValue;
            }

            if (isMetaTrafficFromStorage) {
                fbq("trackCustom", "UTM_Purchase", pixelPayload);
            } else {
                fbq("track", "Purchase", pixelPayload);
            }
        }

        var conversionsPromise;
        if (isMetaTrafficFromStorage) {
            var utmPayload = FbEvents.mapProducts(items, {
                order_id: txId
            });
            utmPayload.value = totalValue || utmPayload.value || 0;
            conversionsPromise = FbEvents.CustomEvent('UTM_Purchase', utmPayload);
        } else {
            var purchasePayload = FbEvents.mapProducts(items, {
                order_id: txId
            });
            if (totalValue > 0) {
                purchasePayload.value = totalValue;
            }
            conversionsPromise = FbEvents.Purchase(items, txId);
        }

        if (conversionsPromise && typeof conversionsPromise.then === 'function') {
            conversionsPromise
                .then(function(result) {
                    try {
                        sessionStorage.setItem('fb_purchase_sent_' + txId, '1');
                        if (purchaseData) {
                            localStorage.removeItem('fb_purchase_data_' + txId);
                        }
                    } catch (e) {}
                })
                .catch(function(error) {});
        } else {
            try {
                sessionStorage.setItem('fb_purchase_sent_' + txId, '1');
                if (purchaseData) {
                    localStorage.removeItem('fb_purchase_data_' + txId);
                }
            } catch (e) {}
        }
    } catch (err) {}
}

if (window.FbEvents) {
    FbEvents.init();
    subscribeToDataLayer(cApiEvent);

    if (window.location.pathname.indexOf('/thanks') !== -1 || window.location.pathname.indexOf('/thank-you') !== -1 || window.location.pathname.indexOf('/success') !== -1) {
        setTimeout(function() {
            sendPurchaseFromThanksPage();
        }, 1000);
    }
} else {
    function initHandler() {
        if (window.FbEvents) {
            FbEvents.init();
subscribeToDataLayer(cApiEvent);

            if (window.location.pathname.indexOf('/thanks') !== -1 || window.location.pathname.indexOf('/thank-you') !== -1 || window.location.pathname.indexOf('/success') !== -1) {
                setTimeout(function() {
                    sendPurchaseFromThanksPage();
                }, 1000);
            }
        } else {
            setTimeout(initHandler, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHandler);
    } else {
        initHandler();
    }
}
