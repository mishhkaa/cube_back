FbEvents.ViewContent()
document.querySelectorAll('.btn-lead a.elementor-button').forEach(function(el) {
    el.addEventListener('click', function (e){
        e.preventDefault()
        FbEvents.Lead().then(function (){
            location.href = el.href;
        })
    })
})
