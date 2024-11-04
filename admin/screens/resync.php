<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

if ( isset( $_POST['mrs_gitdeploy_resync_all_files_submit'] ) ) {
    // Verify nonce
    if ( ! isset( $_POST['mrs_gitdeploy_resync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mrs_gitdeploy_resync_nonce'] ) ), 'mrs_gitdeploy_resync_action' ) ) {
        wp_die( esc_html__( 'Nonce verification failed.', 'gitdeploy' ) );
    }

    // Check if the current user has permission to perform this action
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'gitdeploy' ) );
    }

    if ( 'yes' === get_option( 'mrs_gitdeploy_resync_in_progress' ) ) {
        echo '<div class="error"><p>' . esc_html__('A Resync is already in progress. Check the Deployments tab!', 'gitdeploy') . '</p></div>';
    } else {
        // Calling the PHP function to resync
        $response = mrs_gitdeploy_resync_all_files_from_github_repo();

        if ( ! $response ) {
            echo '<div class="error"><p>' . esc_html__('A deployment is already in progress!', 'gitdeploy' ) . '</p></div>';
        } else {
            echo '<div class="updated"><p>' . esc_html__( 'Deployment process Started!', 'gitdeploy' ) . '</p></div>';
        }
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
<?php }

// Wrapping the UI inside the WordPress settings page
?>
<div class="wrap">
    <h1><?php esc_html_e( 'GitDeploy Settings', 'gitdeploy' ); ?></h1>

    <!-- General Instructions Section -->
    <div style="margin-bottom: 30px;">
        <h2 style="text-align: center;"><?php esc_html_e( 'Resync Your GitHub Repository with WordPress', 'gitdeploy' ); ?></h2>
        <p style="font-size: 16px; text-align: center;">
            <?php esc_html_e( 'This tool allows you to resynchronize your connected GitHub repository\'s code with your WordPress codebase. A new commit will be created in your GitHub repo, updating it to match the current codebase, including plugins, themes, mu-plugins, and the index.php file from the wp-content folder. Note: The "mrs-gitdeploy" plugin will always be excluded from resync or deployment actions.', 'gitdeploy' ); ?>
        </p>
    </div>

    <!-- Resync Options Section: Main Container -->
    <div style="display: flex; flex-direction: column; gap: 40px;">

        <!-- First Row: Two Columns -->
        <div style="display: flex; gap: 40px; justify-content: space-between;">

            <!-- Automatic Resync Github Repo from WordPress Section -->
            <div style="flex: 1; background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'ReSync GitHub repo from WordPress codebase', 'gitdeploy' ); ?></h3>
                <p style="font-size: 14px; color: #555;">
                    <?php esc_html_e( 'With this option, the plugin will automatically create a GitHub commit using the WordPress codebase. This includes plugins, themes, mu-plugins, and the index.php file. It\'s a seamless way to keep your GitHub repository in sync with your WordPress environment.', 'gitdeploy' ); ?>
                </p>
                <div style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                    <button id="resync-button" class="button button-primary"><?php esc_html_e( 'Update GitHub Repository', 'gitdeploy' ); ?></button>
                    <div id="resync-status" style="font-size: 14px; color: #0073aa;"></div>
                </div>
            </div>

            <!-- Automatic Resync WordPress from GitHub Repo Section -->
            <div style="flex: 1; background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'ReSync WordPress codebase from GitHub repo', 'gitdeploy' ); ?></h3>
                <p style="font-size: 14px; color: #555;">
                    <?php esc_html_e( 'With this option, the plugin will automatically update your WordPress codebase with the code from your repo. This includes plugins, themes, mu-plugins, and the index.php file. It\'s a seamless way to keep your WordPress codebase in sync with your GitHub repository.', 'gitdeploy' ); ?>
                </p>

                <div style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                    <button type="button" id="resync-all-files-button" class="button button-secondary">
                        <?php esc_html_e( 'Update WordPress Codebase', 'gitdeploy' ); ?>
                    </button>
                </div>
            </div>

            <!-- Popup Modal HTML -->
            <div id="confirmation-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0, 0, 0, 0.5); justify-content:center; align-items:center;">
                <div style="background-color:#fff; padding:20px; border-radius:8px; max-width:400px; text-align:center;">
                    <h3><?php esc_html_e( 'Are you sure?', 'gitdeploy' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'This will delete all your existing plugins, themes, and mu-plugins files, and replace them with the ones from your GitHub repo.', 'gitdeploy' ); ?>
                        <br>
                        <span style="color: red;">
                            <b>
                                <?php esc_html_e( 'This action is not reversible.', 'gitdeploy' ); ?>
                            </b>
                        </span>
                    </p>
                    <div style="margin-top:20px;">
                        <form method="post">
                            <?php wp_nonce_field( 'mrs_gitdeploy_resync_action', 'mrs_gitdeploy_resync_nonce' ); ?>
                                <button name="mrs_gitdeploy_resync_all_files_submit" id="proceed-button" class="button button-primary"><?php esc_html_e( 'Yes, proceed', 'gitdeploy' ); ?></button>
                        </form>
                        </br>
                        <button id="cancel-button" class="button button-secondary"><?php esc_html_e( 'Cancel', 'gitdeploy' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Second Row: Single Column -->
        <div style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-align: center;">
            <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'Manually Resync GitHub Repo', 'gitdeploy' ); ?></h3>
            <p style="font-size: 14px; color: #555;">
                <?php esc_html_e( 'Clicking the button below will download the complete WordPress codebase (plugins, themes, mu-plugins, and index.php). This can be manually uploaded to GitHub, which is helpful if there are issues with the GitHub API, such as rate limits.', 'gitdeploy' ); ?>
            </p>
            <div style="align-items: center; gap: 15px; margin-top: 15px;">
                <button id="generate-zip-btn" class="button button-secondary" style="margin-top: 15px;"><?php esc_html_e( 'Download WordPress Code', 'gitdeploy' ); ?></button>
                <div id="loading-indicator" style="display:none;"><?php esc_html_e('Generating... Please wait.', 'gitdeploy'); ?></div>
                <div id="download-link" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>
<?php