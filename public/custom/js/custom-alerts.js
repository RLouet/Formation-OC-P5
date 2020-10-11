$(document).ready(function() {

    var $flashContainer = $('#flashAlert');

    $('button.close', $flashContainer).click(function () {
        $flashContainer.hide(400);
    })

    setTimeout(function(){
        $flashContainer.hide(400);
    }, 10000);


})

function showFlashMessage(type, message) {
    var $flashContainer = $('#flashAlert');
    $flashContainer.removeClass('alert-success').removeClass('alert-danger');
    $('div', $flashContainer).remove();
    if (type === "success") {
        $flashContainer.addClass('alert-success')
    }
    if (type === "error") {
        $flashContainer.addClass('alert-danger')
    }
    $flashContainer.append('<div>' + message + '</div>');
    $flashContainer.addClass('show');
    $flashContainer.show(400);
    setTimeout(function(){
        $flashContainer.hide(400);
    }, 10000);
}