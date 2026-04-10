<?php
/*
Plugin Name: User List by Role
Description: Een shortcode om een lijst van gebruikers per rol te tonen met avatar, zoekfunctie en optionele profielgegevens: Functie, Team en Instructeursniveau.
Version: 1.8
Author: Chantor Pascal van Beek
Author URI: https://runiversity.nl
Plugin URI: https://leden.kvjasonarnhem.nl/user-list-by-role/
*/

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sta data:image/svg+xml;base64 toe voor fallback avatars.
 */
function ulbr_sanitize_image_url($url) {
    if (!$url) {
        return '';
    }

    if (strpos($url, 'data:image/svg+xml;base64,') === 0) {
        return esc_attr($url);
    }

    return esc_url($url);
}

/**
 * Initialen opbouwen zoals in de profielfoto-plugin.
 */
function ulbr_get_initials($user) {
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

/**
 * Kleurenset zoals in de profielfoto-plugin.
 */
function ulbr_get_avatar_colors($seed) {
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

/**
 * Genereer SVG-avatar met initialen.
 */
function ulbr_get_generated_avatar_url($user_id, $size = 256) {
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }

    $initials = ulbr_get_initials($user);
    list($text_color, $bg_color) = ulbr_get_avatar_colors($user->user_email . '|' . $user->user_login . '|' . $user->ID);

    $font_size = max(60, (int) round($size * 0.28));

    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d"><rect width="100%%" height="100%%" rx="24" ry="24" fill="%2$s"/><text x="50%%" y="50%%" dy=".1em" text-anchor="middle" dominant-baseline="middle" font-family="Arial, Helvetica, sans-serif" font-size="%3$d" font-weight="700" fill="%4$s">%5$s</text></svg>',
        (int) $size,
        esc_attr($bg_color),
        (int) $font_size,
        esc_attr($text_color),
        esc_html($initials)
    );

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Haal profielfoto op uit de profielplugin.
 * Geen upload? Dan fallback naar initialen.
 */
function ulbr_get_profile_avatar($user_id, $size = 96) {
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }

    $attachment_id = (int) get_user_meta($user_id, 'kvjason_profile_photo_id', true);

    if ($attachment_id) {
        return wp_get_attachment_image(
            $attachment_id,
            array($size, $size),
            false,
            array(
                'class'    => 'ulbr-avatar',
                'alt'      => $user->display_name,
                'loading'  => 'lazy',
                'decoding' => 'async',
                'style'    => 'width:' . (int) $size . 'px;height:' . (int) $size . 'px;object-fit:cover;border-radius:6px;',
            )
        );
    }

    $fallback_url = ulbr_get_generated_avatar_url($user_id, max(256, (int) $size));

    return sprintf(
        '<img src="%s" alt="%s" class="ulbr-avatar" width="%d" height="%d" loading="lazy" decoding="async" style="width:%dpx;height:%dpx;object-fit:cover;border-radius:6px;" />',
        ulbr_sanitize_image_url($fallback_url),
        esc_attr($user->display_name),
        (int) $size,
        (int) $size,
        (int) $size,
        (int) $size
    );
}

