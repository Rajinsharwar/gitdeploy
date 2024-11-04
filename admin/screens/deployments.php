<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

$setup_done = get_option( 'mrs_gitdeploy_setup_complete' );

// Setup Complete Message
if ( $setup_done ) { ?>
    <div class="setup-complete">
        <span class="dashicons dashicons-yes"></span>
        <span><?php esc_html_e( 'Setup complete!', 'gitdeploy' ); ?></span>
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

global $wpdb;

// Deleting all logs
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (check_admin_referer('mrs_gitdeploy_clear_logs_nonce', 'mrs_gitdeploy_nonce_field')) {
        // Clear all logs
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mrs_gitdeploy_deployments");
    
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All logs have been cleared.', 'gitdeploy') . '</p></div>';
    } else {
        // Nonce check failed
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Nonce verification failed.', 'gitdeploy') . '</p></div>';
    }    
}

// Cancel Resync
if (isset($_POST['action']) && $_POST['action'] === 'cancel_resync') {
    if (check_admin_referer('mrs_gitdeploy_cancel_resync_nonce', 'mrs_gitdeploy_nonce_field')) {
        // Cancel ReSync
        delete_option('mrs_gitdeploy_resync_in_progress');
    
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Resync has been cancelled.', 'gitdeploy') . '</p></div>';
    } else {
        // Nonce check failed
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Nonce verification failed.', 'gitdeploy') . '</p></div>';
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
    <?php
    $resync_in_progress = ( 'yes' === get_option( 'mrs_gitdeploy_resync_in_progress' ) );
    if ( $resync_in_progress ) { ?>
        <div style="display: flex; flex-direction: column; padding: 1rem; background-color: #ffeb3b; color: #333; border: 1px solid black; border-radius: 5px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center;">
                <div style="flex-grow: 1; font-size: 1rem; font-weight: bold;">
                    <?php echo esc_html__('Updating GitHub Repository', 'gitdeploy'); ?>
                </div>
                <form method="post" action="" style="margin: 0;">
                    <?php wp_nonce_field('mrs_gitdeploy_cancel_resync_nonce', 'mrs_gitdeploy_nonce_field'); ?>
                    <input type="hidden" name="action" value="cancel_resync">
                    <button type="submit" class="button button-secondary" style="margin-left: 20px; background-color: #333; color: #fff; padding: 10px 20px; border-radius: 5px;">
                        <b><?php esc_html_e('Cancel ReSync', 'gitdeploy'); ?></b>
                    </button>
                </form>
            </div>
            <div style="font-size: 1rem; font-weight: normal; margin-top: 10px;">
                <?php echo esc_html( mrs_gitdeploy_get_resync_status() ); ?>
            </div>
            <div style="font-size: 0.9rem; font-style: italic; color: #666; margin-top: 10px;">
                (<?php echo esc_html__('Refresh to see updated status', 'gitdeploy'); ?>)
            </div>
        </div>
    <?php } ?>
    
    <h1><?php esc_html_e('Deployment Logs', 'gitdeploy'); ?></h1>

    <div style="display: flex;">
        <?php if ( ! empty( $grouped_deployments ) ) { ?>
            <form method="post" action="">
                <?php wp_nonce_field('mrs_gitdeploy_clear_logs_nonce', 'mrs_gitdeploy_nonce_field'); ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="button button-secondary" style="background-color: #ff0000; color: #ffffff;">
                    <b>
                        <?php esc_html_e('Clear all logs', 'gitdeploy'); ?>
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
                    <th><?php esc_html_e('Deployment Time', 'gitdeploy'); ?></th>
                    <th><?php esc_html_e('Type', 'gitdeploy'); ?></th>
                    <th><?php esc_html_e('Status', 'gitdeploy'); ?></th>
                    <th><?php esc_html_e('Actions', 'gitdeploy'); ?></th>
                </tr>
            </thead>
        </table>
    <?php }

    foreach ($grouped_deployments as $date => $deployments): ?>
        <h2><?php echo esc_html($date); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Deployment Time', 'gitdeploy'); ?></th>
                    <th><?php esc_html_e('Type', 'gitdeploy'); ?></th>
                    <th><?php esc_html_e('Status', 'gitdeploy'); ?></th>
                    <th><?php esc_html_e('Actions', 'gitdeploy'); ?></th>
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
                                <?php esc_html_e('View Details', 'gitdeploy'); ?>
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
            'prev_text' => __('« Previous', 'gitdeploy'),
            'next_text' => __('Next »', 'gitdeploy'),
            'type' => 'list',
        ]);
        ?>
    </div>
</div>

<!-- Modal for Deployment Details -->
<div id="mrs-gitdeploy-details-modal" class="mrs-gitdeploy-modal">
    <div class="mrs-gitdeploy-modal-content">
        <span class="mrs-gitdeploy-close">&times;</span>
        <h2><?php esc_html_e('Deployment Details', 'gitdeploy'); ?></h2>
        <div id="mrs-gitdeploy-details-content" class="mrs-gitdeploy-details-scroll"></div>
    </div>
</div>

<?php
?>
