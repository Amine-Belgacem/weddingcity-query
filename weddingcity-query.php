<?php
/**
 * Weddingcity reports. In a PHP snippet plugin, omit this file's opening <?php tag.
 */
$weddingcity_query_settings = array(
    // users_without_listings | users_without_listings_by_date |
    // vendors_without_listings | listings_by_title
    'report' => 'users_without_listings',
    'start_date' => '2021-09-07',
    'end_date' => '2021-09-30',
    // Use 'any' to count listings in every status, including drafts and trash.
    'listing_status' => 'publish',
    'titles' => array(
        'Loca Caftan', 'Douniagency', 'Chaima Drawing', "Tradition'Elles",
        'L evenementiel créatrice d’ambiance', "Muslim Celebration'Z",
        "D'Or et de Caftan Negafa/Ziana", 'Fleur de Henné', 'Beauty Go',
        'Henna Wassila', '1001 Diadèmes', 'Nawsa Décoration', 'Souad Beauty',
        'Rk Ziana Luxury', 'Camille Makeup Artist', 'Zayna Orientale',
        'Diamantnoir.Makeup', 'Ashera', 'Feramo', 'Cakes & Cocotte',
        'Perles de Miel', 'By Monica', 'Tagine and Cakes', 'Wivent',
        'Leomixevents', 'Drive inside', 'DJ Oriental Mariage Mixte',
        '15 Schuman', 'Love_passion_event', 'Nadia bel photography',
        'Leena Bollywood Dancer', 'Jn deco', 'Anastatica Fatima',
        'Royal Mariage', 'Noor Weddings',
    ),
);

if (!defined('ABSPATH')) {
    return;
}

