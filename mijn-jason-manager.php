<?php
/**
 * Plugin Name: Mijn Jason Manager
 * Description: Beheer welke menu-items zichtbaar zijn op de WooCommerce Mijn account pagina en overschrijf de inhoud per tab.
 * Version: 2.3
 * Author: Chantor Pascal van Beek
 * Author URI: https://runiversity.nl
 * Plugin URI: https://runiversity.nl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check of WooCommerce actief is.
 */
function kvj_my_account_wc_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ),
        true
    );
}

/**
 * Filter het "Mijn account" menu op basis van instellingen.
 */
function kvj_filter_woocommerce_account_menu_items( $items ) {
    if ( ! kvj_my_account_wc_active() ) {
        return $items;
    }

    // Dashboard verbergen
    if ( get_option( 'kvj_hide_dashboard' ) ) {
        unset( $items['dashboard'] );
    }

    // Downloads verbergen
    if ( get_option( 'kvj_hide_downloads' ) ) {
        unset( $items['downloads'] );
    }

    // Adres / Adressen verbergen
    if ( get_option( 'kvj_hide_edit_address' ) ) {
        unset( $items['edit-address'] );
    }

    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'kvj_filter_woocommerce_account_menu_items', 99 );

/**
 * Overschrijf de inhoud van de Mijn account tabs indien HTML is ingevuld.
 */
function kvj_override_my_account_content() {
    if ( ! kvj_my_account_wc_active() ) {
        return;
    }

    if ( ! function_exists( 'WC' ) ) {
        return;
    }

    $endpoint = WC()->query->get_current_endpoint();

    // Lege endpoint = dashboard
    if ( empty( $endpoint ) ) {
        $endpoint = 'dashboard';
    }

    // Alleen voor de drie die we in de settings aanbieden
    $supported_endpoints = array( 'dashboard', 'downloads', 'edit-address' );

    if ( ! in_array( $endpoint, $supported_endpoints, true ) ) {
        return;
    }

    $option_key  = 'kvj_html_' . str_replace( '-', '_', $endpoint );
    $custom_html = get_option( $option_key );

    if ( empty( $custom_html ) ) {
        // Geen maatwerk inhoud → laat Woo gewoon zijn ding doen
        return;
    }

    // Huidige gebruiker ophalen
    $first_name = '';
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        if ( $user && $user->ID ) {
            $first_name = $user->first_name ? $user->first_name : $user->display_name;
        }
    }

    // Placeholder vervangen in de custom HTML
    $custom_html = str_replace(
        array( '{{first_name}}', '{first_name}' ),
        esc_html( $first_name ),
        $custom_html
    );

    // Standaard WooCommerce account content uitschakelen
    if ( function_exists( 'remove_action' ) ) {
        remove_action( 'woocommerce_account_content', 'woocommerce_account_content', 10 );
    }

    // Onze eigen inhoud tonen
    echo wp_kses_post( $custom_html );
}
add_action( 'woocommerce_account_content', 'kvj_override_my_account_content', 1 );

/**
 * Verberg weergavenaam veld op accountgegevens pagina.
 */
function kvj_hide_account_display_name_field() {
    if ( ! kvj_my_account_wc_active() ) {
        return;
    }

    if ( ! is_account_page() || ! is_user_logged_in() ) {
        return;
    }

    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-account' ) ) {
        return;
    }

    if ( ! get_option( 'kvj_hide_account_display_name' ) ) {
        return;
    }

    ?>
    <style>
        p.woocommerce-form-row label[for="account_display_name"] {
            display: none !important;
        }

        #account_display_name,
        #account_display_name_description {
            display: none !important;
        }

        p.woocommerce-form-row:has(#account_display_name) {
            display: none !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var field = document.getElementById('account_display_name');
            if (field) {
                var row = field.closest('p.woocommerce-form-row');
                if (row) {
                    row.style.display = 'none';
                }
            }
        });
    </script>
    <?php
}
add_action( 'wp_head', 'kvj_hide_account_display_name_field' );

/**
 * Admin: instellingen registreren.
 */
