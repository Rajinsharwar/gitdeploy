jQuery(document).ready(function($) {
    $('#resync-button').click(function() {
        $('#resync-status').html('<p>Resyncing...</p>');
        
        $.ajax({
            url: mrs_gitdeploy_resync_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mrs_gitdeploy_resync',
                nonce: mrs_gitdeploy_resync_ajax.nonce
            },
            success: function(response) {
                // Check if the response has the success key
                $('#resync-status').html(response.data.message);
            },
            error: function(response) {
                // Use the error message returned from the PHP function if available
                if (response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
                    $('#resync-status').html('<p>' + response.responseJSON.data.message + '</p>');
                } else {
                    $('#resync-status').html('<p>Resync Failed!</p>');
                }
            }
        });
    });
});
