# ARK App Designer WordPress Plugin

**Plugin:** ARK App Designer 1.6.3  
**GlowHit app engine:** 1.0.24 (build 23)  
**Companion app:** ARK Studio 1.0.0

ARK App Designer controls supported Flutter app layouts and splash configuration from WordPress. Install it as a separate plugin next to WooCommerce and the ARK App Push Notifications plugin.

## Install
1. Download the `ark-app-designer` folder as a ZIP, or use the GitHub Actions artifact `ark-app-designer-1.6.3`.
2. WordPress Admin → Plugins → Add Plugin → Upload Plugin.
3. Upload and activate **ARK App Designer**.
4. Open **ARK App Designer** in the WordPress admin menu.
5. Install GlowHit APK 1.0.21 or later that contains the server-driven UI engine.

The app requests its published design from:
`/wp-json/ark-designer/v1/config`

The public endpoint returns layout/theme/splash JSON only. Draft and GitHub source endpoints require a logged-in WordPress administrator with `manage_options` and a WordPress REST nonce.

## Visual Designer

The designer uses a full-screen workspace with larger typography, a searchable component library and an editable menu builder.
- Pages: Home, Categories, Product and Account.
- Add allow-listed components from the left palette.
- Click a component to edit its properties.
- Drag a component by its title to reorder it; use the arrow buttons for precise movement.
- Hide/show components, change titles, descriptions, product sources, grid columns and spacing.
- Banner sliders support multiple images, per-slide title/link mode, Media Library selection, height, card width, auto-slide interval, transition speed, dots, arrows and image fit.
- Search supports rotating hint phrases and live product filtering while typing.
- Product grids/sliders support configurable card sizing and optional destination links.
- Component library includes searchable components, FAQ accordion, trust badges and coupon cards.
- Theme controls: primary/accent/background/surface/text colours, card radius and typography scale.
- App identity: in-app name and tagline.
- Navigation: show/hide the bottom bar; add/delete menu items; configure labels, icons, in-app page destinations, deep links, Chrome URLs and animation presets.
- Splash screen: image/logo with real image preview, background, title, subtitle, fit, duration and native Flutter entrance animation (none, fade, zoom or slide).
- Save Draft does not affect customers. Publish creates a versioned configuration and keeps up to 20 previous revisions.

## Designer 1.6.3 improvements
- All configured slider images render in the multi-slide preview; the Media Library picker supports adding multiple slides.
- In-app slider links can target all supported native pages and up to 500 published WooCommerce products.
- App identity supports a remotely published in-app header icon URL; Android launcher artwork remains bundled in the APK.
- Every component exposes size and spacing controls for width, height, minimum height, maximum width, per-edge padding/margins and corner radius.
- Properties panel collapse/expand keeps its toggle visible. Delivery address selector adds phone, city/PIN, address switching and compact-layout settings.

## Mobile app behavior
- GlowHit reads the last good config from local app storage, then fetches the published config.
- While the app is running, it checks for a new config approximately once per minute.
- Home, Categories, Product and authenticated Account page sections are rendered using the native allow-listed components already compiled into the APK.
- The in-app Flutter splash can be changed remotely. Android's system launch drawable is static; the remotely configurable splash appears after the Flutter engine opens.
- A previous cached design remains available if WordPress is temporarily offline.

## GitHub source editor
The **App source files** panel can browse and edit text source files in the configured private GitHub repository:
- `flutter-app/` (including Android/iOS text configuration, Dart, localization, SVG and build files)
- `studio-app/`
- `wordpress-addon/`
- `.github/workflows/`
- `docs/`
- root `README.md` and `.gitignore`

Text source and configuration files are exposed, including Dart, Android/iOS text configuration, localization, SVG, web and build files. Binary images, fonts and APKs are not edited in the text editor; use WordPress Media Library for remotely hosted design images. Files are committed to the configured working branch. This is real source editing, not hot-patching a customer's installed APK. A code edit still requires a new build/release before it runs on user devices.

### Configure GitHub
1. Create a GitHub fine-grained personal access token limited to this private repository.
2. Grant **Contents: Read and write**. No organization/repository admin permission is needed.
3. In ARK App Designer → **App source files**, enter owner, repository, working branch and token, then Save GitHub access.
4. The token is stored only in WordPress options on the server. It is never sent to GlowHit or ARK Studio.
5. Select a file, edit its source and choose **Commit file to GitHub**.

Default repository/branch:
- Owner: `arshadzen023-byte`
- Repository: `ark-native-app`
- Working branch: `feat/ark-app-designer-server-driven-engine`

Use a dedicated branch, review commits and open/merge a pull request before deploying code edits to production.

## ARK Studio
ARK Studio is a separate Android companion app. It opens the same WordPress designer in a persistent WebView, so you can edit layouts and GitHub source from a phone. Sign in with a WordPress administrator account. The privileged WebView is restricted to `pluginstore.shop`; external links open outside the app.

