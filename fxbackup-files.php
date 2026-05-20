<?php
require_once FX_BACKUP_PLUGIN_PATH . '/inc/functions.php';

$cfg           = fxbackup_get_options();
$tar_available = fxbackup_tar_available();

fxbackup_render_page_open( __( 'Files Manager', 'fx-backup' ) );
fxbackup_render_stats_cards( $cfg['export_dir'] ?? '' );

if ( isset( $_POST['fxbackup_contentcreate'] ) ) {
    check_admin_referer( 'fxbackup_files_backup' );

    if ( empty( $cfg['export_dir'] ) ) {
        echo '<div id="message" class="error"><p>' . esc_html__( 'Set a backup directory on the main settings page first.', 'fx-backup' ) . '</p></div>';
    } elseif ( ! $tar_available ) {
        echo '<div id="message" class="error"><p>' . esc_html__( 'File backup requires tar and exec() on the server.', 'fx-backup' ) . '</p></div>';
    } else {
        if ( ! is_dir( $cfg['export_dir'] ) ) {
            wp_mkdir_p( $cfg['export_dir'] );
        }

        $timenow    = time();
        $mtime      = explode( ' ', microtime() );
        $time_start = $mtime[1] + $mtime[0];
        $key        = substr( md5( md5( DB_NAME . '|' . microtime() ) ), 0, 6 );
        $date       = wp_date( 'm.d.y-H.i.s', $timenow );
        $files_path = $cfg['export_dir'] . '/' . fxbackup_files_archive_basename( $date, $key );
        $files_ok   = fxbackup_create_content_archive( $files_path, $cfg['export_dir'] );
        $mtime      = explode( ' ', microtime() );
        $time_end   = $mtime[1] + $mtime[0];
        $time_total = $time_end - $time_start;

        if ( ! isset( $cfg['logs'] ) || ! is_array( $cfg['logs'] ) ) {
            $cfg['logs'] = [];
        }

        $cfg['logs'][] = [
            'file'    => $files_path,
            'type'    => 'files',
            'size'    => ( $files_ok && is_file( $files_path ) ) ? filesize( $files_path ) : 0,
            'started' => $timenow,
            'took'    => $time_total,
            'status'  => $files_ok ? __( 'Successful', 'fx-backup' ) : __( 'Failed', 'fx-backup' ),
            'removed' => 0,
        ];
        update_option( FX_BACKUP_OPTIONS, $cfg );

        if ( $files_ok ) {
            $download_url = fxbackup_get_backup_download_url( $files_path, $cfg['export_dir'] );
            echo '<div id="message" class="updated fade"><p>' . esc_html__( 'File backup successfully created!', 'fx-backup' ) . '</p></div>';
            if ( $download_url !== '' ) {
                ?>
                <p><a href="<?php echo esc_url( $download_url ); ?>"><?php _e( 'Click to download the archive!', 'fx-backup' ); ?></a></p>
                <?php
            }
        } else {
            echo '<div id="message" class="error"><p>' . esc_html__( 'File backup failed. Ensure tar is available and the backup directory is writable.', 'fx-backup' ) . '</p></div>';
        }
    }
}
?>

<div id="poststuff">
    <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Files Manager Actions', 'fx-backup' ); ?></span></h3>
        <div class="inside">
            <p><?php _e( 'Create an archive of your full <code>wp-content</code> folder (excluding the backup directory), plus site root files such as <code>wp-config.php</code> and <code>index.php</code>. Archives are saved in your configured backup folder. Does not include <code>wp-admin</code>, <code>wp-includes</code>, or the database.', 'fx-backup' ); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field( 'fxbackup_files_backup' ); ?>
                <p>
                    <input type="submit" name="fxbackup_contentcreate" class="button button-primary" value="<?php esc_attr_e( 'Generate archive now!', 'fx-backup' ); ?>"<?php echo $tar_available ? '' : ' disabled'; ?>>
                    <label><?php _e( 'Use for migration or occasional file backup. Enable scheduled file backups on the main settings page.', 'fx-backup' ); ?></label>
                </p>
            </form>
            <p><?php _e( 'You need enough disk space or the archive may be incomplete.', 'fx-backup' ); ?></p>
            <?php fxbackup_render_environment_badges(); ?>
        </div>
    </div>
</div>

<?php fxbackup_render_page_close(); ?>
