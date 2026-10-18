# Changelog

All notable changes to YT RuTube. Versions follow [Semantic Versioning](https://semver.org/).

## [1.1.5] — 2026-10-18

### Fixed
- Pause with an ad blocker: the RuTube player resumes playback right after a pause when its ad module is blocked. If the video is still playing a second after the pause command, the player is unloaded and the poster is shown; Play loads it again and continues from the same position.

## [1.1.4] — 2026-10-18

### Fixed
- Pause did not work with an ad blocker: the blocked RuTube ad module leaves the player reporting wrong states (stuck "advert" or none), so the play button sent "play" instead of "pause" or the click went to the ad module. Playback is now judged by the playback time actually moving; ads are detected only by `player:rollState`; the pause command is repeated if the video keeps playing.

## [1.1.3] — 2026-10-18

### Fixed
- Video still did not start for viewers who had watched it before: RuTube reports the saved resume position before playback, which was taken as "playing", so the cover blocked the click again. The cover now turns on only when the player reports `playing`.
- If a play command from own controls is not confirmed within 2 seconds, the cover is removed and the RuTube controls are shown again, so the next click starts the video in the player.

### Added
- Diagnostics: open a page with `?ytrutube_debug=1` to log player events and commands to the browser console.

## [1.1.2] — 2026-10-18

### Fixed
- Video did not start with own controls: the cover over the RuTube player was enabled before playback began, so a click could not reach the player when the browser blocked autoplay. Until the video really plays (and while ads play), clicks now go to the RuTube player; after that the player is covered and its own controls are hidden.
- Pause, end and ad states of the current RuTube player (`pause`, `completed`, `advert`) are recognized.

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
