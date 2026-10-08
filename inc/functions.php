<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Backup streams SQL dumps and gzip data outside WP_Filesystem.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- tar archives require exec().

function fxbackup_compress( $source, $level = 9 ) {
    $cfg   = function_exists( 'fxbackup_get_options' ) ? fxbackup_get_options() : [ 'gzip_lvl' => 1 ];
    $level = max( 1, min( 9, (int) $cfg['gzip_lvl'] ) );

    $dest  = $source . '.gz';
    $mode  = 'wb' . $level;
    $error = false;
    if ( $fp_out = gzopen( $dest, $mode ) ) {
        if ( $fp_in = fopen( $source, 'rb' ) ) {
            while ( ! feof( $fp_in ) ) {
                gzwrite( $fp_out, fread( $fp_in, 1024 * 512 ) );
            }
            fclose( $fp_in );
        } else {
            $error = true;
        }
        gzclose( $fp_out );
    } else {
        $error = true;
    }
    if ( $error ) {
        return false;
    }
    return $dest;
}

function fxbackup_exec( $file, $action ) {
    global $wpdb;

    $handle = fopen( $file, 'wb' );

    if ( ! $handle ) {
        return new WP_Error( 'db_dump', sprintf( __( 'Could not open %s for writing.', 'fx-backup' ), $file ) );
    }

    fwrite( $handle, "/**\n" );
    fwrite( $handle, " * SQL Dump created with FX Backup\n" );
    fwrite( $handle, " *\n" );
    fwrite( $handle, " */\n\n" );

    fwrite( $handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" );
    fwrite( $handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n" );
    fwrite( $handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n" );
    fwrite( $handle, '/*!40101 SET NAMES ' . DB_CHARSET . " */;\n" );
    fwrite( $handle, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n" );
    fwrite( $handle, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n" );
    fwrite( $handle, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n" );

    $tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_A );

    if ( empty( $tables ) ) {
        fclose( $handle );
        return new WP_Error( 'db_dump', __( 'There are no tables in the database.', 'fx-backup' ) );
    }

    foreach ( $tables as $table_array ) {
        $table     = (string) current( $table_array );
        $table_sql = esc_sql( $table );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name from SHOW TABLES, escaped with esc_sql().
        $create = $wpdb->get_var( "SHOW CREATE TABLE `{$table_sql}`", 1 );
        $myisam = strpos( $create, 'MyISAM' );

        fwrite( $handle, '/* Dump of table `' . $table . "`\n" );
        fwrite( $handle, " * ------------------------------------------------------------*/\n\n" );

        fwrite( $handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n\n" . $create . ";\n\n" );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from SHOW TABLES, escaped with esc_sql().
        $data = $wpdb->get_results( "SELECT * FROM `{$table_sql}` LIMIT 1000", ARRAY_A );
        if ( ! empty( $data ) ) {
            fwrite( $handle, 'LOCK TABLES `' . $table . "` WRITE;\n" );
            if ( false !== $myisam ) {
                fwrite( $handle, '/*!40000 ALTER TABLE `' . $table . "` DISABLE KEYS */;\n\n" );
            }

            $offset = 0;
            do {
                foreach ( $data as $entry ) {
                    foreach ( $entry as $key => $value ) {
                        if ( null === $value ) {
                            $entry[ $key ] = 'NULL';
                        } elseif ( '' === $value || false === $value ) {
                            $entry[ $key ] = "''";
                        } elseif ( ! is_numeric( $value ) ) {
                            $entry[ $key ] = "'" . esc_sql( (string) $value ) . "'";
                        }
                    }
                    fwrite( $handle, 'INSERT INTO `' . $table . '` (' . implode( ', ', array_keys( $entry ) ) . ') VALUES (' . implode( ', ', $entry ) . " );\n" );
                }

                $offset += 1000;
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from SHOW TABLES, escaped with esc_sql(). Offset is a %d placeholder.
                $data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_sql}` LIMIT %d, 1000", $offset ), ARRAY_A );
            } while ( ! empty( $data ) );

            if ( false !== $myisam ) {
                fwrite( $handle, "\n/*!40000 ALTER TABLE `" . $table . '` ENABLE KEYS */;' );
            }
            fwrite( $handle, "\nUNLOCK TABLES;\n\n" );
        }
    }

    fwrite( $handle, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n" );
    fwrite( $handle, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n" );
    fwrite( $handle, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n" );
    fwrite( $handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" );
    fwrite( $handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" );
    fwrite( $handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n" );

    fclose( $handle );

    $cfg = function_exists( 'fxbackup_get_options' ) ? fxbackup_get_options() : [ 'compression' => 'none' ];
    if ( $cfg['compression'] === 'gz' ) {
        $compressed = fxbackup_compress( $file );
        if ( $compressed === false ) {
            return new WP_Error( 'db_dump', __( 'Could not compress the database backup.', 'fx-backup' ) );
        }
        unlink( $file );
    }

    return true;
}

function fxbackup_tar_available() {
    if ( ! function_exists( 'exec' ) ) {
        return false;
    }
    exec( 'tar --version 2>&1', $output, $return_var );
    return $return_var === 0;
}

function fxbackup_files_archive_basename( $date, $key ) {
    return 'Files_Backup_' . $date . '_' . $key . '.tar.gz';
}

function fxbackup_export_exclude_pattern( $export_dir ) {
    $export_dir = wp_normalize_path( $export_dir );
    $abspath    = trailingslashit( wp_normalize_path( ABSPATH ) );
    if ( $export_dir === '' || strpos( $export_dir, $abspath ) !== 0 ) {
        return '';
    }
    return ltrim( substr( $export_dir, strlen( $abspath ) ), '/\\' );
}

function fxbackup_list_root_files() {
    $files   = [];
    $abspath = wp_normalize_path( ABSPATH );
    $entries = scandir( $abspath );
    if ( $entries === false ) {
        return $files;
    }
    foreach ( $entries as $name ) {
        if ( $name === '.' || $name === '..' ) {
            continue;
        }
        $path = $abspath . '/' . $name;
        if ( is_file( $path ) ) {
            $files[] = $name;
        }
    }
    sort( $files );
    return $files;
}

function fxbackup_create_content_archive( $destination_path, $export_dir = '' ) {
    if ( ! fxbackup_tar_available() ) {
        return false;
    }
    $parent = dirname( $destination_path );
    if ( ! is_dir( $parent ) || ! is_writable( $parent ) ) {
        return false;
    }
    if ( $export_dir === '' && function_exists( 'fxbackup_get_options' ) ) {
        $export_dir = fxbackup_get_options()['export_dir'];
    }

    $abspath          = trailingslashit( wp_normalize_path( ABSPATH ) );
    $content_dir_path = wp_normalize_path( WP_CONTENT_DIR );
    if ( strpos( $content_dir_path, $abspath ) === 0 ) {
        $tar_content_base = rtrim( $abspath, '/' );
        $content_relative = ltrim( substr( $content_dir_path, strlen( $abspath ) ), '/\\' );
    } else {
        $tar_content_base = wp_normalize_path( dirname( WP_CONTENT_DIR ) );
        $content_relative = basename( WP_CONTENT_DIR );
    }

    $exclude    = fxbackup_export_exclude_pattern( $export_dir );
    $root_files = fxbackup_list_root_files();
    $parts      = [ 'tar', '-czf', escapeshellarg( $destination_path ) ];
    $parts[]    = '-C';
    $parts[]    = escapeshellarg( $tar_content_base );
    if ( $exclude !== '' ) {
        $parts[] = '--exclude';
        $parts[] = escapeshellarg( $exclude );
    }
    $parts[] = escapeshellarg( $content_relative );
    if ( ! empty( $root_files ) ) {
        $parts[] = '-C';
        $parts[] = escapeshellarg( $abspath );
        foreach ( $root_files as $file ) {
            $parts[] = escapeshellarg( $file );
        }
    }
    $return_var = 1;
    exec( implode( ' ', $parts ), $output, $return_var );
    if ( $return_var === 0 && is_file( $destination_path ) ) {
        return $destination_path;
    }
    return false;
}

function fxbackup_is_rotatable_backup_file( $filename ) {
    if ( $filename === '.' || $filename === '..' ) {
        return false;
    }
    if ( preg_match( '/^Backup_.+\.sql(\.gz)?$/i', $filename ) ) {
        return true;
    }
    if ( preg_match( '/^Files_Backup_.+\.tar\.gz$/i', $filename ) ) {
        return true;
    }
    if ( preg_match( '/^backup_.+\.tar\.gz$/i', $filename ) ) {
        return true;
    }
    return false;
}

function fxbackup_get_backup_download_url( $file_path, $export_dir ) {
    $file_path   = wp_normalize_path( $file_path );
    $content_dir = wp_normalize_path( WP_CONTENT_DIR );
    if ( strpos( $file_path, $content_dir ) === 0 ) {
        $relative = ltrim( substr( $file_path, strlen( $content_dir ) ), '/\\' );
        return content_url( $relative );
    }
    return '';
}

function fxbackup_log_type_label( $type ) {
    if ( $type === 'files' ) {
        return __( 'Files', 'fx-backup' );
    }
    return __( 'Database', 'fx-backup' );
}

function fxbackup_rotate( $cfg, $timenow ) {
    $removed = 0;

    if ( (int) $cfg['rotate'] < 0 ) {
        return $removed;
    }

    $compare = 86400 * (int) $cfg['rotate'];
    $dir     = $cfg['export_dir'];
    if ( ! is_dir( $dir ) || ! ( $handle = opendir( $dir ) ) ) {
        return $removed;
    }

    $file = readdir( $handle );
    while ( false !== $file ) {
        if ( fxbackup_is_rotatable_backup_file( $file ) ) {
            $path = $dir . '/' . $file;
            if ( is_file( $path ) ) {
                $mtime = filemtime( $path );
                if ( $mtime !== false && $timenow > $mtime + $compare && unlink( $path ) ) {
                    ++$removed;
                }
            }
        }
        $file = readdir( $handle );
    }
    closedir( $handle );
    return $removed;
}
