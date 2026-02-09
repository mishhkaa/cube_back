(function() {
    var userIdName = 'median_user_id';
    var initKey = 'median_35_init';
    
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }
    
    function setCookie(name, value) {
        var date = new Date();
        date.setTime(date.getTime() + 2592000000);
        var expires = "; expires=" + date.toUTCString();
        document.cookie = name + "=" + value + expires + "; path=/";
    }
    
    function getQueryParam(name) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    
    function setUserId(userId) {
        try {
            localStorage.setItem(userIdName, userId);
        } catch(e) {}
        setCookie(userIdName, userId);
    }
    
    function getUserId() {
        var userId = getCookie(userIdName);
        if (!userId) {
            try {
                userId = localStorage.getItem(userIdName);
            } catch(e) {}
        }
        return userId;
    }
    
    function getRealIdFromParams() {
        var fbclid = getQueryParam('fbclid') || getCookie('fbclid');
        var ttclid = getQueryParam('ttclid') || getCookie('ttclid');
        var gclid = getQueryParam('gclid') || getCookie('gclid');
        
        if (fbclid) {
            setCookie('fbclid', fbclid);
            return fbclid;
        }
        if (ttclid) {
            setCookie('ttclid', ttclid);
            return ttclid;
        }
        if (gclid) {
            setCookie('gclid', gclid);
            return gclid;
        }
        
        return null;
    }

    function MedianGetExternalId() {
        var userId = getUserId();
        
        if (!userId) {
            userId = getRealIdFromParams();
            
            if (userId) {
                setUserId(userId);
            } else {
                var wasInit = sessionStorage.getItem(initKey);
                var hasInitParam = getQueryParam('_median_init');
                
                if (!wasInit && !hasInitParam) {
                    sessionStorage.setItem(initKey, '1');
                    var currentUrl = window.location.href.split('?')[0];
                    var separator = '?';
                    var newUrl = currentUrl + separator + '_median_init=1';
                    window.location.replace(newUrl);
                    return null;
                }
            }
        }
        
        if (typeof MedianGRPUtils !== 'undefined' && MedianGRPUtils.setUserId) {
            MedianGRPUtils.setUserId(userId);
        }
        
        return userId;
    }

    window.MedianGetExternalId = MedianGetExternalId;

    if (getQueryParam('_median_init')) {
        sessionStorage.removeItem(initKey);
    }
    
    MedianGetExternalId();
})();

