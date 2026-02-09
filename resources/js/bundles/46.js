

    function sendClickButton() {
        if (window.FbEvents && typeof window.FbEvents.CustomEvent === 'function') {
            window.FbEvents.CustomEvent('click_button', {});
        }
    }

    function handleClick(e) {
        var target = e.target;
        if (!target || !target.closest) return;
        if (target.closest('.connect_wallet_button') || target.closest('.submit_button.connect_wallet')) {
            sendClickButton();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', handleClick, true);
        });
    } else {
        document.addEventListener('click', handleClick, true);
    }

