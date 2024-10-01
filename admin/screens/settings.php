<?php

// Handle form submission and save options
if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! empty( $_POST['mrs_gitdeploy_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_settings_nonce'] ) ), 'mrs_gitdeploy_save_settings' ) ) {
        update_option('mrs_gitdeploy_disable_wp_management', isset($_POST['mrs_gitdeploy_disable_wp_management']) ? '1' : '0');
        
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
        <span><?php _e('Setup complete!', 'mrs-gitdeploy'); ?></span>
    </div>
<?php } else { ?>
    <div class="setup-incomplete">
        <span class="dashicons dashicons-info"></span>
        <span>
            <?php
            echo sprintf(
                __('Setup not yet complete, please <a style="color: white;" href="%s">finish the Setup</a>!', 'mrs-gitdeploy'),
                esc_url(admin_url('admin.php?page=mrs_gitdeploy_setup'))
            );
            ?>
        </span>
    </div>
<?php }
?>

<div class="wrap">
    <h1><?php _e('GitHub Press Setup', 'mrs-gitdeploy'); ?></h1>
    <p><?php _e('Configure your GitHub Press settings here.', 'mrs-gitdeploy'); ?></p>
    
    <form method="post" action="">
        <?php wp_nonce_field('mrs_gitdeploy_save_settings', 'mrs_gitdeploy_settings_nonce'); ?>
        
        <h2><?php _e('General Settings', 'mrs-gitdeploy'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mrs_gitdeploy_disable_wp_management"><?php _e('Disable File changes', 'mrs-gitdeploy'); ?></label>
                </th>
                <td>
                    <input name="mrs_gitdeploy_disable_wp_management" type="checkbox" id="mrs_gitdeploy_disable_wp_management" value="1" <?php checked(get_option('mrs_gitdeploy_disable_wp_management'), '1'); ?>>
                    <p class="description">
                        <?php _e('Recommended. Disables Installing/Updating/Deleting Plugins and Themes. Turn this option on to manage all the file changes to your plugins and theme from your connected GitHub repo.', 'mrs-gitdeploy'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'mrs-gitdeploy')); ?>
    </form>
</div>