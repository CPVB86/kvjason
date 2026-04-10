<?php
/**
 * Plugin Name: Baken PDF Manager
 * Description: Beheer en toon PDF-edities als custom post type met shortcode ondersteuning.
 * Version: 2.4
 * Author: Chantor Pascal van Beek
 * Author URI: https://runiversity.nl
 * Plugin URI: https://leden.kvjasonarnhem.nl/baken-pdf-manager/
 */

// Register Custom Post Type
function baken_register_cpt() {
    $labels = array(
        'name' => 'Baken Magazines',
        'singular_name' => 'Baken',
        'add_new' => 'Nieuwe editie',
        'add_new_item' => 'Nieuwe Baken toevoegen',
        'edit_item' => 'Baken bewerken',
        'new_item' => 'Nieuwe Baken',
        'view_item' => 'Bekijk Baken',
        'search_items' => 'Zoek Baken',
        'not_found' => 'Geen Baken gevonden',
        'not_found_in_trash' => 'Geen Baken in prullenbak'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'rewrite' => array('slug' => 'baken'),
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_icon' => 'dashicons-media-document',
    );

    register_post_type('baken', $args);
}
add_action('init', 'baken_register_cpt');

function baken_add_multipart_encoding() {
    echo ' enctype="multipart/form-data"';
}
add_action('post_edit_form_tag', 'baken_add_multipart_encoding');

function baken_add_meta_box() {
    add_meta_box(
        'baken_pdf_meta',
        'PDF-bestand',
        'baken_render_pdf_meta_box',
        'baken',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'baken_add_meta_box');

function baken_render_pdf_meta_box($post) {
    $pdf_url = get_post_meta($post->ID, 'pdf_url', true);
    wp_nonce_field('baken_save_pdf_meta', 'baken_pdf_meta_nonce');

    echo '<p><label for="baken_pdf_url">Voer hier de URL van het PDF-bestand in (of upload hieronder):</label></p>';
    echo '<input type="url" id="baken_pdf_url" name="baken_pdf_url" value="' . esc_attr($pdf_url) . '" style="width:100%; margin-bottom:10px;" />';

    echo '<input type="file" id="baken_pdf_file" name="baken_pdf_file" accept="application/pdf" />';
}

function baken_save_pdf_meta($post_id) {
    if (!isset($_POST['baken_pdf_meta_nonce']) || !wp_verify_nonce($_POST['baken_pdf_meta_nonce'], 'baken_save_pdf_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['baken_pdf_url'])) {
        $url = trim($_POST['baken_pdf_url']);
        if ($url !== '') {
            update_post_meta($post_id, 'pdf_url', esc_url_raw($url));
        } else {
            delete_post_meta($post_id, 'pdf_url');
        }
    }

    if (!empty($_FILES['baken_pdf_file']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $uploaded = media_handle_upload('baken_pdf_file', $post_id);

        if (!is_wp_error($uploaded)) {
            $url = wp_get_attachment_url($uploaded);
            update_post_meta($post_id, 'pdf_url', esc_url_raw($url));
        }
    }
}
add_action('save_post', 'baken_save_pdf_meta');

// Bulk upload-pagina in backend
function baken_bulk_upload_menu() {
    add_submenu_page(
        'edit.php?post_type=baken',
        'Bulk Upload',
        'Bulk Upload',
        'manage_options',
        'baken-bulk-upload',
        'baken_bulk_upload_page'
    );
}
add_action('admin_menu', 'baken_bulk_upload_menu');

function baken_bulk_upload_page() {
    echo '<div class="wrap"><h1>Bulk Upload Baken Edities</h1>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('baken_bulk_upload')) {
        for ($i = 0; $i < 4; $i++) {
            $title = sanitize_text_field($_POST['baken_title'][$i]);
            $date = sanitize_text_field($_POST['baken_date'][$i]);

            // Upload PDF
            if (!empty($_FILES['baken_pdf']['name'][$i])) {
                $_FILES['single_pdf'] = [
                    'name' => $_FILES['baken_pdf']['name'][$i],
                    'type' => $_FILES['baken_pdf']['type'][$i],
                    'tmp_name' => $_FILES['baken_pdf']['tmp_name'][$i],
                    'error' => $_FILES['baken_pdf']['error'][$i],
                    'size' => $_FILES['baken_pdf']['size'][$i]
                ];

                $pdf_id = media_handle_upload('single_pdf', 0);

                if (!is_wp_error($pdf_id)) {
                    $pdf_url = wp_get_attachment_url($pdf_id);
                } else {
                    echo '<div class="notice notice-error"><p>PDF upload mislukt voor regel ' . ($i + 1) . '.</p></div>';
                    continue;
                }
            } else {
                continue;
            }

            // Upload thumbnail
            $thumb_id = 0;
            if (!empty($_FILES['baken_thumb']['name'][$i])) {
                $_FILES['single_thumb'] = [
                    'name' => $_FILES['baken_thumb']['name'][$i],
                    'type' => $_FILES['baken_thumb']['type'][$i],
                    'tmp_name' => $_FILES['baken_thumb']['tmp_name'][$i],
                    'error' => $_FILES['baken_thumb']['error'][$i],
                    'size' => $_FILES['baken_thumb']['size'][$i]
                ];

                $thumb_id = media_handle_upload('single_thumb', 0);
                if (is_wp_error($thumb_id)) {
                    echo '<div class="notice notice-error"><p>Thumbnail upload mislukt voor regel ' . ($i + 1) . '.</p></div>';
                    $thumb_id = 0;
                }
            }

            $new_post = array(
                'post_title' => $title,
                'post_status' => 'publish',
                'post_type' => 'baken',
                'post_date' => $date ?: current_time('mysql')
            );
            $post_id = wp_insert_post($new_post);

            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, 'pdf_url', esc_url_raw($pdf_url));
                if ($thumb_id) {
                    set_post_thumbnail($post_id, $thumb_id);
                }
                echo '<div class="notice notice-success"><p>Regel ' . ($i + 1) . ' succesvol toegevoegd.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Fout bij invoegen van regel ' . ($i + 1) . '.</p></div>';
            }
        }
    }

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('baken_bulk_upload');
    echo '<table class="form-table">';
    for ($i = 0; $i < 4; $i++) {
        echo '<tr><th colspan="2">Baken ' . ($i + 1) . '</th></tr>';
        echo '<tr><td>Titel:</td><td><input type="text" name="baken_title[]" style="width:100%" /></td></tr>';
        echo '<tr><td>Datum:</td><td><input type="date" name="baken_date[]" /></td></tr>';
        echo '<tr><td>PDF:</td><td><input type="file" name="baken_pdf[]" accept="application/pdf" /></td></tr>';
        echo '<tr><td>Afbeelding:</td><td><input type="file" name="baken_thumb[]" accept="image/*" /></td></tr>';
    }
    echo '</table>';
    echo '<p><input type="submit" class="button-primary" value="Uploaden"></p>';
    echo '</form></div>';
}

