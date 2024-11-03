<?php
/*
Plugin Name: GitDeploy - Deploy from GitHub
Plugin URI: https://github.com/rajinsharwar/gitdeploy/
Description: Easily deploy your website code from your GitHub repositiory directly to your WordPress environment.
Version: 1.0.0
Author: Rajin Sharwar
Author URI: https://www.linkedin.com/in/rajinsharwar/
Text Domain: gitdeploy
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
*/

defined( 'ABSPATH' ) or die( "Direct access not allowed" );

/** Defining Constants */

// Outputs as Path: eg: www/public_html/wp-content/plugins/mrs-gitdeploy/
define( 'MRS_GITDEPLOY_PLUGIN_PATH', wp_normalize_path( plugin_dir_path( __FILE__ ) ) );
// Outputs as URL: eg: https://example.com/wp-content/plugins/mrs-gitdeploy/
define( 'MRS_GITDEPLOY_PLUGIN_URL', wp_normalize_path( plugin_dir_url( __FILE__ ) ) );
// Outputs as Path: eg: www/public_html/
define( 'MRS_GITDEPLOY_WORDPRESS_ROOT', wp_normalize_path( ABSPATH ) );
// Outputs as Path: eg: www/public_html/wp-content/uploads/
define( 'MRS_GITDEPLOY_UPLOAD_DIR', wp_normalize_path( wp_upload_dir()[ 'basedir' ] ) . '/mrs-gitdeploy-zips/' );
// Outputs as URL: eg: https://example.com/wp-content/uploads/
define( 'MRS_GITDEPLOY_UPLOAD_URL', wp_normalize_path( wp_upload_dir()[ 'baseurl' ] ) . '/mrs-gitdeploy-zips/' );
// Outputs as Path: eg: www/public_html/wp-content/uploads/
define( 'MRS_GITDEPLOY_RESYNC_DIR', wp_normalize_path( wp_upload_dir()[ 'basedir' ] ) . '/mrs-gitdeploy-resync/' );
// Outputs as Path: eg: https://example.com/wp-content/uploads/
define( 'MRS_GITDEPLOY_RESYNC_URL', wp_normalize_path( wp_upload_dir()[ 'baseurl' ] ) . '/mrs-gitdeploy-resync/' );
// Outputs as Path: eg: www/public_html/wp-content/uploads/
define( 'MRS_GITDEPLOY_PULL_DIR', wp_normalize_path( wp_upload_dir()[ 'basedir' ] ) . '/mrs-gitdeploy-pull/' );

// Returns plugin version.
$plugin_version = get_file_data( (__FILE__), array('Version' => 'Version'))['Version'];
define( 'MRS_GITDEPLOY_CURRENT_VERSION', $plugin_version );

/** Composer autoload */
if ( ! class_exists( 'WP_Async_Request' ) ) {
    require_once( MRS_GITDEPLOY_PLUGIN_PATH . '/vendor/autoload.php' );
}

/** Require Files */
require_once( ABSPATH . '/wp-includes/pluggable.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/classes/background-async-pull/class-bg-async-pull.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/classes/class-deploy-to-wp.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/classes/deployments/class-deployments.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/classes/resync/class-resync.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/menus/setup-admin-menu.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/inc/functions.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/inc/hooks.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/endpoints/github-to-wp-endpoint.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/endpoints/wp-to-github-endpoint.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/assets/assets.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/inc/admin-notices.php' );
require_once( MRS_GITDEPLOY_PLUGIN_PATH . 'admin/inc/admin-toolbar.php' );

/**
 * Hooks.
 */
register_activation_hook( __FILE__, 'mrs_gitdeploy_required_tables' );

/**
 * Creating DB Tables on activation.
 */
function mrs_gitdeploy_required_tables() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'mrs_gitdeploy_deployments';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        deployment_time DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status VARCHAR(20) NOT NULL,
        type TEXT NOT NULL,
        reason TEXT NOT NULL,
        files_changed TEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Start off the Async
add_action('plugins_loaded', function () {
    $async_request = new MRS_GitDeploy_Async();
});