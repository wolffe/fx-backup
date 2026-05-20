<?php

function fxbackup_resolve_backup_path( $basename, $export_dir ) {
    $basename = sanitize_file_name( $basename );
    if ( $basename === '' || $export_dir === '' ) {
        return '';
    }
    $export_dir  = rtrim( wp_normalize_path( $export_dir ), '/' );
    $path        = $export_dir . '/' . $basename;
    $real_export = realpath( $export_dir );
    $real_path   = realpath( $path );
    if ( $real_export === false || $real_path === false ) {
        return '';
    }
    if ( strpos( $real_path, $real_export ) !== 0 ) {
        return '';
    }
    if ( ! is_file( $real_path ) || ! fxbackup_is_rotatable_backup_file( $basename ) ) {
        return '';
    }
    return $real_path;
}

function fxbackup_is_database_backup( $basename ) {
    return (bool) preg_match( '/^Backup_.+\.sql(\.gz)?$/i', $basename );
}

function fxbackup_is_files_backup( $basename ) {
    return (bool) preg_match( '/^(Files_Backup_|backup_).+\.tar\.gz$/i', $basename );
}

function fxbackup_mysql_cli_available() {
    if ( ! function_exists( 'exec' ) ) {
        return false;
    }
    exec( 'mysql --version 2>&1', $output, $return_var );
    return $return_var === 0;
}

function fxbackup_decompress_sql_dump( $path ) {
    if ( preg_match( '/\.gz$/i', $path ) ) {
        if ( ! function_exists( 'gzopen' ) ) {
            return new WP_Error( 'restore', __( 'GZIP extension is required to read this backup.', 'fx-backup' ) );
        }
        $temp = wp_tempnam( 'fxbackup-restore.sql' );
        if ( $temp === false ) {
            return new WP_Error( 'restore', __( 'Could not create a temporary file.', 'fx-backup' ) );
        }
        $in  = gzopen( $path, 'rb' );
        $out = fopen( $temp, 'wb' );
        if ( ! $in || ! $out ) {
            if ( $in ) {
                gzclose( $in );
            }
            if ( $out ) {
                fclose( $out );
            }
            @unlink( $temp );
            return new WP_Error( 'restore', __( 'Could not decompress the backup file.', 'fx-backup' ) );
        }
        while ( ! gzeof( $in ) ) {
            fwrite( $out, gzread( $in, 512000 ) );
        }
        gzclose( $in );
        fclose( $out );
        return $temp;
    }
    return $path;
}

function fxbackup_restore_database_cli( $sql_path ) {
    $host = DB_HOST;
    $port = null;
    if ( strpos( $host, ':' ) !== false ) {
        list($host, $port) = explode( ':', $host, 2 );
    }
    $cnf = wp_tempnam( 'fxbackup-mysql.cnf' );
    if ( $cnf === false ) {
        return false;
    }
    $lines = [
        '[client]',
        'user=' . DB_USER,
        'password=' . DB_PASSWORD,
        'host=' . $host,
    ];
    if ( $port !== null && $port !== '' ) {
        $lines[] = 'port=' . $port;
    }
    file_put_contents( $cnf, implode( "\n", $lines ) . "\n" );

    $cmd        = 'mysql --defaults-extra-file=' . escapeshellarg( $cnf )
        . ' ' . escapeshellarg( DB_NAME )
        . ' < ' . escapeshellarg( $sql_path );
    $return_var = 1;
    exec( $cmd, $output, $return_var );
    @unlink( $cnf );
    return $return_var === 0;
}

function fxbackup_restore_database_mysqli( $sql_path ) {
    global $wpdb;

    if ( ! isset( $wpdb->dbh ) || ! ( $wpdb->dbh instanceof mysqli ) ) {
        return new WP_Error( 'restore', __( 'Database driver does not support bulk import.', 'fx-backup' ) );
    }

    $sql = file_get_contents( $sql_path );
    if ( $sql === false || $sql === '' ) {
        return new WP_Error( 'restore', __( 'Backup file is empty or unreadable.', 'fx-backup' ) );
    }

    mysqli_report( MYSQLI_REPORT_OFF );
    if ( ! mysqli_multi_query( $wpdb->dbh, $sql ) ) {
        return new WP_Error( 'restore', mysqli_error( $wpdb->dbh ) );
    }

    do {
        if ( $result = mysqli_store_result( $wpdb->dbh ) ) {
            mysqli_free_result( $result );
        }
    } while ( mysqli_more_results( $wpdb->dbh ) && mysqli_next_result( $wpdb->dbh ) );

    if ( mysqli_errno( $wpdb->dbh ) ) {
        return new WP_Error( 'restore', mysqli_error( $wpdb->dbh ) );
    }

    return true;
}

