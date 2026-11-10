=== ARK WordPress GitHub Bridge ===
Contributors: ark
Tags: github, deployment, woocommerce, developer-tools
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Safely export selected live WordPress theme/plugin folders to a private GitHub repository and deploy approved repository files back to hosting without cPanel Terminal.

== Description ==

ARK WordPress GitHub Bridge can poll a private GitHub repository every five minutes (WordPress cron; traffic-dependent) and accept signed GitHub push webhooks for faster deployment. It deploys only files under wp-content/themes/, wp-content/plugins/, and wp-content/mu-plugins/.

Existing files are backed up outside the public_html document root before replacement. Uploads, caches, backups, wp-config.php, database/customer/order data and remote deletions are excluded. File size is limited to 5 MiB and each run processes up to 40 changed files.

== Installation ==

1. Upload the ZIP through WordPress Plugins > Add New > Upload Plugin.
2. Activate ARK WordPress GitHub Bridge.
3. Open ARK GitHub Bridge in the WordPress admin menu.
4. Create a GitHub fine-grained personal access token limited to this private repository with Contents: Read-only and Metadata: Read-only permissions.
5. Enter the owner, repository, branch and token; enable deployment and save.
6. Optionally configure a GitHub push webhook using the displayed REST URL, a long random secret, application/json, and the push event.
7. Run a manual sync and inspect the status before relying on automatic deployment.

== Repository layout ==

Place approved files under:
- wp-content/themes/your-theme/
- wp-content/plugins/your-plugin/
- wp-content/mu-plugins/

== Important ==

Back up the entire website and database before enabling deployment. Test on staging first. Only push trusted code to the configured branch. The GitHub token is stored in WordPress options and must be treated as a server secret. WordPress scheduled tasks run when the site receives traffic; use a real server cron for dependable timing if your hosting provider supports it. This plugin does not sync database content, uploads, plugin settings, WooCommerce orders, or delete files removed from GitHub.

== Version 1.1.0 ==

- Added GitHub connection test.
- Added manual export of explicitly selected live theme/plugin folders to GitHub.
- Export is limited to 100 files per run and 5 MiB per file.
- Continues to exclude uploads, credentials, database/customer data and remote deletions.
