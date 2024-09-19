<?php

add_action('rest_api_init', function () {
    register_rest_route('wp_gitdeploy/v1', '/push-completed', [
        'methods' => 'POST',
        'callback' => 'wp_gitdeploy_handle_push_completed',
        'permission_callback' => 'wp_gitdeploy_push_completed_permission_callback',
    ]);
});

/**
 * Permission callback for the push completed endpoint.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return bool True if permission is granted, false otherwise.
 */
function wp_gitdeploy_push_completed_permission_callback( $request ) {

    if ( 'yes' !== get_option( 'wp_gitdeploy_resync_in_progress' ) ) {
        return false;
    }

    // Get the file_name parameter from the request
    $file_name = $request->get_param( 'file_name' );
    $zip_file = WP_GITDEPLOY_RESYNC_DIR . $file_name;
    $action_id = $request->get_param( 'action_id' );

    // Perform a basic check to see if file_name is provided
    if ( ! empty( $file_name ) ) {
        if ( ! file_exists( $zip_file ) ) {
            return false;
        }
    }

    if ( wp_gitdeploy_is_github_action_running( $action_id, $zip_file ) ) {
        return true; // Permissions granted
    }

    return false; // Permissions denied
}

function wp_gitdeploy_handle_push_completed(WP_REST_Request $request) {
    $file_name = $request->get_param( 'file_name' );
    $zip_file = WP_GITDEPLOY_RESYNC_DIR . $file_name;

    if ( file_exists( $zip_file ) ) {
        unlink( $zip_file );
    }

    update_option( 'wp_gitdeploy_resync_in_progress', false, false );

    $status = 'Success';
    $deployment_log = new WP_GitDeploy_Deployments( $status, 
        __( 'WP -> GitHub' ), __( 'Resync Deployment from WordPress code to GitHub repository has been completed.' ) );

    return new WP_REST_Response('Webhook processed successfully', 200);
}
