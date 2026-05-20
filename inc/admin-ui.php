<?php

function fxbackup_admin_enqueue_assets( $hook_suffix ) {
    if ( ! is_string( $hook_suffix ) || ( strpos( $hook_suffix, 'fx-backup' ) === false && strpos( $hook_suffix, 'fxbackup' ) === false ) ) {
        return;
    }

    wp_enqueue_style(
        'fxbackup-admin',
        FX_BACKUP_PLUGIN_URL . '/assets/admin.css',
        [],
        FX_BACKUP_VERSION
    );
}

function fxbackup_disk_stats( $export_dir = '' ) {
    if ( ! function_exists( 'fxbackup_is_rotatable_backup_file' ) ) {
        require_once FX_BACKUP_PLUGIN_PATH . '/inc/functions.php';
    }

    $stats = [
        'count'    => 0,
        'bytes'    => 0,
        'db'       => 0,
        'files'    => 0,
        'readable' => false,
    ];

    if ( $export_dir === '' ) {
        $export_dir = fxbackup_get_options()['export_dir'] ?? '';
    }

    $export_dir = rtrim( (string) $export_dir, '/\\' );
    if ( $export_dir === '' || ! is_dir( $export_dir ) || ! is_readable( $export_dir ) ) {
        return $stats;
    }

    $stats['readable'] = true;
    $handle            = opendir( $export_dir );
    if ( $handle === false ) {
        return $stats;
    }

    while ( false !== ( $file = readdir( $handle ) ) ) {
        if ( ! fxbackup_is_rotatable_backup_file( $file ) ) {
            continue;
        }
        $path = $export_dir . '/' . $file;
        if ( ! is_file( $path ) ) {
            continue;
        }
        $size = filesize( $path );
        if ( $size === false ) {
            continue;
        }
        ++$stats['count'];
        $stats['bytes'] += $size;
        if ( preg_match( '/\.tar\.gz$/i', $file ) ) {
            ++$stats['files'];
        } else {
            ++$stats['db'];
        }
    }
    closedir( $handle );

    return $stats;
}

function fxbackup_parse_ini_bytes( $value ) {
    if ( $value === false || $value === '' ) {
        return 0;
    }
    $value = trim( (string) $value );
    if ( $value === '-1' ) {
        return -1;
    }
    $unit  = strtolower( substr( $value, -1 ) );
    $bytes = (int) $value;
    if ( $unit === 'g' ) {
        return $bytes * 1024 * 1024 * 1024;
    }
    if ( $unit === 'm' ) {
        return $bytes * 1024 * 1024;
    }
    if ( $unit === 'k' ) {
        return $bytes * 1024;
    }
    return $bytes;
}

function fxbackup_memory_limit_state( $ini_value ) {
    $bytes = fxbackup_parse_ini_bytes( $ini_value );
    if ( $bytes === -1 ) {
        return 'ok';
    }
    if ( $bytes >= 256 * 1024 * 1024 ) {
        return 'ok';
    }
    if ( $bytes >= 128 * 1024 * 1024 ) {
        return 'warn';
    }
    return 'fail';
}

function fxbackup_max_execution_state( $ini_value ) {
    if ( $ini_value === false || $ini_value === '' ) {
        return 'neutral';
    }
    $seconds = (int) $ini_value;
    if ( $seconds === 0 ) {
        return 'ok';
    }
    if ( $seconds >= 300 ) {
        return 'ok';
    }
    if ( $seconds >= 60 ) {
        return 'warn';
    }
    return 'fail';
}

function fxbackup_mysqli_available() {
    global $wpdb;

    return isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli;
}

