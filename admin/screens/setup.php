<?php

// Handle form submission for saving settings
if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    // Check if the nonce for saving settings is set and valid
    if ( ! empty( $_POST['mrs_gitdeploy_save_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_save_settings_nonce'] ) ), 'mrs_gitdeploy_save_settings' ) ) {
        $mrs_gitdeploy_creds = array();
        $mrs_gitdeploy_creds[ 'mrs_gitdeploy_username' ] = ! empty( $_POST[ 'mrs_gitdeploy_username' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'mrs_gitdeploy_username' ] ) ) : '';
        $mrs_gitdeploy_creds[ 'mrs_gitdeploy_token' ] = ! empty( $_POST[ 'mrs_gitdeploy_token' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'mrs_gitdeploy_token' ] ) ) : '';
        $mrs_gitdeploy_creds[ 'mrs_gitdeploy_repo' ] = ! empty( $_POST[ 'mrs_gitdeploy_repo' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'mrs_gitdeploy_repo' ] ) ) : '';

        // Save the Branch.
        $selected_branch = ! empty( $_POST[ 'mrs_gitdeploy_repo_branch' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'mrs_gitdeploy_repo_branch' ] ) ) : '';

        if ( '' === $selected_branch ) {
            $selected_branch = 'main';
        }

        $mrs_gitdeploy_creds[ 'mrs_gitdeploy_repo_branch' ] = $selected_branch;

        // Update setup completion option
        update_option( 'mrs_gitdeploy_creds', $mrs_gitdeploy_creds, 'no' );

        // Run Setup functions to setup webhook.
        $setup_response = mrs_gitdeploy_finish_setup();
        if ( true !== $setup_response ) {
            update_option('mrs_gitdeploy_setup_complete', 0);
            update_option('mrs_gitdeploy_creds_error', 1);
            $setup_error = '<div class="error"><p>' . $setup_response . '</p></div>';
            echo $setup_error;
        } else {
            update_option('mrs_gitdeploy_creds_error', 0);
            echo '<div class="updated"><p>' . __('Settings saved successfully', 'gitdeploy') . '</p></div>';
        }

        // echo '<div class="updated"><p>Settings saved successfully. Please click Step 3: Finish Setup to continue</p></div>';
    } elseif ( isset( $_POST[ 'mrs_gitdeploy_finish_setup_nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_finish_setup_nonce'] ) ), 'mrs_gitdeploy_finish_setup' ) ) {
        $mrs_gitdeploy_creds = get_option( 'mrs_gitdeploy_creds', array( 'mrs_gitdeploy_username' => '', 'mrs_gitdeploy_token' => '', 'mrs_gitdeploy_repo' => '', 'mrs_gitdeploy_repo_branch' => '' ) );
        
        // Update setup completion option
        $finish_setup_disabled = ! (
            $mrs_gitdeploy_creds[ 'mrs_gitdeploy_username' ] &&
            $mrs_gitdeploy_creds[ 'mrs_gitdeploy_token' ] &&
            $mrs_gitdeploy_creds[ 'mrs_gitdeploy_repo' ]
        );

        $settings_have_errors = get_option( 'mrs_gitdeploy_creds_error' );

        if ( $finish_setup_disabled || 1 == $settings_have_errors ) {
            update_option('mrs_gitdeploy_setup_complete', 0);
            $setup_error = '<div class="error"><p>' . __( "Please enter correct credentials in Step 1, and Save Settings first!", "mrs-gitdeploy" ) . '</p></div>';
            echo $setup_error;
        } else {
            $workflow_file_setup = mrs_gitdeploy_process_workflow_file();

            if ( true === mrs_gitdeploy_process_workflow_file() ) {
                // Successfully created/updated workflow file.
                update_option('mrs_gitdeploy_setup_complete', 1);
                update_option('mrs_gitdeploy_first_resync', 1);
                echo '<div class="updated"><p>' . __('Setup has been completed! Thank you!', 'gitdeploy') . '</p></div>';
            } elseif ( 'missing_creds' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'Please enter correct credentials in Step 1, and Save Settings first!', 'gitdeploy' ) . '</p></div>';
            } elseif ( 'file_not_found' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'The workflow template file does not exist. Kindly delete and reinstall the plugin', 'gitdeploy') . '</p></div>';
            } elseif ( 'file_read_error' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'Failed to read the workflow template file', 'gitdeploy') . '</p></div>';
            } elseif ( 'api_request_failed' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'Failed to communicate with GitHub API', 'gitdeploy') . '</p></div>';
            } elseif ( 'api_request_failed_2' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'Failed to communicate with GitHub API to update the workflow file', 'gitdeploy') . '</p></div>';
            } elseif ( 'api_request_failed_3' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'Connection with GitHub API caused an unexpected error, please re-check your Credentials, Repo and Branch in Step 1.', 'gitdeploy') . '</p></div>';
            } elseif ( 'api_rate_limit_exceeded' === $workflow_file_setup ) {
                echo '<div class="error"><p>' . __( 'GitHub API rate of your account has exceeded it\'s limit, kindly wait one hour for the rate limit to reset.', 'gitdeploy') . '</p></div>';
            }
        }
    } else {
        echo '<div class="error"><p>Nonce verification failed. Settings not saved.</p></div>';
    }
}
// Setup Done?
$setup_done = get_option( 'mrs_gitdeploy_setup_complete' );
// Get creds array
$creds = get_option( 'mrs_gitdeploy_creds', array( 'mrs_gitdeploy_username' => '', 'mrs_gitdeploy_token' => '', 'mrs_gitdeploy_repo' => '', 'mrs_gitdeploy_repo_branch' => '' ) );
// Check if the Finish Setup button should be disabled
$finish_setup_disabled = ! (
    $creds[ 'mrs_gitdeploy_username' ] &&
    $creds[ 'mrs_gitdeploy_token' ] &&
    $creds[ 'mrs_gitdeploy_repo' ]
);
?>

