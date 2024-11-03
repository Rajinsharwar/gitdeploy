<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

add_action('rest_api_init', function () {
    register_rest_route('mrs_gitdeploy/v1', '/push-completed', [
        'methods' => 'POST',
        'callback' => 'mrs_gitdeploy_handle_push_completed',
        'permission_callback' => 'mrs_gitdeploy_push_completed_permission_callback',
    ]);
});

/**
 * Permission callback for the push completed endpoint.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return bool True if permission is granted, false otherwise.
 */
function mrs_gitdeploy_push_completed_permission_callback( $request ) {

    if ( 'yes' !== get_option( 'mrs_gitdeploy_resync_in_progress' ) ) {
        return false;
    }

    // Get the file_name parameter from the request
    $file_name = $request->get_param( 'file_name' );
    $zip_file = MRS_GITDEPLOY_RESYNC_DIR . $file_name;
    $action_id = $request->get_param( 'action_id' );

    // Perform a basic check to see if file_name is provided
    if ( ! empty( $file_name ) ) {
        if ( ! file_exists( $zip_file ) ) {
            return false;
        }
    }

    if ( mrs_gitdeploy_is_github_action_running( $action_id, $zip_file ) ) {
        return true; // Permissions granted
    }

    return false; // Permissions denied
}

function mrs_gitdeploy_handle_push_completed(WP_REST_Request $request) {
    $file_name = $request->get_param( 'file_name' );
    $status_from_gh = $request->get_param( 'status' );
    $zip_file = MRS_GITDEPLOY_RESYNC_DIR . $file_name;

    if ( file_exists( $zip_file ) ) {
        wp_delete_file( $zip_file );
    }

    delete_option( 'mrs_gitdeploy_resync_in_progress' );

    if ( 'success' === $status_from_gh ) {
        $status = 'Success';
        $deployment_log = new MRS_GitDeploy_Deployments( $status, 
            __( 'WP -> GitHub', 'gitdeploy' ), __( 'Resync Deployment from WordPress code to GitHub repository has been completed.', 'gitdeploy' ) );
        
        delete_option( 'mrs_gitdeploy_first_resync' );
        return new WP_REST_Response('Webhook processed successfully', 200);
    } else {
        $status = 'Failed';
        $deployment_log = new MRS_GitDeploy_Deployments( $status, 
            __( 'WP -> GitHub', 'gitdeploy' ), __( 'An error was reported from GitHub Actions. Please Check the latest WorkFlow job under the Actions tab of your repository.', 'gitdeploy' ) );
    
        return new WP_REST_Response('Webhook not processed successfully', 404);
    }
}
