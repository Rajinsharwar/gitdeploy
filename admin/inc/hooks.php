<?php

/** File to have all the hooks of the plugin  */

// AJAX handler for generating ZIP
add_action('wp_ajax_mrs_gitdeploy_generate_zip', 'mrs_gitdeploy_generate_zip');

// Hook for Cron deleting ZIP
add_action( 'mrs_gitdeploy_delete_zip_cron', 'mrs_gitdeploy_delete_zips' );

// Hook for Deployments Ajax
add_action('wp_ajax_mrs_gitdeploy_get_deployment_details', 'mrs_gitdeploy_get_deployment_details');

// Hook to handle AJAX request
add_action('wp_ajax_mrs_gitdeploy_resync', 'mrs_gitdeploy_resync_action');
