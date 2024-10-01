<?php

add_action('rest_api_init', function () {
    register_rest_route('mrs_gitdeploy/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'mrs_gitdeploy_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function mrs_gitdeploy_permission_callback(WP_REST_Request $request) {
    $headers = $request->get_headers();
    $signature = $headers['x_hub_signature_256'][0] ?? '';

    if (empty($signature)) {
        return new WP_Error('rest_forbidden', __('Invalid signature', 'mrs-gitdeploy'), ['status' => 401]);
    }

    // Validate the signature
    $signature_validation = mrs_gitdeploy_verify_github_signature($request, $signature);

    if (!$signature_validation) {
        return new WP_Error('rest_forbidden', __('Invalid signature', 'mrs-gitdeploy'), ['status' => 401]);
    }

    return true; // Allow the request to proceed if the signature is valid
}

function mrs_gitdeploy_handle_webhook(WP_REST_Request $request) {
    $payload = $request->get_json_params();

    // No need to Push when Pull event is runned from GH actions.
    if ( isset( $payload[ 'head_commit' ][ 'message' ] ) && str_contains( $payload[ 'head_commit' ][ 'message' ], '[skip ci]' ) ) {
        return new WP_REST_Response('Webhook not processed', 200);
    }

    // Process
    update_option( 'mrs_gitdeploy_deployment_in_progress', 'yes' );
    $process = new MRS_GitDeploy_Pull_from_GitHub($payload);

    return new WP_REST_Response('Webhook processed successfully', 200);
}
