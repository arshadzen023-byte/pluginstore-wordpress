# ARK App Designer 1.17.8 release checks

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
