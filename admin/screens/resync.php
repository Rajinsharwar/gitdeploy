<?php

if ( isset( $_POST['wp_gitdeploy_resync_all_files_submit'] ) ) {
    // Verify nonce
    if ( ! isset( $_POST['wp_gitdeploy_resync_nonce'] ) || ! wp_verify_nonce( $_POST['wp_gitdeploy_resync_nonce'], 'wp_gitdeploy_resync_action' ) ) {
        wp_die( __( 'Nonce verification failed.', 'wp-gitdeploy' ) );
    }

    // Check if the current user has permission to perform this action
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to perform this action.', 'wp-gitdeploy' ) );
    }

    if ( 'yes' === get_option( 'wp_gitdeploy_resync_in_progress' ) ) {
        echo '<div class="error"><p>' . __('A Resync is already in progress. Check the Deployments tab!', 'wp-gitdeploy') . '</p></div>';
    } else {
        // Calling the PHP function to resync
        wp_gitdeploy_resync_all_files_from_github_repo();

        echo '<div class="updated"><p>' . __('ReSync Started!', 'wp-gitdeploy') . '</p></div>';
    }
}

$setup_done = get_option( 'wp_gitdeploy_setup_complete' );

// Setup Complete Message
if ( $setup_done ) { ?>
    <div class="setup-complete">
        <span class="dashicons dashicons-yes"></span>
        <span><?php _e('Setup complete!', 'wp-gitdeploy'); ?></span>
    </div>
<?php } else { ?>
    <div class="setup-incomplete">
        <span class="dashicons dashicons-info"></span>
        <span>
            <?php
            echo sprintf(
                __('Setup not yet complete, please <a style="color: white;" href="%s">finish the Setup</a>!', 'wp-gitdeploy'),
                esc_url(admin_url('admin.php?page=wp_gitdeploy_setup'))
            );
            ?>
        </span>
    </div>
<?php }

// Wrapping the UI inside the WordPress settings page
?>
<div class="wrap">
    <h1><?php esc_html_e( 'GitDeploy Settings', 'wp-gitdeploy' ); ?></h1>

    <!-- General Instructions Section -->
    <div style="margin-bottom: 30px;">
        <h2 style="text-align: center;"><?php esc_html_e( 'Resync Your GitHub Repository with WordPress', 'wp-gitdeploy' ); ?></h2>
        <p style="font-size: 16px; text-align: center;">
            <?php esc_html_e( 'This tool allows you to resynchronize your connected GitHub repository\'s code with your WordPress codebase. A new commit will be created in your GitHub repo, updating it to match the current codebase, including plugins, themes, mu-plugins, and the index.php file from the wp-content folder. Note: The "wp-gitdeploy" plugin will always be excluded from resync or deployment actions.', 'wp-gitdeploy' ); ?>
        </p>
    </div>

    <!-- Resync Options Section: Main Container -->
    <div style="display: flex; flex-direction: column; gap: 40px;">

        <!-- First Row: Two Columns -->
        <div style="display: flex; gap: 40px; justify-content: space-between;">

            <!-- Automatic Resync Github Repo from WordPress Section -->
            <div style="flex: 1; background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'ReSync GitHub repo from WordPress codebase', 'wp-gitdeploy' ); ?></h3>
                <p style="font-size: 14px; color: #555;">
                    <?php esc_html_e( 'With this option, the plugin will automatically create a GitHub commit using the WordPress codebase. This includes plugins, themes, mu-plugins, and the index.php file. It\'s a seamless way to keep your GitHub repository in sync with your WordPress environment.', 'wp-gitdeploy' ); ?>
                </p>
                <div style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                    <button id="resync-button" class="button button-primary"><?php esc_html_e( 'Update GitHub Repo from WP', 'wp-gitdeploy' ); ?></button>
                    <div id="resync-status" style="font-size: 14px; color: #0073aa;"></div>
                </div>
            </div>

            <!-- Automatic Resync WordPress from GitHub Repo Section -->
            <div style="flex: 1; background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'ReSync WordPress codebase from GitHub repo', 'wp-gitdeploy' ); ?></h3>
                <p style="font-size: 14px; color: #555;">
                    <?php esc_html_e( 'With this option, the plugin will automatically update your WordPress codebase with the code from your repo. This includes plugins, themes, mu-plugins, and the index.php file. It\'s a seamless way to keep your WordPress codebase in sync with your GitHub repository.', 'wp-gitdeploy' ); ?>
                </p>

                <div style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                    <button type="button" id="resync-all-files-button" class="button button-secondary">
                        <?php esc_html_e( 'Update WP from GitHub Repo', 'wp-gitdeploy' ); ?>
                    </button>
                </div>
            </div>

            <!-- Popup Modal HTML -->
            <div id="confirmation-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0, 0, 0, 0.5); justify-content:center; align-items:center;">
                <div style="background-color:#fff; padding:20px; border-radius:8px; max-width:400px; text-align:center;">
                    <h3><?php esc_html_e( 'Are you sure?', 'wp-gitdeploy' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'This will delete all your existing plugins, themes, and mu-plugins files, and replace them with the ones from your GitHub repo.', 'wp-gitdeploy' ); ?>
                        <br>
                        <span style="color: red;">
                            <b>
                                <?php esc_html_e( 'This action is not reversible.', 'wp-gitdeploy' ); ?>
                            </b>
                        </span>
                    </p>
                    <div style="margin-top:20px;">
                        <form method="post">
                            <?php wp_nonce_field( 'wp_gitdeploy_resync_action', 'wp_gitdeploy_resync_nonce' ); ?>
                                <button name="wp_gitdeploy_resync_all_files_submit" id="proceed-button" class="button button-primary"><?php esc_html_e( 'Yes, proceed', 'wp-gitdeploy' ); ?></button>
                        </form>
                        </br>
                        <button id="cancel-button" class="button button-secondary"><?php esc_html_e( 'Cancel', 'wp-gitdeploy' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var resyncButton = document.getElementById('resync-all-files-button');
            var proceedButton = document.getElementById('proceed-button');
            var cancelButton = document.getElementById('cancel-button');
            var confirmationModal = document.getElementById('confirmation-modal');
            var resyncForm = document.getElementById('resync-form');

            // Show the modal when the resync button is clicked
            resyncButton.addEventListener('click', function() {
                confirmationModal.style.display = 'flex';
            });

            // Hide the modal and submit the form when the proceed button is clicked
            proceedButton.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
                resyncForm.submit();  // Submit the form programmatically
            });

            // Hide the modal when the cancel button is clicked
            cancelButton.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
            });
        });
        </script>
        <!-- Second Row: Single Column -->
        <div style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-align: center;">
            <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'Manually Resync GitHub Repo', 'wp-gitdeploy' ); ?></h3>
            <p style="font-size: 14px; color: #555;">
                <?php esc_html_e( 'Clicking the button below will download the complete WordPress codebase (plugins, themes, mu-plugins, and index.php). This can be manually uploaded to GitHub, which is helpful if there are issues with the GitHub API, such as rate limits.', 'wp-gitdeploy' ); ?>
            </p>
            <div style="align-items: center; gap: 15px; margin-top: 15px;">
                <button id="generate-zip-btn" class="button button-secondary" style="margin-top: 15px;"><?php esc_html_e( 'Download WordPress Code', 'wp-gitdeploy' ); ?></button>
                <div id="loading-indicator" style="display:none;"><?php _e('Generating... Please wait.', 'wp-gitdeploy'); ?></div>
                <div id="download-link" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>
<?php