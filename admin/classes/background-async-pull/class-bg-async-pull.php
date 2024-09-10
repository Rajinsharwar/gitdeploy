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

        $status = 'Success'; // The default status is success.

        foreach ($changed_files as $file) {
            $url = "https://api.github.com/repos/$username/$repo/contents/$file?ref=$branch";
    
            $response = wp_remote_get($url, [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer ' . $token,
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]);
    
            if ( is_wp_error( $response ) ) {
                $status = 'Failed';
                continue; // Handle the error as needed
            }
    
            $body = wp_remote_retrieve_body($response);
            $file_data = json_decode($body, true);
            $upload_dir = WP_CONTENT_DIR . '/';
            $file_path = $upload_dir . $file;
    
            if (isset($file_data['content']) && $file_data['encoding'] == 'base64') {
                $file_content = base64_decode($file_data['content']);
    
                // Ensure the directory exists
                if (!file_exists(dirname($file_path))) {
                    mkdir(dirname($file_path), 0755, true);
                }
    
                // Write the file content
                file_put_contents($file_path, $file_content);
            } elseif ( 404 == $file_data['status'] ) {
                // Handle file or folder deletion
                if (file_exists($file_path)) {
                    unlink($file_path);
                    // Check if the directory is empty and delete it if so
                    $this->delete_empty_directories( dirname( $file_path ) );
                }
            }
        }

        $deployment_log = new \WP_GitDeploy_Deployments( $status, json_encode( $changed_files ) );
	}

    /**
     * Helper function to delete empty directories.
     *
     * @param string $dir Directory path.
     * @return void
     */
    private function delete_empty_directories( $dir ) {
        if ( is_dir( $dir ) ) {
            $items = array_diff( scandir( $dir ), [ '.', '..' ] ); // Get items excluding . and ..
            if ( empty( $items ) ) {
                rmdir( $dir ); // Delete the directory if empty
                $this->delete_empty_directories( dirname( $dir ) ); // Recursively check and delete parent directories if empty
            }
        }
    }
}