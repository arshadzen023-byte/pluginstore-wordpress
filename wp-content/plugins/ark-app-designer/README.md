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


## Version 1.7.3 — Product page controls

- Added product gallery auto-slide, image fit, pagination and full-screen zoom settings.
- Added editable floating Wishlist/Share layout, button size, icon size, gap, offsets, background and icon colour.
- Added Recommended Offers carousel controls for auto-slide, interval, card/image dimensions and sponsored AD badge.
- Product gallery and offer settings are sanitized server-side with bounded numeric values and hex colours.
- The mobile app renders remote product photos through HTTPS-only cached image loading; page transitions and zoom are native Flutter widgets.