function fxbackup_requirement_checks() {
    require_once FX_BACKUP_PLUGIN_PATH . '/inc/functions.php';
    require_once FX_BACKUP_PLUGIN_PATH . '/inc/restore.php';

    $cfg             = fxbackup_get_options();
    $export_dir      = rtrim( (string) ( $cfg['export_dir'] ?? '' ), '/\\' );
    $php_ok          = version_compare( PHP_VERSION, '8.0', '>=' );
    $gzip            = function_exists( 'gzopen' );
    $tar             = fxbackup_tar_available();
    $exec_ok         = function_exists( 'exec' );
    $files_ok        = $tar && $exec_ok;
    $export_writable = ( $export_dir !== '' && is_dir( $export_dir ) && is_writable( $export_dir ) );
    $export_exists   = ( $export_dir !== '' && is_dir( $export_dir ) );
    $scheduled       = wp_next_scheduled( 'do_fx_backup' ) !== false;
    $restore_ok      = fxbackup_mysqli_available() || fxbackup_mysql_cli_available();
    $memory_ini      = ini_get( 'memory_limit' );
    $max_exec_ini    = ini_get( 'max_execution_time' );
    $memory_state    = fxbackup_memory_limit_state( $memory_ini );
    $max_exec_state  = fxbackup_max_execution_state( $max_exec_ini );

    $checks = [
        [
            'label'  => __( 'PHP 8.0+', 'fx-backup' ),
            'detail' => $php_ok
                ? sprintf( __( 'Running PHP %s.', 'fx-backup' ), PHP_VERSION )
                : sprintf( __( 'PHP %s is installed; 8.0 or newer is required.', 'fx-backup' ), PHP_VERSION ),
            'state'  => $php_ok ? 'ok' : 'fail',
        ],
        [
            'label'  => __( 'Backup folder', 'fx-backup' ),
            'detail' => $export_writable
                ? ( $export_dir !== '' ? $export_dir : __( 'Writable.', 'fx-backup' ) )
                : ( $export_exists
                    ? __( 'Folder exists but is not writable.', 'fx-backup' )
                    : __( 'Set a writable backup directory in settings.', 'fx-backup' ) ),
            'state'  => $export_writable ? 'ok' : ( $export_exists ? 'warn' : 'fail' ),
        ],
        [
            'label'  => __( 'GZIP', 'fx-backup' ),
            'detail' => $gzip
                ? __( 'Compressed .sql.gz backups are supported.', 'fx-backup' )
                : __( 'Optional; uncompressed .sql dumps only.', 'fx-backup' ),
            'state'  => $gzip ? 'ok' : 'warn',
        ],
        [
            'label'  => __( 'File archives', 'fx-backup' ),
            'detail' => $files_ok
                ? __( 'tar and exec() are available.', 'fx-backup' )
                : __( 'Optional; required for file backups and restore.', 'fx-backup' ),
            'state'  => $files_ok ? 'ok' : 'warn',
        ],
        [
            'label'  => __( 'Database restore', 'fx-backup' ),
            'detail' => $restore_ok
                ? ( fxbackup_mysql_cli_available()
                    ? __( 'mysql CLI or mysqli can import dumps.', 'fx-backup' )
                    : __( 'mysqli multi-query can import dumps.', 'fx-backup' ) )
                : __( 'Needs mysqli or mysql CLI for restore.', 'fx-backup' ),
            'state'  => $restore_ok ? 'ok' : 'fail',
        ],
        [
            'label'  => __( 'memory_limit', 'fx-backup' ),
            'detail' => $memory_ini !== false && $memory_ini !== ''
                ? (string) $memory_ini . ( $memory_state === 'ok' ? '' : __( ' — 256M+ recommended for large backups.', 'fx-backup' ) )
                : __( 'Unknown', 'fx-backup' ),
            'state'  => $memory_state,
        ],
        [
            'label'  => __( 'max_execution_time', 'fx-backup' ),
            'detail' => $max_exec_ini !== false && $max_exec_ini !== ''
                ? ( (int) $max_exec_ini === 0
                    ? __( 'Unlimited (0).', 'fx-backup' )
                    : (string) $max_exec_ini . 's' . ( $max_exec_state === 'ok' ? '' : __( ' — 300s+ recommended for large backups.', 'fx-backup' ) ) )
                : __( 'Unknown', 'fx-backup' ),
            'state'  => $max_exec_state,
        ],
        [
            'label'  => __( 'Scheduled backups', 'fx-backup' ),
            'detail' => empty( $cfg['active'] )
                ? __( 'Schedule is off in settings.', 'fx-backup' )
                : ( $scheduled
                    ? __( 'Cron event is registered.', 'fx-backup' )
                    : __( 'Schedule is on but no cron event found.', 'fx-backup' ) ),
            'state'  => empty( $cfg['active'] ) ? 'neutral' : ( $scheduled ? 'ok' : 'warn' ),
        ],
    ];

    if ( ! empty( $cfg['backup_files'] ) && ! $files_ok ) {
        $checks[] = [
            'label'  => __( 'Scheduled file backup', 'fx-backup' ),
            'detail' => __( 'Enabled in settings but tar/exec() is missing.', 'fx-backup' ),
            'state'  => 'fail',
        ];
    }

    if ( ! empty( $cfg['compression'] ) && $cfg['compression'] === 'gz' && ! $gzip ) {
        $checks[] = [
            'label'  => __( 'GZIP compression', 'fx-backup' ),
            'detail' => __( 'Selected in settings but gzopen is not available.', 'fx-backup' ),
            'state'  => 'fail',
        ];
    }

    return $checks;
}

