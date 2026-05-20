<?php
require_once FX_BACKUP_PLUGIN_PATH . '/inc/functions.php';
require_once FX_BACKUP_PLUGIN_PATH . '/inc/restore.php';

$cfg            = fxbackup_get_options();
$export_dir     = $cfg['export_dir'] ?? '';
$restore_notice = '';

if ( isset( $_POST['fxbackup_restore_db'] ) || isset( $_POST['fxbackup_restore_files'] ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to restore backups.', 'fx-backup' ) );
    }
    check_admin_referer( 'fxbackup_restore' );

    if ( ! fxbackup_restore_confirm_valid( $_POST['confirm_text'] ?? '' ) ) {
        $restore_notice = [
            'type' => 'error',
            'text' => __( 'Confirmation did not match. Type your site name or RESTORE to proceed.', 'fx-backup' ),
        ];
    } else {
        $path = fxbackup_resolve_backup_path( $_POST['backup_file'] ?? '', $export_dir );
        if ( $path === '' ) {
            $restore_notice = [
                'type' => 'error',
                'text' => __( 'Invalid backup file.', 'fx-backup' ),
            ];
        } elseif ( isset( $_POST['fxbackup_restore_db'] ) ) {
            $result = fxbackup_restore_database( $path );
            if ( is_wp_error( $result ) ) {
                $restore_notice = [
                    'type' => 'error',
                    'text' => $result->get_error_message(),
                ];
            } else {
                $restore_notice = [
                    'type' => 'updated',
                    'text' => sprintf( __( 'Database restored from %s.', 'fx-backup' ), basename( $path ) ),
                ];
            }
        } else {
            $result = fxbackup_restore_files( $path, $export_dir );
            if ( is_wp_error( $result ) ) {
                $restore_notice = [
                    'type' => 'error',
                    'text' => $result->get_error_message(),
                ];
            } else {
                $restore_notice = [
                    'type' => 'updated',
                    'text' => sprintf( __( 'Files restored from %s.', 'fx-backup' ), basename( $path ) ),
                ];
            }
        }
    }
}

$disk_backups = fxbackup_list_disk_backups( $export_dir );

fxbackup_render_page_open( __( 'Backup Manager', 'fx-backup' ) );
fxbackup_render_stats_cards( $export_dir );

if ( $restore_notice !== '' ) {
    $class = $restore_notice['type'] === 'error' ? 'error' : 'updated';
    echo '<div id="message" class="' . esc_attr( $class ) . ' fade"><p>' . esc_html( $restore_notice['text'] ) . '</p></div>';
}
?>

<?php if ( ! empty( $disk_backups ) ) { ?>
    <table class="widefat fxbackup-backups-table">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col"><?php _e( 'Type', 'fx-backup' ); ?></th>
                <th scope="col"><?php _e( 'Modified', 'fx-backup' ); ?></th>
                <th scope="col"><?php _e( 'File (backup name)', 'fx-backup' ); ?></th>
                <th scope="col"><?php _e( 'Filesize', 'fx-backup' ); ?></th>
                <th scope="col"><?php _e( 'Actions', 'fx-backup' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $row = 0;
            foreach ( $disk_backups as $item ) {
                $download_url = fxbackup_get_backup_download_url( $item['path'], $export_dir );
                $is_db        = ( $item['type'] === 'database' );
                ++$row;
                ?>
                <tr>
                    <td><?php echo (int) $row; ?></td>
                    <td><?php echo esc_html( fxbackup_log_type_label( $item['type'] ) ); ?></td>
                    <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $item['mtime'] ) ); ?></td>
                    <td>
                        <?php if ( $download_url !== '' ) { ?>
                            <a href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html( $item['basename'] ); ?></a>
                            <?php
                        } else {
                            echo esc_html( $item['basename'] );
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html( size_format( (int) $item['size'], 2 ) ); ?></td>
                    <td class="fxbackup-actions-cell">
                        <?php if ( $is_db ) { ?>
                            <details class="fxbackup-restore-details">
                                <summary class="button button-secondary"><?php esc_html_e( 'Restore database', 'fx-backup' ); ?></summary>
                                <form method="post" class="fxbackup-restore-form">
                                    <?php wp_nonce_field( 'fxbackup_restore' ); ?>
                                    <input type="hidden" name="backup_file" value="<?php echo esc_attr( $item['basename'] ); ?>">
                                    <p class="description"><?php esc_html_e( 'This replaces all database tables with the backup. The site may be unusable until restore completes.', 'fx-backup' ); ?></p>
                                    <p>
                                        <label for="confirm_db_<?php echo (int) $row; ?>"><?php esc_html_e( 'Type your site name or RESTORE to confirm', 'fx-backup' ); ?></label><br>
                                        <input type="text" name="confirm_text" id="confirm_db_<?php echo (int) $row; ?>" class="regular-text" autocomplete="off">
                                    </p>
                                    <p>
                                        <input type="submit" name="fxbackup_restore_db" class="button button-primary" value="<?php esc_attr_e( 'Run database restore', 'fx-backup' ); ?>">
                                    </p>
                                </form>
                            </details>
                        <?php } else { ?>
                            <details class="fxbackup-restore-details">
                                <summary class="button button-secondary"><?php esc_html_e( 'Restore files', 'fx-backup' ); ?></summary>
                                <form method="post" class="fxbackup-restore-form">
                                    <?php wp_nonce_field( 'fxbackup_restore' ); ?>
                                    <input type="hidden" name="backup_file" value="<?php echo esc_attr( $item['basename'] ); ?>">
                                    <p class="description"><?php esc_html_e( 'This overwrites wp-content and matching root files from the archive. Does not restore the database.', 'fx-backup' ); ?></p>
                                    <p>
                                        <label for="confirm_files_<?php echo (int) $row; ?>"><?php esc_html_e( 'Type your site name or RESTORE to confirm', 'fx-backup' ); ?></label><br>
                                        <input type="text" name="confirm_text" id="confirm_files_<?php echo (int) $row; ?>" class="regular-text" autocomplete="off">
                                    </p>
                                    <p>
                                        <input type="submit" name="fxbackup_restore_files" class="button button-primary" value="<?php esc_attr_e( 'Run file restore', 'fx-backup' ); ?>">
                                    </p>
                                </form>
                            </details>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php } else { ?>
    <div id="poststuff">
        <div class="postbox">
            <h3 class="hndle"><span><?php esc_html_e( 'No backups on disk', 'fx-backup' ); ?></span></h3>
            <div class="inside">
                <p><?php esc_html_e( 'No backup files were found in your backup folder. Run a scheduled backup or create a file archive from Files Manager.', 'fx-backup' ); ?></p>
            </div>
        </div>
    </div>
<?php } ?>

<div id="poststuff">
    <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Manager Status', 'fx-backup' ); ?></span></h3>
        <div class="inside">
            <p>
                <strong><?php _e( 'Your site is protected by FX Backup.', 'fx-backup' ); ?></strong><br>
                <?php echo wp_kses_post( sprintf( __( 'There are %s backup files on disk.', 'fx-backup' ), '<strong>' . count( $disk_backups ) . '</strong>' ) ); ?>
            </p>
            <p><span class="description"><?php _e( 'Database and file archives are stored in your backup folder. Copy important backups offsite before restoring on a live site.', 'fx-backup' ); ?></span></p>
            <?php fxbackup_render_environment_badges(); ?>
        </div>
    </div>
</div>

<?php fxbackup_render_page_close(); ?>
