<?php
/*
Plugin Name: Mijn Vloot
Description: De eigen vloot van KV Jason Arnhem.
Version: 0.1.4
Author: Chantor Pascal van Beek
Text Domain: mijn-vloot
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MijnVloot_Plugin {

    public function __construct() {
        // CPT
        add_action( 'init', [ $this, 'register_post_type' ] );

        // Metaboxes
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_mijnvloot_kayak', [ $this, 'save_meta_boxes' ] );

        // Admin menu voor export/import
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

        // Admin post actions voor export/import
        add_action( 'admin_post_mijnvloot_export', [ $this, 'handle_export' ] );
        add_action( 'admin_post_mijnvloot_import', [ $this, 'handle_import' ] );

        // Frontend shortcode
        add_shortcode( 'mijn_vloot', [ $this, 'shortcode_mijn_vloot' ] );

        // Assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_assets' ] );

        // Admin: extra kolom met shortcode
        add_filter( 'manage_mijnvloot_kayak_posts_columns', [ $this, 'add_shortcode_column' ] );
        add_action( 'manage_mijnvloot_kayak_posts_custom_column', [ $this, 'render_shortcode_column' ], 10, 2 );

        // JS voor click-to-copy op de lijstpagina
        add_action( 'admin_footer-edit.php', [ $this, 'add_admin_list_js' ] );
    }

    /* -------------------------------------------------------------------------
     * CPT
     * --------------------------------------------------------------------- */

    public function register_post_type() {
        $labels = [
            'name'               => 'Mijn Vloot',
            'singular_name'      => 'Kayak',
            'add_new'            => 'Nieuwe kayak',
            'add_new_item'       => 'Nieuwe kayak toevoegen',
            'edit_item'          => 'Kayak bewerken',
            'new_item'           => 'Nieuwe kayak',
            'view_item'          => 'Kayak bekijken',
            'search_items'       => 'Kayaks zoeken',
            'not_found'          => 'Geen kayaks gevonden',
            'not_found_in_trash' => 'Geen kayaks in prullenbak',
            'menu_name'          => 'Mijn Vloot',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'menu_position'      => 20,
            'menu_icon'          => plugin_dir_url( __FILE__ ) . 'assets/paddle-outline-xs-y.png',
            'supports'           => [ 'title', 'editor' ],
            'show_in_rest'       => true,
            'capability_type'    => 'post',
        ];

        register_post_type( 'mijnvloot_kayak', $args );
    }

    /* -------------------------------------------------------------------------
     * Admin kolom: shortcode
     * --------------------------------------------------------------------- */

    public function add_shortcode_column( $columns ) {
        $new = [];

        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;

            if ( 'title' === $key ) {
                $new['mijnvloot_shortcode'] = 'Shortcode';
            }
        }

        return $new;
    }

    public function render_shortcode_column( $column, $post_id ) {
        if ( 'mijnvloot_shortcode' !== $column ) {
            return;
        }

        $shortcode = '[mijn_vloot id="' . $post_id . '"]';

        echo '<button type="button"
                     class="button button-small mijnvloot-copy-shortcode"
                     data-shortcode="' . esc_attr( $shortcode ) . '"
                     title="Klik om te kopi&euml;ren">'
                . esc_html( $shortcode ) .
             '</button>';
    }

    public function add_admin_list_js() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-mijnvloot_kayak' !== $screen->id ) {
            return;
        }
        ?>
        <script>
        (function() {
            function copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.top = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                } catch (err) {}
                document.body.removeChild(textarea);
                return Promise.resolve();
            }

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.mijnvloot-copy-shortcode');
                if (!btn) return;

                var shortcode = btn.getAttribute('data-shortcode');
                if (!shortcode) return;

                copyToClipboard(shortcode).then(function() {
                    var originalText = btn.textContent;
                    btn.textContent = 'Gekopieerd!';
                    btn.classList.add('button-primary');

                    setTimeout(function() {
                        btn.textContent = originalText;
                        btn.classList.remove('button-primary');
                    }, 1500);
                });
            });
        })();
        </script>
        <?php
    }

    /* -------------------------------------------------------------------------
     * Metaboxes
     * --------------------------------------------------------------------- */

    public function register_meta_boxes() {
        add_meta_box(
            'mijnvloot_basis',
            'Basisgegevens & geschikt voor',
            [ $this, 'render_basis_metabox' ],
            'mijnvloot_kayak',
            'normal',
            'high'
        );

        add_meta_box(
            'mijnvloot_eigenschappen',
            'Eigenschappen (ratings)',
            [ $this, 'render_eigenschappen_metabox' ],
            'mijnvloot_kayak',
            'normal',
            'default'
        );

        add_meta_box(
            'mijnvloot_technisch',
            'Technische aspecten',
            [ $this, 'render_technisch_metabox' ],
            'mijnvloot_kayak',
            'normal',
            'default'
        );

        add_meta_box(
            'mijnvloot_afbeeldingen',
            'Foto\'s',
            [ $this, 'render_afbeeldingen_metabox' ],
            'mijnvloot_kayak',
            'side',
            'default'
        );
    }

    private function get_meta( $post_id, $key, $default = '' ) {
        $value = get_post_meta( $post_id, $key, true );
        return $value === '' ? $default : $value;
    }

    /* -------------------- Basis + Geschikt voor -------------------- */

    public function render_basis_metabox( $post ) {
        wp_nonce_field( 'mijnvloot_save', 'mijnvloot_nonce' );

        // Basis
        $brand    = $this->get_meta( $post->ID, '_mijnvloot_brand' );
        $color    = $this->get_meta( $post->ID, '_mijnvloot_color' );
        $location = $this->get_meta( $post->ID, '_mijnvloot_location' );

        $pub_brand    = $this->get_meta( $post->ID, '_mijnvloot_public_brand', 0 );
        $pub_color    = $this->get_meta( $post->ID, '_mijnvloot_public_color', 0 );
        $pub_location = $this->get_meta( $post->ID, '_mijnvloot_public_location', 0 );

        // Geschikt voor
        $suitable = $this->get_meta( $post->ID, '_mijnvloot_suitable_for' );
        $hip_room = $this->get_meta( $post->ID, '_mijnvloot_hip_room' );
        $heavy    = $this->get_meta( $post->ID, '_mijnvloot_heavy_people', 0 );

        $pub_suitable = $this->get_meta( $post->ID, '_mijnvloot_public_suitable_for', 0 );
        $pub_hip_room = $this->get_meta( $post->ID, '_mijnvloot_public_hip_room', 0 );
        $pub_heavy    = $this->get_meta( $post->ID, '_mijnvloot_public_heavy', 0 );
        ?>
        <p><em>Naam = titel van het bericht hierboven.</em></p>

        <p>
            <label for="mijnvloot_brand"><strong>Merk</strong></label><br>
            <input type="text" name="mijnvloot_brand" id="mijnvloot_brand"
                   value="<?php echo esc_attr( $brand ); ?>" class="regular-text">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_brand" value="1" <?php checked( $pub_brand, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_color"><strong>Kleur boot</strong></label><br>
            <input type="text" name="mijnvloot_color" id="mijnvloot_color"
                   value="<?php echo esc_attr( $color ); ?>" class="regular-text"
                   placeholder="Bijv. rood / geel / mix">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_color" value="1" <?php checked( $pub_color, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_location"><strong>Locatie</strong></label><br>
            <input type="text" name="mijnvloot_location" id="mijnvloot_location"
                   value="<?php echo esc_attr( $location ); ?>" class="regular-text"
                   placeholder="Bijv. loods, rek 3, club, prive">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_location" value="1" <?php checked( $pub_location, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <hr>

        <h4>Geschikt voor...</h4>

        <p>
            <label for="mijnvloot_suitable_for"><strong>Lengte bereik</strong></label><br>
            <select name="mijnvloot_suitable_for" id="mijnvloot_suitable_for">
                <option value="">&ndash; Selecteer &ndash;</option>
                <option value="short" <?php selected( $suitable, 'short' ); ?>>Korte mensen (&lt; 1,65 m)</option>
                <option value="tall"  <?php selected( $suitable, 'tall' );  ?>>Lange mensen (&gt; 1,86 m)</option>
            </select>
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_suitable_for" value="1" <?php checked( $pub_suitable, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_hip_room"><strong>Heupruimte</strong></label><br>
            <select name="mijnvloot_hip_room" id="mijnvloot_hip_room">
                <option value="">&ndash; Selecteer &ndash;</option>
                <option value="S" <?php selected( $hip_room, 'S' ); ?>>S</option>
                <option value="M" <?php selected( $hip_room, 'M' ); ?>>M</option>
                <option value="L" <?php selected( $hip_room, 'L' ); ?>>L</option>
            </select>
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_hip_room" value="1" <?php checked( $pub_hip_room, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label>
                <input type="checkbox" name="mijnvloot_heavy_people" value="1" <?php checked( $heavy, 1 ); ?>>
                Geschikt voor zwaardere mensen
            </label>
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_heavy" value="1" <?php checked( $pub_heavy, 1 ); ?>>
                Tonen op website
            </label>
        </p>
        <?php
    }

    /* -------------------- Eigenschappen (ratings) -------------------- */

    private function rating_field( $name, $label, $value ) {
        ?>
        <p class="mijnvloot-rating-field">
            <strong><?php echo esc_html( $label ); ?></strong><br>
            <select name="<?php echo esc_attr( $name ); ?>" class="mijnvloot-rating-select">
                <option value="">&ndash; Geen rating &ndash;</option>
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <option value="<?php echo $i; ?>" <?php selected( (int) $value, $i ); ?>>
                        <?php echo $i; ?> <?php echo str_repeat( '&#9733;', $i ); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </p>
        <?php
    }

