jQuery(document).ready(function($) {
    $('#resync-button').click(function() {
        $('#resync-status').html('<p>Resyncing...</p>');
        
        $.ajax({
            url: wp_gitdeploy_resync_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_gitdeploy_resync',
                nonce: wp_gitdeploy_resync_ajax.nonce
            },
            success: function(response) {
                $('#resync-status').html('<p>Resync Complete!</p>');
            },
            error: function() {
                $('#resync-status').html('<p>Resync Failed!</p>');
            }
        });
    });
});
