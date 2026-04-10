<?php
/**
 * Plugin Name: KVJason Profile Photo
 * Description: Super lichtgewicht plugin waarmee leden in Mijn account > Accountgegevens een profielfoto kunnen uploaden.
 * Version: 1.5.0
 * Author: Chantor Pascal van Beek
 * Author URI: https://runiversity.nl
 * License: GPL2+
 */

if (!defined('ABSPATH')) {
    exit;
}

class KVJason_Profile_Photo_Plugin {
    const META_KEY = 'kvjason_profile_photo_id';
    const AJAX_ACTION = 'kvjason_remove_profile_photo';

    public function __construct() {
        add_action('woocommerce_edit_account_form_tag', array($this, 'add_multipart_form_encoding'));
        add_action('woocommerce_edit_account_form', array($this, 'render_field'));
        add_action('woocommerce_save_account_details', array($this, 'save_field'));
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'ajax_remove_photo'));
        add_filter('get_avatar', array($this, 'replace_avatar'), 10, 6);
        add_shortcode('kvjason_profile_photo', array($this, 'photo_shortcode'));
    }

    public function add_multipart_form_encoding() {
        echo 'enctype="multipart/form-data"';
    }

    private function sanitize_image_url($url) {
        if (!$url) {
            return '';
        }

        if (strpos($url, 'data:image/svg+xml;base64,') === 0) {
            return esc_attr($url);
        }

        return esc_url($url);
    }

    private function remove_user_photo($user_id) {
        $old_attachment_id = (int) get_user_meta($user_id, self::META_KEY, true);
        delete_user_meta($user_id, self::META_KEY);

        if ($old_attachment_id) {
            wp_delete_attachment($old_attachment_id, true);
        }
    }

    public function render_field() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id         = get_current_user_id();
        $attachment_id   = (int) get_user_meta($user_id, self::META_KEY, true);
        $image_url       = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : $this->get_generated_avatar_url($user_id, 512);
        $safe_image_url  = $this->sanitize_image_url($image_url);
        $fallback_url    = $this->sanitize_image_url($this->get_generated_avatar_url($user_id, 512));
        $ajax_nonce      = wp_create_nonce(self::AJAX_ACTION);
        $ajax_url        = admin_url('admin-ajax.php');

        wp_nonce_field('kvjason_profile_photo_upload', 'kvjason_profile_photo_nonce');
        ?>
        <style>
            .kvjason-profile-photo-wrap {
                display: inline-block;
                position: relative;
                width: 96px;
                height: 96px;
                margin-bottom: 12px;
            }
            .kvjason-profile-photo-preview {
                display: block;
                width: 96px;
                height: 96px;
                object-fit: cover;
                border-radius: 6px;
            }
            .kvjason-profile-photo-remove {
                position: absolute;
                top: -8px;
                right: -8px;
                width: 28px;
                height: 28px;
                border: 0;
                border-radius: 999px;
                background: #ef4444;
                color: #111111;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                box-shadow: 0 2px 8px rgba(0,0,0,.18);
                z-index: 2;
            }
            .kvjason-profile-photo-remove:hover {
                transform: scale(1.04);
            }
            .kvjason-profile-photo-remove:disabled {
                opacity: .65;
                cursor: wait;
                transform: none;
            }
            .kvjason-profile-photo-remove svg {
                width: 14px;
                height: 14px;
                display: block;
                fill: #111111;
            }
            .kvjason-profile-photo-input {
                display: block;
                margin-top: 0;
            }
            .kvjason-profile-photo-help {
                display:block;
                font-size:12px;
                opacity:.75;
                margin-top:6px;
            }
        </style>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <span style="display:block;font-weight:600;margin-bottom:8px;"><?php esc_html_e('Profielfoto', 'kvjason-profile-photo'); ?></span>

            <span class="kvjason-profile-photo-wrap">
                <img
                    src="<?php echo $safe_image_url; ?>"
                    alt="<?php esc_attr_e('Profielfoto', 'kvjason-profile-photo'); ?>"
                    class="kvjason-profile-photo-preview"
                    id="kvjason-profile-photo-preview"
                />
                <?php if ($attachment_id) : ?>
                    <button type="button" class="kvjason-profile-photo-remove" id="kvjason-profile-photo-remove" aria-label="<?php esc_attr_e('Profielfoto verwijderen', 'kvjason-profile-photo'); ?>" title="<?php esc_attr_e('Profielfoto verwijderen', 'kvjason-profile-photo'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 6h2v8h-2V9Zm4 0h2v8h-2V9ZM7 9h2v8H7V9Zm-1 12a2 2 0 0 1-2-2V8h16v11a2 2 0 0 1-2 2H6Z"/></svg>
                    </button>
                <?php endif; ?>
            </span>

            <input type="file" name="kvjason_profile_photo" id="kvjason_profile_photo" class="kvjason-profile-photo-input" accept="image/jpeg,image/png,image/webp" />
            <span class="kvjason-profile-photo-help">JPG, PNG of WebP.</span>
        </p>
        <script>
            (function() {
                var removeBtn = document.getElementById('kvjason-profile-photo-remove');
                var preview = document.getElementById('kvjason-profile-photo-preview');
                var fileInput = document.getElementById('kvjason_profile_photo');
                var fallback = <?php echo wp_json_encode($fallback_url); ?>;
                var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
                var ajaxNonce = <?php echo wp_json_encode($ajax_nonce); ?>;

                if (fileInput && preview) {
                    fileInput.addEventListener('change', function(event) {
                        var file = event.target.files && event.target.files[0];
                        if (!file) {
                            return;
                        }

                        var reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    });
                }

                if (removeBtn && preview) {
                    removeBtn.addEventListener('click', function() {
                        if (!window.confirm('Weet je zeker dat je je profielfoto wilt verwijderen?')) {
                            return;
                        }

                        removeBtn.disabled = true;

                        var formData = new FormData();
                        formData.append('action', 'kvjason_remove_profile_photo');
                        formData.append('_ajax_nonce', ajaxNonce);

                        fetch(ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        })
                        .then(function(response) {
                            return response.json();
                        })
                        .then(function(data) {
                            if (!data || !data.success) {
                                throw new Error((data && data.data && data.data.message) ? data.data.message : 'Verwijderen mislukt.');
                            }

                            preview.src = fallback;
                            if (fileInput) {
                                fileInput.value = '';
                            }
                            removeBtn.remove();
                        })
                        .catch(function(error) {
                            window.alert(error.message || 'Verwijderen mislukt.');
                            removeBtn.disabled = false;
                        });
                    });
                }
            })();
        </script>
        <?php
    }

    public function ajax_remove_photo() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Niet ingelogd.', 'kvjason-profile-photo')), 403);
        }

        check_ajax_referer(self::AJAX_ACTION);

        $user_id = get_current_user_id();
        $this->remove_user_photo($user_id);

        wp_send_json_success(array(
            'message'  => __('Profielfoto verwijderd.', 'kvjason-profile-photo'),
            'fallback' => $this->get_generated_avatar_url($user_id, 512),
        ));
    }

    public function save_field($user_id) {
        if (!isset($_POST['kvjason_profile_photo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kvjason_profile_photo_nonce'])), 'kvjason_profile_photo_upload')) {
            return;
        }

        if (empty($_FILES['kvjason_profile_photo']) || empty($_FILES['kvjason_profile_photo']['name'])) {
            return;
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        if (!function_exists('wp_insert_attachment')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $file = $_FILES['kvjason_profile_photo'];

        if (!empty($file['error'])) {
            wc_add_notice(__('Upload mislukt. Probeer het opnieuw.', 'kvjason-profile-photo'), 'error');
            return;
        }

        $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
        $filetype      = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

        if (empty($filetype['type']) || !in_array($filetype['type'], $allowed_types, true)) {
            wc_add_notice(__('Alleen JPG, PNG en WebP zijn toegestaan.', 'kvjason-profile-photo'), 'error');
            return;
        }

        $upload_overrides = array(
            'test_form' => false,
            'mimes'     => array(
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
            ),
        );

        $uploaded = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded['error'])) {
            wc_add_notice(__('Upload mislukt: ', 'kvjason-profile-photo') . esc_html($uploaded['error']), 'error');
            return;
        }

        $attachment = array(
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name(pathinfo($uploaded['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);

        if (is_wp_error($attachment_id)) {
            wc_add_notice(__('Opslaan van de profielfoto is mislukt.', 'kvjason-profile-photo'), 'error');
            return;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        $old_attachment_id = (int) get_user_meta($user_id, self::META_KEY, true);
        update_user_meta($user_id, self::META_KEY, $attachment_id);

        if ($old_attachment_id && $old_attachment_id !== $attachment_id) {
            wp_delete_attachment($old_attachment_id, true);
        }
    }

    private function get_initials($user) {
        $parts = array();

        if (!empty($user->first_name)) {
            $parts[] = $user->first_name;
        }

        if (!empty($user->last_name)) {
            $parts[] = $user->last_name;
        }

        if (empty($parts) && !empty($user->display_name)) {
            $parts = preg_split('/\s+/', trim($user->display_name));
        }

        if (empty($parts) && !empty($user->user_login)) {
            $parts = preg_split('/[\s._-]+/', trim($user->user_login));
        }

        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
            }
            if (strlen($initials) >= 2) {
                break;
            }
        }

        if ($initials === '' && !empty($user->user_email)) {
            $initials = strtoupper(substr($user->user_email, 0, 1));
        }

        return strtoupper($initials ?: 'U');
    }

    private function get_avatar_colors($seed) {
        $palette = array(
            array('#1D4ED8', '#DBEAFE'),
            array('#7C3AED', '#EDE9FE'),
            array('#0F766E', '#CCFBF1'),
            array('#BE123C', '#FFE4E6'),
            array('#B45309', '#FEF3C7'),
            array('#166534', '#DCFCE7'),
            array('#374151', '#F3F4F6'),
            array('#C2410C', '#FFEDD5'),
        );

        $index = abs(crc32((string) $seed)) % count($palette);
        return $palette[$index];
    }

    public function get_generated_avatar_url($user_id, $size = 512) {
        $user = get_userdata($user_id);
        if (!$user) {
            return '';
        }

        $initials = $this->get_initials($user);
        list($text_color, $bg_color) = $this->get_avatar_colors($user->user_email . '|' . $user->user_login . '|' . $user->ID);

        $font_size = max(120, (int) round($size * 0.28));
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d"><rect width="100%%" height="100%%" rx="32" ry="32" fill="%2$s"/><text x="50%%" y="50%%" dy=".1em" text-anchor="middle" dominant-baseline="middle" font-family="Arial, Helvetica, sans-serif" font-size="%3$d" font-weight="700" fill="%4$s">%5$s</text></svg>',
            (int) $size,
            esc_attr($bg_color),
            (int) $font_size,
            esc_attr($text_color),
            esc_html($initials)
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function replace_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
        $user = false;

        if (is_numeric($id_or_email)) {
            $user = get_user_by('id', (int) $id_or_email);
        } elseif ($id_or_email instanceof WP_User) {
            $user = $id_or_email;
        } elseif ($id_or_email instanceof WP_Comment) {
            $user = get_user_by('id', (int) $id_or_email->user_id);
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
        }

        if (!$user || empty($user->ID)) {
            return $avatar;
        }

        $attachment_id = (int) get_user_meta($user->ID, self::META_KEY, true);
        $url = $attachment_id ? wp_get_attachment_image_url($attachment_id, array($size, $size)) : $this->get_generated_avatar_url($user->ID, max(512, (int) $size));

        if (!$url) {
            return $avatar;
        }

        $alt_text = $alt ? $alt : $user->display_name;

        return sprintf(
            '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" loading="lazy" decoding="async" style="object-fit:cover;border-radius:6px;" />',
            esc_attr($alt_text),
            $this->sanitize_image_url($url),
            (int) $size,
            (int) $size,
            (int) $size
        );
    }

    public function photo_shortcode() {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id       = get_current_user_id();
        $attachment_id = (int) get_user_meta($user_id, self::META_KEY, true);

        if ($attachment_id) {
            return wp_get_attachment_image($attachment_id, 'thumbnail', false, array(
                'style' => 'width:96px;height:96px;object-fit:cover;border-radius:6px;',
            ));
        }

        return '<img src="' . $this->sanitize_image_url($this->get_generated_avatar_url($user_id, 512)) . '" alt="Avatar" width="96" height="96" style="width:96px;height:96px;object-fit:cover;border-radius:6px;" />';
    }
}

new KVJason_Profile_Photo_Plugin();
