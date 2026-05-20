<?php
$cfg          = fxbackup_get_options();
$fxbackup_msg = [];

if ( isset( $_POST['fxbackupsubmit'] ) ) {
    check_admin_referer( 'fxbackup_options' );
    $temp['export_dir']      = rtrim( stripslashes_deep( trim( $_POST['export_dir'] ?? '' ) ), '/' );
    $temp['compression']     = stripslashes_deep( trim( $_POST['compression'] ?? 'none' ) );
    $temp['gzip_lvl']        = intval( $_POST['gzip_lvl'] ?? 1 );
    $temp['period']          = intval( $_POST['severy'] ?? 1 ) * intval( $_POST['speriod'] ?? 86400 );
    $temp['active']          = ! empty( $_POST['active'] ) ? 1 : 0;
    $temp['rotate']          = intval( $_POST['rotate'] ?? -1 );
    $temp['logs']            = $cfg['logs'] ?? [];
    $temp['smart_email']     = sanitize_email( $_POST['smart_email'] ?? '' );
    $temp['send_attachment'] = ! empty( $_POST['send_attachment'] ) ? 1 : 0;
    $temp['backup_files']    = ! empty( $_POST['backup_files'] ) ? 1 : 0;

    $timenow          = time();
    $year             = wp_date( 'Y', $timenow );
    $month            = wp_date( 'n', $timenow );
    $day              = wp_date( 'j', $timenow );
    $hours            = intval( $_POST['hours'] ?? 0 );
    $minutes          = intval( $_POST['minutes'] ?? 0 );
    $seconds          = intval( $_POST['seconds'] ?? 0 );
    $temp['schedule'] = mktime( $hours, $minutes, $seconds, $month, $day, $year );

    update_option( FX_BACKUP_OPTIONS, $temp );

    if ( empty( $_POST['active'] ) ) {
        wp_clear_scheduled_hook( 'do_fx_backup' );
    } else {
        wp_clear_scheduled_hook( 'do_fx_backup' );
        wp_schedule_event( $temp['schedule'], 'fx_backup', 'do_fx_backup' );
    }

    $cfg = $temp;
    echo '<div id="message" class="updated fade"><p>' . esc_html__( 'Options saved!', 'fx-backup' ) . '</p></div>';
}

if ( ! empty( $cfg['export_dir'] ) ) {
    if ( ! is_dir( $cfg['export_dir'] ) ) {
        if ( wp_mkdir_p( $cfg['export_dir'] ) ) {
            $fxbackup_msg[] = sprintf( __( 'Folder <strong>%s</strong> was successfully created!', 'fx-backup' ), esc_html( $cfg['export_dir'] ) );
        } else {
            $fxbackup_msg[] = sprintf( __( 'Folder <strong>%s</strong> was not created. Check permissions!', 'fx-backup' ), esc_html( $cfg['export_dir'] ) );
        }
    } else {
        $fxbackup_msg[] = sprintf( __( '<small>Folder <strong>%s</strong> is available</small>', 'fx-backup' ), esc_html( $cfg['export_dir'] ) );
    }

    if ( is_dir( $cfg['export_dir'] ) ) {
        $condoms = [ '.htaccess', 'index.html' ];
        foreach ( $condoms as $condom ) {
            if ( ! file_exists( $cfg['export_dir'] . '/' . $condom ) ) {
                $file = fopen( $cfg['export_dir'] . '/' . $condom, 'w' );
                if ( $file ) {
                    $contents = ( $condom === 'index.html' ) ? '' : "Deny from all\n";
                    fwrite( $file, $contents );
                    fclose( $file );
                    $fxbackup_msg[] = sprintf( __( 'File <strong>%s</strong> was created', 'fx-backup' ), esc_html( $condom ) );
                } else {
                    $fxbackup_msg[] = sprintf( __( 'File <strong>%s</strong> was not created. Check permissions!', 'fx-backup' ), esc_html( $condom ) );
                }
            } else {
                $fxbackup_msg[] = sprintf( __( 'File <strong>%s</strong> is available', 'fx-backup' ), esc_html( $condom ) );
            }
        }
    }
} else {
    $fxbackup_msg[] = __( 'Specify the folder where the backups will be stored', 'fx-backup' );
}

$period  = (int) $cfg['period'];
$speriod = 86400;
$severy  = 1;
if ( $period > 0 && $period % 2592000 === 0 ) {
    $speriod = 2592000;
    $severy  = intdiv( $period, 2592000 );
} elseif ( $period > 0 && $period % 604800 === 0 ) {
    $speriod = 604800;
    $severy  = intdiv( $period, 604800 );
} elseif ( $period > 0 && $period % 86400 === 0 ) {
    $speriod = 86400;
    $severy  = intdiv( $period, 86400 );
} elseif ( $period > 0 && $period % 3600 === 0 ) {
    $speriod = 3600;
    $severy  = intdiv( $period, 3600 );
}
?>

