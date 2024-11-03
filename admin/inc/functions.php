<?php

/** FUnctions file for all the functions of the admin side of the plugin */

function mrs_gitdeploy_generate_zip() {
    $items_to_include = mrs_gitdeploy_allowed_items();

    // Clear out existing ZIPs.
    mrs_gitdeploy_delete_zips();

    $zip = new ZipArchive();
    $zip_file = MRS_GITDEPLOY_UPLOAD_DIR . 'wp-content-' . time() . '.zip';
    $zip_file_url = MRS_GITDEPLOY_UPLOAD_URL . 'wp-content-' . time() . '.zip';

    if ( ! is_dir( MRS_GITDEPLOY_UPLOAD_DIR ) ) {
        wp_mkdir_p( MRS_GITDEPLOY_UPLOAD_DIR );
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
                        if ( false === strpos( wp_normalize_path( $relative_path ), 'plugins/mrs-gitdeploy' ) ) {
                            $zip->addFile($file_path, $relative_path);
                        }
                    }
                }
            } elseif (is_file($full_path)) {
                // Add the individual file to the ZIP file
                $relative_path = substr($full_path, strlen($wp_content_dir) + 1);
                if ( false === strpos( wp_normalize_path( $relative_path ), 'plugins/mrs-gitdeploy' ) ) {
                    $zip->addFile($full_path, $relative_path);
                }
            }
        }

        $zip->close();

        // UnScheudle and Schedule cron to delete the ZIPs after 10 hours.
        wp_unschedule_hook( 'mrs_gitdeploy_delete_zip_cron' );
        wp_schedule_single_event( time() + 36000, 'mrs_gitdeploy_delete_zip_cron', array() );

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
function mrs_gitdeploy_allowed_items() {
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
function mrs_gitdeploy_delete_zips() {
    global $wp_filesystem;
    
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();

    $dir = MRS_GITDEPLOY_UPLOAD_DIR;

    // Check if the directory exists
    if ( is_dir( $dir ) ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $file_path = $fileinfo->getRealPath();
            if ($fileinfo->isDir()) {
                $wp_filesystem->rmdir($file_path); // Remove directory
            } else {
                wp_delete_file($file_path); // Remove file
            }
        }
    }
}

/**
 * Function to setup webhooks to complete setup.
 * 
 * @since 1.0
 */
