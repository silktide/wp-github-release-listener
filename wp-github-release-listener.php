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
    // We will send a response on every request
    header( "Content-Type: application/json" );

    $raw_data = file_get_contents( 'php://input' );
    $hash = hash_hmac( 'sha1', $raw_data, get_option('wgrl-webhook-secret') );

    if ($_SERVER["CONTENT_TYPE"] != 'application/json') {
        echo json_encode( [ 'success' => false, 'error' => 'Wrong content type seleted' ] );
    } else if ( 'sha1=' . $hash != $_SERVER['HTTP_X_HUB_SIGNATURE'] ) {
        echo json_encode( [ 'success' => false, 'error' => 'Failed to validate the secret' ] );
    } else {
        $data = json_decode($raw_data, true);
        $release_published = wgrl_add_post($data);
        echo json_encode( [ 'success' => true, 'release_published' => $release_published ] );
    }
    exit;
}

function wgrl_add_post($data) {
    if ( isset($data['action']) && isset($data['release']) ) {
        global $wpdb;
        try {
            $name = $data['release']['name'] != '' ? $data['release']['name'] : $data['release']['tag_name'];
            $new_post = [
                'post_title' => wp_strip_all_tags( $name ),
                'post_content' => $data['release']['body'],
                'post_author' => get_option('wgrl-post-author'),
                'post_status' => 'publish',
            ];
            if (get_option('wgrl-custom-post-type')) {
                $new_post['post_type'] = 'release';
            }
            $post_id = wp_insert_post( $new_post );

            // These have to be run after inserting the post due to user right restrictions
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

    $query = wgrl_get_query($options['limit']);
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $return .= '<div class="release">';
            $return .= ($options['title'] || $options['date']) ? '<h3 class="release-title">' : '';
            $return .= $options['title'] ? get_the_title() : '';
            $return .= ($options['title'] && $options['date']) ? ' - ' : '';
            $return .= $options['date'] ? get_the_date() : '';
            $return .= ($options['title'] || $options['date']) ? '</h3>' : '';
            $return .= '<div class="release-body">'.apply_filters('the_content', get_the_content()).'</div>';
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

    $query = wgrl_get_query(1);
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

function wgrl_get_query($limit) {
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
    ?>
    <div class="wrap">
        <h1>GitHub release listener setup</h1>
        <form method="post" action="options.php" style="text-align: left;">
            <?php settings_fields('wgrl-options'); ?>
            <?php do_settings_sections( 'wgrl-options' ); ?>
            <table class="form-table">
                <tr>
                    <th>Secret</th>
                        <td><input type="password" name="wgrl-webhook-secret" value="<?= esc_attr( get_option('wgrl-webhook-secret') ) ?>" required /></td>
                </tr>
                <tr>
                    <th>Payload URL</th>
                    <td><code><?= esc_url(admin_url('admin-ajax.php')) ?>?action=wgrl_release_post</code></td>
                </tr>
            </table>

            <a href="#" id="wgrl_help_link" onClick="showHelp()">How do I use this?</a>
            <div id="wgrl_help" class="card" style="display:none;">
                <h3>Connecting with GitHub</h3>
                <ol>
                    <li>Go to your project settings on GitHub</li>
                    <li>Select Webhooks -> Add webhook</li>
                    <li>Copy payload URL from here to GitHub</li>
                    <li>Select "application/json" as content type</li>
                    <li>Create a passcode (a random string) and copy it to "Secret" field on both here and GitHub</li>
                    <li>Choose "Let me select individual events" as triggers</li>
                    <li>Tick "Release" and untick everything else</li>
                    <li>Save your plugin settings</li>
                    <li>Click "Add webhook" on GitHub</li>
                </ol>
                <p>
                    GitHub sends a ping to your payload URL on webhook activation.
                    If the activation was successful it should return status 200 and <code>{"success":true,"release_published":false}</code>.
                    Please note that nothing will be published on your site before an actual release is made on GitHub.
                </p>
                <a href="#" onClick="closeHelp()">Close</a>
            </div>

            <table class="form-table">
                <tr>
                    <th>Assign posts to user</th>
                    <td>
                        <?= wp_dropdown_users(['name' => 'wgrl-post-author', 'echo' => false, 'selected' => get_option('wgrl-post-author') ]) ?>
                        <p class="description">User must have post create and publish capabilities</p>
                    </td>
                </tr>
                <tr>
                    <th>Post type</th>
                    <td>
                        <select name="wgrl-custom-post-type" id="wgrl-custom-post-type">
                            <option value="0">Post</option>
                            <option value="1" <?= (get_option('wgrl-custom-post-type') ? 'selected' : '') ?>>Release (custom post type)</option>
                        </select>
                        <p class="description">Choose Release if you do not wish to list releases with your other posts</p>
                    </td>
                </tr>
                <tr id="wgrl-tag-post-tr">
                    <th>Tag post</th>
                   <td>
                       <input type="text" name="wgrl-tag-post" value="<?= wgrl_get_custom_tag() ?>" required />
                       <p class="description">Tag is used to list posts containing release notes, e.g. using shortcode</p>
                   </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
        var option = jQuery( "#wgrl-custom-post-type" );
        var tagLine = jQuery('#wgrl-tag-post-tr');
        var helpLink = jQuery('#wgrl_help_link');
        var help = jQuery('#wgrl_help');
        option.change(function() {toggleTagline()});
        function toggleTagline() {
            if ( option.val() == 1 ) {
                tagLine.hide();
            } else {
                tagLine.show();
            }
        }
        function showHelp() {
            help.show();
            helpLink.hide();
        }
        function closeHelp() {
            help.hide();
            helpLink.show();
        }
        toggleTagline();
    </script>
    <?php
}