<?php
fxbackup_render_page_open( __( 'FX Backup', 'fx-backup' ) );
fxbackup_render_stats_cards( $cfg['export_dir'] ?? '' );
?>

    <div id="poststuff">
        <div class="postbox">
            <h3><?php _e( 'Welcome', 'fx-backup' ); ?></h3>
            <div class="inside">
                <p><?php _e( '<strong>FX Backup</strong> is a complete ClassicPress solution for database backup operations. You can create backups of your ClassicPress database. Backups can be restored from Backup Manager or used for easy migration.', 'fx-backup' ); ?></p>
                <p>
                    <a href="http://getbutterfly.com/wordpress-plugins/fxbackup/"><?php _e( 'Plugin Home', 'fx-backup' ); ?></a>
                    <br><?php _e( 'For support, feature requests and bug reporting, visit the <a href="http://getbutterfly.com/wordpress-plugins/fxbackup/" rel="external">official website</a>.', 'fx-backup' ); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="postbox-container" style="width: 100%">
        <div class="metabox-holder">
            <div class="postbox">
                <h3 class="hndle"><span><?php _e( 'Diagnostics', 'fx-backup' ); ?></span></h3>
                <div class="inside">
                    <?php if ( ! empty( $fxbackup_msg ) ) { ?>
                        <p><?php echo implode( '<br>', $fxbackup_msg ); ?></p>
                    <?php } ?>
                    <?php
                    $scheduledtime = wp_next_scheduled( 'do_fx_backup' );
                    ?>
                    <div class="fxbackup-diagnostics-meta">
                        <?php
                        echo esc_html__( 'Plugin version', 'fx-backup' ) . ': <strong>' . esc_html( FX_BACKUP_VERSION ) . '</strong><br>';
                        echo esc_html__( 'Next scheduled backup', 'fx-backup' ) . ': <strong>';
                        echo $scheduledtime !== false ? esc_html( wp_date( 'F j, Y, H:i:s', $scheduledtime ) ) : esc_html__( 'Not scheduled', 'fx-backup' );
                        echo '</strong><br>';
                        echo esc_html__( 'Server time', 'fx-backup' ) . ': <strong>' . esc_html( wp_date( 'F j, Y, H:i:s' ) ) . '</strong>';
                        if ( ! empty( $cfg['active'] ) && $scheduledtime !== false ) {
                            echo '<br>' . esc_html__( 'Scheduling is active.', 'fx-backup' );
                        }
                        ?>
                    </div>
                    <?php fxbackup_render_environment_badges(); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="clear"></div>

    <div class="postbox-container" style="width: 100%">
        <div class="metabox-holder">
            <div class="postbox">
                <h3 class="hndle"><span><?php _e( 'Plugin Settings', 'fx-backup' ); ?></span></h3>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field( 'fxbackup_options' ); ?>
                        <h4><?php _e( 'General Settings', 'fx-backup' ); ?></h4>
                        <p>
                            <input type="text" name="export_dir" id="export_dir" value="<?php echo esc_attr( $cfg['export_dir'] ); ?>" class="regular-text">
                            <label for="export_dir"><?php _e( 'Backup directory', 'fx-backup' ); ?></label>
                            <br><small><?php _e( 'All your backups will be saved here. Default is', 'fx-backup' ); ?> <?php echo esc_html( WP_CONTENT_DIR . '/fxbackups' ); ?></small>
                        </p>
                        <p>
                            <input type="email" name="smart_email" id="smart_email" value="<?php echo esc_attr( $cfg['smart_email'] ); ?>" class="regular-text">
                            <label for="smart_email"><?php _e( 'Notification email', 'fx-backup' ); ?></label>
                            <br><small><?php _e( 'You will receive notification messages at this address.', 'fx-backup' ); ?></small>
                        </p>
                        <p>
                            <?php
                            $none_selected = ( $cfg['compression'] === 'none' ) ? 'selected' : '';
                            $gz_selected   = ( $cfg['compression'] === 'gz' ) ? 'selected' : '';
                            ?>
                            <select name="compression" id="compression">
                                <option value="none" <?php echo $none_selected; ?>><?php _e( 'None', 'fx-backup' ); ?></option>
                                <?php
                                if ( function_exists( 'gzopen' ) ) {
                                    ?>
                                    <option value="gz" <?php echo $gz_selected; ?>><?php _e( 'GZIP', 'fx-backup' ); ?></option> <?php } ?>
                            </select>
                            <?php if ( function_exists( 'gzopen' ) ) { ?>
                                <select name="gzip_lvl">
                                    <?php
                                    for ( $i = 1; $i <= 9; $i++ ) {
                                        $selected = ( (int) $cfg['gzip_lvl'] === $i ) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo esc_attr( (string) $i ); ?>" <?php echo $selected; ?>><?php echo esc_html( sprintf( __( 'Use GZIP compression level %d', 'fx-backup' ), $i ) ); ?></option>
                                    <?php } ?>
                                </select>
                            <?php } ?>
                        </p>
                        <p>
                            <input type="checkbox" name="active" value="1" <?php checked( ! empty( $cfg['active'] ) ); ?>> <?php _e( 'Activate backup schedule', 'fx-backup' ); ?><br>
                            <input type="checkbox" name="send_attachment" value="1"<?php checked( ! empty( $cfg['send_attachment'] ) ); ?> /> <?php _e( 'Send backup as attachment', 'fx-backup' ); ?><br>
                            <input type="checkbox" name="backup_files" value="1"<?php checked( ! empty( $cfg['backup_files'] ) ); ?> /> <?php _e( 'Include file backup (wp-content and site root files)', 'fx-backup' ); ?>
                            <br><small><?php _e( 'Creates a .tar.gz with wp-content (excluding this backup folder) and root files such as wp-config.php. Needs tar and enough disk space. Copy backups offsite for production sites.', 'fx-backup' ); ?></small><br>
                            <?php
                            list($hours, $minutes, $seconds) = explode( '-', wp_date( 'H-i-s', (int) $cfg['schedule'] ) );
                            $times                           = [ 'hours', 'minutes', 'seconds' ];
                            $periods                         = [
                                3600    => __( 'Hour(s)', 'fx-backup' ),
                                86400   => __( 'Day(s)', 'fx-backup' ),
                                604800  => __( 'Week(s)', 'fx-backup' ),
                                2592000 => __( 'Month(s)', 'fx-backup' ),
                            ];
                            ?>
                            <strong><?php _e( 'Run every ', 'fx-backup' ); ?></strong>
                            <select name="severy">
                                <?php
                                for ( $i = 1; $i <= 12; $i++ ) {
                                    $selected = ( $severy === $i ) ? 'selected' : '';
                                    ?>
                                    <option <?php echo $selected; ?>><?php echo esc_html( (string) $i ); ?></option>
                                <?php } ?>
                            </select>
                            <select name="speriod">
                                <?php
                                foreach ( $periods as $period_val => $display ) {
                                    $selected = ( $period_val === $speriod ) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo esc_attr( (string) $period_val ); ?>" <?php echo $selected; ?>><?php echo esc_html( $display ); ?></option>
                                <?php } ?>
                            </select>

                            <strong><?php _e( 'When', 'fx-backup' ); ?></strong>
                            <?php
                            foreach ( $times as $time ) {
                                $max = $time === 'hours' ? 24 : 60;
                                ?>
                                : <select name="<?php echo esc_attr( $time ); ?>">
                                    <?php
                                    for ( $i = 0; $i < $max; $i++ ) {
                                        $selected = ( ${$time} == $i ) ? 'selected' : '';
                                        ?>
                                        <option <?php echo $selected; ?>><?php echo esc_html( (string) $i ); ?></option>
                                    <?php } ?>
                                </select>
                            <?php } ?>
                        </p>
                        <p>
                            <select name="rotate">
                                <?php
                                for ( $i = -1; $i <= 90; $i++ ) {
                                    switch ( $i ) {
                                        case -1:
                                            $display = __( 'Do not remove previous backups', 'fx-backup' );
                                            break;
                                        case 0:
                                            $display = __( 'Remove all previous backups', 'fx-backup' );
                                            break;
                                        default:
                                            $display = __( 'Remove backups older than ', 'fx-backup' ) . $i . ( $i > 1 ? __( ' days', 'fx-backup' ) : __( ' day', 'fx-backup' ) );
                                            break;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( (int) $cfg['rotate'], $i ); ?>><?php echo esc_html( $display ); ?></option>
                                <?php } ?>
                            </select>
                            <br><small><?php _e( 'The removal of old backups occurs during new backup generation.', 'fx-backup' ); ?></small>
                        </p>

                        <p>
                            <input type="submit" name="fxbackupsubmit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'fx-backup' ); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php fxbackup_render_page_close(); ?>
