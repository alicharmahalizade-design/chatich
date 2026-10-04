# Third-party scripts

## heic-to.js

- heic-to 1.6.5 — https://github.com/hoppergee/heic-to — LGPL-3.0 (heic-to.LICENSE.txt).
- Bundles a current libheif (https://github.com/strukturag/libheif, LGPL-3.0) compiled to WebAssembly; replaces heic2any 0.0.4 (2020), whose libheif could not open many recent iPhone photos.
- Loaded only in the browser, and only when a shopper picks a HEIC/HEIF photo the browser cannot open itself.

## Pose check (not bundled)

- MediaPipe Tasks Vision 1.0.1 + Pose Landmarker Lite model — Apache-2.0 — https://github.com/google-ai-edge/mediapipe
- About 17 MB, so not shipped in the plugin: loaded from jsDelivr/Google, or from wp-content/uploads/ofr-pose after the admin copies the files there (settings → «دانلود فایل‌ها روی سرور سایت»).
