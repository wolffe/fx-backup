<?php
/*
Plugin Name: FX Backup
Plugin URI: https://getbutterfly.com/wordpress-plugins/fxbackup/
Description: Local database backups plus optional file archives (wp-content and site root files) for ClassicPress.
Version: 2.1.2
Author: Ciprian Popescu
Author URI: https://getbutterfly.com/
Requires at least: 2.7
Requires PHP: 8.0
Requires CP: 2.5
*/

if ( ! defined( 'FX_BACKUP_OPTIONS' ) ) {
    define( 'FX_BACKUP_OPTIONS', 'fxbackup_options' );
}
define( 'FX_BACKUP_PLUGIN_URL', WP_PLUGIN_URL . '/' . dirname( plugin_basename( __FILE__ ) ) );
define( 'FX_BACKUP_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) );
define( 'FX_BACKUP_VERSION', '2.1.2' );

require_once FX_BACKUP_PLUGIN_PATH . '/inc/admin-ui.php';

add_action( 'admin_enqueue_scripts', 'fxbackup_admin_enqueue_assets' );

function fxbackup_default_options() {
    return [
        'export_dir'      => WP_CONTENT_DIR . '/fxbackups',
        'compression'     => 'none',
        'gzip_lvl'        => 1,
        'period'          => 86400,
        'schedule'        => time(),
        'active'          => 0,
        'rotate'          => -1,
        'smart_email'     => '',
        'send_attachment' => 0,
        'backup_files'    => 0,
        'logs'            => [],
    ];
}

function fxbackup_get_options() {
    $cfg = get_option( FX_BACKUP_OPTIONS );
    if ( ! is_array( $cfg ) ) {
        return fxbackup_default_options();
    }
    return array_merge( fxbackup_default_options(), $cfg );
}

function fxbackup_admin_menu() {
    add_menu_page( __( 'FX Backup', 'fx-backup' ), __( 'FX Backup', 'fx-backup' ), 'manage_options', __FILE__, 'fxbackup_render_settings_page', 'dashicons-cloud' );
    add_submenu_page( __FILE__, __( 'Backup Manager', 'fx-backup' ), __( 'Backup Manager', 'fx-backup' ), 'manage_options', FX_BACKUP_PLUGIN_PATH . '/fxbackup-manager.php' );
    add_submenu_page( __FILE__, __( 'Files Manager', 'fx-backup' ), __( 'Files Manager', 'fx-backup' ), 'manage_options', FX_BACKUP_PLUGIN_PATH . '/fxbackup-files.php' );
}

add_action( 'admin_menu', 'fxbackup_admin_menu' );

function fxbackup_render_settings_page() {
    include FX_BACKUP_PLUGIN_PATH . '/fxbackup-options.php';
}

register_activation_hook( __FILE__, 'fxbackup_install' );

function fxbackup_install() {
    add_option( FX_BACKUP_OPTIONS, fxbackup_default_options() );
}

add_action( 'do_fx_backup', 'fxbackup_run' );

function fxbackup_run() {
    @ini_set( 'memory_limit', '256M' );
    @ini_set( 'max_execution_time', 600 );

    if ( defined( 'FX_BACKUP_RETURN' ) ) {
        return;
    }

    $cfg = fxbackup_get_options();
    if ( ! $cfg['active'] ) {
        return;
    }
    if ( empty( $cfg['export_dir'] ) ) {
        return;
    }

    require_once FX_BACKUP_PLUGIN_PATH . '/inc/functions.php';
    define( 'FX_BACKUP_RETURN', true );

    $timenow    = time();
    $mtime      = explode( ' ', microtime() );
    $time_start = $mtime[1] + $mtime[0];
    $key        = substr( md5( md5( DB_NAME . '|' . microtime() ) ), 0, 6 );
    $date       = wp_date( 'm.d.y-H.i.s', $timenow );
    $sql_path   = $cfg['export_dir'] . '/Backup_' . $date . '_' . $key . '.sql';
    $stored     = ( $cfg['compression'] === 'gz' ) ? $sql_path . '.gz' : $sql_path;

    if ( ! isset( $cfg['logs'] ) || ! is_array( $cfg['logs'] ) ) {
        $cfg['logs'] = [];
    }

    $removed = fxbackup_rotate( $cfg, $timenow );
    $dump    = fxbackup_exec( $sql_path, 'backup' );
    $ok      = ! is_wp_error( $dump ) && is_file( $stored );

    if ( $ok && ! empty( $cfg['send_attachment'] ) && ! empty( $cfg['smart_email'] ) ) {
        $smart_subject = get_bloginfo() . ' new backup available!';
        wp_mail( $cfg['smart_email'], $smart_subject, __( 'A new backup is now available!', 'fx-backup' ), '', [ $stored ] );
    }

    $mtime         = explode( ' ', microtime() );
    $time_end      = $mtime[1] + $mtime[0];
    $time_total    = $time_end - $time_start;
    $logged_file   = $ok ? $stored : $sql_path;
    $cfg['logs'][] = [
        'file'    => $logged_file,
        'type'    => 'database',
        'size'    => is_file( $logged_file ) ? filesize( $logged_file ) : 0,
        'started' => $timenow,
        'took'    => $time_total,
        'status'  => $ok ? __( 'Successful', 'fx-backup' ) : __( 'Failed', 'fx-backup' ),
        'removed' => $removed,
    ];

    if ( ! empty( $cfg['backup_files'] ) ) {
        $files_mtime      = explode( ' ', microtime() );
        $files_time_start = $files_mtime[1] + $files_mtime[0];
        $files_path       = $cfg['export_dir'] . '/' . fxbackup_files_archive_basename( $date, $key );
        $files_ok         = fxbackup_create_content_archive( $files_path, $cfg['export_dir'] );
        $files_mtime      = explode( ' ', microtime() );
        $files_time_end   = $files_mtime[1] + $files_mtime[0];
        $files_time_total = $files_time_end - $files_time_start;
        $cfg['logs'][]    = [
            'file'    => $files_path,
            'type'    => 'files',
            'size'    => ( $files_ok && is_file( $files_path ) ) ? filesize( $files_path ) : 0,
            'started' => $timenow,
            'took'    => $files_time_total,
            'status'  => $files_ok ? __( 'Successful', 'fx-backup' ) : __( 'Failed', 'fx-backup' ),
            'removed' => 0,
        ];
    }

    update_option( FX_BACKUP_OPTIONS, $cfg );
}

add_filter( 'cron_schedules', 'fxbackup_interval' );

function fxbackup_interval() {
    $cfg    = fxbackup_get_options();
    $period = ( $cfg['period'] == 0 ) ? 86400 : (int) $cfg['period'];
    return [
        'fx_backup' => [
            'interval' => $period,
            'display'  => sprintf( __( 'FX Backup Interval - %s minutes', 'fx-backup' ), (string) ( $period / 60 ) ),
        ],
    ];
}
