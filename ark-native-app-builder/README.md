# ARK Native App Builder 0.2.0

Configure a native Flutter Android app and trigger an APK build from GitHub Actions.

## Default app icon

Open **ARK App Builder → Settings**, choose a square PNG from the WordPress Media Library in **Default Android App Icon**, then save settings. The saved HTTPS media URL is included in each future workflow dispatch. The build workflow generates launcher PNGs for all Android density buckets and adaptive launcher icons. The setting persists for later app builds; it does not need to be selected again.

A square 512×512 or larger transparent PNG is recommended. Leave the field empty to use the standard Flutter launcher icon.

## Build

Set the GitHub owner, repository, workflow file and fine-grained token with Actions write permission. Save settings, then select **Generate Android APK**. The workflow uploads the completed APK to the configured WordPress `/wp-json/ark-native/v1/build-artifact` endpoint.