public function render_eigenschappen_metabox( $post ) {
    $stability_primary   = $this->get_meta( $post->ID, '_mijnvloot_stability_primary' );
    $stability_secondary = $this->get_meta( $post->ID, '_mijnvloot_stability_secondary' );
    $mobility            = $this->get_meta( $post->ID, '_mijnvloot_maneuverability' );
    $speed               = $this->get_meta( $post->ID, '_mijnvloot_speed' );
    $tracking            = $this->get_meta( $post->ID, '_mijnvloot_tracking' );
    ?>
    <p>Gebruik 1-5 paddel-iconen (we slaan het op als cijfer).</p>

    <?php
    $this->rating_field( 'mijnvloot_speed',               'Snelheid',              $speed );
    $this->rating_field( 'mijnvloot_maneuverability',     'Wendbaarheid',          $mobility );
    $this->rating_field( 'mijnvloot_stability_primary',   'Stabiliteit primair',   $stability_primary );
    $this->rating_field( 'mijnvloot_stability_secondary', 'Stabiliteit secundair', $stability_secondary );
    $this->rating_field( 'mijnvloot_tracking',            'Tracking',              $tracking );
    ?>

    <hr>
    <p><strong>Legenda eigenschappen</strong></p>
    <ul style="margin-left:1.2em;list-style:disc;">
        <li><strong>Snelheid</strong> - hoe efficient je vaart op vlak water.</li>
        <li><strong>Wendbaarheid</strong> - hoe goed de boot draait en reageert.</li>
        <li><strong>Stabiliteit primair</strong> - hoe stabiel hij aanvoelt bij stilzitten (belangrijk voor beginners).</li>
        <li><strong>Stabiliteit secundair</strong> - hoe stabiel hij blijft als je kantelt (belangrijk bij golven/hellingen).</li>
        <li><strong>Tracking (rechtuit gaan)</strong> - hoe goed hij koers houdt zonder correcties.</li>
    </ul>

    <p><em>Legenda sterren: 1 = heel slecht, 5 = heel goed.</em></p>
    <?php
}
    /* -------------------- Technische aspecten -------------------- */

    public function render_technisch_metabox( $post ) {
        // Technische basis
        $material = $this->get_meta( $post->ID, '_mijnvloot_material' );
        $length   = $this->get_meta( $post->ID, '_mijnvloot_length' );
        $weight   = $this->get_meta( $post->ID, '_mijnvloot_weight' );
        $type     = $this->get_meta( $post->ID, '_mijnvloot_type' );

        // Extra technische gegevens
        $cockpit_length = $this->get_meta( $post->ID, '_mijnvloot_cockpit_length' );
        $cockpit_width  = $this->get_meta( $post->ID, '_mijnvloot_cockpit_width' );
        $footrest       = $this->get_meta( $post->ID, '_mijnvloot_footrest' );
        $seat_foot_max  = $this->get_meta( $post->ID, '_mijnvloot_seat_foot_max' );
        $backrest_type  = $this->get_meta( $post->ID, '_mijnvloot_backrest_type' );
        $hatches        = $this->get_meta( $post->ID, '_mijnvloot_hatches' );

        // Public-vlaggen
        $pub_material       = $this->get_meta( $post->ID, '_mijnvloot_public_material', 0 );
        $pub_length         = $this->get_meta( $post->ID, '_mijnvloot_public_length', 0 );
        $pub_weight         = $this->get_meta( $post->ID, '_mijnvloot_public_weight', 0 );
        $pub_type           = $this->get_meta( $post->ID, '_mijnvloot_public_type', 0 );
        $pub_cockpit_length = $this->get_meta( $post->ID, '_mijnvloot_public_cockpit_length', 0 );
        $pub_cockpit_width  = $this->get_meta( $post->ID, '_mijnvloot_public_cockpit_width', 0 );
        $pub_footrest       = $this->get_meta( $post->ID, '_mijnvloot_public_footrest', 0 );
        $pub_seat_foot_max  = $this->get_meta( $post->ID, '_mijnvloot_public_seat_foot_max', 0 );
        $pub_backrest_type  = $this->get_meta( $post->ID, '_mijnvloot_public_backrest_type', 0 );
        $pub_hatches        = $this->get_meta( $post->ID, '_mijnvloot_public_hatches', 0 );
        ?>
        <p>
            <label for="mijnvloot_material"><strong>Materiaal</strong></label><br>
            <select name="mijnvloot_material" id="mijnvloot_material">
                <option value="">&ndash; Selecteer &ndash;</option>
                <option value="PE"        <?php selected( $material, 'PE' ); ?>>PE</option>
                <option value="composite" <?php selected( $material, 'composite' ); ?>>Composiet</option>
            </select>
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_material" value="1" <?php checked( $pub_material, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_length"><strong>Lengte (cm of m)</strong></label><br>
            <input type="text" name="mijnvloot_length" id="mijnvloot_length"
                   value="<?php echo esc_attr( $length ); ?>" class="small-text">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_length" value="1" <?php checked( $pub_length, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_weight"><strong>Gewicht (kg)</strong></label><br>
            <input type="text" name="mijnvloot_weight" id="mijnvloot_weight"
                   value="<?php echo esc_attr( $weight ); ?>" class="small-text">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_weight" value="1" <?php checked( $pub_weight, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_type"><strong>Type</strong></label><br>
            <select name="mijnvloot_type" id="mijnvloot_type">
                <option value="">&ndash; Selecteer &ndash;</option>
                <option value="kayak" <?php selected( $type, 'kayak' ); ?>>Kayak</option>
                <option value="ski"   <?php selected( $type, 'ski' ); ?>>Ski</option>
            </select>
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_type" value="1" <?php checked( $pub_type, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <hr>

        <p>
            <label for="mijnvloot_cockpit_length"><strong>Kuiplengte</strong></label><br>
            <input type="text" name="mijnvloot_cockpit_length" id="mijnvloot_cockpit_length"
                   value="<?php echo esc_attr( $cockpit_length ); ?>" class="small-text"
                   placeholder="cm">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_cockpit_length" value="1" <?php checked( $pub_cockpit_length, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_cockpit_width"><strong>Kuipbreedte</strong></label><br>
            <input type="text" name="mijnvloot_cockpit_width" id="mijnvloot_cockpit_width"
                   value="<?php echo esc_attr( $cockpit_width ); ?>" class="small-text"
                   placeholder="cm">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_cockpit_width" value="1" <?php checked( $pub_cockpit_width, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_footrest"><strong>Voetensteun</strong></label><br>
            <input type="text" name="mijnvloot_footrest" id="mijnvloot_footrest"
                   value="<?php echo esc_attr( $footrest ); ?>" class="regular-text"
                   placeholder="Bijv. verstelbaar, schuifrail, geen">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_footrest" value="1" <?php checked( $pub_footrest, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_seat_foot_max"><strong>Afstand stoel-voet max</strong></label><br>
            <input type="text" name="mijnvloot_seat_foot_max" id="mijnvloot_seat_foot_max"
                   value="<?php echo esc_attr( $seat_foot_max ); ?>" class="small-text"
                   placeholder="cm">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_seat_foot_max" value="1" <?php checked( $pub_seat_foot_max, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_backrest_type"><strong>Type rugsteun</strong></label><br>
            <input type="text" name="mijnvloot_backrest_type" id="mijnvloot_backrest_type"
                   value="<?php echo esc_attr( $backrest_type ); ?>" class="regular-text">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_backrest_type" value="1" <?php checked( $pub_backrest_type, 1 ); ?>>
                Tonen op website
            </label>
        </p>

        <p>
            <label for="mijnvloot_hatches"><strong>Aantal luiken</strong></label><br>
            <input type="text" name="mijnvloot_hatches" id="mijnvloot_hatches"
                   value="<?php echo esc_attr( $hatches ); ?>" class="regular-text"
                   placeholder="Bijv. 2, of korte omschrijving">
            <br>
            <label>
                <input type="checkbox" name="mijnvloot_public_hatches" value="1" <?php checked( $pub_hatches, 1 ); ?>>
                Tonen op website
            </label>
        </p>
        <?php
    }

    /* -------------------- Foto's -------------------- */

    public function render_afbeeldingen_metabox( $post ) {
        $gallery_ids  = $this->get_meta( $post->ID, '_mijnvloot_gallery_ids', [] );
        $highlight_id = $this->get_meta( $post->ID, '_mijnvloot_highlight_id', '' );

        if ( ! is_array( $gallery_ids ) ) {
            $gallery_ids = [];
        }
        ?>
        <p>
            <button type="button" class="button" id="mijnvloot_add_images">
                Foto's kiezen / aanpassen
            </button>
        </p>

        <input type="hidden" id="mijnvloot_gallery_ids" name="mijnvloot_gallery_ids"
               value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
        <input type="hidden" id="mijnvloot_highlight_id" name="mijnvloot_highlight_id"
               value="<?php echo esc_attr( $highlight_id ); ?>">

        <div id="mijnvloot_gallery_preview">
            <?php
            if ( ! empty( $gallery_ids ) ) {
                foreach ( $gallery_ids as $id ) {
                    $thumb = wp_get_attachment_image( $id, 'thumbnail' );
                    $class = ( $id == $highlight_id ) ? 'mijnvloot-highlight' : '';
                    echo '<div class="mijnvloot-thumb ' . esc_attr( $class ) . '" data-id="' . esc_attr( $id ) . '">' . $thumb . '</div>';
                }
            }
            ?>
        </div>

        <p><em>Klik op een thumbnail om de highlight-foto te kiezen.</em></p>
        <?php
    }

    /* -------------------------------------------------------------------------
     * Save meta
     * --------------------------------------------------------------------- */

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['mijnvloot_nonce'] ) || ! wp_verify_nonce( $_POST['mijnvloot_nonce'], 'mijnvloot_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Tekst / select velden
        $fields = [
            // Basis
            '_mijnvloot_brand'               => 'mijnvloot_brand',
            '_mijnvloot_color'               => 'mijnvloot_color',
            '_mijnvloot_location'            => 'mijnvloot_location',
            '_mijnvloot_suitable_for'        => 'mijnvloot_suitable_for',
            '_mijnvloot_hip_room'            => 'mijnvloot_hip_room',

            // Technisch
            '_mijnvloot_material'            => 'mijnvloot_material',
            '_mijnvloot_length'              => 'mijnvloot_length',
            '_mijnvloot_weight'              => 'mijnvloot_weight',
            '_mijnvloot_type'                => 'mijnvloot_type',
            '_mijnvloot_cockpit_length'      => 'mijnvloot_cockpit_length',
            '_mijnvloot_cockpit_width'       => 'mijnvloot_cockpit_width',
            '_mijnvloot_footrest'            => 'mijnvloot_footrest',
            '_mijnvloot_seat_foot_max'       => 'mijnvloot_seat_foot_max',
            '_mijnvloot_backrest_type'       => 'mijnvloot_backrest_type',
            '_mijnvloot_hatches'             => 'mijnvloot_hatches',

            // Eigenschappen (ratings)
            '_mijnvloot_stability_primary'   => 'mijnvloot_stability_primary',
            '_mijnvloot_stability_secondary' => 'mijnvloot_stability_secondary',
            '_mijnvloot_maneuverability'     => 'mijnvloot_maneuverability',
            '_mijnvloot_speed'               => 'mijnvloot_speed',
            '_mijnvloot_tracking'            => 'mijnvloot_tracking',
        ];

        foreach ( $fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }

        // Checkbox: zwaardere mensen
        $heavy_value = isset( $_POST['mijnvloot_heavy_people'] ) ? 1 : 0;
        update_post_meta( $post_id, '_mijnvloot_heavy_people', $heavy_value );

        // Public-vlaggen (default 0)
        $public_flags = [
            '_mijnvloot_public_brand'           => 'mijnvloot_public_brand',
            '_mijnvloot_public_color'           => 'mijnvloot_public_color',
            '_mijnvloot_public_location'        => 'mijnvloot_public_location',
            '_mijnvloot_public_suitable_for'    => 'mijnvloot_public_suitable_for',
            '_mijnvloot_public_hip_room'        => 'mijnvloot_public_hip_room',
            '_mijnvloot_public_heavy'           => 'mijnvloot_public_heavy',
            '_mijnvloot_public_material'        => 'mijnvloot_public_material',
            '_mijnvloot_public_length'          => 'mijnvloot_public_length',
            '_mijnvloot_public_weight'          => 'mijnvloot_public_weight',
            '_mijnvloot_public_type'            => 'mijnvloot_public_type',
            '_mijnvloot_public_cockpit_length'  => 'mijnvloot_public_cockpit_length',
            '_mijnvloot_public_cockpit_width'   => 'mijnvloot_public_cockpit_width',
            '_mijnvloot_public_footrest'        => 'mijnvloot_public_footrest',
            '_mijnvloot_public_seat_foot_max'   => 'mijnvloot_public_seat_foot_max',
            '_mijnvloot_public_backrest_type'   => 'mijnvloot_public_backrest_type',
            '_mijnvloot_public_hatches'         => 'mijnvloot_public_hatches',
        ];

        foreach ( $public_flags as $meta_key => $post_key ) {
            $value = isset( $_POST[ $post_key ] ) ? 1 : 0;
            update_post_meta( $post_id, $meta_key, $value );
        }

        // Afbeeldingen
        if ( isset( $_POST['mijnvloot_gallery_ids'] ) ) {
            $ids = array_filter( array_map( 'absint', explode( ',', $_POST['mijnvloot_gallery_ids'] ) ) );
            update_post_meta( $post_id, '_mijnvloot_gallery_ids', $ids );
        }

        if ( isset( $_POST['mijnvloot_highlight_id'] ) ) {
            update_post_meta( $post_id, '_mijnvloot_highlight_id', absint( $_POST['mijnvloot_highlight_id'] ) );
        }
    }

    /* -------------------------------------------------------------------------
     * Export / Import
     * --------------------------------------------------------------------- */

    public function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=mijnvloot_kayak',
            'Export / Import',
            'Export / Import',
            'manage_options',
            'mijnvloot-export-import',
            [ $this, 'render_export_import_page' ]
        );
    }

    public function render_export_import_page() {
        ?>
        <div class="wrap">
            <h1>Mijn Vloot &ndash; Export &amp; Import</h1>

            <h2>Exporteren</h2>
            <p>Download alle kayaks als JSON bestand.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mijnvloot_export', 'mijnvloot_export_nonce' ); ?>
                <input type="hidden" name="action" value="mijnvloot_export">
                <button type="submit" class="button button-primary">Exporteer Mijn Vloot</button>
            </form>

            <hr>

            <h2>Importeren</h2>
            <p>Upload een eerder ge&euml;xporteerd JSON bestand.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'mijnvloot_import', 'mijnvloot_import_nonce' ); ?>
                <input type="hidden" name="action" value="mijnvloot_import">
                <input type="file" name="mijnvloot_import_file" accept=".json" required>
                <button type="submit" class="button button-secondary">Importeer Mijn Vloot</button>
            </form>
        </div>
        <?php
    }

    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang.' );
        }

        if ( ! isset( $_POST['mijnvloot_export_nonce'] ) || ! wp_verify_nonce( $_POST['mijnvloot_export_nonce'], 'mijnvloot_export' ) ) {
            wp_die( 'Ongeldige nonce.' );
        }

        $args = [
            'post_type'      => 'mijnvloot_kayak',
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'private', 'draft' ],
        ];

        $posts = get_posts( $args );
        $data  = [];

        foreach ( $posts as $post ) {
            $meta    = get_post_meta( $post->ID );
            $data[] = [
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_status'  => $post->post_status,
                'meta'         => $meta,
            ];
        }

        $json = wp_json_encode( $data, JSON_PRETTY_PRINT );

        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename=mijn-vloot-export-' . date( 'Ymd-His' ) . '.json' );
        header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        echo $json;
        exit;
    }

    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang.' );
        }

        if ( ! isset( $_POST['mijnvloot_import_nonce'] ) || ! wp_verify_nonce( $_POST['mijnvloot_import_nonce'], 'mijnvloot_import' ) ) {
            wp_die( 'Ongeldige nonce.' );
        }

        if ( ! isset( $_FILES['mijnvloot_import_file'] ) || $_FILES['mijnvloot_import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( 'Upload mislukt.' );
        }

        $content = file_get_contents( $_FILES['mijnvloot_import_file']['tmp_name'] );
        $items   = json_decode( $content, true );

        if ( ! is_array( $items ) ) {
            wp_die( 'Ongeldig JSON bestand.' );
        }

        foreach ( $items as $item ) {
            $post_id = wp_insert_post( [
                'post_type'    => 'mijnvloot_kayak',
                'post_title'   => $item['post_title']   ?? 'Onbekende kayak',
                'post_content' => $item['post_content'] ?? '',
                'post_status'  => $item['post_status']  ?? 'draft',
            ] );

            if ( ! $post_id || is_wp_error( $post_id ) ) {
                continue;
            }

            if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
                foreach ( $item['meta'] as $meta_key => $values ) {
                    delete_post_meta( $post_id, $meta_key );
                    foreach ( $values as $v ) {
                        add_post_meta( $post_id, $meta_key, maybe_unserialize( $v ) );
                    }
                }
            }
        }

        wp_redirect( admin_url( 'edit.php?post_type=mijnvloot_kayak&page=mijnvloot-export-import&import=success' ) );
        exit;
    }

    /* -------------------------------------------------------------------------
     * Shortcode
     * --------------------------------------------------------------------- */

    public function shortcode_mijn_vloot( $atts ) {
    $args = shortcode_atts( [
        'status' => 'publish',
        'id'     => 0,
    ], $atts );

    $id = absint( $args['id'] );

    if ( $id ) {
        $query_args = [
            'post_type'   => 'mijnvloot_kayak',
            'p'           => $id,
            'post_status' => 'any',
        ];
    } else {
        $query_args = [
            'post_type'      => 'mijnvloot_kayak',
            'posts_per_page' => -1,
            'post_status'    => $args['status'],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        if ( $id ) {
            return '<p>Deze boot kon niet worden gevonden.</p>';
        }
        return '<p>Er staan nog geen boten in de vloot.</p>';
    }

    ob_start();
    echo '<div class="mijnvloot-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();

        // Basis
        $title    = get_the_title();
        $brand    = get_post_meta( $post_id, '_mijnvloot_brand', true );
        $color    = get_post_meta( $post_id, '_mijnvloot_color', true );
        $location = get_post_meta( $post_id, '_mijnvloot_location', true );

        $pub_brand    = (int) get_post_meta( $post_id, '_mijnvloot_public_brand', true );
        $pub_color    = (int) get_post_meta( $post_id, '_mijnvloot_public_color', true );
        $pub_location = (int) get_post_meta( $post_id, '_mijnvloot_public_location', true );

        // Geschikt voor
        $suitable = get_post_meta( $post_id, '_mijnvloot_suitable_for', true );
        $hip_room = get_post_meta( $post_id, '_mijnvloot_hip_room', true );
        $heavy    = (int) get_post_meta( $post_id, '_mijnvloot_heavy_people', true );

        $pub_suitable = (int) get_post_meta( $post_id, '_mijnvloot_public_suitable_for', true );
        $pub_hip_room = (int) get_post_meta( $post_id, '_mijnvloot_public_hip_room', true );
        $pub_heavy    = (int) get_post_meta( $post_id, '_mijnvloot_public_heavy', true );

        // Technisch
        $material       = get_post_meta( $post_id, '_mijnvloot_material', true );
        $length         = get_post_meta( $post_id, '_mijnvloot_length', true );
        $weight         = get_post_meta( $post_id, '_mijnvloot_weight', true );
        $type           = get_post_meta( $post_id, '_mijnvloot_type', true );
        $cockpit_length = get_post_meta( $post_id, '_mijnvloot_cockpit_length', true );
        $cockpit_width  = get_post_meta( $post_id, '_mijnvloot_cockpit_width', true );
        $footrest       = get_post_meta( $post_id, '_mijnvloot_footrest', true );
        $seat_foot_max  = get_post_meta( $post_id, '_mijnvloot_seat_foot_max', true );
        $backrest_type  = get_post_meta( $post_id, '_mijnvloot_backrest_type', true );
        $hatches        = get_post_meta( $post_id, '_mijnvloot_hatches', true );

        $pub_material       = (int) get_post_meta( $post_id, '_mijnvloot_public_material', true );
        $pub_length         = (int) get_post_meta( $post_id, '_mijnvloot_public_length', true );
        $pub_weight         = (int) get_post_meta( $post_id, '_mijnvloot_public_weight', true );
        $pub_type           = (int) get_post_meta( $post_id, '_mijnvloot_public_type', true );
        $pub_cockpit_length = (int) get_post_meta( $post_id, '_mijnvloot_public_cockpit_length', true );
        $pub_cockpit_width  = (int) get_post_meta( $post_id, '_mijnvloot_public_cockpit_width', true );
        $pub_footrest       = (int) get_post_meta( $post_id, '_mijnvloot_public_footrest', true );
        $pub_seat_foot_max  = (int) get_post_meta( $post_id, '_mijnvloot_public_seat_foot_max', true );
        $pub_backrest_type  = (int) get_post_meta( $post_id, '_mijnvloot_public_backrest_type', true );
        $pub_hatches        = (int) get_post_meta( $post_id, '_mijnvloot_public_hatches', true );

        // Afbeeldingen
        $gallery   = get_post_meta( $post_id, '_mijnvloot_gallery_ids', true );
        $highlight = get_post_meta( $post_id, '_mijnvloot_highlight_id', true );

        echo '<div class="mijnvloot-item">';

        // Foto bovenaan
        echo '<div class="mijnvloot-media">';

        if ( is_array( $gallery ) && ! empty( $gallery ) ) {
            echo '<div class="mijnvloot-slider" data-count="' . count( $gallery ) . '">';
            echo '<button type="button" class="mijnvloot-nav mijnvloot-prev" aria-label="Vorige foto">&#10094;</button>';
            echo '<div class="mijnvloot-slides">';

            $index = 0;
            foreach ( $gallery as $img_id ) {
                $is_active = ( $highlight && $highlight == $img_id ) || ( ! $highlight && $index === 0 ) ? ' active' : '';
                echo '<div class="mijnvloot-slide' . $is_active . '" data-index="' . $index . '">';
                echo wp_get_attachment_image( $img_id, 'large' );
                echo '</div>';
                $index++;
            }

            echo '</div>';
            echo '<button type="button" class="mijnvloot-nav mijnvloot-next" aria-label="Volgende foto">&#10095;</button>';
            echo '</div>';
        } elseif ( $highlight ) {
            echo '<div class="mijnvloot-single-image">';
            echo wp_get_attachment_image( $highlight, 'large' );
            echo '</div>';
        }

        // Overlay titel + locatie
        echo '<div class="mijnvloot-overlay">';
        echo '<span class="mijnvloot-overlay-title">' . esc_html( $title );

        if ( $location && $pub_location ) {
            echo ' - ' . esc_html( $location );
        }

        echo '</span>';
        echo '</div>';

        echo '</div>'; // .mijnvloot-media

        // Body
        echo '<div class="mijnvloot-body">';

        // Pills
        echo '<div class="mijnvloot-pills">';

if ( $brand && $pub_brand ) {
    echo '<span class="mijnvloot-pill">Merk: ' . esc_html( $brand ) . '</span>';
}
if ( $type && $pub_type ) {
    echo '<span class="mijnvloot-pill">Type: ' . esc_html( ucfirst( $type ) ) . '</span>';
}
if ( $color && $pub_color ) {
    echo '<span class="mijnvloot-pill">Kleur: ' . esc_html( $color ) . '</span>';
}
if ( $material && $pub_material ) {
    echo '<span class="mijnvloot-pill">Materiaal: ' . esc_html( strtoupper( $material ) ) . '</span>';
}
if ( $length && $pub_length ) {
    echo '<span class="mijnvloot-pill">Lengte: ' . esc_html( $length ) . ' cm</span>';
}
if ( $weight && $pub_weight ) {
    echo '<span class="mijnvloot-pill">Gewicht: ' . esc_html( $weight ) . ' kg</span>';
}
if ( $hip_room && $pub_hip_room ) {
    echo '<span class="mijnvloot-pill">Heupruimte: ' . esc_html( $hip_room ) . '</span>';
}
		
if ( $pub_suitable ) {
    if ( $suitable === 'short' ) {
        echo '<span class="mijnvloot-pill mijnvloot-pill-check">&#10003; Korte mensen</span>';
    } elseif ( $suitable === 'tall' ) {
        echo '<span class="mijnvloot-pill mijnvloot-pill-check">&#10003; Lange mensen</span>';
    } else {
        echo '<span class="mijnvloot-pill mijnvloot-pill-check">&#10003; Korte mensen</span>';
        echo '<span class="mijnvloot-pill mijnvloot-pill-check">&#10003; Lange mensen</span>';
    }
}

if ( $heavy && $pub_heavy ) {
    echo '<span class="mijnvloot-pill mijnvloot-pill-check">&#10003; Geschikt voor zwaardere mensen</span>';
}

echo '</div>';

        // Toggles
        echo '<div class="mijnvloot-toggles">';

        echo '<button type="button" class="mijnvloot-toggle is-open" data-target="scores-' . esc_attr( $post_id ) . '">';
        echo 'Prestatiescores';
        echo '</button>';

        echo '<button type="button" class="mijnvloot-toggle" data-target="tech-' . esc_attr( $post_id ) . '">';
        echo 'Technische aspecten';
        echo '</button>';

        echo '</div>';

        // Toggle inhoud: scores
        echo '<div id="scores-' . esc_attr( $post_id ) . '" class="mijnvloot-panel is-open">';
        echo '<div class="mijnvloot-ratings">';
        $this->render_front_rating_row( $post_id, '_mijnvloot_stability_primary',   'Stabiliteit (primair)' );
        $this->render_front_rating_row( $post_id, '_mijnvloot_stability_secondary', 'Stabiliteit (secundair)' );
        $this->render_front_rating_row( $post_id, '_mijnvloot_maneuverability',     'Wendbaarheid' );
        $this->render_front_rating_row( $post_id, '_mijnvloot_speed',               'Snelheid' );
        $this->render_front_rating_row( $post_id, '_mijnvloot_tracking',            'Tracking' );
        echo '</div>';
        echo '</div>';

        // Toggle inhoud: techniek
        $has_tech =
            ( $cockpit_length && $pub_cockpit_length ) ||
            ( $cockpit_width && $pub_cockpit_width ) ||
            ( $footrest && $pub_footrest ) ||
            ( $seat_foot_max && $pub_seat_foot_max ) ||
            ( $backrest_type && $pub_backrest_type ) ||
            ( $hatches && $pub_hatches );

        echo '<div id="tech-' . esc_attr( $post_id ) . '" class="mijnvloot-panel">';

        if ( $has_tech ) {
            echo '<div class="mijnvloot-tech-table-wrap">';
            echo '<table class="mijnvloot-tech-table">';

            if ( $cockpit_length && $pub_cockpit_length ) {
                echo '<tr><th>Kuiplengte</th><td>' . esc_html( $cockpit_length ) . ' cm</td></tr>';
            }
            if ( $cockpit_width && $pub_cockpit_width ) {
                echo '<tr><th>Kuipbreedte</th><td>' . esc_html( $cockpit_width ) . ' cm</td></tr>';
            }
            if ( $footrest && $pub_footrest ) {
                echo '<tr><th>Voetensteun</th><td>' . esc_html( $footrest ) . '</td></tr>';
            }
            if ( $seat_foot_max && $pub_seat_foot_max ) {
                echo '<tr><th>Afstand stoel-voet max</th><td>' . esc_html( $seat_foot_max ) . ' cm</td></tr>';
            }
            if ( $backrest_type && $pub_backrest_type ) {
                echo '<tr><th>Type rugsteun</th><td>' . esc_html( $backrest_type ) . '</td></tr>';
            }
            if ( $hatches && $pub_hatches ) {
                echo '<tr><th>Aantal luiken</th><td>' . esc_html( $hatches ) . '</td></tr>';
            }

            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>Geen technische aspecten beschikbaar.</p>';
        }

        echo '</div>';

        // Optioneel: bestaande content
        $content = trim( get_the_content() );
        if ( $content !== '' ) {
            echo '<div class="mijnvloot-content">' . wpautop( $content ) . '</div>';
        }

        echo '</div>'; // .mijnvloot-body
        echo '</div>'; // .mijnvloot-item
    }

    echo '</div>';
    wp_reset_postdata();

    return ob_get_clean();
}

