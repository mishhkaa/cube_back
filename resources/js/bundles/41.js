// tp-datalayer-site.js — вставити у footer (WPCode або theme child)
(function(){
    document.addEventListener('DOMContentLoaded', function() {
        window.dataLayer = window.dataLayer || [];

        // ---------- helpers ----------
        function safePush(obj){
            try{
                window.dataLayer.push(obj);
                console.log('DL ▶', obj.event, obj);
            }catch(e){
                console.error('DL push error', e);
            }
        }

        function getCurrency(){
            return document.body.getAttribute('data-currency') || null;
        }

        function parsePriceText(txt){
            if(!txt) return null;
            var cleaned = String(txt).replace(/[^\d.,]/g,'').replace(',', '.');
            var num = parseFloat(cleaned);
            return isNaN(num) ? null : num;
        }

        function guessProduct(){
            try {
                var id = document.body.getAttribute('data-product-id') || null;
                var titleEl = document.querySelector('h1.product_title') || document.querySelector('.product_title') || document.querySelector('h1.entry-title') || document.querySelector('h1');
                var name = titleEl ? titleEl.textContent.trim() : document.title;
                var priceEl = document.querySelector('.price .amount, .woocommerce-Price-amount, .product .price, p.price, span.price');
                var price = priceEl ? parsePriceText(priceEl.innerText) : null;
                return { item_id: id, item_name: name, price: price, currency: getCurrency() };
            } catch(e){ return { item_id: null, item_name: document.title, price: null, currency: getCurrency() }; }
        }

        // ---------- view_item (reliable detection including /shop/) ----------
        (function(){
            try {
                var path = window.location.pathname || '';
                var hasProductTitle = !!(document.querySelector('h1.product_title') || document.querySelector('.product_title') || document.querySelector('h1.entry-title'));
                var hasProductSchema = !!document.querySelector('[itemtype*="Product"], [itemscope][itemtype*="Product"]');
                var isShopProduct = path.indexOf('/shop/') !== -1 || path.indexOf('/product/') !== -1;
                var isSingleProductClass = document.body.classList.contains('single-product') || document.body.classList.contains('product');

                if (isShopProduct || hasProductTitle || hasProductSchema || isSingleProductClass) {
                    var p = guessProduct();
                    safePush({ event: 'view_item', ecommerce: { items: [p] }});
                }
            } catch(e){ console.error('view_item detection error', e); }
        })();

        // ---------- add_to_cart (debounced AJAX + fallback click) ----------
        (function(){
            // cooldown to avoid duplicates (ms)
            var ADD_COOLDOWN = 1200;
            window._tp_last_add_to_cart = window._tp_last_add_to_cart || 0;

            function pushAddToCart(item){
                try {
                    var now = Date.now();
                    if (now - (window._tp_last_add_to_cart || 0) > ADD_COOLDOWN) {
                        window._tp_last_add_to_cart = now;
                        safePush({ event: 'add_to_cart', ecommerce: { items: [item] }});
                    } else {
                        console.log('DL ▶ add_to_cart suppressed (cooldown)');
                    }
                } catch(e){ console.error('pushAddToCart error', e); }
            }

            // WooCommerce AJAX event
            try {
                if (window.jQuery) {
                    jQuery(document).on('added_to_cart', function(event, fragments, cart_hash, $btn){
                        try {
                            var pid = null, title = null, price = null;
                            if ($btn) {
                                try {
                                    pid = (typeof $btn.data === 'function' ? $btn.data('product_id') : null) || ($btn.attr ? $btn.attr('data-product_id') : null) || ($btn.attr ? $btn.attr('data-product-id') : null);
                                    title = (typeof $btn.data === 'function' ? $btn.data('product_title') : null) || ($btn.attr ? $btn.attr('data-product_title') : null) || ($btn.attr ? $btn.attr('data-product-title') : null);
                                    price = (typeof $btn.data === 'function' ? $btn.data('price') : null) || ($btn.attr ? $btn.attr('data-price') : null);
                                } catch(e){}
                            }
                            if (!title) {
                                var h = document.querySelector('h1.product_title') || document.querySelector('h1.entry-title') || document.querySelector('h1');
                                title = h ? h.innerText.trim() : document.title;
                            }
                            var item = { item_id: pid || null, item_name: title, price: price ? parseFloat(String(price).replace(',', '.')) : null, quantity: 1, currency: getCurrency() };
                            pushAddToCart(item);
                        } catch(e){ console.error('added_to_cart handler', e); }
                    });
                }
            } catch(e){ console.error('jQuery added_to_cart hook error', e); }

            // Click fallback
            document.addEventListener('click', function(ev){
                try {
                    var btn = ev.target.closest('.single_add_to_cart_button, .add_to_cart_button, button.add_to_cart, a.add_to_cart_button');
                    if(!btn) return;
                    var pid = btn.getAttribute && (btn.getAttribute('data-product_id') || btn.getAttribute('data-product-id')) || (btn.dataset && (btn.dataset.productId || btn.dataset.product_id)) || null;
                    var title = btn.getAttribute && (btn.getAttribute('data-product_title') || btn.getAttribute('data-product-title')) || (btn.dataset && (btn.dataset.productTitle || btn.dataset.product_title)) || (document.querySelector('h1.product_title') ? document.querySelector('h1.product_title').innerText.trim() : document.title);
                    var price = btn.getAttribute && (btn.getAttribute('data-price')) || (btn.dataset && btn.dataset.price) || null;
                    var item = { item_id: pid || null, item_name: title, price: price ? parseFloat(String(price).replace(',', '.')) : null, quantity: 1, currency: getCurrency() };
                    pushAddToCart(item);
                } catch(e){ console.error('add_to_cart click handler', e); }
            }, true);
        })();

        // ---------- view_cart ----------
        (function(){
            try {
                var path = window.location.pathname || '';
                if (path.indexOf('/cart') !== -1 || document.body.classList.contains('woocommerce-cart')) {
                    var items = [];
                    var rows = document.querySelectorAll('.cart_item');
                    if (rows && rows.length) {
                        rows.forEach(function(r){
                            try {
                                var nameEl = r.querySelector('.product-name, .cart__product-name, a') || r.querySelector('td.product-name');
                                var qtyEl = r.querySelector('.quantity input, input.qty') || r.querySelector('.cart_item .qty');
                                var priceEl = r.querySelector('.product-price .amount, .woocommerce-Price-amount, .amount, .product-total .amount');
                                var qty = qtyEl ? (parseInt(qtyEl.value || (qtyEl.textContent || '').replace(/\D/g,'')) || 1) : 1;
                                var price = priceEl ? parsePriceText(priceEl.innerText) : null;
                                var name = nameEl ? nameEl.innerText.trim() : null;
                                items.push({ item_id: null, item_name: name, price: price, quantity: qty });
                            } catch(e){}
                        });
                    }
                    safePush({ event: 'view_cart', ecommerce: { items: items.length ? items : undefined }});
                }
            } catch(e){ console.error('view_cart error', e); }
        })();

        // ---------- begin_checkout ----------
        (function(){
            try {
                var path = window.location.pathname || '';
                if (path.indexOf('/checkout') !== -1 || document.body.classList.contains('woocommerce-checkout')) {
                    var lastE = (window.dataLayer || []).slice().reverse().find(function(d){ return d && d.ecommerce && d.event; });
                    if (lastE && lastE.ecommerce && lastE.ecommerce.items) {
                        safePush({ event: 'begin_checkout', ecommerce: lastE.ecommerce });
                    } else {
                        safePush({ event: 'begin_checkout', ecommerce: { items: undefined, value: null, currency: getCurrency() }});
                    }
                }
            } catch(e){ console.error('begin_checkout error', e); }
        })();

        // ---------- add_payment_info ----------
        (function(){
            try {
                document.addEventListener('change', function(e){
                    var el = e.target;
                    if(!el) return;
                    if(el.matches && el.matches('input[name="payment_method"], input[name^="payment_method"]')) {
                        safePush({ event: 'add_payment_info', payment_type: el.value || el.id || null });
                    }
                }, true);
            } catch(e){ console.error('add_payment_info setup error', e); }
        })();

        // ---------- add_shipping_info ----------
        (function(){
            try {
                document.addEventListener('submit', function(e){
                    var form = e.target && e.target.closest ? e.target.closest('form.checkout, form[name="checkout"]') : null;
                    if(!form) return;
                    var method = document.querySelector('input[name^="shipping_method"]:checked') || null;
                    var countrySel = form.querySelector('select[name="shipping_country"], select[name="billing_country"], select#shipping_country');
                    safePush({ event: 'add_shipping_info', shipping_method: method ? method.value : null, shipping_country: countrySel ? (countrySel.value || null) : null });
                }, true);
            } catch(e){ console.error('add_shipping_info setup error', e); }
        })();

        // ---------- purchase (only if not already pushed) ----------
        (function(){
            try {
                var found = (window.dataLayer || []).slice().reverse().find(function(d){ return d && d.event === 'purchase' && d.ecommerce; });
                if(found){
                    console.log('purchase already present in dataLayer:', found);
                    return;
                }
                var path = window.location.pathname || '';
                if(path.indexOf('order-received') !== -1 || document.body.classList.contains('order-received') || /\/checkout\/.*order-received/.test(path)) {
                    var txn = null, total = null, items = [];
                    var txnEl = document.querySelector('.woocommerce-order-overview__order, .order-number, .order > .order-number');
                    if(txnEl) {
                        var m = txnEl.innerText.match(/(\d{3,}|\d+)/);
                        if(m) txn = m[0];
                    }
                    var totEl = document.querySelector('.order-total .amount, .woocommerce-order-overview__total .amount, .order_total .amount');
                    if(totEl) total = parsePriceText(totEl.innerText);
                    var rows = document.querySelectorAll('.order_item, .woocommerce-table--order-items tr');
                    if(rows && rows.length){
                        rows.forEach(function(r){
                            try {
                                var nameEl = r.querySelector('.product-name, td.product-name, th');
                                var qtyEl = r.querySelector('.product-quantity, .quantity, .qty');
                                var priceEl = r.querySelector('.product-total .amount, .amount');
                                var name = nameEl ? nameEl.innerText.trim() : null;
                                var qty = qtyEl ? parseInt((qtyEl.innerText||'1').replace(/[^\d]/g,''))||1 : 1;
                                var price = priceEl ? parsePriceText(priceEl.innerText) : null;
                                if(name) items.push({ item_id: null, item_name: name, price: price, quantity: qty });
                            } catch(e){}
                        });
                    }
                    safePush({ event: 'purchase', ecommerce: { transaction_id: txn, value: total, currency: getCurrency(), items: items.length ? items : undefined }});
                }
            } catch(e){ console.error('purchase detection error', e); }
        })();

    }); // DOMContentLoaded
})(); // IIFE
