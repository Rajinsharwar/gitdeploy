jQuery(document).ready(function($) {
    $('#generate-zip-btn').click(function() {
        $('#loading-indicator').show();
        $('#download-link').hide();
        
        $.ajax({
            url: ajaxurl, 
            type: 'POST',
            data: {
                action: 'mrs_gitdeploy_generate_zip',
            },
            success: function(response) {
                $('#loading-indicator').hide();
                if(response.success) {
                    $('#download-link').html('<a href="' + response.data.zip_url + '" class="button button-primary">Download ZIP</a>');
                    $('#download-link').show();
                } else {
                    alert('Failed to generate ZIP. Please try again.');
                }
            },
            error: function() {
                $('#loading-indicator').hide();
                alert('An error occurred. Please try again.');
            }
        });
    });
});