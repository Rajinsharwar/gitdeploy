<?php

function mrs_gitdeploy_admin_enqueue_scripts($hook) {
    // Check if we're on the admin page where we need the script
    $mrs_gitdeploy_hooks = [ 'toplevel_page_mrs_gitdeploy_setup',
    'gitdeploy_page_mrs_gitdeploy_settings',
    'gitdeploy_page_mrs_gitdeploy_deployments',
    'gitdeploy_page_mrs_gitdeploy_resync' ];
    if ( ! in_array( $hook, $mrs_gitdeploy_hooks ) ) {
        return;
    }

    // Enqueue the script

    if ( 'toplevel_page_mrs_gitdeploy_setup' === $hook ) {
        wp_enqueue_script(
            'mrs-gitdeploy-admin-repo-fields', // Handle
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/repo-fields.js', // Script path
            array('jquery'), // Dependencies
            null, // Version (null for no version)
            true // Load in footer
        );
    }

    if ( 'gitdeploy_page_mrs_gitdeploy_deployments' === $hook ) {
        wp_enqueue_script(
            'mrs-gitdeploy-deployments-model', // Handle
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/deployments-model.js', // Script path
            array('jquery'), // Dependencies
            null, // Version (null for no version)
            true // Load in footer
        );

        wp_localize_script('mrs-gitdeploy-deployments-model', 'wpGitDeployData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mrs_gitdeploy_deployments_view_nonce')
        ]);

        // Enqueue the style
        wp_enqueue_style(
            'mrs-gitdeploy-deployments-page', // Handle
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/deployments-page.css'
        );
    }

    if ( 'gitdeploy_page_mrs_gitdeploy_resync' === $hook ) {
        // Enqueue the script
        wp_enqueue_script('mrs-gitdeploy-resync', plugin_dir_url(__FILE__) . 'js/resync-screen.js', ['jquery'], null, true);

        // Localize the script with the AJAX URL and nonce
        wp_localize_script('mrs-gitdeploy-resync', 'mrs_gitdeploy_resync_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mrs_gitdeploy_resync_nonce')
        ]);

        // Enqueue the script
        wp_enqueue_script(
            'mrs-gitdeploy-admin-download-field', // Handle
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/download-zip.js', // Script path
            array('jquery'), // Dependencies
            null, // Version (null for no version)
            true // Load in footer
        );
    }

    // Enqueue the style
    wp_enqueue_style(
        'mrs-gitdeploy-setup-page', // Handle
        MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/setup-page.css'
    );
}
add_action('admin_enqueue_scripts', 'mrs_gitdeploy_admin_enqueue_scripts');
