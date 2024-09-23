<?php

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

    <!-- Resync Options Section: Two Columns -->
    <div style="display: flex; gap: 40px; justify-content: space-between;">

        <!-- Automatic Resync Section -->
        <div style="flex: 1; background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'Automatically Resync GitHub Repo', 'wp-gitdeploy' ); ?></h3>
            <p style="font-size: 14px; color: #555;">
                <?php esc_html_e( 'With this option, the plugin will automatically create a GitHub commit using the WordPress codebase. This includes plugins, themes, mu-plugins, and the index.php file. It\'s a seamless way to keep your GitHub repository in sync with your WordPress environment.', 'wp-gitdeploy' ); ?>
            </p>
            <div style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                <button id="resync-button" class="button button-primary"><?php esc_html_e( 'Automatically Resync', 'wp-gitdeploy' ); ?></button>
                <div id="resync-status" style="font-size: 14px; color: #0073aa;"></div>
            </div>
        </div>

        <!-- Manual Resync Section -->
        <div style="flex: 1; background-color: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <h3 style="font-size: 20px; margin-bottom: 10px;"><?php esc_html_e( 'Manually Resync GitHub Repo', 'wp-gitdeploy' ); ?></h3>
            <p style="font-size: 14px; color: #555;">
                <?php esc_html_e( 'Clicking the button below will download the complete WordPress codebase (plugins, themes, mu-plugins, and index.php). This can be manually uploaded to GitHub, which is helpful if there are issues with the GitHub API, such as rate limits.', 'wp-gitdeploy' ); ?>
            </p>
            <div style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                <button id="generate-zip-btn" class="button button-secondary" style="margin-top: 15px;"><?php esc_html_e( 'Download WordPress Code', 'wp-gitdeploy' ); ?></button>
                <div id="loading-indicator" style="display:none;"><?php _e('Generating... Please wait.', 'wp-gitdeploy'); ?></div>
                <div id="download-link" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>
<?php