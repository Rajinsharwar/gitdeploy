<?php

/**
 * Main Class file for resyncing from WP to GitHub.
 */
class MRS_GitDeploy_Resync {

    private $creds;
    private $token;
    private $repo;
    private $username;
    private $branch;
    private $workflow_file;
    private $workflow_dispatch_url;
    private $zip_file;
    private $zip_file_url;
    private $status;

    public function __construct( $zip_file_url, $zip_file ) {
        // Get GitHub credentials
        $this->creds = get_option( 'mrs_gitdeploy_creds', array() );
        $this->token = $this->creds['mrs_gitdeploy_token'] ?? '';
        $this->repo = $this->creds['mrs_gitdeploy_repo'] ?? '';
        $this->username = $this->creds['mrs_gitdeploy_username'] ?? '';
        $this->branch = $this->creds['mrs_gitdeploy_repo_branch'] ?? 'main';
        $this->workflow_file = 'pull-from-wp.yml';
        $this->workflow_dispatch_url = "https://api.github.com/repos/{$this->username}/{$this->repo}/actions/workflows/{$this->workflow_file}/dispatches";
        $this->zip_file = $zip_file;
        $this->zip_file_url = $zip_file_url;
    }

    public function sync() {
        update_option( 'mrs_gitdeploy_resync_status', 'two' );

        // pass true or false as string
        $is_basic_auth = ( 1 == get_option( 'mrs_gitdeploy_basic_auth_enabled' ) ) ? 'true' : 'false';
        $basic_auth_username = get_option( 'mrs_gitdeploy_basic_auth_username' ) ?? '';
        $basic_auth_password = get_option( 'mrs_gitdeploy_basic_auth_password' ) ?? '';

        $post_fields = wp_json_encode([
            'ref' => $this->branch,
            'inputs' => [
                'file_url'            => $this->zip_file_url,
                'file_name'           => basename( $this->zip_file ),
                'is_basic_auth'       => $is_basic_auth,
                'basic_auth_username' => $basic_auth_username,
                'basic_auth_password' => $basic_auth_password
            ]
        ]);
    
        $response = wp_remote_post( $this->workflow_dispatch_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/vnd.github.v3+json'
            ],
            'body' => $post_fields
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
            delete_option( 'mrs_gitdeploy_resync_in_progress' );
            delete_option( 'mrs_gitdeploy_resync_status' );
            if ( isset( $response_data['message'] ) && strpos( $response_data['message'], 'API rate limit exceeded') !== false ) {
                $this->status = 'Failed';
                $deployment_log = new MRS_GitDeploy_Deployments(
                    $this->status, 
                    __( 'WP -> GitHub', 'gitdeploy' ), 
                    sprintf(
                        __( 'GitHub API rate limit exceeded. <br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'gitdeploy' ),
                        $limit_cap,
                        $rate_used,
                        $human_readable_time
                    )
                );
                wp_delete_file( $this->zip_file );
                return false;
            } else {
                $this->status = 'Failed';
                $deployment_log = new MRS_GitDeploy_Deployments(
                    $this->status, 
                    __( 'WP -> GitHub', 'gitdeploy' ), 
                    __( 'Wrong API credentials given.', 'gitdeploy' )
                );
                wp_delete_file( $this->zip_file );
                return false;
            }
        }

        // check for no repo found.
        if ( $response_code === 404 ) {
            delete_option( 'mrs_gitdeploy_resync_in_progress' );
            delete_option( 'mrs_gitdeploy_resync_status' );

            $this->status = 'Failed';
            $deployment_log = new MRS_GitDeploy_Deployments(
                $this->status, 
                __( 'WP -> GitHub', 'gitdeploy' ),
                __( 'Connected GitHub Repository was not found.', 'gitdeploy' )
            );
            wp_delete_file( $this->zip_file );
            return false;
        }

        if ( is_wp_error( $response ) ) {
            delete_option( 'mrs_gitdeploy_resync_in_progress' );
            delete_option( 'mrs_gitdeploy_resync_status' );

            $this->status = 'Failed';
            $error_string = $response->get_error_message();
            $deployment_log = new MRS_GitDeploy_Deployments( $this->status, 
                __( 'WP -> GitHub', 'gitdeploy' ),
                sprintf(
                    __( 'Error from WordPress. Error: %s. <br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'gitdeploy' ),
                    $error_string,
                    $limit_cap,
                    $rate_used,
                    $human_readable_time
                )
            );
            wp_delete_file( $this->zip_file );
            return false;
        }
        
        if ( 204 === $response_code ) {
            update_option( 'mrs_gitdeploy_resync_status', 'three' );
            return true;
        } else {
            delete_option( 'mrs_gitdeploy_resync_in_progress' );
            delete_option( 'mrs_gitdeploy_resync_status' );

            $this->status = 'Failed';
            $error_string = wp_remote_retrieve_body( $response );
            $deployment_log = new MRS_GitDeploy_Deployments( $this->status, 
                __( 'WP -> GitHub', 'gitdeploy' ),
                sprintf(
                    __( 'Error from Github API. <br><br> %s. <br><br> API Limit Cap: %d. <br> API Rate used: %d. <br> API Limit will reset at: %s', 'gitdeploy' ),
                    $error_string,
                    $limit_cap,
                    $rate_used,
                    $human_readable_time
                )
            );
            wp_delete_file( $this->zip_file );
            return false;
        }
    }
}
