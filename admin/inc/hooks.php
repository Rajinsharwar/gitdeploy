<?php

/** File to have all the hooks of the plugin  */

// AJAX handler for generating ZIP
add_action('wp_ajax_wp_gitdeploy_generate_zip', 'wp_gitdeploy_generate_zip');

// Hook for Cron deleting ZIP
add_action( 'wp_gitdeploy_delete_zip_cron', 'wp_gitdeploy_delete_zips' );

// Hook for Deployments Ajax
add_action('wp_ajax_wp_gitdeploy_get_deployment_details', 'wp_gitdeploy_get_deployment_details');
