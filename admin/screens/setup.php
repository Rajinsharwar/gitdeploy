<?php

// Handle form submission for saving settings
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    // Check if the nonce for saving settings is set and valid
    if (isset($_POST['wp_gitdeploy_save_settings_nonce']) && wp_verify_nonce($_POST['wp_gitdeploy_save_settings_nonce'], 'wp_gitdeploy_save_settings')) {
        $wp_gitdeploy_creds = array();
        $wp_gitdeploy_creds[ 'wp_gitdeploy_username' ] = sanitize_text_field( $_POST[ 'wp_gitdeploy_username' ] );
        $wp_gitdeploy_creds[ 'wp_gitdeploy_token' ] = sanitize_text_field( $_POST[ 'wp_gitdeploy_token' ] );
        $wp_gitdeploy_creds[ 'wp_gitdeploy_repo' ] = sanitize_text_field( $_POST[ 'wp_gitdeploy_repo' ] );

        // Save the Branch.
        $selected_branch = sanitize_text_field( $_POST[ 'wp_gitdeploy_repo_branch' ] );

        if ( '' === $selected_branch ) {
            $selected_branch = 'main';
        }

        $wp_gitdeploy_creds[ 'wp_gitdeploy_repo_branch' ] = $selected_branch;

        // Update setup completion option
        update_option( 'wp_gitdeploy_creds', $wp_gitdeploy_creds, 'no' );

        // Run Setup functions to setup webhook.
        $setup_response = wp_gitdeploy_finish_setup();
        if ( true !== $setup_response ) {
            update_option('wp_gitdeploy_setup_complete', 0);
            update_option('wp_gitdeploy_creds_error', 1);
            $setup_error = '<div class="error"><p>' . $setup_response . '</p></div>';
            echo $setup_error;
        } else {
            update_option('wp_gitdeploy_creds_error', 0);
            echo '<div class="updated"><p>' . __('Settings saved successfully', 'wp-gitdeploy') . '</p></div>';
        }

        // echo '<div class="updated"><p>Settings saved successfully. Please click Step 3: Finish Setup to continue</p></div>';
    } elseif (isset($_POST['wp_gitdeploy_finish_setup_nonce']) && wp_verify_nonce($_POST['wp_gitdeploy_finish_setup_nonce'], 'wp_gitdeploy_finish_setup')) {
        $wp_gitdeploy_creds = get_option( 'wp_gitdeploy_creds', array( 'wp_gitdeploy_username' => '', 'wp_gitdeploy_token' => '', 'wp_gitdeploy_repo' => '', 'wp_gitdeploy_repo_branch' => '' ) );
        
        // Update setup completion option
        $finish_setup_disabled = ! (
            $wp_gitdeploy_creds[ 'wp_gitdeploy_username' ] &&
            $wp_gitdeploy_creds[ 'wp_gitdeploy_token' ] &&
            $wp_gitdeploy_creds[ 'wp_gitdeploy_repo' ]
        );

        $settings_have_errors = get_option( 'wp_gitdeploy_creds_error' );

        if ( $finish_setup_disabled || 1 == $settings_have_errors ) {
            update_option('wp_gitdeploy_setup_complete', 0);
            $setup_error = '<div class="error"><p>Please enter correct credentials in Step 2, and Save Settings first!</p></div>';
            echo $setup_error;
        } else {
            update_option('wp_gitdeploy_setup_complete', 1);
            echo '<div class="updated"><p>' . __('Setup has been completed! Thank you!', 'wp-gitdeploy') . '</p></div>';
        }
    } else {
        echo '<div class="error"><p>Nonce verification failed. Settings not saved.</p></div>';
    }
}
// Setup Done?
$setup_done = get_option( 'wp_gitdeploy_setup_complete' );
// Get creds array
$creds = get_option( 'wp_gitdeploy_creds', array( 'wp_gitdeploy_username' => '', 'wp_gitdeploy_token' => '', 'wp_gitdeploy_repo' => '', 'wp_gitdeploy_repo_branch' => '' ) );
// Check if the Finish Setup button should be disabled
$finish_setup_disabled = ! (
    $creds[ 'wp_gitdeploy_username' ] &&
    $creds[ 'wp_gitdeploy_token' ] &&
    $creds[ 'wp_gitdeploy_repo' ]
);
?>

