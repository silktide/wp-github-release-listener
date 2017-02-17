# GitHub release listener for WordPress
Wordpress plugin to listen to a GitHub webhook and create a new post every time a release is made.

## Installation
- Upload the zip via WP admin panel (Plugins > Add New > Upload Plugin) or extract it to your plugins folder

## Setup
- Open plugin settings (Settings > GitHub release listener)
- Go to your project settings on GitHub
- Select Webhooks from the menu
- Click "Add webhook" (top right)
- Copy payload URL from the plugin settings to GitHub
- Select "application/json" as content type
- Create a passcode (a random string) and copy it to "Secret" field on both plugin settings and GitHub
- Choose "Let me select individual events" as triggers
- Tick "Release" and untick everything else
- Save your plugin settings
- Click "Add webhook" on GitHub

GitHub sends a ping to your payload URL on webhook activation. It can be found under Recent Deliveries and details will open upon clicking on the most recent one. In case the activation was successful the Response tab should read 200 and response body should be {"success":true,"release_published":false}. 

Nothing will be published on your site before an actual release is made on GitHub.

## Usage
A new post (or a custom post type is that option is selected) will be created every time a release is made on GitHub. You can display the release post with your other posts or use the shortcode to generate changelogs or links to the latest release.

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
 - 'zip-url' - return URL to download release as a zip file
 - 'tar-url' - return URL to download release as a tar file
 - 'zip-link' - return title, which is a link to download release as a zip file (default)
 - 'tar-link' - return title, which is a link to download release as a tar file
- classes	- Add classes to links. Restricted to types zip-link and tar-link only.
