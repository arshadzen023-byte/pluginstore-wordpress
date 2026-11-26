# ARK App Designer WordPress Plugin

**Plugin:** ARK App Designer 1.17.4  
**GlowHit app engine:** 1.0.41 (build 41)  
**Companion app:** ARK Studio 1.0.0

## 1.17.4 Native Navigation Bar Builder persistence
- Adds a separate native `navigation_bar` JSON contract without replacing the legacy web-designer `navigation` settings.
- Persists up to five navigation buttons with fixed/shifting mode, order, labels, app links, custom URLs, icon families, active icons and per-item colors.
- Publishes the sanitized navigation JSON in the API response so the Flutter Designer can verify that WordPress retained the exact configuration.

ARK App Designer controls supported Flutter app layouts and splash configuration from WordPress. Install it as a separate plugin next to WooCommerce and the ARK App Push Notifications plugin.

## 1.17.2 Native page routing, theme and animation fixes
- Opens Categories, Wishlist and Account in the full native app page shell when selected from an in-app component link, including page heading, back navigation, background, status bar and navigation bar styling.
- Applies published text/background contrast at the app Navigator level so dark page backgrounds keep readable headings, icons and text across pushed routes.
- Replays section entrance animations when a new published design version arrives. Supports Fade, Slide, Zoom, Bounce, Rotate and 3D Flip with configurable duration.
- Uses a shared native page shell for component links to Categories, Wishlist and Account; Cart, Orders, Notifications and Product continue to use their full native screens.
- Makes the Account welcome/profile header wrap long names and email addresses safely at larger Android text scaling.
- Requires GlowHit app engine 1.0.41 or later for the native routing, theme and animation changes.


- Default Heading Bar background images now persist their WordPress Media Library attachment ID and are restored from the attachment after refresh.
- Saved Draft and Published configurations are recursively merged with newer defaults; stale editor tabs cannot remove fields that they do not know about.
- Custom pages can be created, edited, linked from component destinations, published and opened in the native app.
- Heading bars can be placed in the page component order and support background effects and animation.
- Loading animation settings control enablement, size, duration and page visibility; the bundled Lottie is used when no remote JSON URL is set.
- Native section and gallery keys keep image/slider state stable while scrolling; image requests use the cached image layer.

## Install
1. Download the `ark-app-designer` folder as a ZIP, or use the GitHub Actions artifact `ark-app-designer-1.17.2`.
2. WordPress Admin → Plugins → Add Plugin → Upload Plugin.
3. Upload and activate **ARK App Designer**.
4. Open **ARK App Designer** in the WordPress admin menu.
5. Install GlowHit APK 1.0.41 or later that contains the server-driven UI engine.

The app requests its published design from:
`/wp-json/ark-designer/v1/config`

The public endpoint returns layout/theme/splash JSON only. Draft and GitHub source endpoints require a logged-in WordPress administrator with `manage_options` and a WordPress REST nonce.

## Visual Designer

The designer uses a full-screen workspace with larger typography, a searchable component library and an editable menu builder.
- Pages: Home, Search, Categories, Wishlist, Product and Account.
- Add allow-listed components from the left palette.
- Click a component to edit its properties.
- Drag a component by its title to reorder it; use the arrow buttons for precise movement.
- Hide/show components, change titles, descriptions, product sources, grid columns and spacing.
- Banner sliders support multiple images, per-slide title/link mode, Media Library selection, height, card width, auto-slide interval, transition speed, dots, arrows and image fit.
- Search has its own drag-and-drop page, recent searches, image-backed live product suggestions, trending/recommended product rails, configurable placeholder/colours, voice search (English/Hindi locale) and camera text search.
- Product grids/sliders support configurable card sizing and optional destination links.
- Component library includes searchable components, FAQ accordion, trust badges and coupon cards.
- Theme controls: primary/accent/background/surface/text colours, card radius and typography scale.
- App identity: in-app name, tagline, Media Library icon/logo and splash settings. The APK build requires a published icon/logo so the Android launcher icon does not silently fall back to the old default.
- Icon visibility: independently show/hide app icon, app name, page title, refresh, notifications and cart on Home, Search, Categories, Wishlist, Product and Account.
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


## Version 1.8.1 — Product page controls

- Added product gallery auto-slide, image fit, pagination and full-screen zoom settings.
- Added editable floating Wishlist/Share layout, button size, icon size, gap, offsets, background and icon colour.
- Added Recommended Offers carousel controls for auto-slide, interval, card/image dimensions and sponsored AD badge.
- Product gallery and offer settings are sanitized server-side with bounded numeric values and hex colours.
- The mobile app renders remote product photos through HTTPS-only cached image loading; page transitions and zoom are native Flutter widgets.


## Version 1.8.1 — Image Slider persistence and native tap destinations

- Image Slider slides keep both the WordPress Media Library attachment ID and canonical image URL in saved/published configuration.
- Explicitly removed slides remain removed after refresh; empty sliders can be saved without restoring stale legacy data.
- The common Tap action editor offers native page and published product destinations; deep links and HTTPS website links remain selectable.
- Delivery card settings are normalized and published for the native app renderer.
- Product Description preview length defaults to 80 words; the native renderer reveals the full text and embedded images when expanded.


## ARK Live Slider — version 1.8.1

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


## 1.8.1 — Product card layout and 3D controls

Product grid, product carousel and recently viewed components now save and render product card design, card animation, 3D depth, corner radius, image aspect ratio and image fit. The 3D styles include interactive tilt, elevated depth and a glass finish. The selected options are retained in the published native configuration.


## Version 1.17.3 — Product media and banner child migration

- Studio targets now return WooCommerce product featured/gallery image URLs and prices for native product-layout previews.
- Studio category targets now return the WooCommerce category thumbnail URL.
- Legacy Banner Slider children are migrated into the SQL slider payload during normalization, so selected child images are not dropped.