function kvj_my_account_register_settings() {

    // Checkboxen (verbergen)
    register_setting(
        'kvj_my_account_settings',
        'kvj_hide_dashboard',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        )
    );

    register_setting(
        'kvj_my_account_settings',
        'kvj_hide_downloads',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        )
    );

    register_setting(
        'kvj_my_account_settings',
        'kvj_hide_edit_address',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        )
    );

    register_setting(
        'kvj_my_account_settings',
        'kvj_hide_account_display_name',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        )
    );

    // HTML-velden (inhoud overschrijven)
    register_setting(
        'kvj_my_account_settings',
        'kvj_html_dashboard',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        )
    );

    register_setting(
        'kvj_my_account_settings',
        'kvj_html_downloads',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        )
    );

    register_setting(
        'kvj_my_account_settings',
        'kvj_html_edit_address',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        )
    );
}
add_action( 'admin_init', 'kvj_my_account_register_settings' );

/**
 * Admin: submenu onder WooCommerce.
 */
function kvj_my_account_add_settings_page() {
    if ( ! kvj_my_account_wc_active() ) {
        return;
    }

    add_submenu_page(
        'woocommerce',
        __( 'Mijn account instellingen', 'kvj' ),
        __( 'Mijn Jason Manager', 'kvj' ),
        'manage_woocommerce',
        'kvj-my-account-settings',
        'kvj_my_account_render_settings_page'
    );
}
add_action( 'admin_menu', 'kvj_my_account_add_settings_page' );

/**
 * Admin: HTML van de instellingenpagina.
 */
function kvj_my_account_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Mijn account instellingen</h1>
        <p>Hier bepaal je welke tabs zichtbaar zijn en kun je de inhoud per tab overschrijven met eigen HTML.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'kvj_my_account_settings' ); ?>

            <table class="form-table" role="presentation">
                <tbody>

                <!-- Dashboard -->
                <tr>
                    <th scope="row"><h2>Dashboard</h2></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kvj_hide_dashboard" value="1" <?php checked( get_option( 'kvj_hide_dashboard' ), 1 ); ?> />
                            Verberg dashboard in het Mijn account menu
                        </label>
                        <p class="description">
                            Laat leeg als je de standaard WooCommerce dashboard-inhoud wilt tonen.
                        </p>
                        <textarea name="kvj_html_dashboard" rows="6" style="width:100%;max-width:800px;"><?php echo esc_textarea( get_option( 'kvj_html_dashboard' ) ); ?></textarea>
                        <p class="description">
                            Vul hier HTML in om de dashboard-inhoud volledig te vervangen (bijvoorbeeld een welkomsttekst, knoppen, links, etc.).
                        </p>
                    </td>
                </tr>

                <!-- Downloads -->
                <tr>
                    <th scope="row"><h2>Downloads</h2></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kvj_hide_downloads" value="1" <?php checked( get_option( 'kvj_hide_downloads' ), 1 ); ?> />
                            Verberg downloads in het Mijn account menu
                        </label>
                        <p class="description">
                            Laat leeg als je de standaard WooCommerce downloads-inhoud wilt tonen.
                        </p>
                        <textarea name="kvj_html_downloads" rows="6" style="width:100%;max-width:800px;"><?php echo esc_textarea( get_option( 'kvj_html_downloads' ) ); ?></textarea>
                        <p class="description">
                            Vul hier HTML in om de downloads-inhoud volledig te vervangen.
                        </p>
                    </td>
                </tr>

                <!-- Adres / Adressen -->
                <tr>
                    <th scope="row"><h2>Adres / Adressen</h2></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kvj_hide_edit_address" value="1" <?php checked( get_option( 'kvj_hide_edit_address' ), 1 ); ?> />
                            Verberg adres(sen) in het Mijn account menu
                        </label>
                        <p class="description">
                            Laat leeg als je de standaard WooCommerce adres-inhoud wilt tonen.
                        </p>
                        <textarea name="kvj_html_edit_address" rows="6" style="width:100%;max-width:800px;"><?php echo esc_textarea( get_option( 'kvj_html_edit_address' ) ); ?></textarea>
                        <p class="description">
                            Vul hier HTML in om de adres-inhoud volledig te vervangen.
                        </p>
                    </td>
                </tr>

                <!-- Accountgegevens -->
                <tr>
                    <th scope="row"><h2>Accountgegevens</h2></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kvj_hide_account_display_name" value="1" <?php checked( get_option( 'kvj_hide_account_display_name' ), 1 ); ?> />
                            Verberg het veld "Weergavenaam" op de pagina accountgegevens
                        </label>
                        <p class="description">
                            Hiermee wordt alleen het veld "Weergavenaam" verborgen op de WooCommerce pagina accountgegevens bewerken.
                        </p>
                    </td>
                </tr>

                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
