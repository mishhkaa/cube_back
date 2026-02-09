

    var AD_URL_KEY = 'median_grp_adUrl';
    var UTM_PREFIX = 'median_grp_';

    function getAllUtmFromUrl(url) {
        try {
            var u = url || (typeof location !== 'undefined' ? location.href : '');
            var parsed = new URL(u, u.startsWith('http') ? undefined : 'http://localhost');
            var params = parsed.searchParams;
            var utm = {};

            params.forEach(function (value, key) {
                if (key.toLowerCase().indexOf('utm_') === 0) {
                    utm[key] = value;
                }
            });

            return utm;
        } catch (e) {
            return {};
        }
    }

    function hasUtmInUrl(url) {
        var u = url || (typeof location !== 'undefined' ? location.href : '');
        try {
            var parsed = new URL(u, u.startsWith('http') ? undefined : 'http://localhost');
            var hasUtm = false;
            parsed.searchParams.forEach(function (_, key) {
                if (key.toLowerCase().indexOf('utm_') === 0) hasUtm = true;
            });
            return hasUtm;
        } catch (e) {
            return false;
        }
    }

    function saveUtm() {
        var url = typeof location !== 'undefined' ? location.href : '';
        if (!hasUtmInUrl(url)) return;

        var utm = getAllUtmFromUrl(url);
        if (Object.keys(utm).length === 0) return;

        try {
            for (var key in utm) {
                localStorage.setItem(UTM_PREFIX + key, utm[key]);
            }
            localStorage.setItem(AD_URL_KEY, url);
        } catch (e) {}
    }

    saveUtm();

    if (typeof history !== 'undefined' && history.replaceState) {
        var origReplace = history.replaceState;
        history.replaceState = function () {
            origReplace.apply(this, arguments);
            saveUtm();
        };
        var origPush = history.pushState;
        history.pushState = function () {
            origPush.apply(this, arguments);
            saveUtm();
        };
    }

