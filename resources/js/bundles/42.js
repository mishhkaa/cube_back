    // Збереження clickId з URL параметра (для партнерської інтеграції)
    if (window.MedianGRPUtils) {
        const clickIdFromUrl = window.MedianGRPUtils.getQueryParam('clickid');
        if (clickIdFromUrl) {
            // Зберігаємо clickId в cookie та через MedianGRPUtils
            window.MedianGRPUtils.setUserId(clickIdFromUrl);
            console.log("[PARTNER INTEGRATION] clickId saved from URL:", clickIdFromUrl);
        }
    }

    let registrationSent = false;

    function sendRegistration() {
        if (registrationSent) return;
        registrationSent = true;
        FbEvents.CompleteRegistration();
        console.log("[FB REGISTRATION] CompleteRegistration sent");
    }

    // Слухач на форму реєстрації
    const form = document.querySelector('[data-cy="sign-up-form"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            sendRegistration();
        });
    }

    // Слухач на кнопку Sign Up
    const signUpButton = document.querySelector('.action-button');
    if (signUpButton && signUpButton.textContent.trim() === 'Sign Up') {
        signUpButton.addEventListener('click', function(e) {
            setTimeout(() => sendRegistration(), 500);
        });
    }

    // Також слухаємо на зміну URL (якщо після реєстрації відбувається редирект)
    const originalPushState = history.pushState;
    const originalReplaceState = history.replaceState;

    history.pushState = function(...args) {
        originalPushState.apply(this, args);
        if (location.pathname.includes('success') || location.pathname.includes('profile') || location.pathname.includes('dashboard')) {
            sendRegistration();
        }
    };

    history.replaceState = function(...args) {
        originalReplaceState.apply(this, args);
        if (location.pathname.includes('success') || location.pathname.includes('profile') || location.pathname.includes('dashboard')) {
            sendRegistration();
        }
    };

    window.addEventListener('popstate', () => {
        if (location.pathname.includes('success') || location.pathname.includes('profile') || location.pathname.includes('dashboard')) {
            sendRegistration();
        }
    });