function fxbackup_environment_checks() {
    $checks = [
        [
            'label'  => 'DB_CHARSET',
            'detail' => defined( 'DB_CHARSET' ) ? DB_CHARSET : __( 'Not defined', 'fx-backup' ),
            'state'  => defined( 'DB_CHARSET' ) ? 'ok' : 'warn',
        ],
        [
            'label'  => 'ABSPATH',
            'detail' => defined( 'ABSPATH' ) ? ABSPATH : __( 'Not defined', 'fx-backup' ),
            'state'  => defined( 'ABSPATH' ) ? 'ok' : 'fail',
        ],
        [
            'label'  => 'WP_CONTENT_DIR',
            'detail' => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : __( 'Not defined', 'fx-backup' ),
            'state'  => ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) ? 'ok' : 'fail',
        ],
    ];

    if ( defined( 'CP_VERSION' ) ) {
        $checks[] = [
            'label'  => __( 'ClassicPress', 'fx-backup' ),
            'detail' => CP_VERSION,
            'state'  => 'ok',
        ];
    }

    return $checks;
}

function fxbackup_render_badge_list( $checks, $heading, $wrap_class ) {
    if ( $checks === [] ) {
        return;
    }
    ?>
    <div class="<?php echo esc_attr( $wrap_class ); ?>">
        <h4 class="fxbackup-subheading"><?php echo esc_html( $heading ); ?></h4>
        <ul class="fxbackup-badges">
            <?php
            foreach ( $checks as $check ) {
                $state = $check['state'];
                ?>
                <li class="fxbackup-badge fxbackup-badge--<?php echo esc_attr( $state ); ?>">
                    <span class="fxbackup-badge__label"><?php echo esc_html( $check['label'] ); ?></span>
                    <span class="fxbackup-badge__detail"><?php echo esc_html( $check['detail'] ); ?></span>
                </li>
            <?php } ?>
        </ul>
    </div>
    <?php
}

function fxbackup_render_requirement_badges() {
    fxbackup_render_badge_list(
        fxbackup_requirement_checks(),
        __( 'Requirements', 'fx-backup' ),
        'fxbackup-requirements-wrap'
    );
}

function fxbackup_render_page_open( $title ) {
    echo '<div class="wrap fxbackup-wrap">';
    echo '<header class="fxbackup-header">';
    echo '<h1 class="fxbackup-title"><span class="dashicons dashicons-cloud" aria-hidden="true"></span>';
    echo esc_html( $title );
    echo '</h1>';
    echo '</header>';
}

function fxbackup_render_page_close() {
    echo '</div>';
}

function fxbackup_render_stats_cards( $export_dir = '' ) {
    $stats = fxbackup_disk_stats( $export_dir );
    ?>
    <div class="fxbackup-stats" role="group" aria-label="<?php esc_attr_e( 'Backup storage summary', 'fx-backup' ); ?>">
        <div class="fxbackup-stat-card">
            <span class="fxbackup-stat-value"><?php echo esc_html( (string) $stats['count'] ); ?></span>
            <span class="fxbackup-stat-label"><?php esc_html_e( 'Backups on disk', 'fx-backup' ); ?></span>
        </div>
        <div class="fxbackup-stat-card">
            <span class="fxbackup-stat-value"><?php echo esc_html( size_format( $stats['bytes'], 2 ) ); ?></span>
            <span class="fxbackup-stat-label"><?php esc_html_e( 'Total size on disk', 'fx-backup' ); ?></span>
        </div>
        <div class="fxbackup-stat-card">
            <span class="fxbackup-stat-value"><?php echo esc_html( (string) $stats['db'] ); ?></span>
            <span class="fxbackup-stat-label"><?php esc_html_e( 'Database dumps', 'fx-backup' ); ?></span>
        </div>
        <div class="fxbackup-stat-card">
            <span class="fxbackup-stat-value"><?php echo esc_html( (string) $stats['files'] ); ?></span>
            <span class="fxbackup-stat-label"><?php esc_html_e( 'File archives', 'fx-backup' ); ?></span>
        </div>
    </div>
    <?php
    if ( $export_dir !== '' && ! $stats['readable'] ) {
        echo '<p class="fxbackup-note">' . esc_html__( 'Backup folder is missing or not readable. Stats count only matching backup files on disk.', 'fx-backup' ) . '</p>';
    }
    fxbackup_render_requirement_badges();
}

function fxbackup_render_environment_badges() {
    fxbackup_render_badge_list(
        fxbackup_environment_checks(),
        __( 'Environment', 'fx-backup' ),
        'fxbackup-badges-wrap'
    );
}
