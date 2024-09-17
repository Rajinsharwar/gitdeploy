<?php

add_action('rest_api_init', function () {
    register_rest_route('wp_gitdeploy/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'wp_gitdeploy_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function wp_gitdeploy_permission_callback(WP_REST_Request $request) {
    $headers = $request->get_headers();
    $signature = $headers['x_hub_signature_256'][0] ?? '';

    if (empty($signature)) {
        return new WP_Error('rest_forbidden', __('Invalid signature', 'wp-gitdeploy'), ['status' => 401]);
    }

    // Validate the signature
    $signature_validation = wp_gitdeploy_verify_github_signature($request, $signature);

    if (!$signature_validation) {
        return new WP_Error('rest_forbidden', __('Invalid signature', 'wp-gitdeploy'), ['status' => 401]);
    }

    return true; // Allow the request to proceed if the signature is valid
}

function wp_gitdeploy_handle_webhook(WP_REST_Request $request) {
    $payload = $request->get_json_params();

    // No need to Push when Pull event is runned from GH actions.
    if ( isset( $payload[ 'head_commit' ][ 'message' ] ) && str_contains( $payload[ 'head_commit' ][ 'message' ], '[skip ci]' ) ) {
        return new WP_REST_Response('Webhook not processed', 200);
    }

    // Process
    $process = new WP_GitDeploy_Pull_from_GitHub($payload);

    return new WP_REST_Response('Webhook processed successfully', 200);
}
