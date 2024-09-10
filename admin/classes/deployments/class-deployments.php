<?php

/**
 * Main Class file for deploying from GitHub to WP.
 */

class WP_GitDeploy_Deployments {

    function __construct( $status, $files_changed ) {
        $this->save_deployment_log( $status, $files_changed );
    }

    private function save_deployment_log( $status, $files_changed ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_gitdeploy_deployments';

        // Insert deployment record into the database
        $wpdb->insert(
            $table_name,
            [
                'status' => $status,
                'files_changed' => $files_changed,
            ]
        );
    }
}
