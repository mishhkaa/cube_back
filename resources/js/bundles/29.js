if (document.cookie.includes('utm_source=')) {
    localStorage.setItem('adUrl', document.location.href);
}

function getUTMsFromUrl(u) {
    try {
        var url = new URL(u || location.href);
        var p = new URLSearchParams(url.search);
        return {
            utm_source: p.get('utm_source') || '(none)',
            utm_medium: p.get('utm_medium') || '(none)',
            utm_campaign: p.get('utm_campaign') || '(none)',
            utm_content: p.get('utm_content') || '(none)',
            utm_term: p.get('utm_term') || '(none)'
        };
    } catch (e) {
        return {
            utm_source: '(none)',
            utm_medium: '(none)',
            utm_campaign: '(none)',
            utm_content: '(none)',
            utm_term: '(none)'
        };
    }
}

function getUTMs() {
    var fromState = (typeof trafficSourceData === 'object' && trafficSourceData) ? {
        utm_source: trafficSourceData.utm_source || '(none)',
        utm_medium: trafficSourceData.utm_medium || '(none)',
        utm_campaign: trafficSourceData.utm_campaign || '(none)',
        utm_content: trafficSourceData.utm_content || '(none)',
        utm_term: trafficSourceData.utm_term || '(none)'
    } : null;

    if (fromState) return fromState;
    return getUTMsFromUrl(localStorage.getItem('adUrl') || location.href);
}

var viewContentItemsForAddToWishlist = null;
var lastViewContentItems = null;

function dlEvent(event) {
    if (!event || !event.event) return;
    
    if (!event.ecommerce || !event.ecommerce.items) {
        if (event.event === 'quick_order') FbEvents.CustomEvent('QuickOrder');
        return;
    }

    if (event.event === 'view_item') {
        viewContentItemsForAddToWishlist = event.ecommerce.items;
        lastViewContentItems = event.ecommerce.items;
        FbEvents.ViewContent(event.ecommerce.items);
        return;
    }

    if (event.event === 'add_to_cart') {
        FbEvents.AddToCart(event.ecommerce.items);
        return;
    }

    if (event.event === 'begin_checkout') {
        FbEvents.InitiateCheckout(event.ecommerce.items);
        return;
    }

    if (event.event === 'purchase') {
        var txId = event.ecommerce.transaction_id;
        if (!txId) return;
        if (localStorage.getItem('purchase_' + txId)) return;
        localStorage.setItem('purchase_' + txId, '1');

        FbEvents.Purchase(event.ecommerce.items, txId);

        var mapped = (typeof FbEvents.mapProducts === 'function') ? FbEvents.mapProducts(event.ecommerce.items) : { value: 0 };
        var utm = getUTMs();
        var payload = {
            date_time: new Date().toISOString(),
            order_id: txId,
            value: mapped.value || 0,
            utm_source: utm.utm_source,
            utm_medium: utm.utm_medium,
            utm_campaign: utm.utm_campaign,
            utm_content: utm.utm_content,
            utm_term: utm.utm_term,
            url: localStorage.getItem('adUrl') || location.href
        };

        if (typeof addRowToGoogleSheet === 'function') {
            setTimeout(function () { addRowToGoogleSheet(payload); }, 800);
        }
        return;
    }
}

subscribeToDataLayer(dlEvent);

// Додаткова обробка delivery.update подій, які можуть приходити напряму
(function() {
    function handleDeliveryUpdate(event) {
        if (event && event.type === 'delivery.update' && event.data && event.data.order) {
            dlEvent(event);
        }
    }

    // Слухач для подій, які можуть бути відправлені через window.postMessage або інші механізми
    if (typeof window.addEventListener !== 'undefined') {
        window.addEventListener('message', function(e) {
            try {
                var data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
                if (data && data.type === 'delivery.update') {
                    handleDeliveryUpdate(data);
                }
            } catch(err) {
                // Ігноруємо помилки парсингу
            }
        });

        // Слухач для кастомних подій
        window.addEventListener('delivery.update', function(e) {
            if (e.detail) {
                handleDeliveryUpdate(e.detail);
            }
        });
    }

    // Якщо подія вже є в глобальному об'єкті
    if (typeof window.deliveryUpdateEvent !== 'undefined') {
        handleDeliveryUpdate(window.deliveryUpdateEvent);
    }
})();

