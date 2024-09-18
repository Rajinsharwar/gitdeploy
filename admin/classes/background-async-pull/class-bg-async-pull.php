<?php

class WP_GitDeploy_Async extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'wp_gitdeploy_pull';

    private $status;

    private $reason;

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
    
        $this->status = 'Success'; // Default status is success.
        $this->reason = '';

        // Define the directory to store the downloaded ZIP file and extracted contents
        $pull_dir = WP_GITDEPLOY_PULL_DIR;
        if ( ! file_exists( $pull_dir ) ) {
            mkdir( $pull_dir, 0755, true );
        }

        // Download the ZIP archive from GitHub
        $zip_file = $this->download_repo_zip( $username, $repo, $branch, $token, $pull_dir );

        if ( false !== $zip_file ) {            
            // Extract the ZIP archive
            $dynamic_pull_dir = $this->extract_zip( $zip_file, $pull_dir );

            if ( $dynamic_pull_dir ) {
                // Replace the changed files
                $this->replace_changed_files( $dynamic_pull_dir, $changed_files );

                // Clean up the downloaded ZIP and extracted files
                $this->cleanup_pull_dir( $pull_dir );
            }   
        } else {
            $this->status = 'Failed';
            $this->reason = __( 'Failed to download the GitHub repository ZIP file.', 'wp-gitdeploy' );
        }

        $deployment_log = new \WP_GitDeploy_Deployments( $this->status, __( 'GitHub -> WP' ), $this->reason, json_encode( $changed_files ) );
    }

    /**
     * Download the repository ZIP archive from GitHub.
     *
     * @param string $username GitHub username.
     * @param string $repo GitHub repository name.
     * @param string $branch Branch name.
     * @param string $token GitHub personal access token.
     * @param string $pull_dir The directory to save the downloaded ZIP file.
     * @return string|false Path to the downloaded ZIP file or false on failure.
     */
    protected function download_repo_zip( $username, $repo, $branch, $token, $pull_dir ) {
        // Create the dynamic ZIP file name based on the repo and timestamp
        $timestamp = time();
        $zip_file_name = $repo . '-' . $timestamp . '.zip';
        $zip_file = $pull_dir . $zip_file_name;

        $zip_url = "https://api.github.com/repos/$username/$repo/zipball/$branch";

        $response = wp_remote_get( $zip_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
            'timeout' => 1000,
            'stream' => true,
            'filename' => $zip_file,
        ]);

        $response_code = wp_remote_retrieve_response_code( $response );

        // Check for rate limit exceeded error
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $this->status = 'Failed';
            $this->reason = __( 'Unable to get Repository data' );
            return false;
        }

        return $zip_file;  // Return the dynamically named ZIP file path
    }

    /**
     * Extract the downloaded ZIP archive and move the contents to the desired root folder.
     *
     * @param string $zip_file Path to the ZIP file.
     * @param string $pull_dir Directory where the contents will be extracted.
     * @return string The extracted folder path.
     */
    protected function extract_zip( $zip_file, $pull_dir ) {
        global $wp_filesystem;

        // Initialize the filesystem, if it's not already initialized
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        WP_Filesystem();

        // Create the extracted folder name based on the ZIP file name (without the .zip extension)
        $extract_folder = $pull_dir . basename( $zip_file, '.zip' ) . '/';

        // Unzip the file to the dynamically named folder
        $extracted_zip_response = unzip_file( $zip_file, $extract_folder );

        if ( is_wp_error( $extracted_zip_response ) ) {
            $this->status = 'Failed';
            $this->reason = __( 'Unable to extract Repository data' );
            return false;
        }

        // Find the subfolder with the "weird" name (GitHub-specific folder)
        $subfolders = glob( $extract_folder . '*', GLOB_ONLYDIR );

        if ( ! $subfolders ) {
            $this->status = 'Failed';
            $this->reason = __( 'Cannot find pathnames in Repo data.', 'wp-gitdeploy' );
            return;
        }

        if ( count( $subfolders ) > 0 ) {
            // Move contents of the GitHub folder to the main extract folder
            $github_folder = $subfolders[0]; // This is the GitHub-generated folder
            $this->move_files_to_root( $github_folder, $extract_folder );

            // Remove the now-empty GitHub folder
            $this->delete_directory( $github_folder );
        } else {
            $this->status = 'Failed';
            $this->reason = __( 'No Repository data found' );
            return false;
        }

        return $extract_folder;  // Return the cleaned-up extracted folder path
    }

    /**
     * Move all files from the GitHub-generated folder to the main extracted folder.
     *
     * @param string $source_dir The GitHub-generated folder (unpredictable name).
     * @param string $target_dir The root folder where the contents should be moved.
     */
    protected function move_files_to_root( $source_dir, $target_dir ) {
        $files = scandir( $source_dir );

        if ( ! $files ) {
            $this->status = 'Failed';
            $this->reason = __( 'Couldn\'t scan Repo Data after downloading.', 'wp-gitdeploy' );
            return;
        }

        foreach ( $files as $file ) {
            if ( $file != '.' && $file != '..' ) {
                $rename = rename( $source_dir . '/' . $file, $target_dir . '/' . $file );

                if ( ! $rename ) {
                    $this->status = 'Failed';
                    $this->reason = __( 'Couldn\'t rename Repo data after downloading.', 'wp-gitdeploy' );
                    return;
                }
            }
        }
    }

    /**
     * Replace the changed files in the correct WordPress content directory (plugins, themes, mu-plugins).
     *
     * @param string $extract_folder The directory where the ZIP contents are extracted.
     * @param array $changed_files Array of changed file paths.
     */
    protected function replace_changed_files( $extract_folder, $changed_files ) {
        // Base directory for the wp-content directory
        $content_dir = WP_CONTENT_DIR . '/';

        // Directories for plugins, themes, and mu-plugins
        $plugin_dir = $content_dir . 'plugins/';
        $theme_dir = $content_dir . 'themes/';
        $mu_plugin_dir = $content_dir . 'mu-plugins/';

        if ( ! $changed_files || count( $changed_files ) < 0 ) {
            $this->status = 'Failed';
            $this->reason = __( 'No changed files found.', 'wp-gitdeploy' );
            return false;
        }

        foreach ( $changed_files as $changed_file ) {
            // Determine where the file should go based on its path
            if ( strpos( $changed_file, 'plugins/' ) === 0 ) {
                // If it's a plugin file, copy to the plugins directory
                $src_file = $extract_folder . $changed_file;
                $dest_file = $plugin_dir . substr( $changed_file, strlen( 'plugins/' ) );

            } elseif ( strpos( $changed_file, 'themes/' ) === 0 ) {
                // If it's a theme file, copy to the themes directory
                $src_file = $extract_folder . $changed_file;
                $dest_file = $theme_dir . substr( $changed_file, strlen( 'themes/' ) );

            } elseif ( strpos( $changed_file, 'mu-plugins/' ) === 0 ) {
                // If it's an mu-plugin file, copy to the mu-plugins directory
                $src_file = $extract_folder . $changed_file;
                $dest_file = $mu_plugin_dir . substr( $changed_file, strlen( 'mu-plugins/' ) );

            } else {
                // If it doesn't match any known type, continue (you can also log or handle this case if necessary)
                continue;
            }

            // Ensure the source file exists in the extracted folder
            if ( file_exists( $src_file ) ) {
                // Ensure the destination directory exists
                if ( ! file_exists( dirname( $dest_file ) ) ) {
                    $mkdir = mkdir( dirname( $dest_file ), 0755, true );

                    if ( ! $mkdir ) {
                        $this->status = 'Failed';
                        $this->reason = __( 'Couldn\'t create temporary directory for repo data.', 'wp-gitdeploy' );
                        return false;
                    }
                }

                // Copy the file from the extracted folder to the correct location
                $copy = copy( $src_file, $dest_file );

                if ( ! $copy ) {
                    $this->status = 'Failed';
                    $this->reason = __( 'Couldn\'t copy repo data from temporary folder.', 'wp-gitdeploy' );
                    return false;
                }
            } else {
                // If the source file does not exist, remove the file from the destination
                if ( file_exists( $dest_file ) ) {
                    $unlink = unlink( $dest_file );

                    if ( ! $unlink ) {
                        $this->status = 'Failed';
                        $this->reason = __( 'Couldn\'t delete one of the changed file from local codebase.', 'wp-gitdeploy' );
                        return false;
                    }
                }
            }
        }
    }

    /**
     * Cleanup the pull directory by deleting the ZIP and extracted files.
     *
     * @param string $pull_dir Directory to clean up.
     */
    protected function cleanup_pull_dir( $pull_dir ) {
        // Delete all files in the directory
        $files = glob( $pull_dir . '*', GLOB_MARK );

        foreach ( $files as $file ) {
            if ( is_dir( $file ) ) {
                $this->delete_directory( $file );
            } else {
                unlink( $file );
            }
        }
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @param string $dir Directory to delete.
     */
    protected function delete_directory( $dir ) {
        $files = array_diff( scandir( $dir ), [ '.', '..' ] );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            if ( is_dir( $path ) ) {
                $this->delete_directory( $path );
            } else {
                unlink( $path );
            }
        }

        rmdir( $dir );
    }
}
