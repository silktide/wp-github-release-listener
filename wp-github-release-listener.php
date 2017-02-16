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

// TODO: shortcode - latest release downlaod button
// TODO: option sections

defined( 'ABSPATH' ) or die( 'No!' );

add_action( 'wp_ajax_nopriv_wgrl_release_post', 'wgrl_new_release_handler' );
function wgrl_new_release_handler() {
    $raw_data = file_get_contents( 'php://input' );
    header( "Content-Type: application/json" );

    // Check secret
    $hash = hash_hmac( 'sha1', $raw_data, get_option('wgrl-webhook-secret') );
    if ( 'sha1=' . $hash != $_SERVER['HTTP_X_HUB_SIGNATURE'] ) {
        echo json_encode( [ 'success' => false, 'error' => 'Failed to validate the secret' ] );
        exit;
    }

    $data = json_decode($raw_data, true);
    $release_published = wgrl_add_post($data);
    echo json_encode( [ 'success' => true, 'release_published' => $release_published ] );
    exit;
}

function wgrl_add_post($data) {
    if ( isset($data['action']) && isset($data['release']) ) {
        global $wpdb;
        try {
            $new_post = [
                'post_title' => wp_strip_all_tags( $data['release']['name'] ),
                'post_content' => $data['release']['body'],
                'post_author' => get_option('wgrl-post-author'),
                'post_status' => 'publish',
            ];
            if (get_option('wgrl-custom-post-type')) {
                $new_post['post_type'] = 'release';
            }
            $post_id = wp_insert_post( $new_post );

            // Post-post stuff
            add_post_meta($post_id, 'download_tar', $data['release']['tarball_url']);
            add_post_meta($post_id, 'download_zip', $data['release']['zipball_url']);
            if (!get_option('wgrl-custom-post-type')) {
                wp_set_object_terms( $post_id, wgrl_get_custom_tag(), 'post_tag' );
            }
        } catch(Exception $e) {
            return false;
        }
        return true;
    }
    return false;
}

add_shortcode( 'wgrl_changelog', 'wgrl_changelog' );
function wgrl_changelog( $atts ) {
    $options = shortcode_atts( [
        'limit' => false,
        'title' => true,
        'date' => false,
        'downloads' => false
    ], $atts );

    $return = '';

    $query = new WP_Query( wgrl_get_query_args($options['limit']) );
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $return .= '<div class="release">';
            $return .= ($options['title'] || $options['date']) ? '<h2 class="release-title">' : '';
            $return .= $options['title'] ? get_the_title() : '';
            $return .= ($options['title'] && $options['date']) ? ' - ' : '';
            $return .= $options['date'] ? get_the_date() : '';
            $return .= ($options['title'] || $options['date']) ? '</h2>' : '';
            $return .= '<div class="release-body">'.get_the_content().'</div>';
            if ($options['downloads']) {
                $zip_url = get_post_meta(get_the_id(), 'download_zip', true);
                $tar_url = get_post_meta(get_the_id(), 'download_tar', true);
                $return .= '<div class="release-downloads"><a href="'.$zip_url.'">[zip]</a> <a href="'.$tar_url.'">[tar]</a></div>';
            }
            $return .= '</div>';
        }
    }
    wp_reset_postdata();
    return $return;
}

add_shortcode( 'wgrl_latest', 'wgrl_latest' );
function wgrl_latest($atts) {
    $options = shortcode_atts( [
        'type' => 'zip_link',
        'classes' => false
    ], $atts );

    $query = new WP_Query( wgrl_get_query_args(1) );
    if ( $query->have_posts() ) {
        while ($query->have_posts()) {
            $query->the_post();
            $classString = $options['classes'] ? ' class="'.$options['classes'].'"' : '';
            switch ($options['type']) {
                case 'title':
                    return get_the_title();
                case 'zip_url':
                    return get_post_meta(get_the_id(), 'download_zip', true);
                case 'tar_url':
                    return get_post_meta(get_the_id(), 'download_zip', true);
                case 'zip_link':
                    return '<a href="'.get_post_meta(get_the_id(), 'download_zip', true).'"'.$classString.'>'.get_the_title().'</a>';
                case 'tar_link':
                    return '<a href="'.get_post_meta(get_the_id(), 'download_tar', true).'"'.$classString.'>'.get_the_title().'</a>';
            }
        }
    }
    return '';
}

add_action('init', 'wgrl_add_custom_post_type');
function wgrl_add_custom_post_type() {
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
        register_post_type( 'release', $args);
        // TODO: is this actulally supported?
        post_type_supports( 'release', 'custom-fields' );
    }
}

function wgrl_get_query_args($limit) {
    $args = [
        'posts_per_page' => $limit
    ];
    if (get_option('wgrl-custom-post-type')) {
        $args['post_type'] = 'release';
    } else {
        $args['tag'] = wgrl_get_custom_tag();
    }
    return $args;
}

function wgrl_get_custom_tag() {
    if (get_option('wgrl-tag-post') && get_option('wgrl-tag-post') != '' ) {
        return esc_attr( get_option('wgrl-tag-post') );
    } else {
        return 'release';
    }
}

add_action( 'admin_menu', 'wgrl_menu' );
function wgrl_menu() {
    add_options_page(
        'GitHub release listener settings',
        'GitHub release listener',
        'manage_options',
        'wgrl-options',
        'wgrl_options_page'
    );
}

add_action( 'admin_init', 'wgrl_register_settings' );
function wgrl_register_settings() {
    register_setting( 'wgrl-options', 'wgrl-webhook-secret' );
    register_setting( 'wgrl-options', 'wgrl-post-author' );
    register_setting( 'wgrl-options', 'wgrl-custom-post-type');
    register_setting( 'wgrl-options', 'wgrl-tag-post');
}

function wgrl_options_page() {
    echo '<div class="wrap">';
    echo '<h2>GitHub release listener settings</h2>';
    echo '<form method="post" action="options.php" style="text-align: left;">';
    settings_fields('wgrl-options');
    do_settings_sections( 'wgrl-options' );
    echo '<table>';
    echo '    <tr>';
    echo '        <th>Webhook secret</th>';
    echo '            <td><input type="password" name="wgrl-webhook-secret" value="'. esc_attr( get_option('wgrl-webhook-secret') ) .'" required /></td>';
    echo '    </tr>';
    echo '    <tr>';
    echo '        <th>Assign posts to user</th>';
    echo '        <td>'. wp_dropdown_users(['name' => 'wgrl-post-author', 'echo' => false, 'selected' => get_option('wgrl-post-author') ]). '</td>';
    echo '    </tr>';
    echo '    <tr>';
    echo '        <th>Post type</th>';
    echo '        <td>';
    echo '            <select name="wgrl-custom-post-type">';
    echo '                <option value="0">Post</option>';
    echo '                <option value="1" '.(get_option('wgrl-custom-post-type') ? 'selected' : '').'>Release</option>';
    echo '            </select>';
    echo '        </td>';
    echo '    </tr>';
    echo '    <tr>';
    echo '        <th>Tag post (only for post type post)</th>';
    echo '       <td><input type="text" name="wgrl-tag-post" value="'. wgrl_get_custom_tag() .'" required /></td>';
    echo '    </tr>';
    echo '    <tr>';
    echo '        <th>Webhook callback URL</th>';
    echo '        <td><code>'. esc_url(admin_url('admin-ajax.php')) . '?action=wgrl_release_post</code></td>';
    echo '    </tr>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '</div>';
    echo '<script>';

    echo '</script>';
}
