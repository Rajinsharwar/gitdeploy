<?php

/** FUnctions file for all the functions of the admin side of the plugin */

function wp_gitdeploy_generate_zip() {
    $items_to_include = wp_gitdeploy_allowed_items();

    // Clear out existing ZIPs.
    wp_gitdeploy_delete_zips();

    $zip = new ZipArchive();
    $zip_file = WP_GITDEPLOY_UPLOAD_DIR . 'wp-content-' . time() . '.zip';
    $zip_file_url = WP_GITDEPLOY_UPLOAD_URL . 'wp-content-' . time() . '.zip';

    if ( ! is_dir( WP_GITDEPLOY_UPLOAD_DIR ) ) {
        wp_mkdir_p( WP_GITDEPLOY_UPLOAD_DIR );
    }

    if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
        $wp_content_dir = WP_CONTENT_DIR;

        foreach ( $items_to_include as $item ) {
            $full_path = $wp_content_dir . '/' . $item;

            if (is_dir($full_path)) {
                // Add the folder and its contents to the ZIP file
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full_path), RecursiveIteratorIterator::LEAVES_ONLY);

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $file_path = $file->getRealPath();
                        $relative_path = substr($file_path, strlen($wp_content_dir) + 1);
                        $zip->addFile($file_path, $relative_path);
                    }
                }
            } elseif (is_file($full_path)) {
                // Add the individual file to the ZIP file
                $relative_path = substr($full_path, strlen($wp_content_dir) + 1);
                $zip->addFile($full_path, $relative_path);
            }
        }

        $zip->close();

        // UnScheudle and Schedule cron to delete the ZIPs after 10 hours.
        wp_unschedule_hook( 'wp_gitdeploy_delete_zip_cron' );
        wp_schedule_single_event( time() + 36000, 'wp_gitdeploy_delete_zip_cron', array() );

        // Send JSON success.
        wp_send_json_success(['zip_url' => $zip_file_url ]);
    } else {
        wp_send_json_error();
    }
}

/**
 * FFunction to return the names of the folders and files to be included in the ZIP
 * 
 * @since 1.0
 */
function wp_gitdeploy_allowed_items() {
    $items_to_include = [
        'languages',
        'mu-plugins',
        'plugins',
        'themes',
        'index.php',
    ];

    return $items_to_include;
}

/**
 * Function to delete existing ZIPs from uploads
 * 
 * @since 1.0
 */
function wp_gitdeploy_delete_zips() {
    $dir = WP_GITDEPLOY_UPLOAD_DIR;

    // Check if the directory exists
    if ( is_dir( $dir ) ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $file_path = $fileinfo->getRealPath();
            if ($fileinfo->isDir()) {
                rmdir($file_path); // Remove directory
            } else {
                unlink($file_path); // Remove file
            }
        }
    }
}

/**
 * Function to setup webhooks to complete setup.
 * 
 * @since 1.0
 */
function wp_gitdeploy_finish_setup() {
    $creds = get_option( 'wp_gitdeploy_creds', array() );

    $username = $creds[ 'wp_gitdeploy_username' ] ?? '';
    $gh_token = $creds[ 'wp_gitdeploy_token' ] ?? '';
    $repo_name = $creds[ 'wp_gitdeploy_repo' ] ?? '';

    $webhook_created_for = 'https://github.com/' . $username . '/' . $repo_name . '/' . $gh_token;
    $webhook_already_created_for = get_option( 'wp_gitdeploy_webhook_created_repo', array() );

    if ( ! $username || ! $gh_token || ! $repo_name ) {
        return __('Couldn\'t complete Setup. Required Credentials are missing. Please re-save the settings from Step 2', 'wp-gitdeploy');
    }

    if ( isset( $webhook_already_created_for[ $webhook_created_for ] ) ) {
        return true;
    }

    // GitHub API endpoint to create a webhook
    $api_url = "https://api.github.com/repos/$username/$repo_name/hooks";

    $secret = wp_gitdeploy_create_github_secret( $username, $repo_name );
    // Webhook configuration
    $webhook_config = array(
        'url' => home_url('/wp-json/wp_gitdeploy/v1/webhook'),
        'content_type' => 'json',
        'secret' => $secret
    );

    // Webhook events
    $webhook_events = array('push');

    // Data to send to GitHub API
    $data = json_encode(array(
        'config' => $webhook_config,
        'events' => $webhook_events,
        'active' => true
    ));

    // Set up the HTTP request
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => 'token ' . $gh_token,
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress GitDeploy Plugin'
        ),
        'body' => $data,
        'timeout' => 45,
        'sslverify' => false
    ));

    // Check for errors
    if ( is_wp_error( $response ) ) {
        return sprintf(
            __('Error setting up GitHub webhook: %s', 'wp-gitdeploy'),
            $response->get_error_message()
        );
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    if ( isset( $response_data[ 'id' ] ) ) {
        // just adding it to the array.
        $webhook_already_created_for[ $webhook_created_for ] = 1;
        update_option( 'wp_gitdeploy_webhook_created_repo', $webhook_already_created_for, 'no' );
        return true;
    } else {
        // Webhook creation failed
        return sprintf(
            __('Failed to create GitHub webhook. Error: %s', 'wp-gitdeploy'),
            $response_data[ 'message' ]
        );
    }
}

/**
 * Function to disable installing/updating/deleting of plugins and themes.
 * 
 * @since 1.0
 */
function wp_gitdeploy_disable_wp_management() {
    if ( ! get_option( 'wp_gitdeploy_disable_wp_management', false ) ) {
        return;
    }
    define('DISALLOW_FILE_MODS',true);
}

/**
 * Create the GitHub webhook secret.
 *
 * @param string $username Username
 * @param string $repo_name Repo Name
 */
function wp_gitdeploy_create_github_secret( $username, $repo_name ) {
    return hash('sha256', $username . $repo_name);
}

/**
 * Verify the GitHub webhook signature.
 *
 * @param string $payload The raw payload from the webhook.
 * @param string $signature The X-Hub-Signature-256 header from the request.
 * @param string $secret The secret key used to set up the webhook.
 * @return bool True if the signature is valid, false otherwise.
 */
function wp_gitdeploy_verify_github_signature( $request, $signature ) {
    $raw_payload = $request->get_body();
	$creds = get_option( 'wp_gitdeploy_creds', array() );
	$username = $creds[ 'wp_gitdeploy_username' ] ?? '';
	$repo_name = $creds[ 'wp_gitdeploy_repo' ] ?? '';
	$secret = hash('sha256', $username . $repo_name);

	// Validate the signature
    $response = hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw_payload, $secret ), $signature ); 

	if ( ! $response ) {
        return false;
	} else {
        return true;
    }
}

/**
 * Send Deployment details via AJX
 */
function wp_gitdeploy_get_deployment_details() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wp_gitdeploy_deployments';
    $id = intval($_POST['id']);

    $deployment = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)
    );

    if ($deployment) {
        $files_changed = json_decode($deployment->files_changed, true);
        $details = '<p><strong>Time:</strong> ' . esc_html($deployment->deployment_time) . '</p>';
        $details .= '<p><strong>Status:</strong> ' . esc_html($deployment->status) . '</p>';
        $details .= '<p><strong>Files Changed:</strong></p><ul>';
        foreach ($files_changed as $file) {
            $details .= '<li>' . esc_html($file) . '</li>';
        }
        $details .= '</ul>';

        wp_send_json_success($details);
    } else {
        wp_send_json_error();
    }
}
