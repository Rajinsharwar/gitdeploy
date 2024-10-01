<?php

/**
 * Main Class file for deploying from GitHub to WP.
 */

class MRS_GitDeploy_Pull_from_GitHub {
    
    private $async_request;

    function __construct( $payload ) {
        $this->process( $payload );
    }

    public function process( $payload ) {
        $changed_files = [];
        // Extract changed files from the webhook payload
        if (isset($payload['commits']) && is_array($payload['commits'])) {
            foreach ($payload['commits'] as $commit) {
                if (isset($commit['added'])) {
                    $changed_files = array_merge($changed_files, $commit['added']);
                }
                if (isset($commit['modified'])) {
                    $changed_files = array_merge($changed_files, $commit['modified']);
                }
                if (isset($commit['removed'])) {
                    $changed_files = array_merge($changed_files, $commit['removed']);
                }
            }
        }
    
        if ( ! empty( $changed_files ) ) {
            $this->update_files_from_github( $changed_files );
        }
    }
    
    protected function update_files_from_github( $changed_files ) {
        $this->trigger_async_request( $changed_files );
    }

    /**
     * Dispatch the Async request.
     */
    public function trigger_async_request( $changed_files ) {
        $async_request = new MRS_GitDeploy_Async();
        $async_request->data( array( 'changed_files' => $changed_files ) )->dispatch();
    }
}