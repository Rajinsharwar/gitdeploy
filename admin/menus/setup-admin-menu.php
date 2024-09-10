<?php

// Hook to add the admin menu
add_action('admin_menu', 'wp_gitdeploy_add_admin_menu');

// Function to add the top-level menu and sub-menu
function wp_gitdeploy_add_admin_menu() {

    // Main Menu
    add_menu_page(
        'WP GitDeploy',
        'WP GitDeploy',
        'manage_options',
        'wp_gitdeploy_setup',
        'wp_gitdeploy_setup_page',
        'dashicons-admin-generic'
    );

    // Setup sub-menu for Setup
    add_submenu_page(
        'wp_gitdeploy_setup',
        'Setup',
        'Setup',
        'manage_options',
        'wp_gitdeploy_setup',
        'wp_gitdeploy_setup_page'
    );

    // Settings Sub menu
    add_submenu_page(
        'wp_gitdeploy_setup',
        'Settings',
        'Settings',
        'manage_options',
        'wp_gitdeploy_settings',
        'wp_gitdeploy_settings_page'
    );

    // Settings Sub menu
    add_submenu_page(
        'wp_gitdeploy_setup',
        'Deployments',
        'Deployments',
        'manage_options',
        'wp_gitdeploy_deployments',
        'wp_gitdeploy_deployments_page'
    );
}

function wp_gitdeploy_setup_page() {
    include_once( WP_GITDEPLOY_PLUGIN_PATH . 'admin/screens/setup.php' );
}

function wp_gitdeploy_settings_page() {
    include_once( WP_GITDEPLOY_PLUGIN_PATH . 'admin/screens/settings.php' );
}

function wp_gitdeploy_deployments_page() {
    include_once( WP_GITDEPLOY_PLUGIN_PATH . 'admin/screens/deployments.php' );
}