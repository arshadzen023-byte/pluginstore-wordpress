# ARK App Designer 1.18.0 release checks

This build is based on the user's original 1.17.6/1.17.7 Designer baseline. It must preserve:
- Nested Media Library image resolution and the 16-level safe JSON depth.
- Tolerant SQL slider normalization and no destructive replacement when no valid image is supplied.
- Legacy Banner child fallback and height, width, gap, radius and transition mapping.
- Original product card aspect-ratio range and component Margin/Padding/Dimension sanitizer.
- Image and video Media Library query support.
- Canonical WordPress published-option readback verification.
- Per-page Floating Action Button and Navigation Bar persistence.
- App identity and dynamic release stamp.

The new `app_bar` field is additive and must not rewrite any of the above contracts.


- App Bar background and Menu Top Image persist both WordPress attachment IDs and canonical URLs through publish/readback.
- The menu editor is a separate native route and default menu items remain visible in settings when no custom list exists.
- Status Bar background/GIF, icon brightness, and delivery-address text colour are preserved.
- WordPress `manage_options` verification controls the ARK Designer row; the bridge's `/designer/icons` route uses the same administrator authorization.
- The searchable Material icon picker includes 200 additional native icons; WordPress exposes the full 294-name icon catalogue.
