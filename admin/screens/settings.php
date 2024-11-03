<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! empty( $_POST['mrs_gitdeploy_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_settings_nonce'] ) ), 'mrs_gitdeploy_save_settings' ) ) {
        update_option( 'mrs_gitdeploy_disable_wp_management', isset( $_POST['mrs_gitdeploy_disable_wp_management'] ) ? '1' : '0' );
        update_option( 'mrs_gitdeploy_basic_auth_enabled', isset( $_POST['mrs_gitdeploy_basic_auth_enabled'] ) ? '1' : '0' );
        update_option( 'mrs_gitdeploy_basic_auth_username', isset( $_POST['mrs_gitdeploy_basic_auth_username'] ) ? sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_basic_auth_username'] ) ) : '' );
        update_option( 'mrs_gitdeploy_basic_auth_password', isset( $_POST['mrs_gitdeploy_basic_auth_password'] ) ? sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_basic_auth_password'] ) ) : '' );
        
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Nonce verification failed. Settings not saved.</p></div>';
    }
}

$setup_done = get_option( 'mrs_gitdeploy_setup_complete' );

// Setup Complete Message
if ( $setup_done ) { ?>
    <div class="setup-complete">
        <span class="dashicons dashicons-yes"></span>
        <span><?php esc_html_e('Setup complete!', 'gitdeploy'); ?></span>
    </div>
<?php } else { ?>
    <div class="setup-incomplete">
        <span class="dashicons dashicons-info"></span>
        <span>
            <?php
            echo wp_kses(
                sprintf(
                    'Setup not yet complete, please <a style="color: white;" href="%s">finish the Setup</a>!',
                    esc_url(admin_url('admin.php?page=mrs_gitdeploy_setup'))
                ),
                array(
                    'a' => array(
                        'href' => array(),
                        'style' => array(),
                    )
                )
            );
            ?>
        </span>
    </div>
<?php } ?>

<div class="wrap">
    <h1><?php esc_html_e('GitDeploy Setup', 'gitdeploy'); ?></h1>
    <p><?php esc_html_e('Configure your GitDeploy settings here.', 'gitdeploy'); ?></p>
    
    <form method="post" action="">
        <?php wp_nonce_field('mrs_gitdeploy_save_settings', 'mrs_gitdeploy_settings_nonce'); ?>
        
        <h2><?php esc_html_e('General Settings', 'gitdeploy'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mrs_gitdeploy_disable_wp_management"><?php esc_html_e('Disable File changes', 'gitdeploy'); ?></label>
                </th>
                <td>
                    <input name="mrs_gitdeploy_disable_wp_management" type="checkbox" id="mrs_gitdeploy_disable_wp_management" value="1" <?php checked(get_option('mrs_gitdeploy_disable_wp_management'), '1'); ?>>
                    <p class="description">
                        <?php esc_html_e('Recommended. Disables Installing/Updating/Deleting Plugins and Themes. Turn this option on to manage all the file changes to your plugins and theme from your connected GitHub repo.', 'gitdeploy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mrs_gitdeploy_basic_auth_enabled"><?php esc_html_e('Is your website protected by Basic Auth?', 'gitdeploy'); ?></label>
                </th>
                <td>
                    <input name="mrs_gitdeploy_basic_auth_enabled" type="checkbox" id="mrs_gitdeploy_basic_auth_enabled" value="1" <?php checked(get_option('mrs_gitdeploy_basic_auth_enabled'), '1'); ?>>
                    <p class="description">
                        <?php esc_html_e('Enable this if your website is protected by Basic Auth.', 'gitdeploy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mrs_gitdeploy_basic_auth_username"><?php esc_html_e('Basic Auth Username', 'gitdeploy'); ?></label>
                </th>
                <td>
                    <input name="mrs_gitdeploy_basic_auth_username" type="text" id="mrs_gitdeploy_basic_auth_username" value="<?php echo esc_attr(get_option('mrs_gitdeploy_basic_auth_username')); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mrs_gitdeploy_basic_auth_password"><?php esc_html_e('Basic Auth Password', 'gitdeploy'); ?></label>
                </th>
                <td>
                    <input name="mrs_gitdeploy_basic_auth_password" type="password" id="mrs_gitdeploy_basic_auth_password" value="<?php echo esc_attr(get_option('mrs_gitdeploy_basic_auth_password')); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>