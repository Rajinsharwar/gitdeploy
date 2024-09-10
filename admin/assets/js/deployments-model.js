jQuery(document).ready(function($) {
    $('.wp-gitdeploy-view-details').click(function(e) {
        e.preventDefault();

        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_gitdeploy_get_deployment_details',
                id: id,
            },
            success: function(response) {
                if (response.success) {
                    $('#wp-gitdeploy-details-content').html(response.data);
                    $('#wp-gitdeploy-details-modal').fadeIn(); // Smoothly show the modal
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
    $('.wp-gitdeploy-close').click(function() {
        $('#wp-gitdeploy-details-modal').fadeOut(); // Smoothly hide the modal
    });

    // Close the modal when clicking outside the modal content
    $(window).click(function(e) {
        if ($(e.target).is('#wp-gitdeploy-details-modal')) {
            $('#wp-gitdeploy-details-modal').fadeOut();
        }
    });
});