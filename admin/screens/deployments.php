<?php

global $wpdb;
$table_name = $wpdb->prefix . 'wp_gitdeploy_deployments';

// Pagination
$paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$limit = 10;
$offset = ($paged - 1) * $limit;

// Get deployments
$deployments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY deployment_time DESC LIMIT %d OFFSET %d",
        $limit, $offset
    )
);

// Total deployments count
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$total_pages = ceil($total_count / $limit);

?>
<div class="wrap">
    <h1><?php _e('Deployment Logs', 'wp-gitdeploy'); ?></h1>
    <br>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Deployment Time', 'wp-gitdeploy'); ?></th>
                <th><?php _e('Status', 'wp-gitdeploy'); ?></th>
                <th><?php _e('Actions', 'wp-gitdeploy'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deployments as $deployment): ?>
                <tr>
                    <td><?php echo esc_html($deployment->deployment_time); ?></td>
                    <td class="<?php echo esc_attr($deployment->status === 'Success' ? 'status-success' : 'status-failed'); ?>">
                        <?php echo esc_html($deployment->status); ?>
                    </td>
                    <td>
                        <a href="#" class="button button-secondary wp-gitdeploy-view-details" data-id="<?php echo esc_attr($deployment->id); ?>">
                            <?php _e('View Details', 'wp-gitdeploy'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '?paged=%#%',
            'total' => $total_pages,
            'current' => max(1, $paged),
            'prev_text' => __('« Previous', 'wp-gitdeploy'),
            'next_text' => __('Next »', 'wp-gitdeploy'),
            'type' => 'list',
        ]);
        ?>
    </div>
</div>

<!-- Modal for Deployment Details -->
<div id="wp-gitdeploy-details-modal" class="wp-gitdeploy-modal">
    <div class="wp-gitdeploy-modal-content">
        <span class="wp-gitdeploy-close">&times;</span>
        <h2><?php _e('Deployment Details', 'wp-gitdeploy'); ?></h2>
        <div id="wp-gitdeploy-details-content" class="wp-gitdeploy-details-scroll"></div>
    </div>
</div>


<?php
?>