if (!function_exists('weddingcity_query')) {
    /** Return one report as an array; no output or database writes. */
    function weddingcity_query($report, array $options = array())
    {
        $reports = array('users_without_listings', 'users_without_listings_by_date',
            'vendors_without_listings', 'listings_by_title');
        if (!in_array($report, $reports, true)) {
            throw new InvalidArgumentException('Unknown Weddingcity report.');
        }
        if ($report === 'listings_by_title') {
            return weddingcity_query_titles(isset($options['titles']) ? $options['titles'] : array());
        }

        $dated = $report !== 'users_without_listings';
        $date_bounds = $dated ? weddingcity_query_date_bounds($options) : array();
        $status = isset($options['listing_status']) ? $options['listing_status'] : 'publish';
        if (!is_string($status) || ($status !== 'any' && !get_post_status_object($status))) {
            throw new InvalidArgumentException('listing_status must be a registered post status or any.');
        }

        global $wpdb;
        $sql = "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = %s";
        $args = array('listing');
        if ($status !== 'any') {
            $sql .= ' AND post_status = %s';
            $args[] = $status;
        }
        $authors = $wpdb->get_col($wpdb->prepare($sql, $args));
        weddingcity_query_check_database();
        $query = array(
            'fields' => 'all',
            'orderby' => 'ID',
            'order' => 'ASC',
            'exclude' => array_map('intval', $authors),
            'meta_query' => array(array('key' => 'user_role', 'compare' => 'EXISTS')),
        );
        if ($dated) {
            $query['date_query'] = array(array(
                'column' => 'user_registered',
                'after' => $date_bounds[0] . ' 00:00:00',
                'before' => $date_bounds[1] . ' 23:59:59',
                'inclusive' => true,
            ));
        }
        $users = get_users($query);
        weddingcity_query_check_database();
        $vendors = $report === 'vendors_without_listings' ? weddingcity_query_vendors() : array();
        $rows = array();
        $seen = array();
        foreach ($users as $user) {
            $id = (int) $user->ID;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $first_name = weddingcity_query_text(get_user_meta($id, 'first_name', true));
            $last_name = weddingcity_query_text(get_user_meta($id, 'last_name', true));
            $phone = '';
            if (isset($vendors[$id])) {
                $vendor = $vendors[$id];
                $first_name = $vendor['first_name'] !== '' ? $vendor['first_name'] : $first_name;
                $last_name = $vendor['last_name'] !== '' ? $vendor['last_name'] : $last_name;
                $phone = $vendor['phone'];
            }
            $row = array(
                'user_id' => $id,
                'user_email' => $user->user_email,
                'user_first_name' => $first_name,
                'user_last_name' => $last_name,
            );
            if ($dated) {
                $row['user_registration_date'] = $user->user_registered;
            }
            if ($report === 'vendors_without_listings') {
                $row['user_phone'] = $phone;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    function weddingcity_query_date_bounds(array $options)
    {
        $bounds = array();
        foreach (array('start_date', 'end_date') as $key) {
            $value = isset($options[$key]) ? $options[$key] : '';
            if (!is_string($value) || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)
                || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new InvalidArgumentException($key . ' must be a valid date in YYYY-MM-DD format.');
            }
            $bounds[] = $value;
        }
        if ($bounds[0] > $bounds[1]) {
            throw new InvalidArgumentException('start_date must be on or before end_date.');
        }
        return $bounds;
    }

    /** Ignore malformed structured metadata instead of producing PHP notices. */
    function weddingcity_query_text($value)
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** Index published vendor profiles by their user_id metadata, not their author. */
    function weddingcity_query_vendors()
    {
        $posts = get_posts(array(
            'post_type' => 'vendor', 'post_status' => 'publish',
            'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC',
        ));
        weddingcity_query_check_database();
        $vendors = array();
        foreach ($posts as $post) {
            $raw_id = weddingcity_query_text(get_post_meta($post->ID, 'user_id', true));
            if (!ctype_digit($raw_id) || (int) $raw_id < 1 || isset($vendors[(int) $raw_id])) {
                continue;
            }
            $vendors[(int) $raw_id] = array(
                'first_name' => weddingcity_query_text(get_post_meta($post->ID, 'first_name', true)),
                'last_name' => weddingcity_query_text(get_post_meta($post->ID, 'last_name', true)),
                'phone' => weddingcity_query_text(get_post_meta($post->ID, 'user_contact', true)),
            );
        }
        return $vendors;
    }

    /** Preserve SQL title equality and all-status lookup from the original script. */
    function weddingcity_query_titles($titles)
    {
        if (!is_array($titles)) {
            throw new InvalidArgumentException('titles must be an array of nonempty strings.');
        }
        foreach ($titles as $title) {
            if (!is_string($title) || trim($title) === '') {
                throw new InvalidArgumentException('Each listing title must be a nonempty string.');
            }
        }
        global $wpdb;
        $rows = array();
        foreach ($titles as $title) {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s ORDER BY ID ASC LIMIT 1",
                $title, 'listing'
            ));
            weddingcity_query_check_database();
            $post = $id ? get_post((int) $id) : null;
            $author = $post ? get_userdata((int) $post->post_author) : false;
            $rows[] = array(
                'post_title' => $title,
                'found' => (bool) $post,
                'post_id' => $post ? (int) $post->ID : null,
                'post_date' => $post ? $post->post_date : null,
                'post_author_id' => $post ? (int) $post->post_author : null,
                'post_author_email' => $author ? $author->user_email : '',
                'post_author_name' => $author ? weddingcity_query_text(get_user_meta($author->ID, 'first_name', true)) : '',
                'post_author_lastname' => $author ? weddingcity_query_text(get_user_meta($author->ID, 'last_name', true)) : '',
            );
        }
        return $rows;
    }

    function weddingcity_query_check_database()
    {
        global $wpdb;
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('The Weddingcity database query failed. Check the WordPress database error log.');
        }
    }

    /** Render only for administrators; return data separately through weddingcity_query(). */
    function weddingcity_query_display(array $settings)
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        try {
            $report = isset($settings['report']) ? $settings['report'] : '';
            $data = weddingcity_query($report, $settings);
            $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('The report could not be encoded as JSON.');
            }
            echo '<div class="wrap"><h2>Weddingcity query: ' . esc_html($report) . '</h2><pre style="white-space:pre-wrap;overflow-wrap:anywhere">';
            echo esc_html($json);
            echo '</pre></div>';
        } catch (Exception $error) {
            echo '<div class="wrap"><p>Weddingcity query: ' . esc_html($error->getMessage()) . '</p></div>';
        }
    }
}

// Load functions alone from other PHP code by defining this constant before inclusion.
if ((!defined('WEDDINGCITY_QUERY_LIBRARY_ONLY') || !WEDDINGCITY_QUERY_LIBRARY_ONLY)
    && !defined('WEDDINGCITY_QUERY_DISPLAY_REGISTERED')) {
    define('WEDDINGCITY_QUERY_DISPLAY_REGISTERED', true);
    add_action('admin_footer', static function () use ($weddingcity_query_settings) {
        weddingcity_query_display($weddingcity_query_settings);
    });
}
