<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Data is preserved by default. Only wipe everything when the admin
// has explicitly enabled "Delete data on uninstall" in Settings.
$settings = get_option( 'scm_settings', array() );

if ( ! empty( $settings['delete_data_on_uninstall'] ) ) {
    global $wpdb;
    // Drop schemas first (references rules), then rules.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scm_schemas" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scm_rules" );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    delete_option( 'scm_settings' );
    delete_option( 'scm_db_version' );
}