<div class="wrap">
    <h1><?php _e('GitDeploy Setup', 'gitdeploy'); ?></h1>
    <p><?php _e('Setup your GitDeploy from here.', 'gitdeploy'); ?></p>

    <!-- Setup Complete Message -->
    <?php if ( $setup_done ) { ?>
        <div class="setup-complete">
            <span class="dashicons dashicons-yes"></span>
            <span><?php _e('Setup complete!', 'gitdeploy'); ?></span>
        </div>
    <?php } else { ?>
        <div class="setup-incomplete">
            <span class="dashicons dashicons-info"></span>
            <span><?php _e('Setup not yet complete, please finish the Setup!', 'gitdeploy'); ?></span>
        </div>
    <?php } ?>
        
        <div class="mrs-gitdeploy-setup-steps">
        <!-- <div class="step">
            <h2><?php _e('Step 1: Download the ZIP of the Content Folder.', 'gitdeploy'); ?></h2>
            <p><?php _e('Click the button below to generate a ZIP file of your wp-content folder. The ZIP will include the following files and directories:', 'gitdeploy'); ?></p>
            
            <ul id="files-list">
                <li><span class="tick">&#10003;</span> <?php _e('Plugins', 'gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('MU-Plugins', 'gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('Themes', 'gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('Languages (/languages)', 'gitdeploy'); ?></li>
                <li><span class="tick">&#10003;</span> <?php _e('index.php', 'gitdeploy'); ?></li>
                <li><span class="cross">&#10007;</span> <?php _e('Uploads (excluded)', 'gitdeploy'); ?></li>
                <li><span class="cross">&#10007;</span> <?php _e('Cache (excluded)', 'gitdeploy'); ?></li>
            </ul>

            <button id="generate-zip-btn" class="button button-primary"><?php _e('Generate', 'gitdeploy'); ?></button>
            <div id="loading-indicator" style="display:none;"><?php _e('Generating... Please wait.', 'gitdeploy'); ?></div>
            <div id="download-link" style="display:none;"></div>
        </div> -->

        <form method="post" action="">
        <!-- Nonce for settings form -->
        <?php wp_nonce_field('mrs_gitdeploy_save_settings', 'mrs_gitdeploy_save_settings_nonce'); ?>

            <div class="step">
                <h2><?php _e('Step 1: Enter GitHub Credentials', 'gitdeploy'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mrs_gitdeploy_token"><?php _e('GitHub token', 'gitdeploy'); ?><i class="fa fa-asterisk" style="font-size: 8px; color:red; vertical-align: super;"></i></label>
                        </th>
                        <td>
                            <input name="mrs_gitdeploy_token" type="text" id="mrs_gitdeploy_token" placeholder="********" value="<?php echo esc_textarea( $creds[ 'mrs_gitdeploy_token' ] ); ?>" class="regular-text" required>
                            &nbsp;
                            <a href="#" onclick="changeTokenPlaceholderText(); window.open('https://ravlet.agency/product/gitdeploy-auth/', 'WP Pusher Authentication', 'height=800,width=1100'); return false;" class="button">
                                <i class="fa fa-github"></i>&nbsp; <?php _e('Obtain a GitHub token', 'gitdeploy'); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mrs_gitdeploy_username"><?php _e('GitHub Username', 'gitdeploy'); ?></label><i class="fa fa-asterisk" style="font-size: 8px; color:red; vertical-align: super;"></i>
                        </th>
                        <td>
                            <input name="mrs_gitdeploy_username" type="text" id="mrs_gitdeploy_username" value="<?php echo esc_attr( $creds[ 'mrs_gitdeploy_username' ] ); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Enter the username of the GitHub repository in the format', 'gitdeploy'); ?> <code><?php _e('username', 'gitdeploy'); ?></code> <?php _e('excluding the', 'gitdeploy'); ?> <code><?php _e('repo-name/', 'gitdeploy'); ?></code>.
                                <br><?php _e('If your repo URL is like', 'gitdeploy'); ?> <code>https://github.com/yourusername/myawesomerepo</code> <?php _e('only enter', 'gitdeploy'); ?> <code><?php _e('yourusername', 'gitdeploy'); ?></code>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mrs_gitdeploy_repo"><?php _e('GitHub Repository', 'gitdeploy'); ?></label><i class="fa fa-asterisk" style="font-size: 8px; color:red; vertical-align: super;"></i>
                        </th>
                        <td>
                            <input name="mrs_gitdeploy_repo" type="text" id="mrs_gitdeploy_repo" value="<?php echo esc_attr( $creds[ 'mrs_gitdeploy_repo' ] ); ?>" class="regular-text" required>
                            <p class="description">
                                <?php _e('Enter the full name of the GitHub repository in the format', 'gitdeploy'); ?> <code><?php _e('repo-name', 'gitdeploy'); ?></code> <?php _e('excluding the', 'gitdeploy'); ?> <code><?php _e('username/', 'gitdeploy'); ?></code>.
                                <br><?php _e('If your repo URL is like', 'gitdeploy'); ?> <code>https://github.com/yourusername/myawesomerepo</code> <?php _e('only enter', 'gitdeploy'); ?> <code><?php _e('myawesomerepo', 'gitdeploy'); ?></code>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mrs_gitdeploy_repo_branch"><?php _e('Branch', 'gitdeploy'); ?></label>
                        </th>
                        <td>
                            <input name="mrs_gitdeploy_repo_branch" type="text" id="mrs_gitdeploy_repo_branch" value="<?php echo esc_attr( $creds[ 'mrs_gitdeploy_repo_branch' ] ); ?>" class="regular-text" placeholder="main">
                            &nbsp;
                            <a id="test-repo-button" onclick="testGitHubRepo(); return false;" class="button" <?php echo $finish_setup_disabled ? 'disabled' : ''; ?>>
                                <i class="fa fa-github"></i>&nbsp; <?php _e('Test if repo is working', 'gitdeploy'); ?>
                            </a>
                            <p class="description">
                                <?php _e('Enter the branch name you want to connect with this site.', 'gitdeploy'); ?>.
                                <br><?php _e('Default: ', 'gitdeploy'); ?> <code>main</code>.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'gitdeploy')); ?>
            </div>
        </div>
    </form>

    <form method="post" action="">
        <!-- Nonce for finish setup -->
        <?php wp_nonce_field('mrs_gitdeploy_finish_setup', 'mrs_gitdeploy_finish_setup_nonce'); ?>
        <div class="mrs-gitdeploy-setup-steps">
            <div class="step">
                <h2><?php _e('Step 2: Finish Setup', 'gitdeploy'); ?></h2>
                <p><?php _e('After entering your GitHub credentials, please click \'Finish Setup\' to complete the setup.', 'gitdeploy'); ?></p>                
                <button type="submit" name="finish_setup" class="button button-primary" <?php echo $finish_setup_disabled ? 'disabled' : ''; ?>><?php _e('Finish Setup', 'gitdeploy'); ?></button>
            </div>
        </div>
    </form>
</div>
