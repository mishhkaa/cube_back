function DlEvent(event){
    if (!event || !event.event) {
        return;
    }

    if ('page_view' === event.event || 'PageView' === event.event) {
        if (typeof FbEvents !== 'undefined' && FbEvents.CustomEvent) {
            FbEvents.CustomEvent('PageView');
        } else if (typeof fbq !== 'undefined') {
            fbq('track', 'PageView');
        }
        return;
    }

    if ('search' === event.event || 'Search' === event.event) {
        const searchTerm = event.search_term || event.search_string || event.query || event.search || '';
        if (searchTerm && typeof FbEvents !== 'undefined') {
            var currency = (typeof FbEvents.getCurrency === 'function') ? FbEvents.getCurrency() : 'UAH';
            var searchData = {
                search_string: searchTerm,
                content_name: 'Пошук - ' + searchTerm,
                content_type: 'product',
                currency: currency,
                value: 0,
                content_ids: []
            };
            
            if (FbEvents.Search) {
                FbEvents.Search(searchTerm);
            } else if (FbEvents.CustomEvent) {
                FbEvents.CustomEvent('Search', searchData);
            } else if (typeof fbq !== 'undefined') {
                fbq('trackCustom', 'Search', searchData);
            }
        }
        return;
    }

    if ('login' === event.event) {
        if (typeof FbEvents !== 'undefined' && FbEvents.CustomEvent) {
            FbEvents.CustomEvent('Login');
        }
        return;
    }

    // Lead та CompleteRegistration відправляються через постбеки (CAPI 186) - видалено щоб уникнути дублів
    // if ('generate_lead' === event.event || 'lead' === event.event) {
    //     const value = (event.ecommerce && event.ecommerce.value) ? event.ecommerce.value : 0;
    //     FbEvents.Lead(value);
    //     return;
    // }

    // if ('sign_up' === event.event || 'complete_registration' === event.event || 'registration' === event.event) {
    //     FbEvents.CompleteRegistration();
    //     return;
    // }

    if ('view_item_list' === event.event || 'view_category' === event.event) {
        const categoryName = event.ecommerce?.item_list_name || event.ecommerce?.item_list_id || event.category_name || '';
        if (categoryName && typeof FbEvents !== 'undefined') {
            var currency = (typeof FbEvents.getCurrency === 'function') ? FbEvents.getCurrency() : 'UAH';
            var categoryData = {
                content_category: categoryName,
                content_type: 'product',
                currency: currency,
                content_ids: []
            };
            
            if (FbEvents.ViewCategory) {
                FbEvents.ViewCategory(categoryName);
            } else if (FbEvents.CustomEvent) {
                FbEvents.CustomEvent('ViewCategory', categoryData);
            } else if (typeof fbq !== 'undefined') {
                fbq('trackCustom', 'ViewCategory', categoryData);
            }
        }
        return;
    }

    if ('add_to_wishlist' === event.event) {
        if (event.ecommerce && event.ecommerce.items) {
            FbEvents.AddToWishlist(event.ecommerce.items);
        } else {
            FbEvents.AddToWishlist();
        }
        return;
    }

    if (!event.ecommerce || !event.ecommerce.currency || !event.ecommerce.items){
        return;
    }

    if ('view_item' === event.event) {
        FbEvents.ViewContent(event.ecommerce.items)
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items)
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    // Purchase відправляється через постбеки (CAPI 186) - видалено щоб уникнути дублів
    // if ('purchase' === event.event) {
    //     const txId = event.ecommerce.transaction_id;
    //     FbEvents.Purchase(event.ecommerce.items, txId)
    // }
}

subscribeToDataLayer(DlEvent);

(function() {
    if (typeof FbEvents !== 'undefined') {
        if (!FbEvents.Search) {
            FbEvents.Search = function(search_string) {
                var currency = (typeof FbEvents.getCurrency === 'function') ? FbEvents.getCurrency() : 'UAH';
                var searchData = {
                    search_string: search_string,
                    content_name: 'Пошук - ' + search_string,
                    content_type: 'product',
                    currency: currency,
                    value: 0,
                    content_ids: []
                };
                
                if (typeof FbEvents.CustomEvent === 'function') {
                    return FbEvents.CustomEvent('Search', searchData);
                } else if (typeof fbq !== 'undefined') {
                    fbq('trackCustom', 'Search', searchData);
                }
            };
        }
        
        if (!FbEvents.ViewCategory) {
            FbEvents.ViewCategory = function(categoryName) {
                var currency = (typeof FbEvents.getCurrency === 'function') ? FbEvents.getCurrency() : 'UAH';
                var categoryData = {
                    content_category: categoryName,
                    content_type: 'product',
                    currency: currency,
                    content_ids: []
                };
                
                if (typeof FbEvents.CustomEvent === 'function') {
                    return FbEvents.CustomEvent('ViewCategory', categoryData);
                } else if (typeof fbq !== 'undefined') {
                    fbq('trackCustom', 'ViewCategory', categoryData);
                }
            };
        }
    }
})();

