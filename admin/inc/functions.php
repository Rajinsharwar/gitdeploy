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
 * Function to return the names of the folders and files to be included in the ZIP
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
        $reason = esc_html($deployment->reason);
        $details = '<p><strong>Time:</strong> ' . esc_html($deployment->deployment_time) . '</p>';
        $details .= '<p><strong>Status:</strong> ' . esc_html($deployment->status) . '</p>';
        if ( '' !== $reason ) {
            $details .= '<p><strong>Details: </strong> ' . wp_kses( $deployment->reason, array( 'br' => array() ) ) . '</p>';
        }

        if ( $files_changed ) {
            $details .= '<p><strong>Files Changed:</strong></p><ul>';
            foreach ($files_changed as $file) {
                $details .= '<li>' . esc_html($file) . '</li>';
            }
        }
        $details .= '</ul>';

        wp_send_json_success($details);
    } else {
        wp_send_json_error();
    }
}

/**
 * Ajax handler for Resync Action
 */
function wp_gitdeploy_resync_action() {
    // Check nonce for security
    check_ajax_referer('wp_gitdeploy_resync_nonce', 'nonce');

    $items_to_include = wp_gitdeploy_allowed_items();

    $zip = new ZipArchive();
    $zip_file = WP_GITDEPLOY_RESYNC_DIR . 'wp-content-' . time() . '.zip';
    $zip_file_url = WP_GITDEPLOY_RESYNC_URL . 'wp-content-' . time() . '.zip';

    if ( ! is_dir( WP_GITDEPLOY_RESYNC_DIR ) ) {
        wp_mkdir_p( WP_GITDEPLOY_RESYNC_DIR );
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
    }

    //Instantiate the class and run the resync
    $resync = new WP_GitDeploy_Resync( $zip_file_url, $zip_file );
    if ( true === $result = $resync->sync() ) {
        // Return success response
        wp_send_json_success('Resync Complete');   
    } else {
        wp_send_json_error('Failed to Resync');
    }
}

/**
 * Check if a GitHub Actions workflow run is currently running.
 *
 * @param string $token GitHub personal access token.
 * @param string $owner Repository owner.
 * @param string $repo Repository name.
 * @param int $run_id GitHub Actions run ID.
 * @return bool True if the workflow run is currently running, false otherwise.
 */
function wp_gitdeploy_is_github_action_running( $run_id, $zip_file ) {
    $creds = get_option( 'wp_gitdeploy_creds', array() );
    $token = $creds['wp_gitdeploy_token'] ?? '';
    $repo = $creds['wp_gitdeploy_repo'] ?? '';
    $username = $creds['wp_gitdeploy_username'] ?? '';
    $branch = $creds['wp_gitdeploy_repo_branch'] ?? 'main';

    $api_url = "https://api.github.com/repos/$username/$repo/actions/runs/$run_id";

    $response = wp_remote_get( $api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/vnd.github.v3+json'
        ]
    ]);

    $response_code = wp_remote_retrieve_response_code( $response );
    // Check for rate limit exceeded error
    $response_body = wp_remote_retrieve_body( $response );
    $response_data = json_decode( $response_body, true );
    $response_header = wp_remote_retrieve_headers( $response );
    $limit_cap = $response_header[ 'X-RateLimit-Limit' ] ?? 'N/A';
    $rate_used = $response_header[ 'X-RateLimit-Used' ] ?? 'N/A';
    $reset_time = $response_header[ 'X-RateLimit-Reset' ] ?? 'N/A';

    if ($reset_time !== 'N/A') {
        $human_readable_time = gmdate('Y-m-d H:i:s', $reset_time) . ' UTC';
    } else {
        $human_readable_time = 'N/A';
    }

    // check for api rate limit.
    if ( $response_code === 403 ) {
        if ( isset( $response_data['message'] ) && strpos( $response_data['message'], 'API rate limit exceeded') !== false ) {
            $status = 'Failed';
            $deployment_log = new WP_GitDeploy_Deployments(
                $status, 
                __( 'WP -> GitHub', 'wp-gitdeploy' ), 
                sprintf(
                    __( 'GitHub API rate limit exceeded. <br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'wp-gitdeploy' ),
                    $limit_cap,
                    $rate_used,
                    $human_readable_time
                )
            );
            if ( file_exists( $zip_file ) ) {
                unlink( $zip_file );
            }
            update_option( 'wp_gitdeploy_resync_in_progress', false, false );
            return false;
        }
    }

    if ( is_wp_error( $response ) ) {
        $status = 'Failed';
        $error_string = $response->get_error_message();
        $deployment_log = new WP_GitDeploy_Deployments( $status, 
            __( 'WP -> GitHub' ),
            sprintf(
                __( 'Error from WordPress. Error: %s. <br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'wp-gitdeploy' ),
                $error_string,
                $limit_cap,
                $rate_used,
                $human_readable_time
            )
        );
        if ( file_exists( $zip_file ) ) {
            unlink( $zip_file );
        }
        update_option( 'wp_gitdeploy_resync_in_progress', false, false );
        return false;
    }

    if ( 200 === $response_code ) {
        if ( isset( $response_data[ 'status' ] ) && 
        $response_data[ 'status' ] !== 'queued' ||
        $response_data[ 'status' ] !== 'requested' ||
        $response_data[ 'status' ] !== 'in_progress' ||
        $response_data[ 'status' ] !== 'waiting' ||
        $response_data[ 'status' ] !== 'requested' ) {
            return true; // the action workflow is not running, so we can now proceed with deletion.
        }
    }
}
