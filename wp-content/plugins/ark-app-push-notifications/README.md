# ARK App Push Notifications

WordPress plugin and Flutter integration for sending image notifications to registered ARK Native / GlowHit Android app installations.

## Features

- Foreground messages are also posted to the Android notification shade using a high-importance local notification channel.
- Background/terminated app notifications use Firebase's notification + data payload and Android system tray delivery.
- Notification composer destination selector for a WooCommerce product, published WordPress page, product category, or an explicit external link.
- Product and page selectors generate native `ark://product/ID`, `ark://page/ID` and `ark://category/ID` deep links.
- Same-store HTTPS product/page/category permalinks are resolved by the app and stay inside GlowHit rather than launching Chrome.
- WooCommerce order-created and order-status-changed events send customer-specific FCM notifications to the devices linked to that signed-in customer.
- Order notifications also appear in that customer's in-app notification inbox and open the matching native Order Details screen.
- FCM device tokens are linked to accounts only after the WordPress server verifies the ARK Native app JWT; customer IDs are never trusted from an app request body.

- WordPress admin notification composer: title, message, Media Library image, click URL, optional offer countdown, immediate send, one-time scheduled delivery, and weekly recurring delivery.
- Firebase Cloud Messaging HTTP v1 delivery using a service-account key stored only in WordPress options.
- Batch delivery through WP-Cron to avoid large synchronous requests.
- Registered device count, accepted/failed send counts, notification history and unique-device click counts.
- Flutter notification inbox with the 6 newest messages, image cards and a live countdown only when a deadline was selected. Debug/connection diagnostics are not shown in the customer inbox.
- Deep links: `ark://product/123`, `ark://orders`, `ark://notifications`; HTTPS and supported external app schemes open through Android.

## Install

Plugin release: **1.3.2**. GlowHit companion APK: **1.0.20 (build 20)**.

1. Upload and activate this plugin from **WordPress Admin → Plugins → Add Plugin → Upload Plugin**.
2. Confirm the site is HTTPS and WordPress cron can run.
3. In **ARK Notifications → Firebase connection**, enter the Firebase Android app's API Key, App ID, Messaging Sender ID and Project ID.
4. Paste a service-account JSON key from Google Cloud IAM. Grant that service account the Firebase Cloud Messaging permission required to send messages. The private JSON is stored on this WordPress server and is never returned by the public app configuration endpoint.
5. Install the new GlowHit APK on devices and allow notification permission. Device tokens register automatically when the app starts after Firebase settings are complete.
6. In **ARK Notifications → Create sale notification**, set a title and message, optionally choose an image and click URL. Leave the countdown blank when no timer is needed. Press Send.
7. Choose **Send now**, **Schedule once**, or **Repeat weekly** in the Delivery schedule field. Select a future date and time in the WordPress site timezone. Weekly messages repeat on that weekday until you cancel them from the history table.\n8. Use the notification history table to review the 5 newest records, FCM accepted/failed counts and unique-device clicks.

## Firebase app details

For the GlowHit Android app (`com.ark.glowhit`), the original first release contained a one-character typo in the Firebase API key. Version 1.3.0 automatically corrects the saved legacy value for project `glowhit-10a3c` to the exact value from the app's `google-services.json`. Open **ARK Notifications** once after updating the plugin; the settings page migration will update the saved key.


Create the Firebase Android app with the same package ID as the APK (currently `com.ark.glowhit`). Enable the Firebase Cloud Messaging API. The APK initializes Firebase Messaging with the public Android options from the matching `com.ark.glowhit` `google-services.json`. The plugin keeps a matching copy of those public settings for its admin connection status. The private service-account JSON is used only by WordPress to send FCM HTTP v1 messages. Do not upload a service-account JSON or private key to the Flutter project or GitHub.

## Important delivery notes

- Push notifications go only to devices that installed this ARK Native app, opened it, granted notification permission and registered an FCM token. A WordPress plugin cannot push notifications into unrelated apps installed on the phone.
- Order-created and order-status alerts are sent only to active FCM devices linked to the order's WooCommerce customer account. Existing guest orders are matched to an account only when the billing email exactly matches that registered account.
- After installing GlowHit 1.0.20, customers should open the app while signed in (or sign in once). The app re-registers the FCM token with the verified account id; this upgrade is required to route order alerts to the correct customer's phone.
- Campaign/test notifications and customer order alerts send their first device batch immediately from WordPress, so delivery of a few app installations does not depend on WP-Cron. WP-Cron is still used for additional batches when more than 10 devices are registered.
- HTTPS click URLs are opened by Android's compatible browser/app handler. `ark://` URLs route to native GlowHit product, orders or notification screens. Other app schemes work only when a compatible app is installed.
- The optional countdown is shown in the app's notification inbox. Android's system notification itself is a normal push message; it does not tick once per second in the notification shade.
- Click reports count each registered device once per message. This is a device-level click estimate, not a count of verified human identities or unique people.
- FCM HTTP v1 data payloads use the namespaced `ark_message_type` key. Do not rename this to `message_type`; Firebase reserves that key and rejects the whole send request.
- Each app installation sends a persistent installation ID. Token refresh updates the same device row; token changes do not create a second active row for the same installation.
- The app inbox returns at most six messages and supports per-device dismissal with `DELETE /wp-json/ark-push/v1/messages/{id}`. Dismissing a message hides it only on that installation; it does not delete the store-wide campaign or another customer's order alert.
- The app inbox only returns campaigns sent after that device was registered, so a newly installed app does not receive the entire historical campaign archive. Customer order alerts remain scoped to the signed-in customer.
- When a different customer account signs in on the same phone, the campaign inbox cutoff resets to that account-link time. GlowHit also clears local tray alerts and local read/delete state from the previous account.
- If the WordPress pretty REST route returns 404, the app retries via WordPress' built-in `?rest_route=/ark-push/v1/...` endpoint.
- Delivery accepted by FCM means Google accepted the send request; it is not proof that a person viewed the notification.
- WP-Cron sends batches of registered devices. On very low-traffic sites, use a real server cron to call WordPress cron periodically.

 
 ## Scheduled delivery
 
 - Schedule dates and times are entered in the WordPress site's timezone and stored in UTC.
 - **Schedule once** sends one campaign at the chosen date and time.
 - **Repeat weekly** sends on the weekday chosen by the selected first date, at the selected time. The plugin schedules the next occurrence before starting the current send, and the admin history row includes a Cancel schedule action.
 - Scheduled sends select active app devices at delivery time, not when the message is first saved.
 - Delivery is executed by WordPress Cron. For reliable timing, configure a hosting/server cron to call `wp-cron.php` every 1–5 minutes. Without a real cron runner, WordPress Cron is traffic-triggered and a low-traffic site may send late.
 - The app and plugin show only the five newest notifications/history records. Order status events remain customer-specific.