<div class="wrap">
    <h1><?php _e('WP GitDeploy Setup', 'wp-gitdeploy'); ?></h1>
    <p><?php _e('Setup your WP GitDeploy from here.', 'wp-gitdeploy'); ?></p>

    <!-- Setup Complete Message -->
    <?php if ( $setup_done ): ?>
        <div class="setup-complete">
            <span class="dashicons dashicons-yes"></span>
            <span><?php _e('Setup complete!', 'wp-gitdeploy'); ?></span>
        </div>
    <?php endif; ?>
        
        <div class="wp-gitdeploy-setup-steps">
        <div class="step">
            <h2><?php _e('Step 1: Download the ZIP of the Content Folder.', 'wp-gitdeploy'); ?></h2>
            <p><?php _e('Click the button below to generate a ZIP file of your wp-content folder. The ZIP will include the following files and directories:', 'wp-gitdeploy'); ?></p>
            
            <ul id="files-list">
                <li><span class="tick">&#10003;</span> <?php _e('Plugins', 'wp-gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('MU-Plugins', 'wp-gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('Themes', 'wp-gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('Languages (/languages)', 'wp-gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('index.php', 'wp-gitdeploy'); ?></li>
                <li><span class="cross">&#10007;</span> <?php _e('Uploads (excluded)', 'wp-gitdeploy'); ?></li>
                <li><span class="cross">&#10007;</span> <?php _e('Cache (excluded)', 'wp-gitdeploy'); ?></li>
            </ul>

            <button id="generate-zip-btn" class="button button-primary"><?php _e('Generate', 'wp-gitdeploy'); ?></button>
            <div id="loading-indicator" style="display:none;"><?php _e('Generating... Please wait.', 'wp-gitdeploy'); ?></div>
            <div id="download-link" style="display:none;"></div>
        </div>

        <form method="post" action="">
        <!-- Nonce for settings form -->
        <?php wp_nonce_field('wp_gitdeploy_save_settings', 'wp_gitdeploy_save_settings_nonce'); ?>

            <div class="step">
                <h2><?php _e('Step 2: Enter GitHub Credentials', 'wp-gitdeploy'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp_gitdeploy_token"><?php _e('GitHub token', 'wp-gitdeploy'); ?><i class="fa fa-asterisk" style="font-size: 8px; color:red; vertical-align: super;"></i></label>
                        </th>
                        <td>
                            <input name="wp_gitdeploy_token" type="text" id="wp_gitdeploy_token" placeholder="********" value="<?php echo esc_textarea( $creds[ 'wp_gitdeploy_token' ] ); ?>" class="regular-text" required>
                            &nbsp;
                            <a href="#" onclick="changeTokenPlaceholderText(); window.open('https://ravlet.agency/product/gitdeploy-auth/', 'WP Pusher Authentication', 'height=800,width=1100'); return false;" class="button">
                                <i class="fa fa-github"></i>&nbsp; <?php _e('Obtain a GitHub token', 'wp-gitdeploy'); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp_gitdeploy_username"><?php _e('GitHub Username', 'wp-gitdeploy'); ?></label><i class="fa fa-asterisk" style="font-size: 8px; color:red; vertical-align: super;"></i>
                        </th>
                        <td>
                            <input name="wp_gitdeploy_username" type="text" id="wp_gitdeploy_username" value="<?php echo esc_attr( $creds[ 'wp_gitdeploy_username' ] ); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Enter the username of the GitHub repository in the format', 'wp-gitdeploy'); ?> <code><?php _e('username', 'wp-gitdeploy'); ?></code> <?php _e('excluding the', 'wp-gitdeploy'); ?> <code><?php _e('repo-name/', 'wp-gitdeploy'); ?></code>.
                                <br><?php _e('If your repo URL is like', 'wp-gitdeploy'); ?> <code>https://github.com/yourusername/myawesomerepo</code> <?php _e('only enter', 'wp-gitdeploy'); ?> <code><?php _e('yourusername', 'wp-gitdeploy'); ?></code>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp_gitdeploy_repo"><?php _e('GitHub Repository', 'wp-gitdeploy'); ?></label><i class="fa fa-asterisk" style="font-size: 8px; color:red; vertical-align: super;"></i>
                        </th>
                        <td>
                            <input name="wp_gitdeploy_repo" type="text" id="wp_gitdeploy_repo" value="<?php echo esc_attr( $creds[ 'wp_gitdeploy_repo' ] ); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Enter the full name of the GitHub repository in the format', 'wp-gitdeploy'); ?> <code><?php _e('repo-name', 'wp-gitdeploy'); ?></code> <?php _e('excluding the', 'wp-gitdeploy'); ?> <code><?php _e('username/', 'wp-gitdeploy'); ?></code>.
                                <br><?php _e('If your repo URL is like', 'wp-gitdeploy'); ?> <code>https://github.com/yourusername/myawesomerepo</code> <?php _e('only enter', 'wp-gitdeploy'); ?> <code><?php _e('myawesomerepo', 'wp-gitdeploy'); ?></code>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp_gitdeploy_repo_branch"><?php _e('Branch', 'wp-gitdeploy'); ?></label>
                        </th>
                        <td>
                            <input name="wp_gitdeploy_repo_branch" type="text" id="wp_gitdeploy_repo_branch" value="<?php echo esc_attr( $creds[ 'wp_gitdeploy_repo_branch' ] ); ?>" class="regular-text" placeholder="main">
                            &nbsp;
                            <a id="test-repo-button" onclick="testGitHubRepo(); return false;" class="button" <?php echo $finish_setup_disabled ? 'disabled' : ''; ?>>
                                <i class="fa fa-github"></i>&nbsp; <?php _e('Test if repo is working', 'wp-gitdeploy'); ?>
                            </a>
                            <p class="description">
                                <?php _e('Enter the branch name you want to connect with this site.', 'wp-gitdeploy'); ?>.
                                <br><?php _e('Default: ', 'wp-gitdeploy'); ?> <code>main</code>.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'wp-gitdeploy')); ?>
            </div>
        </div>
    </form>

    <form method="post" action="">
        <!-- Nonce for finish setup -->
        <?php wp_nonce_field('wp_gitdeploy_finish_setup', 'wp_gitdeploy_finish_setup_nonce'); ?>
        <div class="wp-gitdeploy-setup-steps">
            <div class="step">
                <h2><?php _e('Step 3: Finish Setup', 'wp-gitdeploy'); ?></h2>
                <p><?php _e('After entering your GitHub credentials, click \'Finish Setup\' to complete the setup.', 'wp-gitdeploy'); ?></p>
                <button type="submit" name="finish_setup" class="button button-primary" <?php echo $finish_setup_disabled ? 'disabled' : ''; ?>><?php _e('Finish Setup', 'wp-gitdeploy'); ?></button>
            </div>
        </div>
    </form>
</div>
