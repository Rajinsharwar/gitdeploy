<?php

class WP_GitDeploy_Async extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'wp_gitdeploy_pull';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
    protected function handle() {
        $creds = get_option( 'wp_gitdeploy_creds', array() );
    
        $username = $creds[ 'wp_gitdeploy_username' ] ?? '';
        $token = $creds[ 'wp_gitdeploy_token' ] ?? '';
        $repo = $creds[ 'wp_gitdeploy_repo' ] ?? '';
        $branch = $creds[ 'wp_gitdeploy_repo_branch' ] ?? 'main';
    
        $changed_files = $_POST[ 'changed_files' ];
    
        $status = 'Success'; // Default status is success.
    
        // Fetch the entire repository recursively starting from the root
        $this->process_directory( $username, $repo, $branch, '', $changed_files, $token, $status );
    
        $deployment_log = new \WP_GitDeploy_Deployments( $status, json_encode( $changed_files ) );
    }

    /**
     * Method to Process the Content Directory
     */
    protected function process_directory( $username, $repo, $branch, $directory, $changed_files, $token, &$status ) {
        // Fetch the contents of the current directory ('' for the root)
        $url = "https://api.github.com/repos/$username/$repo/contents/$directory?ref=$branch";
    
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $token,
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
        ]);
    
        if ( is_wp_error( $response ) ) {
            $status = 'Failed';
            return;
        }
    
        $body = wp_remote_retrieve_body( $response );
        $directory_contents = json_decode( $body, true );
    
        if ( ! is_array( $directory_contents ) ) {
            $status = 'Failed';
            return;
        }
    
        // Extract paths of files and directories in the repo
        $repo_files = array_map( function ( $item ) {
            return $item[ 'path' ];
        }, $directory_contents );
    
        // Handle deletion of local files that no longer exist in the repo
        foreach ( $changed_files as $changed_file ) {
            if ( strpos( $changed_file, $directory ) === 0 ) {
                if ( ! in_array( $changed_file, $repo_files ) ) {
                    $this->delete_file( $changed_file, $status );
                }
            }
        }
    
        // Process each item in the current directory
        foreach ( $directory_contents as $item ) {
            $item_path = $item[ 'path' ];
    
            if ( $item[ 'type' ] === 'dir' ) {
                // Recursively process subdirectories
                $this->process_directory( $username, $repo, $branch, $item_path, $changed_files, $token, $status );
            } elseif ( $item[ 'type' ] === 'file' ) {
                // Download and save changed files
                if ( in_array( $item_path, $changed_files ) ) {
                    $this->download_and_save_file( $item, $changed_files, $status );
                }
            }
        }

        // Handle deletion of empty directories
        $upload_dir = WP_CONTENT_DIR . '/';

        foreach ( $changed_files as $changed_file ) {
            if ( strpos( $changed_file, $directory ) === 0) {
                if ( ! in_array( $changed_file, $repo_files ) ) {
                    $local_file_path = $upload_dir . $changed_file;
                    $this->delete_empty_directories( dirname( $local_file_path ) );
                }
            }
        }
    }
    
    /**
     * Method to Download and Save changed files.
     */
    protected function download_and_save_file( $file_data, $changed_files, &$status ) {
        $file_url = $file_data[ 'download_url' ];
    
        // Fetch the file content
        $file_content = wp_remote_get( $file_url );
    
        if ( is_wp_error( $file_content ) ) {
            $status = 'Failed';
            return;
        }
    
        $decoded_content = wp_remote_retrieve_body( $file_content );
    
        $upload_dir = WP_CONTENT_DIR . '/';
        $local_file_path = $upload_dir . $file_data[ 'path' ];
    
        // Ensure the directory exists
        if ( ! file_exists( dirname( $local_file_path ) ) ) {
            mkdir( dirname( $local_file_path ), 0755, true );
        }
    
        // Write the file content
        file_put_contents( $local_file_path, $decoded_content );
    }

    /**
     * Delete the files that doesn't exist in repo.
     */
    protected function delete_file( $file_path, &$status ) {
        $upload_dir = WP_CONTENT_DIR . '/';
        $local_file_path = $upload_dir . $file_path;
    
        if ( file_exists( $local_file_path ) ) {
            if ( ! unlink( $local_file_path ) ) {
                $status = 'Failed';
            }
        }
    }

    /**
     * Helper function to delete empty directories.
     *
     * @param string $dir Directory path.
     * @return void
     */
    protected function delete_empty_directories( $dir ) {
        // Check if the directory exists
        if ( is_dir( $dir ) ) {
            // Check if the directory is empty
            $files = scandir( $dir );
            if ( count( $files ) == 2 ) { // "." and ".." are always present in a directory
                if ( rmdir( $dir ) ) {
                    $this->delete_empty_directories( dirname( $dir ) );
                }
            }
        }
    }
}