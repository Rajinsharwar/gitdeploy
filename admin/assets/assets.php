<?php

function wp_gitdeploy_admin_enqueue_scripts($hook) {
    // Check if we're on the admin page where we need the script
    $wp_gitdeploy_hooks = [ 'toplevel_page_wp_gitdeploy_setup',
    'wp-gitdeploy_page_wp_gitdeploy_settings',
    'wp-gitdeploy_page_wp_gitdeploy_deployments',
    'wp-gitdeploy_page_wp_gitdeploy_resync' ];
    if ( ! in_array( $hook, $wp_gitdeploy_hooks ) ) {
        return;
    }

    // Enqueue the script

    if ( 'toplevel_page_wp_gitdeploy_setup' === $hook ) {
        wp_enqueue_script(
            'wp-git-deploy-admin-repo-fields', // Handle
            WP_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/repo-fields.js', // Script path
            array('jquery'), // Dependencies
            null, // Version (null for no version)
            true // Load in footer
        );
    }

    // Enqueue the script
    wp_enqueue_script(
        'wp-git-deploy-admin-download-field', // Handle
        WP_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/download-zip.js', // Script path
        array('jquery'), // Dependencies
        null, // Version (null for no version)
        true // Load in footer
    );

    if ( 'wp-gitdeploy_page_wp_gitdeploy_deployments' === $hook ) {
        wp_enqueue_script(
            'wp-git-deploy-deployments-model', // Handle
            WP_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/deployments-model.js', // Script path
            array('jquery'), // Dependencies
            null, // Version (null for no version)
            true // Load in footer
        );

        // Enqueue the style
        wp_enqueue_style(
            'wp-git-deploy-deployments-page', // Handle
            WP_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/deployments-page.css'
        );
    }

    if ( 'wp-gitdeploy_page_wp_gitdeploy_resync' === $hook ) {
        // Enqueue the script
        wp_enqueue_script('wp-git-deploy-resync', plugin_dir_url(__FILE__) . 'js/resync-screen.js', ['jquery'], null, true);

        // Localize the script with the AJAX URL and nonce
        wp_localize_script('wp-git-deploy-resync', 'wp_gitdeploy_resync_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_gitdeploy_resync_nonce')
        ]);
    }

    // Enqueue the style
    wp_enqueue_style(
        'wp-git-deploy-setup-page', // Handle
        WP_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/setup-page.css'
    );
}
add_action('admin_enqueue_scripts', 'wp_gitdeploy_admin_enqueue_scripts');