Build it with GitHub Actions → **Build ARK Studio Android App**.

## Important capability boundary
Server-driven changes can modify layout, content, themes, navigation labels, component visibility/order and remotely hosted media without reinstalling APKs. New native Android behavior (new platform permissions, Bluetooth/hardware access, background services, native SDK integrations or a component not present in the app's renderer) still needs Flutter/Android source code and a new APK build. Remote JSON is data; it cannot safely execute arbitrary downloaded Dart code.

## API routes
- `GET /wp-json/ark-designer/v1/config` – public published config.
- `GET /wp-json/ark-designer/v1/studio/config` – administrator draft config.
- `POST /wp-json/ark-designer/v1/studio/draft` – validate and save draft.
- `POST /wp-json/ark-designer/v1/studio/publish` – version and publish validated config.
- `GET /wp-json/ark-designer/v1/studio/components` – component catalog.
- `GET /wp-json/ark-designer/v1/studio/github/tree` – authorized text file list.
- `GET /wp-json/ark-designer/v1/studio/github/file?path=...` – authorized file read.
- `POST /wp-json/ark-designer/v1/studio/github/file` – commit file to configured branch.
- `POST /wp-json/ark-designer/v1/studio/github/settings` – save GitHub settings securely.

## 1.6.3 updates
- Full-screen image-only splash configuration with background, fit and duration controls.
- Header background/text colours and smooth/gradient header transition controls.
- Editable bottom navigation labels/icons/targets and live phone preview.
- Auto-slide interval setting for each image slider.
- Fixed component/property rails: the centre phone canvas scrolls independently; on mobile the two editing rails remain in their own scroll regions.
- Published config fields are preserved and sanitized by the server.


## Version 1.8.0 — Product page controls

- Added product gallery auto-slide, image fit, pagination and full-screen zoom settings.
- Added editable floating Wishlist/Share layout, button size, icon size, gap, offsets, background and icon colour.
- Added Recommended Offers carousel controls for auto-slide, interval, card/image dimensions and sponsored AD badge.
- Product gallery and offer settings are sanitized server-side with bounded numeric values and hex colours.
- The mobile app renders remote product photos through HTTPS-only cached image loading; page transitions and zoom are native Flutter widgets.


## Version 1.8.0 — Image Slider persistence and native tap destinations

- Image Slider slides keep both the WordPress Media Library attachment ID and canonical image URL in saved/published configuration.
- Explicitly removed slides remain removed after refresh; empty sliders can be saved without restoring stale legacy data.
- The common Tap action editor offers native page and published product destinations; deep links and HTTPS website links remain selectable.
- Delivery card settings are normalized and published for the native app renderer.
- Product Description preview length defaults to 80 words; the native renderer reveals the full text and embedded images when expanded.


## ARK Live Slider — version 1.8.0

The old embedded **Image slider** component has been replaced by **ARK Live Slider**. Designer JSON now stores only a short `slider_key`; it no longer embeds the full array of banner image URLs.

### Fast storage and delivery
- Image files are uploaded to the existing WordPress Media Library / `wp-content/uploads` storage. The plugin saves the WordPress attachment ID and optimized image URL (the `large` image size) for each slide.
- Slider settings live in `{$wpdb->prefix}ark_live_sliders` and individual slide records live in `{$wpdb->prefix}ark_live_slider_items`. The actual SQL table prefix is generated from WordPress `$wpdb->prefix` (the supplied database dump uses `wp0j_`).
- Flutter requests `/wp-json/ark-designer/v1/sliders/{slider_key}`; the response is small JSON metadata plus image URLs, and the image bytes are served directly from Uploads/CDN.
- The public endpoint returns only enabled slides whose optional start/end schedule is active, with short-lived cache headers. Flutter also caches the last successful slider response for offline fallback.
- Existing published/draft `banner_slider` sections are migrated into the SQL tables and converted to `ark_live_slider` references.

### Working controls and capabilities
1. Batch-add or replace images from the Media Library.
2. In-app links to Home, Categories, Product, Account, Wishlist, Cart, WooCommerce products and WooCommerce categories.
3. Website links and app deep links.
4. Per-slide start/end scheduling and active/inactive toggles.
5. Automatic playback and adjustable slide duration.
6. Infinite looping or stop-at-last-slide mode.
7. Slide, fade and zoom transitions.
8. Manual swipe plus optional previous/next arrows.
9. Dots, progress-bar or hidden pagination.
10. Image cover/contain/fill, width, height, gap and corner radius controls.
11. Optional slide title, subtitle, alt text and CTA button.
12. Drag/order arrows and saved slide order.
13. Pause autoplay after a user interaction and resume it again.

The authenticated studio endpoint is `/wp-json/ark-designer/v1/studio/sliders/{slider_key}`; the public app endpoint is `/wp-json/ark-designer/v1/sliders/{slider_key}`.
