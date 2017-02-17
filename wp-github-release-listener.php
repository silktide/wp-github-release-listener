<?php
/**
 * Plugin Name: Github release listener
 * Description: Listens to a GitHub webhook and creates a new post every time a release is made.
 * Version: 0.1
 * Author:  Piiu Pilt, Silktide
 * Author URI: http://www.silktide.com
 * License: GPLv2
 * Text Domain: wp-github-release-listener
 */

defined('ABSPATH') or die('No!');

add_action('wp_ajax_nopriv_wgrl_release_post', 'wgrl_new_release_handler');
function wgrl_new_release_handler()
{
    // We will send a response on every request
    header("Content-Type: application/json");

    $raw_data = file_get_contents('php://input');
    $signatureCheck = 'sha1=' . hash_hmac('sha1', $raw_data, get_option('wgrl-webhook-secret'));

    if ($_SERVER["CONTENT_TYPE"] != 'application/json') {
        echo json_encode(['success' => false, 'error' => 'Wrong content type seleted']);
    } else if ($_SERVER['HTTP_X_HUB_SIGNATURE'] != $signatureCheck) {
        echo json_encode(['success' => false, 'error' => 'Failed to validate the secret']);
    } else {
        $data = json_decode($raw_data, true);
        $release_published = wgrl_add_post($data);
        echo json_encode(['success' => true, 'release_published' => $release_published]);
    }
    exit;
}

function wgrl_add_post($data)
{
    if (isset($data['action']) && isset($data['release'])) {
        global $wpdb;
        try {
            $name = $data['release']['name'] != '' ? $data['release']['name'] : $data['release']['tag_name'];
            $new_post = [
                'post_title' => wp_strip_all_tags($name),
                'post_content' => $data['release']['body'],
                'post_author' => get_option('wgrl-post-author'),
                'post_status' => 'publish',
            ];
            if (get_option('wgrl-custom-post-type')) {
                $new_post['post_type'] = 'release';
            }
            $post_id = wp_insert_post($new_post);

            // These have to be run after inserting the post due to user right restrictions
            add_post_meta($post_id, 'release_tag', $data['release']['tag_name']);
            add_post_meta($post_id, 'download_tar', $data['release']['tarball_url']);
            add_post_meta($post_id, 'download_zip', $data['release']['zipball_url']);
            if (!get_option('wgrl-custom-post-type')) {
                wp_set_object_terms($post_id, wgrl_get_custom_tag(), 'post_tag');
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
    return false;
}

add_shortcode('wgrl-changelog', 'wgrl_changelog');
function wgrl_changelog($attributes)
{
    $options = shortcode_atts([
        'limit' => false,
        'title' => true,
        'date' => false,
        'downloads' => false
    ], $attributes);

    $return = '';

    $query = wgrl_get_query($options['limit']);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $titleValues = [];
            if (wgrl_is_true($options['title'])) {
                array_push($titleValues, get_the_title());
            }
            if (wgrl_is_true($options['date'])) {
                array_push($titleValues, get_the_date());
            }
            $zip_url = get_post_meta(get_the_id(), 'download_zip', true);
            $tar_url = get_post_meta(get_the_id(), 'download_tar', true);

            $return .= '<div class="release">';
            $return .= (!empty($titleValues) ? '<h3 class="release-title">'.implode(' - ', $titleValues).'</h3>' : '';
            $return .= '<div class="release-body">' . apply_filters('the_content', get_the_content()) . '</div>';
            if (wgrl_is_true($options['downloads'])) {
                $return .= '<div class="release-downloads">';
                $return .= ($zip_url && $zip_url != '') ? '<a href="' . $zip_url . '">[zip]</a>' : '';
                $return .= ($tar_url && $tar_url != '') ? ' <a href="' . $tar_url . '">[tar]</a>' : '';
                $return .= '</div>';
            }
            $return .= '</div>';
        }
    }
    wp_reset_postdata();

    return $return;
}

add_shortcode('wgrl-latest', 'wgrl_latest');
function wgrl_latest($attributes)
{
    $options = shortcode_atts([
        'type' => 'zip-link',
        'classes' => false
    ], $attributes);

    $query = wgrl_get_query(1);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $classString = $options['classes'] ? ' class="' . $options['classes'] . '"' : '';
            switch ($options['type']) {
                case 'title':
                    return get_the_title();
                case 'tag':
                    return get_post_meta(get_the_id(), 'release_tag', true);
                case 'zip-url':
                    return get_post_meta(get_the_id(), 'download_zip', true);
                case 'tar-url':
                    return get_post_meta(get_the_id(), 'download_zip', true);
                case 'zip-link':
                    return '<a href="' . get_post_meta(get_the_id(), 'download_zip', true) . '"' . $classString . '>' . get_the_title() . '</a>';
                case 'tar-link':
                    return '<a href="' . get_post_meta(get_the_id(), 'download_tar', true) . '"' . $classString . '>' . get_the_title() . '</a>';
            }
        }
    }
    return '';
}

add_action('init', 'wgrl_add_custom_post_type');
function wgrl_add_custom_post_type()
{
    if (get_option('wgrl-custom-post-type')) {
        $args = [
            'labels' => [
                'name' => 'Releases',
                'singular_name' => 'Release'
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true
        ];
        register_post_type('release', $args);
        post_type_supports('release', 'custom-fields');
    }
}

add_action('admin_menu', 'wgrl_menu');
function wgrl_menu()
{
    add_options_page(
        'GitHub release listener settings',
        'GitHub release listener',
        'manage_options',
        'wgrl-options',
        'wgrl_options_page'
    );
}

add_action('admin_init', 'wgrl_register_settings');
function wgrl_register_settings()
{
    register_setting('wgrl-options', 'wgrl-webhook-secret');
    register_setting('wgrl-options', 'wgrl-post-author');
    register_setting('wgrl-options', 'wgrl-custom-post-type');
    register_setting('wgrl-options', 'wgrl-tag-post');
}

function wgrl_options_page()
{
    include plugin_dir_path(__FILE__) . '/options.php';
}


function wgrl_get_query($limit)
{
    $args = [
        'posts_per_page' => $limit
    ];
    if (get_option('wgrl-custom-post-type')) {
        $args['post_type'] = 'release';
    } else {
        $args['tag'] = wgrl_get_custom_tag();
    }
    return new WP_Query($args);
}

function wgrl_get_custom_tag()
{
    if (get_option('wgrl-tag-post') && get_option('wgrl-tag-post') != '') {
        return esc_attr(get_option('wgrl-tag-post'));
    } else {
        return 'release';
    }
}

function wgrl_is_true($option)
{
    return ($option && $option !== 'false');
}
