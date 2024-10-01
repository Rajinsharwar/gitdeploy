jQuery(document).ready(function($) {
    $('.mrs-gitdeploy-view-details').click(function(e) {
        e.preventDefault();

        var id = $(this).data('id');
        $.ajax({
            url: wpGitDeployData.ajaxurl, // Use localized ajaxurl
            type: 'POST',
            data: {
                action: 'mrs_gitdeploy_get_deployment_details',
                id: id,
                security: wpGitDeployData.nonce // Include nonce in request
            },
            success: function(response) {
                if (response.success) {
                    $('#mrs-gitdeploy-details-content').html(response.data);
                    $('#mrs-gitdeploy-details-modal').fadeIn(); // Smoothly show the modal
                } else {
                    alert('Failed to fetch details.');
                }
            },
            error: function() {
                alert('An error occurred.');
            }
        });
    });

    // Close the modal when the close button is clicked
    $('.mrs-gitdeploy-close').click(function() {
        $('#mrs-gitdeploy-details-modal').fadeOut(); // Smoothly hide the modal
    });

    // Close the modal when clicking outside the modal content
    $(window).click(function(e) {
        if ($(e.target).is('#mrs-gitdeploy-details-modal')) {
            $('#mrs-gitdeploy-details-modal').fadeOut();
        }
    });
});