(function() {
    function sendPageView() {
        if (typeof FbEvents !== 'undefined' && FbEvents.CustomEvent) {
            FbEvents.CustomEvent('PageView');
        } else if (typeof fbq !== 'undefined') {
            fbq('track', 'PageView');
        } else if (typeof FbEvents !== 'undefined' && FbEvents.send) {
            FbEvents.send('PageView');
        }
    }
    
    if (typeof FbEvents !== 'undefined') {
        sendPageView();
    } else {
        var checkFbEvents = setInterval(function() {
            if (typeof FbEvents !== 'undefined') {
                clearInterval(checkFbEvents);
                sendPageView();
            }
        }, 100);
        
        setTimeout(function() {
            clearInterval(checkFbEvents);
            sendPageView();
        }, 3000);
    }
})();

(function() {
    var viewContentItemsForAddToWishlist = null;
    var wishlistEventSent = false;
    
    if (typeof subscribeToDataLayer === 'function') {
        subscribeToDataLayer(function(event) {
            if (event.event === 'view_item' && event.ecommerce && event.ecommerce.items) {
                viewContentItemsForAddToWishlist = event.ecommerce.items;
            }
        });
    }
    
    function sendAddToWishlist() {
        if (wishlistEventSent) return;
        wishlistEventSent = true;
        
        if (viewContentItemsForAddToWishlist) {
            FbEvents.AddToWishlist(viewContentItemsForAddToWishlist);
        } else {
            try {
                if (window.dataLayer && Array.isArray(window.dataLayer)) {
                    for (let i = dataLayer.length - 1; i >= 0; i--) {
                        const event = dataLayer[i];
                        if (event && event.ecommerce && event.ecommerce.items) {
                            FbEvents.AddToWishlist(event.ecommerce.items);
                            return;
                        }
                    }
                }
            } catch(e) {}
            FbEvents.AddToWishlist();
        }
        
        setTimeout(function() {
            wishlistEventSent = false;
        }, 1000);
    }
    
    if (typeof window.wishlist !== 'undefined' && window.wishlist.add) {
        var originalWishlistAdd = window.wishlist.add;
        window.wishlist.add = function(productId) {
            var result = originalWishlistAdd.apply(this, arguments);
            sendAddToWishlist();
            return result;
        };
    }
    
    setTimeout(function() {
        document.querySelectorAll('.wish-but, button[onclick*="wishlist.add"]').forEach(function(button) {
            var onclickAttr = button.getAttribute('onclick') || '';
            if (onclickAttr && onclickAttr.includes('wishlist.add') && typeof window.wishlist !== 'undefined' && window.wishlist.add) {
                return;
            }
            
            button.addEventListener('click', function(e) {
                sendAddToWishlist();
            }, true);
        });
    }, 1000);
})();

(function() {
    function sendSearchEvent(searchTerm) {
        if (!searchTerm || typeof FbEvents === 'undefined') {
            return;
        }
        
        var currency = (typeof FbEvents.getCurrency === 'function') ? FbEvents.getCurrency() : 'UAH';
        var searchData = {
            search_string: searchTerm,
            content_name: 'Пошук - ' + searchTerm,
            content_type: 'product',
            currency: currency,
            value: 0,
            content_ids: []
        };
        
        if (FbEvents.Search) {
            FbEvents.Search(searchTerm);
        } else if (FbEvents.CustomEvent) {
            FbEvents.CustomEvent('Search', searchData);
        } else if (typeof fbq !== 'undefined') {
            fbq('trackCustom', 'Search', searchData);
        }
    }
    
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form.tagName === 'FORM') {
            var searchInput = form.querySelector('input[type="search"], input[name*="search"], input[name*="q"], input[placeholder*="пошук" i], input[placeholder*="search" i]');
            if (searchInput) {
                var searchTerm = searchInput.value.trim();
                if (searchTerm) {
                    setTimeout(function() {
                        sendSearchEvent(searchTerm);
                    }, 100);
                }
            }
        }
    });
    
    (function() {
        try {
            var urlParams = new URLSearchParams(window.location.search);
            var searchTerm = urlParams.get('q') || urlParams.get('search') || urlParams.get('query') || urlParams.get('s');
            if (searchTerm) {
                setTimeout(function() {
                    sendSearchEvent(searchTerm);
                }, 500);
            }
        } catch(e) {}
    })();
})();