function user_list_by_role_shortcode($atts) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts(
        array(
            'role'    => 'subscriber',
            'functie' => false,
            'team'    => false,
            'niveau'  => false,
            'search'  => false,
            'userid'  => false,
        ),
        $atts,
        'user_list_by_role'
    );

    if (!empty($atts['userid'])) {
        $user  = get_user_by('id', intval($atts['userid']));
        $users = $user ? array($user) : array();
    } else {
        $users = get_users(array(
            'role' => $atts['role'],
        ));
    }

    $unique_id = 'user-list-' . $instance;

    $output = '';

    if ($atts['search'] && empty($atts['userid'])) {
        $output .= '<input type="text" id="search-' . esc_attr($unique_id) . '" placeholder="Zoek lid..." style="margin-bottom: 20px; padding: 8px; width: 100%; max-width: 300px;">';
    }

    $output .= '<div class="user-list" id="' . esc_attr($unique_id) . '">';

    foreach ($users as $user) {
        $first_name    = get_user_meta($user->ID, 'first_name', true);
        $last_name     = get_user_meta($user->ID, 'last_name', true);
        $email         = $user->user_email;
        $billing_phone = get_user_meta($user->ID, 'billing_phone', true);

        $extra_info = '';

        if ($atts['functie']) {
            $function = get_user_meta($user->ID, 'function', true);
            if (!empty($function)) {
                $extra_info .= esc_html($function);
            }
        }

        if ($atts['team']) {
            $team = get_user_meta($user->ID, 'team', true);
            if (!empty($team)) {
                $extra_info .= (!empty($extra_info) ? ', ' : '') . esc_html($team);
            }
        }

        if ($atts['niveau']) {
            $level = get_user_meta($user->ID, 'instructor_level', true);
            if (!empty($level)) {
                $extra_info .= (!empty($extra_info) ? ', ' : '') . esc_html($level);
            }
        }

        $full_name = trim($first_name . ' ' . $last_name);
        if ($full_name === '') {
            $full_name = $user->display_name;
        }

        $name_output = esc_html($full_name);
        if (!empty($extra_info)) {
            $name_output .= '<i> - ' . $extra_info . '</i>';
        }

        $avatar = ulbr_get_profile_avatar($user->ID, 96);

        $output .= '<div class="user-item">';
        $output .= '<div class="user-avatar">' . $avatar . '</div>';
        $output .= '<div class="user-details">';
        $output .= '<h4>' . $name_output . '</h4>';
        $output .= '<p>' . esc_html($email) . '</p>';

        if (!empty($billing_phone)) {
            $output .= '<p>' . esc_html($billing_phone) . '</p>';
        }

        $output .= '</div>';
        $output .= '</div>';
    }

    $output .= '</div>';

    if ($atts['search'] && empty($atts['userid'])) {
        $output .= '
        <script>
        (function() {
            var searchInput = document.getElementById("search-' . esc_js($unique_id) . '");
            if (!searchInput) {
                return;
            }

            searchInput.addEventListener("input", function() {
                var filter = this.value.toLowerCase();
                var userItems = document.querySelectorAll("#' . esc_js($unique_id) . ' .user-item");

                userItems.forEach(function(item) {
                    var text = item.innerText.toLowerCase();
                    item.style.display = text.includes(filter) ? "" : "none";
                });
            });
        })();
        </script>';
    }

    return $output;
}
add_shortcode('user_list_by_role', 'user_list_by_role_shortcode');

function add_custom_user_profile_fields($user) { ?>
    <h3>Extra Profielinformatie</h3>
    <table class="form-table">
        <tr>
            <th><label for="function">Functie</label></th>
            <td><input type="text" name="function" id="function" value="<?php echo esc_attr(get_user_meta($user->ID, 'function', true)); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="team">Team</label></th>
            <td><input type="text" name="team" id="team" value="<?php echo esc_attr(get_user_meta($user->ID, 'team', true)); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="instructor_level">Instructeursniveau</label></th>
            <td><input type="text" name="instructor_level" id="instructor_level" value="<?php echo esc_attr(get_user_meta($user->ID, 'instructor_level', true)); ?>" class="regular-text" /></td>
        </tr>
    </table>
<?php }
add_action('show_user_profile', 'add_custom_user_profile_fields');
add_action('edit_user_profile', 'add_custom_user_profile_fields');

function save_custom_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['function'])) {
        update_user_meta($user_id, 'function', sanitize_text_field(wp_unslash($_POST['function'])));
    }

    if (isset($_POST['team'])) {
        update_user_meta($user_id, 'team', sanitize_text_field(wp_unslash($_POST['team'])));
    }

    if (isset($_POST['instructor_level'])) {
        update_user_meta($user_id, 'instructor_level', sanitize_text_field(wp_unslash($_POST['instructor_level'])));
    }
}
add_action('personal_options_update', 'save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'save_custom_user_profile_fields');

function user_list_by_role_styles() {
    echo '<style>
    .user-list {
        margin: 20px 0;
    }
    .user-item {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }
    .user-avatar {
        flex-shrink: 0;
        width: 96px;
        height: 96px;
        margin-right: 15px;
    }
    .user-avatar img,
    .user-avatar .ulbr-avatar {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 6px;
        display: block;
    }
    .user-details {
        flex-grow: 1;
        line-height: 1.6;
    }
    .user-details h4 {
        font-size: 1.2em;
        margin: 0 0 5px;
    }
    .user-details p {
        margin: 0;
        color: #555;
        font-style: italic;
    }
    </style>';
}
add_action('wp_head', 'user_list_by_role_styles');
