<?php

/**
 * Hooks
 */
add_action( 'admin_notices', 'wp_gitdeploy_setup_complete_now_resync' );

/**
 * Functions
 */
function wp_gitdeploy_setup_complete_now_resync() {
    if ( ! get_option( 'wp_gitdeploy_first_resync' ) ) {
        return;
    }

    $class = 'notice notice-success';
    $resync_url = admin_url( 'admin.php?page=wp_gitdeploy_resync' );
    $message = sprintf(
        __( 'Thank you for finishing the setup. Now, please <a href="%s">Resync GitHub repo with your WordPress codebase</a> to kickstart the integration.', 'sample-text-domain' ),
        esc_url( $resync_url )
    );

    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );
}
