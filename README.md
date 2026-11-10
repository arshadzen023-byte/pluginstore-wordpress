# pluginstore-wordpress

Private source repository for pluginstore.shop WordPress/WooCommerce custom code.

## Current source inventory

### Theme
- `wp-content/themes/ark-material-commerce/`

### Custom plugins
- `wp-content/plugins/ark-app-designer/`
- `wp-content/plugins/ark-app-push-notifications/`
- `wp-content/plugins/ark-custom-wishlist/`
- `wp-content/plugins/ark-native-api-bridge/`
- `wp-content/plugins/ark-native-app-builder/`
- `wp-content/plugins/ark-woo-product-promo-slider/`

The ARK WordPress GitHub Bridge plugin is currently kept outside this repository's deployment scope. Its own source should be backed up separately before any attempt to deploy or update the bridge itself.

## Deployment layout

Approved deployable files live under:
- `wp-content/themes/<theme-slug>/`
- `wp-content/plugins/<plugin-slug>/`
- `wp-content/mu-plugins/`

The ARK WordPress GitHub Bridge reads this repository and deploys approved files to matching paths under the hosting site's `wp-content` directory.

## Safety rules

- Never commit `wp-config.php`, credentials, API keys, database exports, customer/order data, or production uploads.
- Use a dedicated fine-grained GitHub token restricted to this repository.
- Keep a verified backup before replacing production files.
- Test changes on staging before enabling production auto-deployment.
- Do not deploy the bridge plugin from itself until a separate recovery/rollback path is verified.
- Remote deletions must remain disabled unless a reviewed deletion workflow and backup restore test exist.

## Sync policy

- GitHub `main` is the intended source of truth for the six ARK plugin folders and the ARK theme listed above.
- Hosting-to-GitHub export is for importing/reconciling live changes; it must not silently overwrite newer GitHub commits.
- GitHub-to-hosting deployment must back up each target file before replacement and report updated, skipped, and failed files.
- A successful sync must be followed by a comparison of deployed files against the GitHub commit.
