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

// TODO: shortcode for: full changelog (option: download link), latest release post (option: download link), latest release title, latest release link, latest release downlaod button

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
            $content_append = '';
            $tar_url = $data['release']['tarball_url'];
            $zip_url = $data['release']['zipball_url'];
            if (get_option('wgrl-add-download-link')) {
                $content_append = '<br /><p><a href="'.$zip_url.'">[zip]</a> <a href="'.$tar_url.'">[tar]</a></p>';
            }

            $new_post = [
                'post_title' => wp_strip_all_tags( $data['release']['name'] ),
                'post_content' => $data['release']['body'].$content_append,
                'post_author' => get_option('wgrl-post-author'),
                'post_status' => 'publish',
            ];
            if (get_option('wgrl-custom-post-type')) {
                $new_post['post_type'] = 'release';
            }
            $post_id = wp_insert_post( $new_post );

            // Post-post stuff
            add_post_meta($post_id, 'download_tar', $tar_url);
            add_post_meta($post_id, 'download_zip', $zip_url);
            if (get_option('wgrl-tag-post') && get_option('wgrl-tag-post') != '') {
                wp_set_object_terms( $post_id, get_option('wgrl-tag-post'), 'post_tag' );
            }
        } catch(Exception $e) {
            return false;
        }
        return true;
    }
    return false;
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
    register_setting( 'wgrl-options', 'wgrl-add-download-link');
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
    echo '            <td><input type="password" name="wgrl-webhook-secret" value="'. esc_attr( get_option('wgrl-webhook-secret') ) .'" /></td>';
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
    echo '       <td><input type="text" name="wgrl-tag-post" value="'. esc_attr( get_option('wgrl-tag-post') ) .'" /></td>';
    echo '    </tr>';
    echo '    <tr>';
    echo '        <th>Append download link to content</th>';
    echo '        <td>';
    echo '            <select name="wgrl-add-download-link">';
    echo '                <option value="0">No</option>';
    echo '                <option value="1" '.(get_option('wgrl-add-download-link') ? 'selected' : '').'>Yes</option>';
    echo '            </select>';
    echo '        </td>';
    echo '    </tr>';
    echo '    <tr>';
    echo '        <th>Webhook callback URL</th>';
    echo '        <td><code>'. esc_url(admin_url('admin-ajax.php')) . '?action=wgrl_release_post</code></td>';
    echo '    </tr>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '</div>';
}