(function() {
    var categorySent = false;
    var lastSentUrl = '';
    
    function isProductPage() {
        var pathname = window.location.pathname.toLowerCase();
        
        if (pathname.match(/\/(?:product|item|tovar|goods|p)\//i)) {
            return true;
        }
        
        var katalogMatch = pathname.match(/\/katalog\/([^\/]+(?:\/[^\/]+)*)/);
        if (katalogMatch) {
            var segments = katalogMatch[1].split('/').filter(function(s) { return s.length > 0; });
            if (segments.length > 1) {
                return true;
            }
        }
        
        var catalogMatch = pathname.match(/\/catalog\/([^\/]+(?:\/[^\/]+)*)/);
        if (catalogMatch) {
            var segments = catalogMatch[1].split('/').filter(function(s) { return s.length > 0; });
            if (segments.length > 1) {
                return true;
            }
        }
        
        try {
            if (window.dataLayer && Array.isArray(window.dataLayer)) {
                for (let i = dataLayer.length - 1; i >= 0; i--) {
                    const event = dataLayer[i];
                    if (event && event.event === 'view_item') {
                        return true;
                    }
                }
            }
        } catch(e) {}
        
        try {
            var productElements = document.querySelector('.product-detail, .product-details, .product-page, [class*="product-detail"], [class*="product-page"], [id*="product-detail"]');
            if (productElements) {
                return true;
            }
        } catch(e) {}
        
        return false;
    }
    
    function sendViewCategory(categoryName) {
        var currentUrl = window.location.pathname;
        
        if (!categoryName || typeof FbEvents === 'undefined' || isProductPage()) {
            return;
        }
        
        if (categorySent && lastSentUrl === currentUrl) {
            return;
        }
        
        categorySent = true;
        lastSentUrl = currentUrl;
        
        var currency = (typeof FbEvents.getCurrency === 'function') ? FbEvents.getCurrency() : 'UAH';
        var categoryData = {
            content_category: categoryName,
            content_type: 'product',
            currency: currency,
            content_ids: []
        };
        
        if (FbEvents.ViewCategory) {
            FbEvents.ViewCategory(categoryName);
        } else if (FbEvents.CustomEvent) {
            FbEvents.CustomEvent('ViewCategory', categoryData);
        } else if (typeof fbq !== 'undefined') {
            fbq('trackCustom', 'ViewCategory', categoryData);
        }
    }
    
    function getCategoryName() {
        var categoryName = '';
        
        try {
            if (window.dataLayer && Array.isArray(window.dataLayer)) {
                for (let i = dataLayer.length - 1; i >= 0; i--) {
                    const event = dataLayer[i];
                    if (event && (event.event === 'view_item_list' || event.event === 'view_category')) {
                        categoryName = (event.ecommerce && (event.ecommerce.item_list_name || event.ecommerce.item_list_id)) || event.category_name || '';
                        if (categoryName) break;
                    }
                }
            }
        } catch(e) {}
        
        if (!categoryName) {
            try {
                var urlParams = new URLSearchParams(window.location.search);
                categoryName = urlParams.get('category') || urlParams.get('cat') || urlParams.get('category_id') || '';
            } catch(e) {}
        }
        
        if (!categoryName) {
            try {
                var pathMatch = window.location.pathname.match(/\/(?:category|catalog|cat|kategoriya|katalog)\/([^\/\?]+)/i);
                if (pathMatch && pathMatch[1]) {
                    categoryName = decodeURIComponent(pathMatch[1]).replace(/[-_]/g, ' ');
                }
            } catch(e) {}
        }
        
        if (!categoryName) {
            try {
                var h1 = document.querySelector('h1, .page-title, .category-title, [class*="category"] h1, [class*="catalog"] h1');
                if (h1) {
                    categoryName = h1.textContent.trim();
                }
            } catch(e) {}
        }
        
        if (!categoryName) {
            try {
                var breadcrumb = document.querySelector('.breadcrumb, .breadcrumbs, [class*="breadcrumb"], [class*="category"]');
                if (breadcrumb) {
                    var links = breadcrumb.querySelectorAll('a');
                    if (links.length > 0) {
                        categoryName = links[links.length - 1].textContent.trim();
                    }
                }
            } catch(e) {}
        }
        
        if (!categoryName) {
            try {
                var categoryLink = document.querySelector('a[href*="category"], a[href*="cat="], a.active[href*="/"]');
                if (categoryLink) {
                    categoryName = categoryLink.textContent.trim() || categoryLink.getAttribute('title') || '';
                }
            } catch(e) {}
        }
        
        return categoryName;
    }
    
    function checkAndSendCategory() {
        if (isProductPage()) {
            return;
        }
        var categoryName = getCategoryName();
        if (categoryName) {
            sendViewCategory(categoryName);
        }
    }
    
    function resetOnUrlChange() {
        var currentUrl = window.location.pathname;
        if (lastSentUrl !== currentUrl) {
            categorySent = false;
            lastSentUrl = '';
        }
    }
    
    window.addEventListener('popstate', resetOnUrlChange);
    
    var originalPushState = history.pushState;
    if (originalPushState) {
        history.pushState = function() {
            originalPushState.apply(history, arguments);
            setTimeout(resetOnUrlChange, 100);
        };
    }
    
    var originalReplaceState = history.replaceState;
    if (originalReplaceState) {
        history.replaceState = function() {
            originalReplaceState.apply(history, arguments);
            setTimeout(resetOnUrlChange, 100);
        };
    }
    
    if (window.location.href.match(/category|catalog|cat=|kategoriya|katalog/i) && !isProductPage()) {
        setTimeout(function() {
            checkAndSendCategory();
        }, 1000);
    }
    
    if (typeof subscribeToDataLayer === 'function') {
        subscribeToDataLayer(function(event) {
            if (event && (event.event === 'view_item_list' || event.event === 'view_category') && !isProductPage()) {
                var categoryName = (event.ecommerce && (event.ecommerce.item_list_name || event.ecommerce.item_list_id)) || event.category_name || '';
                if (categoryName) {
                    sendViewCategory(categoryName);
                }
            }
        });
    }
})();

// Lead відправляється через постбеки (CAPI 186) - видалено щоб уникнути дублів
// (function() {
//     function sendLeadEvent() {
//         if (typeof FbEvents === 'undefined') {
//             return;
//         }
//         
//         var value = 0;
//         try {
//             var totalElement = document.querySelector('.table_total .total-text:last-child, .checkout-totals .total-text:last-child');
//             if (totalElement) {
//                 var totalText = totalElement.textContent.trim();
//                 var match = totalText.match(/([\d\s,]+)/);
//                 if (match) {
//                     value = parseFloat(match[1].replace(/\s/g, '').replace(',', '.')) || 0;
//                 }
//             }
//         } catch(e) {}
//         
//         FbEvents.Lead(value);
//     }
//     
//     function attachLeadEvent() {
//         var checkoutForm = document.getElementById('onepcheckout');
//         var submitButton = document.getElementById('button-register');
//         
//         if (submitButton) {
//             submitButton.addEventListener('click', function(e) {
//                 setTimeout(function() {
//                     sendLeadEvent();
//                 }, 100);
//             });
//         }
//         
//         if (checkoutForm) {
//             checkoutForm.addEventListener('submit', function(e) {
//                 setTimeout(function() {
//                     sendLeadEvent();
//                 }, 100);
//             });
//         }
//     }
//     
//     if (document.readyState === 'loading') {
//         document.addEventListener('DOMContentLoaded', attachLeadEvent);
//     } else {
//         attachLeadEvent();
//     }
//     
//     setTimeout(function() {
//         attachLeadEvent();
//     }, 2000);
// })();

(function() {
    try {
        function getCookie(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        }

        var medianUserId = getCookie('median_user_id');
        
        if (!medianUserId) {
            medianUserId = (typeof MedianGRPUtils !== 'undefined' && MedianGRPUtils.getUserId)
                ? MedianGRPUtils.getUserId()
                : '';
        }

        if (!medianUserId) {
            console.log('[Bundle 37] median_user_id not found in cookie or MedianGRPUtils');
            return;
        }

        console.log('[Bundle 37] Found median_user_id:', medianUserId);

        function addMedianUserIdToForms() {
            var forms = document.querySelectorAll('form');
            var addedCount = 0;
            
            forms.forEach(function(form) {
                if (form.querySelector('input[name="median_user_id"]')) {
                    return;
                }
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'median_user_id';
                input.value = medianUserId;
                form.appendChild(input);
                addedCount++;
                console.log('[Bundle 37] Added median_user_id field to form:', form);
            });
            
            if (addedCount > 0) {
                console.log('[Bundle 37] Added median_user_id to', addedCount, 'form(s)');
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addMedianUserIdToForms);
        } else {
            addMedianUserIdToForms();
        }

        setTimeout(function() {
            addMedianUserIdToForms();
        }, 1000);

        var observer = new MutationObserver(function() {
            addMedianUserIdToForms();
        });

        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        }
    } catch (e) {}
})();

