<?php

/**
 * Main Class file for deploying from GitHub to WP.
 */

class WP_GitDeploy_Deployments {

    function __construct( $status, $type, $reason = '', $files_changed = '' ) {
        $this->save_deployment_log( $status, $type, $reason, $files_changed );
    }

    private function save_deployment_log( $status, $type, $reason = '', $files_changed = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_gitdeploy_deployments';

        // Insert deployment record into the database
        $wpdb->insert(
            $table_name,
            [
                'status' => $status,
                'type' => $type,
                'reason' => $reason,
                'files_changed' => $files_changed,
            ]
        );
    }
}
