# pluginstore-wordpress

Private source repository for pluginstore.shop WordPress/WooCommerce custom code.

## Deployment layout

Approved deployable files must live under:

- `wp-content/themes/<theme-slug>/`
- `wp-content/plugins/<plugin-slug>/`
- `wp-content/mu-plugins/`

The ARK WordPress GitHub Bridge plugin (distributed separately as a WordPress ZIP) reads this repository and deploys approved files to matching paths under the hosting site's `wp-content` directory.

## Safety

- Do not commit `wp-config.php`, credentials, API keys, database exports, customer/order data, or production uploads.
- Use a dedicated fine-grained GitHub token restricted to this repository.
- Test commits on staging before enabling production deployment.
- Existing target files are backed up before replacement.
- Remote deletions are not applied by Bridge v1.0.0.

## Important

This repository currently contains the initial README only. The live site's theme and plugin source has **not** been imported yet. Do not enable production auto-deployment until the intended source files have been added and reviewed.
