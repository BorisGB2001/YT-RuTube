# Changelog

All notable changes to YT RuTube. Versions follow [Semantic Versioning](https://semver.org/).

## [1.1.1] — 2026-10-17

### Changed
- New example video link in the *RuTube Video* field description: `https://rutube.ru/video/5c3731210a7b462285456a4fb90e9009/`.
- README: new screenshots (poster, own player controls, circle play button, builder placeholder).

## [1.1.0] — 2026-10-17

### Added
- **Own player controls** (default): the RuTube player is covered, so viewers can't open RuTube, share, use the settings menu or recommendations. The video plays only in its window and in fullscreen.
- Each control can be turned off: play/pause, progress bar, seeking, time, sound, fullscreen, pause on click, auto-hide while playing. Keyboard: space, arrows, F, M.
- After the video ends the poster is shown again instead of the RuTube end screen (can be turned off).
- Placeholder with a play button in the builder until a video link is set; on the site an element without a link is not shown.

### Changed
- Lightbox mode uses an own UIkit modal, so the same controls work there.
- The *RuTube player* option keeps the full RuTube interface.

## [1.0.0] — 2026-10-17

### Added
- **RuTube** builder element for YOOtheme Pro: poster with a play button, the RuTube player loads on click, in a lightbox or when scrolled into view.
- Video links, Shorts, private videos (`?p=`), embed links, `<iframe>` code and bare IDs; start time from `?t=`.
- Title, poster and duration from the RuTube API with caching.
- Aspect ratios including 9:16 for Shorts and custom ratios.
- Play button styles and colors, overlays, hover zoom, title and duration badges, border radius, box shadow.
- Player options via the RuTube postMessage API: start time, mute, player color, pausing other videos.
- `VideoObject` structured data.
- English and Russian translations; Joomla update server.
