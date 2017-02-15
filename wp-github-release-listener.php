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

defined( 'ABSPATH' ) or die( 'No!' );

add_action( 'wp_ajax_nopriv_wgrl_release_post', 'wgrl_new_release_handler' );
function wgrl_new_release_handler() {
    $raw_data = file_get_contents( 'php://input' );

    $hash = hash_hmac( 'sha1', $raw_data, get_option('wgrl-webhook-secret') );
    if ( 'sha1=' . $hash != $_SERVER['HTTP_X_HUB_SIGNATURE'] ) {
        header( "Content-Type: application/json" );
        echo json_encode( [ 'success' => false, 'error' => 'Failed to validate the secret' ] );
        exit;
    }

    $data = json_decode($raw_data, true);
    if ( isset($data['action']) && isset($data['release']) ) {
        global $wpdb;
        $new_post = [
            'post_title' => wp_strip_all_tags( $data['release']['tag_name'] ),
            'post_content' => $data['release']['body'],
            'post_author' => get_option('wgrl-post-author'),
            'post_status' => 'publish'
        ];
        wp_insert_post( $new_post );
    }

    header( "Content-Type: application/json" );
    echo json_encode( [ 'success' => true ] );
    exit;
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
    add_action( 'admin_init', 'wgrl_register_settings' );
}

function wgrl_register_settings() {
    register_setting( 'wgrl-options', 'wgrl-webhook-secret' );
    register_setting( 'wgrl-options', 'wgrl-post-author' );

}

function wgrl_options_page() {
    echo '<div class="wrap"><h2>GitHub release listener settings</h2>';
    echo '<form method="post" action="options.php" style="text-align: left;">';
    settings_fields('wgrl-options');
    do_settings_sections( 'wgrl-options' );
    echo '<table><tr><th>Webhook secret</th><td><input type="password" name="wgrl-webhook-secret" value="'. esc_attr( get_option('wgrl-webhook-secret') ) .'" /></td></tr>';
    echo '<tr><th>Assign posts to user</th><td>'. wp_dropdown_users(['name' => 'wgrl-post-author', 'echo' => false, 'selected' => get_option('wgrl-post-author') ]). '</td></tr>';
    echo '<tr><th>Webhook callback URL</th><td><code>'. esc_url(admin_url('admin-ajax.php')) . '?action=wgrl_release_post</code></td></tr></table>';
    submit_button();
    echo '</form></div>';
}
