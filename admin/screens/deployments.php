<?php

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

global $wpdb;

// Deleting all logs
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (check_admin_referer('mrs_gitdeploy_clear_logs_nonce', 'mrs_gitdeploy_nonce_field')) {
        // Clear all logs
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mrs_gitdeploy_deployments");

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('All logs have been cleared.', 'mrs-gitdeploy') . '</p></div>';
        });
    } else {
        // Nonce check failed
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Nonce verification failed.', 'mrs-gitdeploy') . '</p></div>';
        });
    }
}

// Cancel Resync
if (isset($_POST['action']) && $_POST['action'] === 'cancel_resync') {
    if (check_admin_referer('mrs_gitdeploy_cancel_resync_nonce', 'mrs_gitdeploy_nonce_field')) {
        // Cancel ReSync
        delete_option( 'mrs_gitdeploy_resync_in_progress' );

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Resync has been cancelled.', 'mrs-gitdeploy') . '</p></div>';
        });
    } else {
        // Nonce check failed
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Nonce verification failed.', 'mrs-gitdeploy') . '</p></div>';
        });
    }
}

// Pagination
$paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$limit = 10;
$offset = ($paged - 1) * $limit;

// Get deployments
$deployments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mrs_gitdeploy_deployments ORDER BY deployment_time DESC LIMIT %d OFFSET %d",
        $limit, $offset
    )
);

// Total deployments count
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mrs_gitdeploy_deployments");
$total_pages = ceil($total_count / $limit);

// Group deployments by date
$grouped_deployments = [];
$timezone = get_option('timezone_string') ? new DateTimeZone(get_option('timezone_string')) : new DateTimeZone('UTC');

foreach ($deployments as $deployment) {
    // Convert deployment time to WordPress timezone
    $dateTime = new DateTime($deployment->deployment_time, new DateTimeZone('UTC'));
    $dateTime->setTimezone($timezone);
    $date = $dateTime->format('Y-m-d');
    
    $grouped_deployments[$date][] = $deployment;
}

?>
<div class="wrap">
    <h1><?php _e('Deployment Logs', 'mrs-gitdeploy'); ?></h1> <?php
    $resync_in_progress = ( 'yes' === get_option( 'mrs_gitdeploy_resync_in_progress' ) ); ?>

    <div style="display: flex;">
        <?php if ( $resync_in_progress ) { ?>
            <form method="post" action="">
                <?php wp_nonce_field('mrs_gitdeploy_cancel_resync_nonce', 'mrs_gitdeploy_nonce_field'); ?>
                <input type="hidden" name="action" value="cancel_resync">
                <button type="submit" class="button button-secondary" style="margin-right: 20px; background-color: yellow; color: black;">
                    <b>
                        <?php _e('Cancel ReSync', 'mrs-gitdeploy'); ?>
                    </b>
                </button>
            </form>
        <?php } ?>
        
        <?php if ( ! empty( $grouped_deployments ) ) { ?>
            <form method="post" action="">
                <?php wp_nonce_field('mrs_gitdeploy_clear_logs_nonce', 'mrs_gitdeploy_nonce_field'); ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="button button-secondary" style="background-color: #ff0000; color: #ffffff;">
                    <b>
                        <?php _e('Clear all logs', 'mrs-gitdeploy'); ?>
                    </b>
                </button>
            </form>
        <?php } ?>
    </div>
    <br>
    
    <?php
    if ( empty( $grouped_deployments ) ) { ?>
        <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Deployment Time', 'mrs-gitdeploy'); ?></th>
                <th><?php _e('Type', 'mrs-gitdeploy'); ?></th>
                <th><?php _e('Status', 'mrs-gitdeploy'); ?></th>
                <th><?php _e('Actions', 'mrs-gitdeploy'); ?></th>
            </tr>
        </thead>
    <?php }

    foreach ($grouped_deployments as $date => $deployments): ?>
        <h2><?php echo esc_html($date); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Deployment Time', 'mrs-gitdeploy'); ?></th>
                    <th><?php _e('Type', 'mrs-gitdeploy'); ?></th>
                    <th><?php _e('Status', 'mrs-gitdeploy'); ?></th>
                    <th><?php _e('Actions', 'mrs-gitdeploy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deployments as $deployment): ?>
                    <tr>
                        <td><?php echo esc_html($deployment->deployment_time); ?></td>
                        <td><b><?php echo esc_html($deployment->type); ?></b></td>
                        <td class="<?php echo esc_attr($deployment->status === 'Success' ? 'status-success' : 'status-failed'); ?>">
                            <?php echo esc_html($deployment->status); ?>
                        </td>
                        <td>
                            <a href="#" class="button button-secondary mrs-gitdeploy-view-details" data-id="<?php echo esc_attr($deployment->id); ?>">
                                <?php _e('View Details', 'mrs-gitdeploy'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <div class="pagination">
        <?php
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '?paged=%#%',
            'total' => $total_pages,
            'current' => max(1, $paged),
            'prev_text' => __('« Previous', 'mrs-gitdeploy'),
            'next_text' => __('Next »', 'mrs-gitdeploy'),
            'type' => 'list',
        ]);
        ?>
    </div>
</div>

<!-- Modal for Deployment Details -->
<div id="mrs-gitdeploy-details-modal" class="mrs-gitdeploy-modal">
    <div class="mrs-gitdeploy-modal-content">
        <span class="mrs-gitdeploy-close">&times;</span>
        <h2><?php _e('Deployment Details', 'mrs-gitdeploy'); ?></h2>
        <div id="mrs-gitdeploy-details-content" class="mrs-gitdeploy-details-scroll"></div>
    </div>
</div>

<?php
?>
