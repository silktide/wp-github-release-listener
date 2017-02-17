# GitHub release listener for WordPress
Wordpress plugin to listen to a GitHub webhook and create a new post every time a release is made.

## Installation
- Upload the zip via WP admin panel (Plugins > Add New > Upload Plugin) or extract it to your plugins folder

## Setup
1. Open plugin settings (Settings > GitHub release listener)
2. Go to your project settings on GitHub
3. Select Webhooks -> Add webhook
4. Copy payload URL from the plugin settings to GitHub
5. Select "application/json" as content type
6. Create a passcode (a random string) and copy it to "Secret" field on both plugin settings and GitHub
7. Choose "Let me select individual events" as triggers
8. Tick "Release" and untick everything else
9. Save your plugin settings
10. Click "Add webhook" on GitHub

GitHub sends a ping to your payload URL on webhook activation. It can be found under Recent Deliveries and details will open upon clicking on the most recent one. In case the activation was successful the Response tab should read 200 and response body should be {"success":true,"release_published":false}. 

Please note that nothing will be published on your site before an actual release is made on GitHub.

## Usage
A new post (or a custom post type is that option is selected) will be created every time a release is made on GitHub. You can display the release post with your other posts or use the shortcode to generate changelogs or links to the latest release.

Example usage: Create a page named "Changelog" containig the shortcode [wgrl-changelog]

## Shortcode
#### [wgrl-changelog]
Displays full changelog. Shows release name and body by default. 
Options: 
- title - Show release title Default: true
- date - Show release date in the post title.	Default: false
- downloads	- Append download links (zip and tar) to every post. Default: false
- limit	- Number (int) of releases to show.	Default: false

####[wgrl-latest]
Displays data of seleted type from the latest release. Useful for generating download links/buttons.
Options:
- type - What to show. Available values:
 - 'title' - return latest release title
 - 'tag' - return latest release tag
 - 'zip-url' - return URL to download release as a zip file
 - 'tar-url' - return URL to download release as a tar file
 - 'zip-link' - return title, which is a link to download release as a zip file (default)
 - 'tar-link' - return title, which is a link to download release as a tar file
- classes	- Add classes to links. Restricted to types zip-link and tar-link only.