function mrs_gitdeploy_finish_setup() {
    $creds = get_option( 'mrs_gitdeploy_creds', array() );

    $username = $creds[ 'mrs_gitdeploy_username' ] ?? '';
    $gh_token = $creds[ 'mrs_gitdeploy_token' ] ?? '';
    $repo_name = $creds[ 'mrs_gitdeploy_repo' ] ?? '';

    if ( ! $username || ! $gh_token || ! $repo_name ) {
        return __('Couldn\'t complete Setup. Required Credentials are missing. Please re-save the settings from Step 2', 'gitdeploy');
    }

    // GitHub API endpoint to create a webhook
    $api_url = "https://api.github.com/repos/$username/$repo_name/hooks";

    $secret = mrs_gitdeploy_create_github_secret( $username, $repo_name );
    // Webhook configuration
    $webhook_config = array(
        'url' => home_url('/wp-json/mrs_gitdeploy/v1/webhook'),
        'content_type' => 'json',
        'secret' => $secret
    );

    // Webhook events
    $webhook_events = array('push');

    // Data to send to GitHub API
    $data = wp_json_encode(array(
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
            __('Error setting up GitHub webhook: %s', 'gitdeploy'),
            $response->get_error_message()
        );
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_code = wp_remote_retrieve_response_code( $response );
    $response_data = json_decode($response_body, true);

    if ( 422 === $response_code ) {
        return true;
    }

    if ( 403 === $response_code ) {
        return __( 'Forbidden: API Rate limit exceeded', 'gitdeploy' );
    }

    if ( 404 === $response_code ) {
        return __( 'Repository credentials are not correct', 'gitdeploy' );
    }

    if ( isset( $response_data[ 'id' ] ) ) {
        $workflow_response = mrs_gitdeploy_fix_workflow_permissions( $username, $repo_name, $gh_token );

        if ( 'could_not_change_workflow_setting' === $workflow_response ) {
            return __( 'Couldn\'t modify Workflow permissions for your repository. Maybe your organization is preventing it?', 'gitdeploy' );
        } elseif ( 'api_limit_exceeded' === $workflow_response ) {
            return __( 'GitHub API rate limit exceeded of your account.', 'gitdeploy' );
        } else {
            return true;
        }
    } else {
        // Webhook creation failed
        return sprintf(
            __('Failed to create GitHub webhook. Error: %s', 'gitdeploy'),
            $response_data[ 'message' ]
        );
    }
}

/**
 * Function to set the workflow permissions for the repo to write.
 */
function mrs_gitdeploy_fix_workflow_permissions( $username, $repo_name, $gh_token ) {
    // GitHub API endpoint to create a webhook
    $api_url = "https://api.github.com/repos/$username/$repo_name/actions/permissions/workflow";

    // Data to send to GitHub API
    $data = wp_json_encode( array(
        'default_workflow_permissions' => 'write'
    ));

    // Set up the HTTP request
    $response = wp_remote_request( $api_url, array(
        'method'  => 'PUT',
        'headers' => array(
            'Authorization' => 'token ' . $gh_token,
            'Accept'        => 'application/vnd.github.v3+json',
            'User-Agent'    => 'WordPress GitDeploy Plugin'
        ),
        'body'      => $data,
        'timeout'   => 45,
        'sslverify' => false
    ));

    if ( is_wp_error( $response ) ) {
        return 'wp_error';
    }

    if ( 409 === wp_remote_retrieve_response_code( $response ) ) {
        return 'could_not_change_workflow_setting';
    } elseif ( 403 === wp_remote_retrieve_response_code( $response ) ) {
        return 'api_limit_exceeded';
    } else {
        return true;
    }
}

/**
 * Function to disable installing/updating/deleting of plugins and themes.
 * 
 * @since 1.0
 */
function mrs_gitdeploy_disable_wp_management() {
    if ( ! get_option( 'mrs_gitdeploy_disable_wp_management', false ) ) {
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
function mrs_gitdeploy_create_github_secret( $username, $repo_name ) {
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
function mrs_gitdeploy_verify_github_signature( $request, $signature ) {
    $raw_payload = $request->get_body();
	$creds = get_option( 'mrs_gitdeploy_creds', array() );
	$username = $creds[ 'mrs_gitdeploy_username' ] ?? '';
	$repo_name = $creds[ 'mrs_gitdeploy_repo' ] ?? '';
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
function mrs_gitdeploy_get_deployment_details() {
    check_ajax_referer('mrs_gitdeploy_deployments_view_nonce', 'security');

    global $wpdb;
    $id = isset( $_POST['id'] ) ? intval( sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) : 0;

    $deployment = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mrs_gitdeploy_deployments WHERE id = %d",
            $id
        )
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
            if ( 'all' === $files_changed[ 0 ] ) {
                $details .= '<li><i>' . __( '(Changed All Files in WordPress codebase from GitHub Repo)', 'gitdeploy' ) . '</i></li>';
            } else {
                foreach ($files_changed as $file) {
                    $details .= '<li>' . esc_html($file) . '</li>';
                }
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
function mrs_gitdeploy_resync_action() {
    // Check nonce for security
    check_ajax_referer('mrs_gitdeploy_resync_nonce', 'nonce');

    if ( ! get_option( 'mrs_gitdeploy_setup_complete' ) ) {
        $setup_url = esc_url( admin_url( 'admin.php?page=mrs_gitdeploy_setup' ) );

        $message = sprintf(
            __('<p><b style="color: red;">Setup not completed yet!</b> <br> Complete the <u><a href="%s" style="color: blue;">Setup</a></u> first.</p>', 'gitdeploy'),
            $setup_url
        );
        
        wp_send_json_success(array(
            'message' => wp_kses(
                $message,
                array(
                    'p' => array(),
                    'b' => array('style' => array()),
                    'br' => array(),
                    'i' => array(),
                    'u' => array(),
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                        'style' => array(),
                    ),
                )
            ),
        ));
        
    }    

    // Bail out for already running sync
    if ( 'yes' === get_option( 'mrs_gitdeploy_resync_in_progress' ) ) {
        wp_send_json_success(array(
            'message' => wp_kses(
                __('<p><b style="color: red;">One Resync action is already running!</b> <br> Check the <u>Actions</u> tab in your GitHub respository for the latest workflow progress.</p>', 'gitdeploy'),
                array(
                    'p' => array(),
                    'b' => array('style' => array()),
                    'br' => array(),
                    'i' => array(),
                    'u' => array(),
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                        'style' => array(),
                    )
                )
            )
        ));
    }

    $items_to_include = mrs_gitdeploy_allowed_items();

    $zip = new ZipArchive();
    $zip_file = MRS_GITDEPLOY_RESYNC_DIR . 'wp-content-' . time() . '.zip';
    $zip_file_url = MRS_GITDEPLOY_RESYNC_URL . 'wp-content-' . time() . '.zip';

    if ( ! is_dir( MRS_GITDEPLOY_RESYNC_DIR ) ) {
        wp_mkdir_p( MRS_GITDEPLOY_RESYNC_DIR );
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
                        if ( false === strpos( wp_normalize_path( $relative_path ), 'plugins/mrs-gitdeploy' ) ) {
                            $zip->addFile($file_path, $relative_path);
                        }
                    }
                }
            } elseif (is_file($full_path)) {
                // Add the individual file to the ZIP file
                $relative_path = substr($full_path, strlen($wp_content_dir) + 1);
                if ( false === strpos( wp_normalize_path( $relative_path ), 'plugins/mrs-gitdeploy' ) ) {
                    $zip->addFile($full_path, $relative_path);
                }
            }
        }

        $zip->close();
    }

    //Instantiate the class and run the resync
    $resync = new MRS_GitDeploy_Resync( $zip_file_url, $zip_file );
    $result = $resync->sync();
    if ( true === $result ) {
        $deployments_url = esc_url( admin_url( 'admin.php?page=mrs_gitdeploy_deployments' ) );
        $message = sprintf(
            __('<p><b> Resync request sent!</b> <br> Check the <a href="%s" target="_blank"><i><u>Deployments</u></i></a> tab for status.</p>', 'gitdeploy'),
            $deployments_url
        );

        // Return success response
        wp_send_json_success(array(
            'message' => wp_kses(
                $message,
                array(
                    'p' => array(),
                    'b' => array('style' => array()),
                    'br' => array(),
                    'i' => array(),
                    'u' => array(),
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                        'style' => array(),
                    )
                )
            )
        ));        
    } else {
        $deployments_url = esc_url( admin_url( 'admin.php?page=mrs_gitdeploy_deployments' ) );

        $message = sprintf(
            __('<p><b style="color: red;">Failed to resync!</b> <br> Check the <a href="%s" target="_blank"><i><u>Deployments</u></i></a> tab for info.</p>', 'gitdeploy'),
            $deployments_url
        );
        
        wp_send_json_error(array(
            'message' => wp_kses(
                $message,
                array(
                    'p' => array(),
                    'b' => array('style' => array()),
                    'br' => array(),
                    'i' => array(),
                    'u' => array(),
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                        'style' => array(),
                    )
                )
            )
        ));        
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
function mrs_gitdeploy_is_github_action_running( $run_id, $zip_file ) {
    $creds = get_option( 'mrs_gitdeploy_creds', array() );
    $token = $creds['mrs_gitdeploy_token'] ?? '';
    $repo = $creds['mrs_gitdeploy_repo'] ?? '';
    $username = $creds['mrs_gitdeploy_username'] ?? '';
    $branch = $creds['mrs_gitdeploy_repo_branch'] ?? 'main';

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
            $deployment_log = new MRS_GitDeploy_Deployments(
                $status, 
                __( 'WP -> GitHub', 'gitdeploy' ), 
                sprintf(
                    __( 'GitHub API rate limit exceeded. <br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'gitdeploy' ),
                    $limit_cap,
                    $rate_used,
                    $human_readable_time
                )
            );
            if ( file_exists( $zip_file ) ) {
                wp_delete_file( $zip_file );
            }
            update_option( 'mrs_gitdeploy_resync_in_progress', false, false );
            return false;
        }
    }

    if ( is_wp_error( $response ) ) {
        $status = 'Failed';
        $error_string = $response->get_error_message();
        $deployment_log = new MRS_GitDeploy_Deployments( $status, 
            __( 'WP -> GitHub', 'gitdeploy' ),
            sprintf(
                __( 'Error from WordPress. Error: %s. <br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'gitdeploy' ),
                $error_string,
                $limit_cap,
                $rate_used,
                $human_readable_time
            )
        );
        if ( file_exists( $zip_file ) ) {
            wp_delete_file( $zip_file );
        }
        update_option( 'mrs_gitdeploy_resync_in_progress', false, false );
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

/**
 * Function to create and update the workflow file after the setup is done.
 */
function mrs_gitdeploy_process_workflow_file() {
    $creds = get_option( 'mrs_gitdeploy_creds', array() );

    $username = $creds['mrs_gitdeploy_username'] ?? '';
    $gh_token = $creds['mrs_gitdeploy_token'] ?? '';
    $repo_name = $creds['mrs_gitdeploy_repo'] ?? '';
    $branch = $creds['mrs_gitdeploy_repo_branch'] ?? 'main';

    // Ensure credentials are set
    if ( empty( $username ) || empty( $gh_token ) || empty( $repo_name ) ) {
        return 'missing_creds';
    }

    // Path to the template file and destination workflow file
    $template_file = MRS_GITDEPLOY_PLUGIN_PATH . 'admin/inc/files/pull-from-wp.yml';
    $workflow_file = '.github/workflows/pull-from-wp.yml';

    // Check if the template file exists
    if ( ! file_exists( $template_file ) ) {
        return 'file_not_found';
    }

    // Read the contents of the template file
    $file_contents = file_get_contents( $template_file );
    if ( $file_contents === false ) {
        return 'file_read_error';
    }

    // Setup the GitHub API endpoint and headers
    $api_url = "https://api.github.com/repos/$username/$repo_name/contents/$workflow_file";
    $headers = [
        'Authorization' => 'Bearer ' . $gh_token,
        "Accept: application/vnd.github.v3+json"
    ];

    // Check if the file already exists in the repository
    $response = wp_remote_get( $api_url, [ 'headers' => $headers ] );
    $existing_sha = '';

    if ( is_wp_error( $response ) ) {
        return 'api_request_failed';
    }

    if ( wp_remote_retrieve_response_code( $response ) === 403 || wp_remote_retrieve_response_code( $response ) === 429 ) {
        return 'api_rate_limit_exceeded';
    }

    // If the file exists, get its SHA to update it
    if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $existing_sha = $body['sha'] ?? '';
    }

    // Prepare the payload for creating/updating the file
    $data = [
        'message'   => 'Add or update workflow file [skip gitdeploy]',
        'content'   => base64_encode( $file_contents ),
        'branch'    => $branch,
    ];

    if ( ! empty( $existing_sha ) ) {
        $data['sha'] = $existing_sha;
    }

    $response = wp_remote_request( $api_url, [
        'method'  => 'PUT',
        'headers' => $headers,
        'body'    => wp_json_encode( $data ),
    ]);

    if ( is_wp_error( $response ) ) {
        return 'api_request_failed_2';
    }

    if ( wp_remote_retrieve_response_code( $response ) === 403 || wp_remote_retrieve_response_code( $response ) === 429 ) {
        return 'api_rate_limit_exceeded';
    }

    if ( wp_remote_retrieve_response_code( $response ) !== 201 && wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return 'api_request_failed_3';
    }

    return true;
}

// Your PHP function that handles the resync
function mrs_gitdeploy_resync_all_files_from_github_repo() {
    update_option( 'mrs_gitdeploy_resync_in_progress', 'yes' );
    $payload = [
        'commits' => [
            [
                'added' => [ 'all' ]
            ]
        ]
    ];
    $process = new MRS_GitDeploy_Pull_from_GitHub( $payload );
}

/**
 * Process the next batch of deployments.
 */
function mrs_gitdeploy_process_next_deployment() {
    // check and process the next deployment
    $waiting_deployments = get_option( 'mrs_gitdeploy_waiting_deployments' );

    if ( false === $waiting_deployments ) {
        return;
    }

    if ( is_array( $waiting_deployments ) && isset( $waiting_deployments[ 0 ] ) ) {
        $process = new MRS_GitDeploy_Pull_from_GitHub( $waiting_deployments[ 0 ] );
    }

    // check and delete the deployment that has been fired.
    if ( false !== $waiting_deployments ) {
        unset( $waiting_deployments[ 0 ] );
        $waiting_deployments = array_values( $waiting_deployments );
        update_option( 'mrs_gitdeploy_waiting_deployments', $waiting_deployments, false );
    }
}

function mrs_gitdeploy_hide_notices_admin(){
    $screen = get_current_screen();

    $mrs_gitdeploy_hooks = [ 'toplevel_page_mrs_gitdeploy_setup',
    'gitdeploy_page_mrs_gitdeploy_settings',
    'gitdeploy_page_mrs_gitdeploy_deployments',
    'gitdeploy_page_mrs_gitdeploy_resync' ];

    if ( in_array( $screen->id, $mrs_gitdeploy_hooks ) ) {
        remove_all_actions( 'user_admin_notices' );
        remove_all_actions( 'admin_notices' );

        add_action( 'admin_notices', 'mrs_gitdeploy_setup_complete_now_resync' );
    }
}