// Shortcode: [baken list=all filter=true year=2024 title=false id=123]
function baken_shortcode($atts) {
    $atts = shortcode_atts(array(
        'list' => 'all',
        'filter' => false,
        'year' => '',
        'title' => 'true',
        'id' => '',
    ), $atts);

    $filter_year = !empty($atts['year']) ? intval($atts['year']) : (isset($_GET['bakenjaar']) ? intval($_GET['bakenjaar']) : '');
    $show_title = $atts['title'] === 'true';

    $args = array(
        'post_type' => 'baken',
        'posts_per_page' => $atts['list'] === 'newest' ? 1 : -1,
        'orderby' => 'date',
        'order' => 'DESC'
    );

    if (!empty($atts['id'])) {
        $args['p'] = intval($atts['id']);
    } elseif ($filter_year) {
        $args['date_query'] = array(
            array('year' => $filter_year)
        );
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<p>Geen edities gevonden.</p>';

    ob_start();

    if ($atts['filter']) {
        global $wpdb;
        $years = $wpdb->get_col("SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type = 'baken' AND post_status = 'publish' ORDER BY post_date DESC");
        if ($years) {
            echo '<div class="baken-filter" style="margin-bottom:20px;">';
            echo '<strong>Jaartal:</strong> ';
            if (!$filter_year) {
                echo '<span style="margin-right:10px; font-weight: bold;">Alle</span>';
            } else {
                echo '<a href="' . esc_url(remove_query_arg('bakenjaar')) . '" style="margin-right:10px;">Alle</a>';
            }
            foreach ($years as $y) {
                if ($filter_year && intval($filter_year) === intval($y)) {
                    echo '<span style="margin-right:10px; font-weight: bold;">' . esc_html($y) . '</span>';
                } else {
                    $url = add_query_arg(array('bakenjaar' => $y));
                    echo '<a href="' . esc_url($url) . '" style="margin-right:10px;">' . esc_html($y) . '</a>';
                }
            }
            echo '</div>';
        }
    }

    $grid_columns = ($atts['list'] === 'newest' || !empty($atts['id'])) ? '1fr' : 'repeat(4, 1fr)';
    echo '<div class="baken-list" style="display: grid; grid-template-columns: ' . $grid_columns . '; gap: 20px;">';
    while ($query->have_posts()) : $query->the_post();
        $pdf_url = get_post_meta(get_the_ID(), 'pdf_url', true);
        $thumb = get_the_post_thumbnail_url(get_the_ID(), 'full') ?: 'https://via.placeholder.com/300x400?text=Baken';
        echo '<div class="baken-item">';
        echo '<a href="' . esc_url($pdf_url) . '" target="_blank">';
        echo '<img loading="lazy" src="' . esc_url($thumb) . '" alt="' . esc_attr(get_the_title()) . '" style="width:100%; height:auto;" />';
        if ($show_title) {
            echo '<p>' . esc_html(get_the_title()) . '</p>';
        }
        echo '</a>';
        echo '</div>';
    endwhile;
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('baken', 'baken_shortcode');
