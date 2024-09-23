<?php

/**
 * Hooks.
 */
add_action( 'admin_head', 'wp_gitdeploy_toolbar_styles' );
add_action( 'admin_bar_menu', 'wp_gitdeploy_admin_toolbar_item', 100 );

/**
 * Functions.
 */

/**
 * Add WP GitDeploy status item to admin toolbar.
 */
function wp_gitdeploy_admin_toolbar_item( $wp_admin_bar ) {
    // Check if the 'wp_gitdeploy_resync_in_progress' option is set to 'yes'
    $is_resyncing = ( get_option( 'wp_gitdeploy_resync_in_progress' ) === 'yes' );
    $is_deploying = ( get_option( 'wp_gitdeploy_deployment_in_progress' ) === 'yes' );
    
    if ( $is_resyncing ) {
        $label = '<span class="dashicons dashicons-image-rotate wp-gitdeploy-icon" style="font-family: dashicons; animation: spin 2s linear infinite; margin-right: 4px;"></span> ' . __( 'Resyncing', 'wp-gitdeploy' );
        $bg_color = '#ffeb3b';
        $text_color = 'black';
    } elseif ( $is_deploying ) {
        $label = '<span class="dashicons dashicons-image-rotate wp-gitdeploy-icon" style="font-family: dashicons; animation: spin 2s linear infinite; margin-right: 4px;"></span> ' . __( 'Deploying', 'wp-gitdeploy' );
        $bg_color = '#ffeb3b';
        $text_color = 'black';
    } else {
        $label = '<span class="dashicons dashicons-cloud-saved wp-gitdeploy-icon" style="font-family: dashicons; color: white; margin-right: 4px;"></span> ' . __( 'WP GitDeploy is Active', 'wp-gitdeploy' );
        $bg_color = '#28a745';
        $text_color = 'white';
    }

    // Add the toolbar item
    $wp_admin_bar->add_node( array(
        'id'    => 'wp_gitdeploy_status',
        'title' => '<span class="wp-gitdeploy-toolbar" style="padding: 4px 8px; background-color: ' . esc_attr( $bg_color ) . '; border-radius: 4px; color: ' . esc_attr( $text_color ) . ';">' . $label . '</span>',
        'href'  => admin_url( 'admin.php?page=wp_gitdeploy_deployments' ),
        'meta'  => array(
            'title' => __( 'View WP GitDeploy Deployments', 'wp-gitdeploy' ), // Tooltip on hover
        ),
    ) );
}

/**
 * Enqueue custom styles for the toolbar item.
 */
function wp_gitdeploy_toolbar_styles() {
    ?>
    <style>
        /* Custom style for the toolbar item */
        .wp-gitdeploy-toolbar {
            display: inline-flex;
            align-items: center;
            font-weight: 600;
        }
        
        /* Style for the spinning icon */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
    <?php
}
