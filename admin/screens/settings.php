<?php

// Handle form submission and save options
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if (isset($_POST['wp_gitdeploy_settings_nonce']) && wp_verify_nonce($_POST['wp_gitdeploy_settings_nonce'], 'wp_gitdeploy_save_settings')) {
        update_option('wp_gitdeploy_disable_wp_management', isset($_POST['wp_gitdeploy_disable_wp_management']) ? '1' : '0');
        
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Nonce verification failed. Settings not saved.</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php _e('GitHub Press Setup', 'wp-gitdeploy'); ?></h1>
    <p><?php _e('Configure your GitHub Press settings here.', 'wp-gitdeploy'); ?></p>
    
    <form method="post" action="">
        <?php wp_nonce_field('wp_gitdeploy_save_settings', 'wp_gitdeploy_settings_nonce'); ?>
        
        <h2><?php _e('General Settings', 'wp-gitdeploy'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wp_gitdeploy_disable_wp_management"><?php _e('Disable File changes', 'wp-gitdeploy'); ?></label>
                </th>
                <td>
                    <input name="wp_gitdeploy_disable_wp_management" type="checkbox" id="wp_gitdeploy_disable_wp_management" value="1" <?php checked(get_option('wp_gitdeploy_disable_wp_management'), '1'); ?>>
                    <p class="description">
                        <?php _e('Recommended. Disables Installing/Updating/Deleting Plugins and Themes. Turn this option on to manage all the file changes to your plugins and theme from your connected GitHub repo.', 'wp-gitdeploy'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'wp-gitdeploy')); ?>
    </form>
</div>