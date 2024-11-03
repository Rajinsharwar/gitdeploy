<?php

function mrs_gitdeploy_admin_enqueue_scripts($hook) {
    // Enqueue admin toolbar style
    wp_enqueue_style(
        'mrs-gitdeploy-admin-toolbar-styles',
        MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/admin-toolbar.css',
        array(),
        MRS_GITDEPLOY_CURRENT_VERSION
    );

    // check if we're on the admin page
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
            array('jquery'),
            MRS_GITDEPLOY_CURRENT_VERSION,
            true
        );
    }

    if ( 'gitdeploy_page_mrs_gitdeploy_deployments' === $hook ) {
        wp_enqueue_script(
            'mrs-gitdeploy-deployments-model',
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/deployments-model.js',
            array('jquery'),
            MRS_GITDEPLOY_CURRENT_VERSION,
            true
        );

        wp_localize_script('mrs-gitdeploy-deployments-model', 'wpGitDeployData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mrs_gitdeploy_deployments_view_nonce')
        ]);

        // Enqueue the style
        wp_enqueue_style(
            'mrs-gitdeploy-deployments-page',
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/deployments-page.css',
            array(),
            MRS_GITDEPLOY_CURRENT_VERSION
        );
    }

    if ( 'gitdeploy_page_mrs_gitdeploy_resync' === $hook ) {
        // Enqueue the script
        wp_enqueue_script('mrs-gitdeploy-resync', plugin_dir_url(__FILE__) . 'js/resync-screen.js', ['jquery'], MRS_GITDEPLOY_CURRENT_VERSION, true);

        // Localize the script with the AJAX URL and nonce
        wp_localize_script('mrs-gitdeploy-resync', 'mrs_gitdeploy_resync_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mrs_gitdeploy_resync_nonce')
        ]);

        // Enqueue the script
        wp_enqueue_script(
            'mrs-gitdeploy-admin-download-field',
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/download-zip.js',
            array('jquery'),
            MRS_GITDEPLOY_CURRENT_VERSION,
            true
        );
    }

    if ( 'gitdeploy_page_mrs_gitdeploy_settings' === $hook ) {
        // Enqueue the script
        wp_enqueue_script(
            'mrs-gitdeploy-settings',
            MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/js/settings-page.js',
            array('jquery'),
            MRS_GITDEPLOY_CURRENT_VERSION,
            true
        );
    }

    // Enqueue the style
    wp_enqueue_style(
        'mrs-gitdeploy-setup-page',
        MRS_GITDEPLOY_PLUGIN_URL . 'admin/assets/css/setup-page.css',
        array(),
        MRS_GITDEPLOY_CURRENT_VERSION
    );
}
add_action('admin_enqueue_scripts', 'mrs_gitdeploy_admin_enqueue_scripts');
