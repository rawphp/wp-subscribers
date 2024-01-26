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

$("#update-subscriber").click(function() {
    var formData = {
        'action': 'update_subscriber',
        'subscriber_id': $("#edit-subscriber-id").val(),
        'name': $("#edit-subscriber-name").val(),
        'email': $("#edit-subscriber-email").val()
    };

    $.ajax({
        type: 'POST',
        url: mySubscriberAjax.ajaxurl,
        data: formData,
        success: function(response) {
            // Handle success (e.g., close modal, refresh list)
            $("#edit-subscriber-modal").dialog("close");
            // Refresh the subscribers list or show a success message
        }
    });
});