(function() {
    function getProductDataFromPage() {
        var items = [];
        
        try {
            if (window.dataLayer && Array.isArray(window.dataLayer)) {
                for (let i = dataLayer.length - 1; i >= 0; i--) {
                    const event = dataLayer[i];
                    if (event && event.ecommerce && event.ecommerce.items) {
                        return event.ecommerce.items;
                    }
                }
            }
        } catch(e) {}
        
        if (lastViewContentItems) {
            return lastViewContentItems;
        }
        
        try {
            var productJson = document.querySelector('script[type="application/ld+json"]');
            if (productJson) {
                var data = JSON.parse(productJson.textContent);
                if (data['@type'] === 'Product' || (Array.isArray(data) && data[0] && data[0]['@type'] === 'Product')) {
                    var product = Array.isArray(data) ? data[0] : data;
                    var item = {
                        id: product.sku || product.productID || product['@id'] || '',
                        item_name: product.name || '',
                        price: product.offers ? (product.offers.price || (product.offers[0] && product.offers[0].price) || 0) : 0,
                        quantity: 1
                    };
                    if (item.id) {
                        items.push(item);
                    }
                }
            }
        } catch(e) {}
        
        if (items.length === 0 && document.querySelector('[data-product-id]')) {
            try {
                var productId = document.querySelector('[data-product-id]').getAttribute('data-product-id');
                var productTitle = document.querySelector('h1.product-title, h1.product__title, .product__title h1, [data-product-title]');
                var productPrice = document.querySelector('.product-price, .product__price, [data-product-price]');
                
                if (productId) {
                    var price = 0;
                    if (productPrice) {
                        var priceText = productPrice.textContent.replace(/[^\d.,]/g, '').replace(',', '.');
                        price = parseFloat(priceText) || 0;
                    }
                    
                    items.push({
                        id: productId,
                        item_name: productTitle ? productTitle.textContent.trim() : '',
                        price: price,
                        quantity: 1
                    });
                }
            } catch(e) {}
        }
        
        return items.length > 0 ? items : null;
    }
    
    function sendAddToCart(items) {
        if (!items || items.length === 0) {
            items = getProductDataFromPage();
        }
        if (items && items.length > 0) {
            console.log('[Bundle 29] Sending AddToCart:', items);
            FbEvents.AddToCart(items);
        } else {
            console.log('[Bundle 29] AddToCart: No product data found');
        }
    }
    
    function sendInitiateCheckout() {
        try {
            var items = [];
            if (window.dataLayer && Array.isArray(window.dataLayer)) {
                for (let i = dataLayer.length - 1; i >= 0; i--) {
                    const event = dataLayer[i];
                    if (event && event.ecommerce && event.ecommerce.items) {
                        items = event.ecommerce.items;
                        break;
                    }
                }
            }
            
            if (items.length === 0) {
                var cartItems = document.querySelectorAll('.cart-item, [data-cart-item], .cart__item');
                cartItems.forEach(function(cartItem) {
                    try {
                        var id = cartItem.getAttribute('data-product-id') || 
                                 cartItem.querySelector('[data-product-id]')?.getAttribute('data-product-id') || '';
                        var name = cartItem.querySelector('.cart-item__name, .cart__item-name, [data-product-title]')?.textContent.trim() || '';
                        var priceEl = cartItem.querySelector('.cart-item__price, .cart__item-price, [data-product-price]');
                        var price = 0;
                        if (priceEl) {
                            var priceText = priceEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.');
                            price = parseFloat(priceText) || 0;
                        }
                        var qtyEl = cartItem.querySelector('input[type="number"], [data-quantity]');
                        var quantity = qtyEl ? (parseInt(qtyEl.value) || parseInt(qtyEl.getAttribute('data-quantity')) || 1) : 1;
                        
                        if (id) {
                            items.push({
                                id: id,
                                item_name: name,
                                price: price,
                                quantity: quantity
                            });
                        }
                    } catch(e) {}
                });
            }
            
            if (items.length > 0) {
                FbEvents.InitiateCheckout(items);
            }
        } catch(e) {}
    }
    
    var addToCartSent = false;
    
    function sendAddToCartWithDelay() {
        if (addToCartSent) {
            console.log('[Bundle 29] AddToCart already sent, skipping');
            return;
        }
        addToCartSent = true;
        console.log('[Bundle 29] AddToCart triggered');
        
        setTimeout(function() {
            sendAddToCart();
            addToCartSent = false;
        }, 800);
    }
    
    if (typeof XMLHttpRequest !== 'undefined') {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url) {
            this._url = url;
            return originalOpen.apply(this, arguments);
        };
        
        XMLHttpRequest.prototype.send = function() {
            if (this._url && (this._url.includes('/cart/add') || this._url.includes('cart/add.js'))) {
                this.addEventListener('load', function() {
                    if (this.status >= 200 && this.status < 300) {
                        sendAddToCartWithDelay();
                    }
                });
            }
            return originalSend.apply(this, arguments);
        };
    }
    
    if (typeof fetch !== 'undefined') {
        var originalFetch = window.fetch;
        window.fetch = function() {
            var url = arguments[0];
            if (typeof url === 'string' && (url.includes('/cart/add') || url.includes('cart/add.js'))) {
                return originalFetch.apply(this, arguments).then(function(response) {
                    if (response.ok) {
                        sendAddToCartWithDelay();
                    }
                    return response;
                });
            }
            return originalFetch.apply(this, arguments);
        };
    }
    
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form.tagName === 'FORM') {
            var action = form.getAttribute('action') || '';
            if (action.includes('/cart/add') || action.includes('cart/add') || form.querySelector('[name="add"], [name="addToCart"]')) {
                sendAddToCartWithDelay();
            }
        }
    }, true);
    
    document.addEventListener('click', function(e) {
        var target = e.target.closest('button[type="submit"], [name="add"], [name="addToCart"], .btn--add-to-cart, [data-add-to-cart], button[data-product-id], .product-form__cart-submit, .add-to-cart, [aria-label*="Add"], [aria-label*="add"]');
        if (target) {
            var form = target.closest('form');
            if (form && (form.action.includes('/cart/add') || form.action.includes('cart/add') || form.querySelector('[name="add"], [name="addToCart"]'))) {
                sendAddToCartWithDelay();
            } else if (target.getAttribute('data-add-to-cart') || 
                      target.classList.contains('btn--add-to-cart') || 
                      target.classList.contains('product-form__cart-submit') ||
                      target.classList.contains('add-to-cart')) {
                sendAddToCartWithDelay();
            }
        }
        
        var checkoutTarget = e.target.closest('a[href*="/checkout"], a[href*="/cart"], button[href*="/checkout"], [data-checkout], [name="checkout"]');
        if (checkoutTarget) {
            setTimeout(function() {
                sendInitiateCheckout();
            }, 300);
        }
    }, true);
    
    if (typeof window.Shopify !== 'undefined' && window.Shopify.theme) {
        document.addEventListener('shopify:cart:updated', function() {
            sendAddToCartWithDelay();
        });
    }
    
    var cartObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.classList && (node.classList.contains('cart') || node.classList.contains('cart-drawer') || node.querySelector('.cart'))) {
                            sendAddToCartWithDelay();
                        }
                    }
                });
            }
        });
    });
    
    if (document.body) {
        cartObserver.observe(document.body, { childList: true, subtree: true });
    }
    
    if (window.location.pathname.includes('/checkout') || window.location.pathname.includes('/cart')) {
        setTimeout(function() {
            sendInitiateCheckout();
        }, 1000);
    }
})();

setTimeout(function () {
    document.querySelectorAll('a[href*="?add_to_wishlist="]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (viewContentItemsForAddToWishlist) {
                FbEvents.AddToWishlist(viewContentItemsForAddToWishlist);
            }
        });
    });
}, 3000);
