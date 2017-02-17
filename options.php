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