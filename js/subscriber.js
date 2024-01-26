jQuery(document).ready(function($) {
    $('#subscriber-form').submit(function(e) {
        e.preventDefault();
        var formData = {
            'action': 'save_subscriber',
            'name': $('input[name=name]').val(),
            'email': $('input[name=email]').val()
        };
        $.ajax({
            type: 'POST',
            url: mySubscriberAjax.ajaxurl,
            data: formData,
            success: function(response) {
                $('#form-message').text(response.data);
                $('#subscriber-form')[0].reset(); // Clear the form fields.
            }
        });
    });
});
