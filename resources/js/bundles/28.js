
    (function(){
    function waitForGAds(callback){
        if(window.GAdsConversion && typeof GAdsConversion.event === 'function'){
            callback();
        } else {
            setTimeout(() => waitForGAds(callback), 100);
        }
    }

    function attachButtonEvent(selector, text, eventName){
    const btn = Array.from(document.querySelectorAll(selector))
    .find(link => link.innerText.toLowerCase().includes(text.toLowerCase()));
    if(btn){
    btn.addEventListener('click', function(e){
    e.preventDefault();
    if(window.GAdsConversion && typeof GAdsConversion.event === 'function'){
    GAdsConversion.event(eventName);
    console.log(eventName, 'fired for button:', btn.innerText);
}
});
}
}

    function attachFormEvent(formSelector, eventName){
    const form = document.querySelector(formSelector);
    if(form){
    form.addEventListener('submit', function(){
    setTimeout(() => {
    if(window.GAdsConversion && typeof GAdsConversion.event === 'function'){
    GAdsConversion.event(eventName);
    console.log(eventName, 'fired for form submit');
}
}, 500);
});
}
}

    waitForGAds(function(){
    attachButtonEvent('a.button__link', 'Book a meeting with us', 'first_click');
    attachButtonEvent('a.button__link', 'Book a meeting', 'bookameeting_click');
    attachFormEvent('form', 'bookameeting_success');
});
})();