private function render_front_rating_row( $post_id, $meta_key, $label ) {
    $value = (int) get_post_meta( $post_id, $meta_key, true );
    if ( $value <= 0 ) {
        return;
    }

    // Tooltip-teksten per eigenschap
    $tooltips = [
        '_mijnvloot_speed'               => 'Snelheid - hoe efficient je vaart op vlak water.',
        '_mijnvloot_maneuverability'     => 'Wendbaarheid - hoe goed de boot draait en reageert.',
        '_mijnvloot_stability_primary'   => 'Stabiliteit primair - hoe stabiel hij aanvoelt bij stilzitten (belangrijk voor beginners).',
        '_mijnvloot_stability_secondary' => 'Stabiliteit secundair - hoe stabiel hij blijft als je kantelt (belangrijk bij golven/hellingen).',
        '_mijnvloot_tracking'            => 'Tracking (rechtuit gaan) - hoe goed hij koers houdt zonder correcties.',
    ];

    $title_attr = '';
    if ( isset( $tooltips[ $meta_key ] ) ) {
        $title_attr = ' title="' . esc_attr( $tooltips[ $meta_key ] ) . '"';
    }

    echo '<p class="mijnvloot-rating-row"' . $title_attr . '>';
    echo '<span class="mijnvloot-rating-label"><strong>' . esc_html( $label ) . ':</strong></span> ';

    for ( $i = 1; $i <= 5; $i++ ) {
        $class = $i <= $value ? 'filled' : 'outline';
        echo '<span class="mijnvloot-paddle ' . esc_attr( $class ) . '"></span>';
    }
    echo '</p>';
}
    /* -------------------------------------------------------------------------
     * Assets
     * --------------------------------------------------------------------- */

    public function enqueue_admin_assets( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        global $post;
        if ( ! $post || $post->post_type !== 'mijnvloot_kayak' ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'mijnvloot-admin',
            plugin_dir_url( __FILE__ ) . 'assets/mijnvloot-admin.css',
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'mijnvloot-admin',
            plugin_dir_url( __FILE__ ) . 'assets/mijnvloot-admin.js',
            [ 'jquery' ],
            '0.1.0',
            true
        );
    }

    public function enqueue_front_assets() {
        wp_enqueue_style(
            'mijnvloot-front',
            plugin_dir_url( __FILE__ ) . 'assets/mijnvloot-front.css',
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'mijnvloot-front',
            plugin_dir_url( __FILE__ ) . 'assets/mijnvloot-front.js',
            [],
            '0.1.0',
            true
        );
    }
}

new MijnVloot_Plugin();
