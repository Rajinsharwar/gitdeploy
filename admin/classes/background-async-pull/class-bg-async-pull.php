<?php

class MRS_GitDeploy_Async extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'mrs_gitdeploy_pull';

    private $status;

    private $reason;

    private $dynamic_pull_dir;

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
    protected function handle() {
        if ( ! get_option( 'mrs_gitdeploy_setup_complete' ) ) {
            $this->status = 'Failed';
            $this->reason = __( 'Setup of the plugin is not completed yet.', 'mrs-gitdeploy' );
            $deployment_log = new \MRS_GitDeploy_Deployments( $this->status, __( 'GitHub -> WP' ), $this->reason, array() );
            
            // process the next batch
            mrs_gitdeploy_process_next_deployment();
            return;
        }


        $creds = get_option( 'mrs_gitdeploy_creds', array() );
    
        $username = $creds[ 'mrs_gitdeploy_username' ] ?? '';
        $token = $creds[ 'mrs_gitdeploy_token' ] ?? '';
        $repo = $creds[ 'mrs_gitdeploy_repo' ] ?? '';
        $branch = $creds[ 'mrs_gitdeploy_repo_branch' ] ?? 'main';
    
        if ( isset( $_POST['changed_files'] ) && is_array( $_POST['changed_files'] ) ) {
            $changed_files = wp_unslash( $_POST['changed_files'] );
            $changed_files = array_map( 'sanitize_text_field', $changed_files );
            
            // Exclude 'plugins/mrs-gitdeploy'
            $filtered_files = array_filter( $changed_files, function( $file ) {
                return strpos( $file, 'plugins/mrs-gitdeploy' ) !== 0;
            });
        } else {
            $filtered_files = [];
        }               
        
        $changed_files = array();
        $changed_files = array_values( $filtered_files );
    
        $this->status = 'Success'; // Default status is success.
        $this->reason = '';

        // Define the directory to store the downloaded ZIP file and extracted contents
        $pull_dir = MRS_GITDEPLOY_PULL_DIR;
        if ( ! file_exists( $pull_dir ) ) {
            wp_mkdir_p( $pull_dir, 0755, true );
        }

        // Download the ZIP archive from GitHub
        $zip_file = $this->download_repo_zip( $username, $repo, $branch, $token, $pull_dir );

        if ( false !== $zip_file ) {            
            // Extract the ZIP archive
            $dynamic_pull_dir = $this->extract_zip( $zip_file, $pull_dir );

            if ( $dynamic_pull_dir ) {
                // Replace the changed files
                $this->dynamic_pull_dir = $dynamic_pull_dir;
                // This is a request to replace all files
                if ( 'all' === $changed_files[ 0 ] ) {
                    $this->replace_all_files( $dynamic_pull_dir );
                } else {
                    $this->replace_changed_files( $dynamic_pull_dir, $changed_files );   
                }

                // Clean up the downloaded ZIP and extracted files
                $this->cleanup_pull_dir( $pull_dir );
            }   
        } else {
            $this->status = 'Failed';
            $this->reason = __( 'Failed to download the GitHub repository ZIP file.', 'mrs-gitdeploy' );
        }

        delete_option( 'mrs_gitdeploy_deployment_in_progress' );
        $deployment_log = new \MRS_GitDeploy_Deployments( $this->status, __( 'GitHub -> WP' ), $this->reason, wp_json_encode( $changed_files ) );
        
        // process the next batch
        mrs_gitdeploy_process_next_deployment();
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
            $this->reason = __( 'Cannot find pathnames in Repo data.', 'mrs-gitdeploy' );
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
        global $wp_filesystem;
    
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
    
        if ( ! $wp_filesystem->is_dir( $source_dir ) ) {
            $this->status = 'Failed';
            $this->reason = __( 'Source directory does not exist.', 'mrs-gitdeploy' );
            return;
        }
    
        $files = $wp_filesystem->dirlist( $source_dir );
    
        if ( ! $files ) {
            $this->status = 'Failed';
            $this->reason = __( 'Couldn\'t scan Repo Data after downloading.', 'mrs-gitdeploy' );
            return;
        }
    
        foreach ( $files as $file => $file_info ) {
            // Build the source and destination paths.
            $source_path = trailingslashit( $source_dir ) . $file;
            $target_path = trailingslashit( $target_dir ) . $file;
    
            // Move the file or directory.
            $moved = $wp_filesystem->move( $source_path, $target_path, true );
    
            if ( ! $moved ) {
                $this->status = 'Failed';
                $this->reason = sprintf( __( 'Couldn\'t move file: %s to the target directory.', 'mrs-gitdeploy' ), $file );
                return;
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
        $content_dir = WP_CONTENT_DIR . '/';

        $plugin_dir = $content_dir . 'plugins/';
        $theme_dir = $content_dir . 'themes/';
        $mu_plugin_dir = $content_dir . 'mu-plugins/';

        if ( ! $changed_files || count( $changed_files ) < 0 ) {
            $this->status = 'Failed';
            $this->reason = __( 'No changed files found.', 'mrs-gitdeploy' );
            return false;
        }

        foreach ( $changed_files as $changed_file ) {
            if ( strpos( $changed_file, 'plugins/' ) === 0 ) {
                $src_file = $extract_folder . $changed_file;
                $dest_file = $plugin_dir . substr( $changed_file, strlen( 'plugins/' ) );

            } elseif ( strpos( $changed_file, 'themes/' ) === 0 ) {
                $src_file = $extract_folder . $changed_file;
                $dest_file = $theme_dir . substr( $changed_file, strlen( 'themes/' ) );

            } elseif ( strpos( $changed_file, 'mu-plugins/' ) === 0 ) {
                $src_file = $extract_folder . $changed_file;
                $dest_file = $mu_plugin_dir . substr( $changed_file, strlen( 'mu-plugins/' ) );

            } else {
                continue;
            }

            if ( file_exists( $src_file ) ) {
                if ( ! file_exists( dirname( $dest_file ) ) ) {
                    $mkdir = wp_mkdir_p( dirname( $dest_file ), 0755, true );

                    if ( ! $mkdir ) {
                        $this->status = 'Failed';
                        $this->reason = __( 'Couldn\'t create temporary directory for repo data.', 'mrs-gitdeploy' );
                        return false;
                    }
                }

                // Copy the file from the extracted folder to the correct location
                $copy = copy( $src_file, $dest_file );

                if ( ! $copy ) {
                    $this->status = 'Failed';
                    $this->reason = __( 'Couldn\'t copy repo data from temporary folder.', 'mrs-gitdeploy' );
                    return false;
                }
            } else {
                // If the source file does not exist, remove the file from the destination
                if ( file_exists( $dest_file ) ) {
                    $unlink = wp_delete_file( $dest_file );

                    if ( ! $unlink ) {
                        $this->status = 'Failed';
                        $this->reason = __( 'Couldn\'t delete one of the changed file from local codebase.', 'mrs-gitdeploy' );
                        return false;
                    }
                }
            }
        }
    }

    /**
     * Replace all files in the correct WordPress content directory (plugins, themes, mu-plugins),
     * except the files under the 'plugins/mrs-gitdeploy' directory.
     *
     * @param string $extract_folder The directory where the ZIP contents are extracted.
     */
    protected function replace_all_files( $extract_folder ) {
        $content_dir = wp_normalize_path( WP_CONTENT_DIR ) . '/';

        $plugin_dir = $content_dir . 'plugins/';
        $theme_dir = $content_dir . 'themes/';
        $mu_plugin_dir = $content_dir . 'mu-plugins/';

        $changed_files = $this->iterateFiles( $extract_folder );

        if ( ! $changed_files || count( $changed_files ) < 0 ) {
            $this->status = 'Failed';
            $this->reason = __( 'No plugins, themes or mu-plugins found in the Github repo', 'mrs-gitdeploy' );
            delete_option( 'mrs_gitdeploy_resync_in_progress' );
            return false;
        }

        foreach ( $changed_files as $changed_file ) {
            if ( strpos( $changed_file, 'plugins/' ) === 0 && strpos( $changed_file, 'plugins/mrs-gitdeploy/' ) !== 0 ) {
                $src_file = $extract_folder . $changed_file;
                $dest_file = $plugin_dir . substr( $changed_file, strlen( 'plugins/' ) );
            } elseif ( strpos( $changed_file, 'themes/' ) === 0 ) {
                $src_file = $extract_folder . $changed_file;
                $dest_file = $theme_dir . substr( $changed_file, strlen( 'themes/' ) );
            } elseif ( strpos( $changed_file, 'mu-plugins/' ) === 0 ) {
                $src_file = $extract_folder . $changed_file;
                $dest_file = $mu_plugin_dir . substr( $changed_file, strlen( 'mu-plugins/' ) );
            } else {
                continue;
            }

            if ( file_exists( $src_file ) ) {
                if ( ! file_exists( dirname( $dest_file ) ) ) {
                    $mkdir = wp_mkdir_p( dirname( $dest_file ), 0755, true );

                    if ( ! $mkdir ) {
                        $this->status = 'Failed';
                        $this->reason = __( 'Couldn\'t create temporary directory for repo data.', 'mrs-gitdeploy' );
                        delete_option( 'mrs_gitdeploy_resync_in_progress' );
                        return false;
                    }
                }

                // Copy the file from the extracted folder to the correct location
                $copy = copy( $src_file, $dest_file );

                if ( ! $copy ) {
                    $this->status = 'Failed';
                    $this->reason = __( 'Couldn\'t copy repo data from temporary folder.', 'mrs-gitdeploy' );
                    delete_option( 'mrs_gitdeploy_resync_in_progress' );
                    return false;
                }
            } else {
                // If the source file does not exist, remove the file from the destination
                if ( file_exists( $dest_file ) ) {
                    $unlink = wp_delete_file( $dest_file );

                    if ( ! $unlink ) {
                        $this->status = 'Failed';
                        $this->reason = __( 'Couldn\'t delete one of the changed file from local codebase.', 'mrs-gitdeploy' );
                        delete_option( 'mrs_gitdeploy_resync_in_progress' );
                        return false;
                    }
                }
            }
        }

        delete_option( 'mrs_gitdeploy_resync_in_progress' );
    }

    /**
     * Helper function to iterate through all files, and get an array of them.
     *
     * @param string $dir Directory to get all files of.
     */
    public function iterateFiles( $dir ) {
        $result = array();
        $cdir = scandir( $dir );
        
        foreach ( $cdir as $key => $value ) {
            if ( ! in_array( $value, array( ".", ".." ) ) ) {
                $fullPath = wp_normalize_path( $dir . DIRECTORY_SEPARATOR . $value );
                
                if ( is_dir( $fullPath ) ) {
                    // Merge the result of the recursive call
                    $result = array_merge( $result, $this->iterateFiles( $fullPath ) );
                } else {
                    $path = str_replace( $this->dynamic_pull_dir, '', $fullPath ); // Store the full path of the file
                    if ( strpos( $path, 'plugins' ) === 0 || 
                        strpos( $path, 'themes' ) === 0 || 
                        strpos( $path, 'mu-plugins' ) === 0 ||
                        strpos( $path, 'index' ) === 0 ) {
                            $result[] = $path;
                    }
                }
            }
        }

        return $result;
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
                wp_delete_file( $file );
            }
        }
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @param string $dir Directory to delete.
     */
    protected function delete_directory( $dir ) {
        global $wp_filesystem;
    
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        $files = array_diff( scandir( $dir ), [ '.', '..' ] );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            if ( is_dir( $path ) ) {
                $this->delete_directory( $path );
            } else {
                wp_delete_file( $path );
            }
        }

        $wp_filesystem->rmdir( $dir );
    }
}
