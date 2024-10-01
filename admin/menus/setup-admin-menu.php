<?php

// Hook to add the admin menu
add_action('admin_menu', 'mrs_gitdeploy_add_admin_menu');

// Function to add the top-level menu and sub-menu
function mrs_gitdeploy_add_admin_menu() {

    // Main Menu
    add_menu_page(
        'GitDeploy',
        'GitDeploy',
        'manage_options',
        'mrs_gitdeploy_setup',
        'mrs_gitdeploy_setup_page',
        'dashicons-admin-generic'
    );

    // Setup sub-menu for Setup
    add_submenu_page(
        'mrs_gitdeploy_setup',
        'Setup',
        'Setup',
        'manage_options',
        'mrs_gitdeploy_setup',
        'mrs_gitdeploy_setup_page'
    );

    // Settings Sub menu
    add_submenu_page(
        'mrs_gitdeploy_setup',
        'Settings',
        'Settings',
        'manage_options',
        'mrs_gitdeploy_settings',
        'mrs_gitdeploy_settings_page'
    );

    // Resync Sub menu
    add_submenu_page(
        'mrs_gitdeploy_setup',
        'ReSync',
        'ReSync',
        'manage_options',
        'mrs_gitdeploy_resync',
        'mrs_gitdeploy_resync_page'
    );
    
    // Deployments Sub menu
    add_submenu_page(
        'mrs_gitdeploy_setup',
        'Deployments',
        'Deployments',
        'manage_options',
        'mrs_gitdeploy_deployments',
        'mrs_gitdeploy_deployments_page'
    );
}

function mrs_gitdeploy_setup_page() {
    include_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/screens/setup.php' );
}

function mrs_gitdeploy_settings_page() {
    include_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/screens/settings.php' );
}

function mrs_gitdeploy_deployments_page() {
    include_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/screens/deployments.php' );
}

function mrs_gitdeploy_resync_page() {
    include_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/screens/resync.php' );
}