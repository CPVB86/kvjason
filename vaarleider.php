<?php
/**
 * Plugin Name: Vaarleider
 * Plugin URI: https://leden.kvjasonarnhem.nl/how-to-use/
 * Description: Beheer per week een vaarleider en toon deze via shortcode op de frontend.
 * Version: 1.1.2
 * Author: Chantor Pascal van Beek
 * Author URI: https://runiversity.nl
 * License: GPL2+
 * Text Domain: vaarleider
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Vaarleider_Plugin {
    const OPTION_KEY = 'vaarleider_assignments';
    const MENU_SLUG  = 'vaarleider';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_post_vaarleider_save', array( $this, 'handle_save' ) );
        add_action( 'admin_post_vaarleider_delete', array( $this, 'handle_delete' ) );
        add_shortcode( 'vaarleider', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_styles' ) );
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'Vaarleider', 'vaarleider' ),
            __( 'Vaarleider', 'vaarleider' ),
            'list_users',
            self::MENU_SLUG,
            array( $this, 'render_admin_page' ),
            $this->get_menu_icon_svg(),
            58
        );
    }

    public function register_frontend_styles() {
        wp_register_style( 'vaarleider-frontend', false, array(), '1.1.2' );

        $css = '
            .vaarleider-grid {
                display:grid;
                gap:20px;
                grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            }
            .vaarleider-card {
                border:1px solid rgba(0,0,0,.08);
                border-radius:16px;
                padding:20px;
                background:#fff;
                box-shadow:0 8px 24px rgba(0,0,0,.06);
                text-align:center;
            }
            .vaarleider-card h3,
            .vaarleider-card p {
                margin:0 0 12px;
            }
            .vaarleider-card p:last-child {
                margin-bottom:0;
            }
            .vaarleider-avatar img {
                width:96px;
                height:96px;
                border-radius:6px;
                object-fit:cover;
                display:block;
                margin:0 auto 14px;
            }
            .vaarleider-date {
                font-size:14px;
                opacity:.75;
            }
            .vaarleider-empty {
                padding:16px 18px;
                border-radius:12px;
                background:#f7f7f7;
            }
        ';

        wp_add_inline_style( 'vaarleider-frontend', $css );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'list_users' ) ) {
            wp_die( esc_html__( 'Je hebt geen toestemming om deze pagina te bekijken.', 'vaarleider' ) );
        }

        $users           = $this->get_vaarleider_users();
        $assignments     = $this->get_assignments();
        $message         = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
        $edit_week_start = isset( $_GET['edit_week'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_week'] ) ) : '';
        $editing         = ! empty( $edit_week_start ) && isset( $assignments[ $edit_week_start ] );
        $edit_user_id    = $editing ? absint( $assignments[ $edit_week_start ] ) : 0;
        $edit_date       = $editing ? $edit_week_start : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Vaarleider', 'vaarleider' ); ?></h1>

            <?php if ( 'saved' === $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Vaarleider opgeslagen.', 'vaarleider' ); ?></p></div>
            <?php elseif ( 'updated' === $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Planning bijgewerkt.', 'vaarleider' ); ?></p></div>
            <?php elseif ( 'deleted' === $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Planning verwijderd.', 'vaarleider' ); ?></p></div>
            <?php elseif ( 'invalid' === $message ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Kies een geldige datum en gebruiker.', 'vaarleider' ); ?></p></div>
            <?php endif; ?>

            <div style="max-width:760px;background:#fff;padding:24px;border:1px solid #ddd;border-radius:12px;margin:20px 0;">
                <h2 style="margin-top:0;"><?php echo esc_html( $editing ? __( 'Week bewerken', 'vaarleider' ) : __( 'Nieuwe week toevoegen', 'vaarleider' ) ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'vaarleider_save_assignment', 'vaarleider_nonce' ); ?>
                    <input type="hidden" name="action" value="vaarleider_save">
                    <input type="hidden" name="original_week_start" value="<?php echo esc_attr( $editing ? $edit_week_start : '' ); ?>">

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="vaarleider_date"><?php esc_html_e( 'Dag', 'vaarleider' ); ?></label></th>
                                <td>
                                    <input type="date" id="vaarleider_date" name="vaarleider_date" value="<?php echo esc_attr( $edit_date ); ?>" required>
                                    <p class="description"><?php esc_html_e( 'Je mag elke dag van de week kiezen. De plugin koppelt dit automatisch aan de juiste week.', 'vaarleider' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="vaarleider_user"><?php esc_html_e( 'Gebruiker', 'vaarleider' ); ?></label></th>
                                <td>
                                    <select id="vaarleider_user" name="vaarleider_user" required>
                                        <option value=""><?php esc_html_e( 'Selecteer een vaarleider', 'vaarleider' ); ?></option>
                                        <?php foreach ( $users as $user ) : ?>
                                            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $edit_user_id, $user->ID ); ?>><?php echo esc_html( $this->get_user_full_name( $user ) ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ( empty( $users ) ) : ?>
                                        <p class="description" style="color:#b32d2e;"><?php esc_html_e( 'Er zijn geen gebruikers gevonden met de rol vaarleider.', 'vaarleider' ); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button( $editing ? __( 'Bijwerken', 'vaarleider' ) : __( 'Opslaan', 'vaarleider' ) ); ?>

                    <?php if ( $editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Annuleren', 'vaarleider' ); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <h2><?php esc_html_e( 'Geplande weken', 'vaarleider' ); ?></h2>
            <?php if ( empty( $assignments ) ) : ?>
                <p><?php esc_html_e( 'Er zijn nog geen weken toegevoegd.', 'vaarleider' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Weeknr.', 'vaarleider' ); ?></th>
                            <th><?php esc_html_e( 'Week vanaf', 'vaarleider' ); ?></th>
                            <th><?php esc_html_e( 'Vaarleider', 'vaarleider' ); ?></th>
                            <th><?php esc_html_e( 'Actie', 'vaarleider' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $assignments as $week_start => $user_id ) :
                            $user = get_user_by( 'id', absint( $user_id ) );
                            if ( ! $user ) {
                                continue;
                            }

                            $edit_url   = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&edit_week=' . rawurlencode( $week_start ) );
                            $delete_url = wp_nonce_url(
                                admin_url( 'admin-post.php?action=vaarleider_delete&week_start=' . rawurlencode( $week_start ) ),
                                'vaarleider_delete_' . $week_start,
                                'vaarleider_nonce'
                            );
                            ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'W', strtotime( $week_start ) ) ); ?></td>
                                <td><?php echo esc_html( $this->format_date( $week_start ) ); ?></td>
                                <td><?php echo esc_html( $this->get_user_full_name( $user ) ); ?></td>
                                <td>
                                    <a class="button button-secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Bewerken', 'vaarleider' ); ?></a>
                                    <a class="button button-secondary" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Weet je zeker dat je deze planning wilt verwijderen?', 'vaarleider' ) ); ?>');"><?php esc_html_e( 'Verwijderen', 'vaarleider' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top:24px;">
                <h2><?php esc_html_e( 'Shortcodes', 'vaarleider' ); ?></h2>
                <p><code>[vaarleider week="now"]</code> — <?php esc_html_e( 'toont de vaarleider van deze week.', 'vaarleider' ); ?></p>
                <p><code>[vaarleider week="next"]</code> — <?php esc_html_e( 'toont de vaarleider van volgende week.', 'vaarleider' ); ?></p>
                <p><code>[vaarleider week="all"]</code> — <?php esc_html_e( 'toont alle huidige en toekomstige weken.', 'vaarleider' ); ?></p>
            </div>
        </div>
        <?php
    }

    public function handle_save() {
        if ( ! current_user_can( 'list_users' ) ) {
            wp_die( esc_html__( 'Geen toestemming.', 'vaarleider' ) );
        }

        check_admin_referer( 'vaarleider_save_assignment', 'vaarleider_nonce' );

        $date                = isset( $_POST['vaarleider_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vaarleider_date'] ) ) : '';
        $user_id             = isset( $_POST['vaarleider_user'] ) ? absint( $_POST['vaarleider_user'] ) : 0;
        $original_week_start = isset( $_POST['original_week_start'] ) ? sanitize_text_field( wp_unslash( $_POST['original_week_start'] ) ) : '';

        if ( empty( $date ) || empty( $user_id ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&message=invalid' ) );
            exit;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! $this->user_has_vaarleider_role( $user ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&message=invalid' ) );
            exit;
        }

        $week_start = $this->get_week_start_from_date( $date );
        if ( ! $week_start ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&message=invalid' ) );
            exit;
        }

        $assignments = $this->get_assignments();
        $is_edit     = ! empty( $original_week_start );

        if ( $is_edit && isset( $assignments[ $original_week_start ] ) && $original_week_start !== $week_start ) {
            unset( $assignments[ $original_week_start ] );
        }

        $assignments[ $week_start ] = $user_id;
        ksort( $assignments );

        update_option( self::OPTION_KEY, $assignments, false );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&message=' . ( $is_edit ? 'updated' : 'saved' ) ) );
        exit;
    }

    public function handle_delete() {
        if ( ! current_user_can( 'list_users' ) ) {
            wp_die( esc_html__( 'Geen toestemming.', 'vaarleider' ) );
        }

        $week_start = isset( $_GET['week_start'] ) ? sanitize_text_field( wp_unslash( $_GET['week_start'] ) ) : '';
        check_admin_referer( 'vaarleider_delete_' . $week_start, 'vaarleider_nonce' );

        $assignments = $this->get_assignments();

        if ( isset( $assignments[ $week_start ] ) ) {
            unset( $assignments[ $week_start ] );
            update_option( self::OPTION_KEY, $assignments, false );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&message=deleted' ) );
        exit;
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'week' => 'now',
            ),
            $atts,
            'vaarleider'
        );

        $week = strtolower( trim( $atts['week'] ) );
        wp_enqueue_style( 'vaarleider-frontend' );

        if ( 'all' === $week ) {
            return $this->render_all_weeks();
        }

        $target_week = $this->get_current_week_start();

        if ( 'next' === $week ) {
            $target_week = wp_date( 'Y-m-d', strtotime( $target_week . ' +7 days' ) );
        }

        return $this->render_single_week( $target_week );
    }

    private function render_single_week( $week_start ) {
        $assignments = $this->get_assignments();

        ob_start();
        echo '<div class="vaarleider-wrapper">';

        if ( empty( $assignments[ $week_start ] ) ) {
            echo '<div class="vaarleider-empty">' . esc_html__( 'Er is nog geen vaarleider ingepland voor deze week.', 'vaarleider' ) . '</div>';
            echo '</div>';
            return ob_get_clean();
        }

        $user = get_user_by( 'id', absint( $assignments[ $week_start ] ) );
        if ( ! $user ) {
            echo '<div class="vaarleider-empty">' . esc_html__( 'De gekoppelde gebruiker kon niet worden gevonden.', 'vaarleider' ) . '</div>';
            echo '</div>';
            return ob_get_clean();
        }

        echo '<div class="vaarleider-grid">';
        echo $this->get_user_card_html( $user, $week_start, false );
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    private function render_all_weeks() {
        $assignments = $this->get_assignments();
        $current     = $this->get_current_week_start();
        $items       = array();

        foreach ( $assignments as $week_start => $user_id ) {
            if ( $week_start < $current ) {
                continue;
            }

            $user = get_user_by( 'id', absint( $user_id ) );
            if ( ! $user ) {
                continue;
            }

            $items[] = $this->get_user_card_html( $user, $week_start, true );
        }

        ob_start();
        echo '<div class="vaarleider-wrapper">';

        if ( empty( $items ) ) {
            echo '<div class="vaarleider-empty">' . esc_html__( 'Er zijn geen huidige of toekomstige weken gevonden.', 'vaarleider' ) . '</div>';
            echo '</div>';
            return ob_get_clean();
        }

        echo '<div class="vaarleider-grid">' . implode( '', $items ) . '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    private function get_user_card_html( $user, $week_start, $show_date = true ) {
        $name   = $this->get_user_full_name( $user );
        $avatar = $this->get_user_avatar_html( $user, $name );

        ob_start();
        ?>
        <div class="vaarleider-card">
            <div class="vaarleider-avatar"><?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h3><?php echo esc_html( $name ); ?></h3>
            <?php if ( $show_date ) : ?>
                <p class="vaarleider-date"><?php echo esc_html( $this->format_date( $week_start ) ); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_vaarleider_users() {
        return get_users(
            array(
                'role'    => 'vaarleider',
                'orderby' => 'display_name',
                'order'   => 'ASC',
            )
        );
    }

    private function user_has_vaarleider_role( $user ) {
        if ( empty( $user ) || empty( $user->roles ) || ! is_array( $user->roles ) ) {
            return false;
        }

        return in_array( 'vaarleider', array_map( 'strtolower', (array) $user->roles ), true );
    }

    private function sanitize_image_url( $url ) {
        if ( ! $url ) {
            return '';
        }

        if ( 0 === strpos( $url, 'data:image/svg+xml;base64,' ) ) {
            return esc_attr( $url );
        }

        return esc_url( $url );
    }

    private function get_initials_from_user( $user ) {
        $parts = array();

        if ( ! empty( $user->first_name ) ) {
            $parts[] = $user->first_name;
        }

        if ( ! empty( $user->last_name ) ) {
            $parts[] = $user->last_name;
        }

        if ( empty( $parts ) && ! empty( $user->display_name ) ) {
            $parts = preg_split( '/\s+/', trim( $user->display_name ) );
        }

        if ( empty( $parts ) && ! empty( $user->user_login ) ) {
            $parts = preg_split( '/[\s._-]+/', trim( $user->user_login ) );
        }

        $initials = '';
        foreach ( $parts as $part ) {
            if ( '' !== $part ) {
                $initials .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
            }
            if ( strlen( $initials ) >= 2 ) {
                break;
            }
        }

        if ( '' === $initials && ! empty( $user->user_email ) ) {
            $initials = strtoupper( substr( $user->user_email, 0, 1 ) );
        }

        return strtoupper( $initials ? $initials : 'U' );
    }

    private function get_avatar_colors( $seed ) {
        $palette = array(
            array( '#1D4ED8', '#DBEAFE' ),
            array( '#7C3AED', '#EDE9FE' ),
            array( '#0F766E', '#CCFBF1' ),
            array( '#BE123C', '#FFE4E6' ),
            array( '#B45309', '#FEF3C7' ),
            array( '#166534', '#DCFCE7' ),
            array( '#374151', '#F3F4F6' ),
            array( '#C2410C', '#FFEDD5' ),
        );

        $index = abs( crc32( (string) $seed ) ) % count( $palette );
        return $palette[ $index ];
    }

    private function get_generated_avatar_url( $user_id, $size = 512 ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return '';
        }

        $initials = $this->get_initials_from_user( $user );
        list( $text_color, $bg_color ) = $this->get_avatar_colors( $user->user_email . '|' . $user->user_login . '|' . $user->ID );

        $font_size = max( 120, (int) round( $size * 0.28 ) );
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d"><rect width="100%%" height="100%%" rx="32" ry="32" fill="%2$s"/><text x="50%%" y="50%%" dy=".1em" text-anchor="middle" dominant-baseline="middle" font-family="Arial, Helvetica, sans-serif" font-size="%3$d" font-weight="700" fill="%4$s">%5$s</text></svg>',
            (int) $size,
            esc_attr( $bg_color ),
            (int) $font_size,
            esc_attr( $text_color ),
            esc_html( $initials )
        );

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    private function get_user_avatar_html( $user, $name ) {
        $attachment_id = (int) get_user_meta( $user->ID, 'kvjason_profile_photo_id', true );
        $url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, array( 160, 160 ) ) : $this->get_generated_avatar_url( $user->ID, 512 );

        if ( ! $url ) {
            return '';
        }

        return sprintf(
            '<img alt="%s" src="%s" class="avatar avatar-160 photo vaarleider-avatar-image" height="96" width="96" loading="lazy" decoding="async" style="object-fit:cover;border-radius:6px;" />',
            esc_attr( $name ),
            $this->sanitize_image_url( $url )
        );
    }

    private function get_assignments() {
        $assignments = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $assignments ) ) {
            return array();
        }

        ksort( $assignments );
        return $assignments;
    }

    private function get_week_start_from_date( $date ) {
        $timestamp = strtotime( $date );
        if ( ! $timestamp ) {
            return false;
        }

        $day_of_week = (int) wp_date( 'N', $timestamp );
        $monday      = strtotime( '-' . ( $day_of_week - 1 ) . ' days', $timestamp );

        return wp_date( 'Y-m-d', $monday );
    }

    private function get_current_week_start() {
        $timestamp   = current_time( 'timestamp' );
        $day_of_week = (int) wp_date( 'N', $timestamp );
        $monday      = strtotime( '-' . ( $day_of_week - 1 ) . ' days', $timestamp );

        return wp_date( 'Y-m-d', $monday );
    }

    private function get_user_full_name( $user ) {
        $first_name = get_user_meta( $user->ID, 'first_name', true );
        $last_name  = get_user_meta( $user->ID, 'last_name', true );
        $full_name  = trim( $first_name . ' ' . $last_name );

        return $full_name ? $full_name : $user->display_name;
    }

    private function format_date( $date ) {
        return wp_date( 'd/m/Y', strtotime( $date ) );
    }

    private function get_menu_icon_svg() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><text x="12" y="17" text-anchor="middle" font-size="16" fill="black">&#9875;</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
}

new Vaarleider_Plugin();