(function() {
    if (typeof FbEvents === 'undefined') {
        return;
    }

    // CompleteRegistration відправляється через постбеки (CAPI 186) - видалено щоб уникнути дублів
    // function sendCompleteRegistration() {
    //     if (typeof FbEvents !== 'undefined' && FbEvents.CompleteRegistration) {
    //         FbEvents.CompleteRegistration();
    //     }
    // }

    function sendLogin() {
        if (typeof FbEvents !== 'undefined' && FbEvents.CustomEvent) {
            FbEvents.CustomEvent('Login');
        } else if (typeof fbq !== 'undefined') {
            fbq('track', 'Login');
        }
    }

    function handleRegistrationForm(form) {
        var formSubmitted = false;
        
        form.addEventListener('submit', function(e) {
            if (formSubmitted) return;
            
            setTimeout(function() {
                var currentUrl = window.location.href;
                var formAction = form.getAttribute('action') || '';
                
                // CompleteRegistration відправляється через постбеки (CAPI 186) - видалено щоб уникнути дублів
                // if (currentUrl.includes('account') || currentUrl.includes('register') || 
                //     currentUrl.includes('success') || currentUrl.includes('my-account') ||
                //     document.querySelector('.alert-success, .success-message, [class*="success"]')) {
                //     sendCompleteRegistration();
                //     formSubmitted = true;
                // } else {
                //     var checkSuccess = setInterval(function() {
                //         var newUrl = window.location.href;
                //         if (newUrl !== currentUrl || 
                //             document.querySelector('.alert-success, .success-message, [class*="success"]') ||
                //             newUrl.includes('account') || newUrl.includes('register') || newUrl.includes('success')) {
                //             clearInterval(checkSuccess);
                //             sendCompleteRegistration();
                //             formSubmitted = true;
                //         }
                //     }, 500);
                //     
                //     setTimeout(function() {
                //         clearInterval(checkSuccess);
                //     }, 5000);
                // }
            }, 100);
        });
    }

    function handleLoginForm(form) {
        var loginEventSent = false;
        
        form.addEventListener('submit', function(e) {
            if (loginEventSent) return;
            
            var initialUrl = window.location.href;
            var initialLoginFormExists = !!document.querySelector('form[action*="login"]');
            
            function checkAndSendLogin() {
                if (loginEventSent) return true;
                
                var currentUrl = window.location.href;
                var loginFormExists = !!document.querySelector('form[action*="login"]');
                var hasSuccessMessage = !!document.querySelector('.alert-success, .success-message, [class*="success"], .text-success');
                var hasAccountElements = !!document.querySelector('[href*="my-account"], [href*="logout"], .account-info, .user-info, .customer-name');
                var urlChanged = currentUrl !== initialUrl;
                var formDisappeared = initialLoginFormExists && !loginFormExists;
                
                if (urlChanged || formDisappeared || hasSuccessMessage || hasAccountElements || 
                    currentUrl.includes('my-account') || currentUrl.includes('account') ||
                    currentUrl.includes('dashboard') || currentUrl.includes('profile')) {
                    sendLogin();
                    loginEventSent = true;
                    return true;
                }
                return false;
            }
            
            setTimeout(function() {
                if (!checkAndSendLogin()) {
                    var checkSuccess = setInterval(function() {
                        if (checkAndSendLogin()) {
                            clearInterval(checkSuccess);
                        }
                    }, 300);
                    
                    setTimeout(function() {
                        clearInterval(checkSuccess);
                        if (!loginEventSent) {
                            sendLogin();
                            loginEventSent = true;
                        }
                    }, 2000);
                }
            }, 800);
        });
    }

    function attachFormHandlers() {
        var registrationForms = document.querySelectorAll('form[action*="create-account"], form[action*="route=account/register"], form[action*="/index.php?route=account/register"]');
        registrationForms.forEach(function(form) {
            if (!form.dataset.medianRegHandled) {
                form.dataset.medianRegHandled = 'true';
                handleRegistrationForm(form);
            }
        });

        var loginForms = document.querySelectorAll('form[action="https://25union.com.ua/login/"], form[action*="/login/"]');
        loginForms.forEach(function(form) {
            if (!form.dataset.medianLoginHandled) {
                form.dataset.medianLoginHandled = 'true';
                handleLoginForm(form);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachFormHandlers);
    } else {
        attachFormHandlers();
    }

    setTimeout(function() {
        attachFormHandlers();
    }, 1000);

    var observer = new MutationObserver(function() {
        attachFormHandlers();
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