function fxbackup_restore_database( $path ) {
    if ( ! fxbackup_is_database_backup( basename( $path ) ) ) {
        return new WP_Error( 'restore', __( 'Not a database backup file.', 'fx-backup' ) );
    }

    @ini_set( 'memory_limit', '512M' );
    @ini_set( 'max_execution_time', '600' );

    $sql_path = fxbackup_decompress_sql_dump( $path );
    if ( is_wp_error( $sql_path ) ) {
        return $sql_path;
    }

    $temp   = ( $sql_path !== $path );
    $result = false;

    if ( fxbackup_mysql_cli_available() ) {
        $result = fxbackup_restore_database_cli( $sql_path );
        if ( $result ) {
            if ( $temp ) {
                @unlink( $sql_path );
            }
            return true;
        }
    }

    $result = fxbackup_restore_database_mysqli( $sql_path );
    if ( $temp ) {
        @unlink( $sql_path );
    }

    if ( is_wp_error( $result ) ) {
        return $result;
    }
    if ( $result === true ) {
        return true;
    }

    return new WP_Error( 'restore', __( 'Database restore failed.', 'fx-backup' ) );
}

function fxbackup_restore_files( $path, $export_dir = '' ) {
    if ( ! fxbackup_is_files_backup( basename( $path ) ) ) {
        return new WP_Error( 'restore', __( 'Not a file archive backup.', 'fx-backup' ) );
    }
    if ( ! fxbackup_tar_available() ) {
        return new WP_Error( 'restore', __( 'File restore requires tar and exec() on the server.', 'fx-backup' ) );
    }

    if ( $export_dir === '' ) {
        $export_dir = fxbackup_get_options()['export_dir'] ?? '';
    }

    $abspath = trailingslashit( wp_normalize_path( ABSPATH ) );
    if ( ! is_writable( $abspath ) ) {
        return new WP_Error( 'restore', __( 'Site root is not writable.', 'fx-backup' ) );
    }

    $exclude = fxbackup_export_exclude_pattern( $export_dir );
    $parts   = [ 'tar', '-xzf', escapeshellarg( $path ), '-C', escapeshellarg( rtrim( $abspath, '/' ) ) ];
    if ( $exclude !== '' ) {
        $parts[] = '--exclude';
        $parts[] = escapeshellarg( $exclude );
    }
    $return_var = 1;
    exec( implode( ' ', $parts ), $output, $return_var );
    if ( $return_var !== 0 ) {
        return new WP_Error( 'restore', __( 'tar extract failed. Check permissions and disk space.', 'fx-backup' ) );
    }
    return true;
}

function fxbackup_restore_confirm_valid( $text ) {
    $text = trim( (string) $text );
    if ( $text === 'RESTORE' ) {
        return true;
    }
    return $text !== '' && $text === get_bloginfo( 'name' );
}

function fxbackup_list_disk_backups( $export_dir ) {
    $items      = [];
    $export_dir = rtrim( (string) $export_dir, '/\\' );
    if ( $export_dir === '' || ! is_dir( $export_dir ) ) {
        return $items;
    }
    $handle = opendir( $export_dir );
    if ( $handle === false ) {
        return $items;
    }
    while ( false !== ( $file = readdir( $handle ) ) ) {
        if ( ! fxbackup_is_rotatable_backup_file( $file ) ) {
            continue;
        }
        $path = $export_dir . '/' . $file;
        if ( ! is_file( $path ) ) {
            continue;
        }
        $mtime   = filemtime( $path );
        $items[] = [
            'basename' => $file,
            'path'     => wp_normalize_path( $path ),
            'size'     => filesize( $path ),
            'mtime'    => $mtime !== false ? $mtime : 0,
            'type'     => fxbackup_is_files_backup( $file ) ? 'files' : 'database',
        ];
    }
    closedir( $handle );
    usort(
        $items,
        function ( $a, $b ) {
            return $b['mtime'] <=> $a['mtime'];
        }
    );
    return $items;
}
