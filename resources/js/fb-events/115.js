const callback = (entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            FbEvents.Lead()
            observer.unobserve(entry.target);
        }
    });
};

const observer = new IntersectionObserver(callback, {
    root: null,
    threshold: 0.1
});

document.querySelectorAll('.w-form-done').forEach(function (el) {
    observer.observe(el);
})
var formLead =document.querySelector('[data-anchor="faq"] form')
if (formLead){
    formLead.addEventListener("submit", function(){
        FbEvents.Lead()
    }, false);
}

if (location.pathname.includes('/main-thanks')){
    FbEvents.Lead()
}
