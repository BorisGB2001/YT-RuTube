<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

namespace YtRutube;

defined('_JEXEC') or die();

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use YOOtheme\Config;
use YOOtheme\Metadata;
use YOOtheme\Path;

use function YOOtheme\app;

class RutubeHelper
{
    public const VERSION = '1.4.1';

    /** Retry interval in seconds after a failed API request. */
    private const RETRY_AFTER = 3600;

    private static ?Registry $params = null;

    public static function setParams(Registry $params): void
    {
        self::$params = $params;
    }

    /**
     * Parses a RuTube, VK Video or Yandex Cloud Video link, embed code or bare video ID.
     *
     * RuTube: rutube.ru/video/{id}/, rutube.ru/video/private/{id}/?p=KEY,
     * rutube.ru/shorts/{id}/, rutube.ru/play/embed/{id}, {id}.
     * VK Video: vkvideo.ru/video-1_2, vk.com/video-1_2, vk.com/clip-1_2, vk.com/video?z=video-1_2,
     * video_ext.php?oid=-1&id=2&hash=KEY, video-1_2.
     * Yandex Cloud Video: runtime.video.cloud.yandex.net/player/video/{id} (also episode/{id}).
     * Both: <iframe src="...">, ?t=90 for the start time.
     *
     * @return array{provider: string, id: string, key: string, start: int}|null
     */
    public static function parse(string $input): ?array
    {
        $input = trim(html_entity_decode($input, ENT_QUOTES, 'UTF-8'));

        if ($input === '') {
            return null;
        }

        if (preg_match('~<iframe[^>]+src=["\']([^"\']+)~i', $input, $m)) {
            $input = $m[1];
        }

        if (preg_match('~^([a-f0-9]{32}|\d+)$~i', $input)) {
            return ['provider' => 'rutube', 'id' => strtolower($input), 'key' => '', 'start' => 0];
        }

        if (preg_match('~^(?:video|clip)?(-?\d+_\d+)$~i', $input, $m)) {
            return ['provider' => 'vk', 'id' => $m[1], 'key' => '', 'start' => 0];
        }

        $query = [];
        parse_str((string) parse_url(str_contains($input, '//') ? $input : 'https://' . $input, PHP_URL_QUERY), $query);
        $start = isset($query['t']) && is_string($query['t']) ? self::parseTime($query['t']) : 0;

        if (preg_match('~video\.cloud\.yandex\.net/player/(video|episode)/([a-z0-9]+)~i', $input, $m)) {
            return ['provider' => 'yandex', 'id' => strtolower($m[1] . '/' . $m[2]), 'key' => '', 'start' => $start];
        }

        if (preg_match('~rutube\.ru/(?:play/embed|video/private|video|shorts|embed)/([a-f0-9]{32}|\d+)~i', $input, $m)) {
            return [
                'provider' => 'rutube',
                'id' => strtolower($m[1]),
                'key' => isset($query['p']) && is_string($query['p']) ? preg_replace('~[^\w-]~', '', $query['p']) : '',
                'start' => $start,
            ];
        }

        if (!preg_match('~(?:^|[/.])(?:vk\.com|vk\.ru|vkvideo\.ru)(?:/|$)~i', $input)) {
            return null;
        }

        $key = isset($query['hash']) && is_string($query['hash']) ? preg_replace('~[^\w]~', '', $query['hash']) : '';

        if (preg_match('~video_e(?:xt|mbed)\.php~i', $input) && isset($query['oid'], $query['id'])
            && preg_match('~^-?\d+$~', (string) $query['oid']) && preg_match('~^\d+$~', (string) $query['id'])) {
            return ['provider' => 'vk', 'id' => $query['oid'] . '_' . $query['id'], 'key' => $key, 'start' => $start];
        }

        if (preg_match('~(?:video|clip)(-?\d+_\d+)~i', rawurldecode($input), $m)) {
            return ['provider' => 'vk', 'id' => $m[1], 'key' => $key, 'start' => $start];
        }

        return null;
    }

    /**
     * Converts "90", "1:30", "1:02:03" or "1h2m3s" to seconds.
     */
    public static function parseTime(string $value): int
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return 0;
        }

        if (preg_match('~^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$~', $value, $m)) {
            return (int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
        }

        $seconds = 0;

        foreach (explode(':', $value) as $part) {
            $seconds = $seconds * 60 + (int) $part;
        }

        return max(0, $seconds);
    }

    /**
     * Normalizes "16:9", "16/9" or "2.35:1" to "16 / 9" (CSS aspect-ratio).
     */
    public static function parseRatio(string $value): string
    {
        if (preg_match('~^\s*(\d+(?:[.,]\d+)?)\s*[:/x]\s*(\d+(?:[.,]\d+)?)\s*$~', $value, $m) && (float) str_replace(',', '.', $m[2]) > 0) {
            return str_replace(',', '.', $m[1]) . ' / ' . str_replace(',', '.', $m[2]);
        }

        return '16 / 9';
    }

    public static function embedUrl(string $id, string $key = '', array $params = [], string $provider = 'rutube'): string
    {
        if ($provider === 'yandex') {
            $params = array_filter($params, static fn($value) => $value !== null && $value !== '' && $value !== 0);

            return 'https://runtime.video.cloud.yandex.net/player/' . $id . ($params ? '?' . http_build_query($params) : '');
        }

        if ($provider === 'vk') {
            [$oid, $vid] = explode('_', $id, 2);
            $params = ['oid' => $oid, 'id' => $vid, 'hash' => $key] + $params;
            $params = array_filter($params, static fn($value) => $value !== null && $value !== '' && $value !== 0);

            return 'https://vkvideo.ru/video_ext.php?' . http_build_query($params);
        }

        if ($key !== '') {
            $params = ['p' => $key] + $params;
        }

        $params = array_filter($params, static fn($value) => $value !== null && $value !== '' && $value !== 0);

        return 'https://rutube.ru/play/embed/' . $id . ($params ? '?' . http_build_query($params) : '');
    }

    public static function videoUrl(string $id, string $key = '', string $provider = 'rutube'): string
    {
        if ($provider === 'vk') {
            return 'https://vkvideo.ru/video' . $id;
        }

        if ($provider === 'yandex') {
            return self::embedUrl($id, '', [], 'yandex');
        }

        return $key !== ''
            ? 'https://rutube.ru/video/private/' . $id . '/?p=' . rawurlencode($key)
            : 'https://rutube.ru/video/' . $id . '/';
    }

    /**
     * Thumbnail endpoint that redirects to the poster image (no API call needed).
     */
    public static function thumbnailUrl(string $id): string
    {
        return 'https://rutube.ru/api/video/' . $id . '/thumbnail/?redirect=1';
    }

    public static function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    public static function isoDuration(int $seconds): string
    {
        return sprintf('PT%dH%dM%dS', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Video metadata from the RuTube API or the VK Video embed page, cached.
     *
     * @return array{title?: string, description?: string, thumbnail?: string, duration?: int, created?: string, key?: string}
     */
    public static function meta(string $id, string $key = '', string $provider = 'rutube'): array
    {
        $cache = null;
        $cacheId = md5($provider . '|' . $id . '|' . $key);

        try {
            $cache = Factory::getContainer()
                ->get(CacheControllerFactoryInterface::class)
                ->createCacheController('output', [
                    'defaultgroup' => 'plg_system_ytrutube',
                    'caching' => true,
                    'lifetime' => max(1, (int) (self::$params?->get('cache_lifetime', 720) ?? 720)),
                ]);

            $cached = $cache->get($cacheId);

            if (is_string($cached) && is_array($data = json_decode($cached, true))) {
                if (empty($data['error']) || ($data['time'] ?? 0) > time() - self::RETRY_AFTER) {
                    return $data;
                }
            }
        } catch (\Throwable) {
            $cache = null;
        }

        $data = match ($provider) {
            'vk' => self::fetchVk($id, $key),
            'yandex' => self::fetchYandex($id),
            default => self::fetch($id, $key),
        };

        try {
            $cache?->store(json_encode($data), $cacheId);
        } catch (\Throwable) {
            // Cache is optional.
        }

        return $data;
    }

    private static function fetch(string $id, string $key): array
    {
        $url = 'https://rutube.ru/api/video/' . $id . '/' . ($key !== '' ? '?p=' . rawurlencode($key) : '');

        try {
            // RuTube rejects requests without a User-Agent header.
            $response = HttpFactory::getHttp()->get($url, [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; YT-RuTube/' . self::VERSION . ')',
            ], 5);

            $json = $response->getStatusCode() === 200 ? json_decode((string) $response->getBody(), true) : null;
        } catch (\Throwable) {
            $json = null;
        }

        if (!is_array($json) || empty($json['id'])) {
            return ['error' => true, 'time' => time()];
        }

        return [
            'title' => (string) ($json['title'] ?? ''),
            'description' => (string) ($json['description'] ?? ''),
            'thumbnail' => (string) ($json['thumbnail_url'] ?? ''),
            'duration' => (int) ($json['duration'] ?? 0),
            'created' => (string) ($json['publication_ts'] ?? $json['created_ts'] ?? ''),
            'time' => time(),
        ];
    }

    /**
     * VK Video has no public API without a token, so the data is read from the
     * embed page (windows-1251), which carries the video in its player config.
     */
    private static function fetchVk(string $id, string $key): array
    {
        [$oid, $vid] = explode('_', $id, 2);
        $url = 'https://vk.com/video_ext.php?' . http_build_query(array_filter(['oid' => $oid, 'id' => $vid, 'hash' => $key]));

        try {
            $response = HttpFactory::getHttp()->get($url, [
                'Accept' => 'text/html',
                'Accept-Language' => 'ru,en;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; YT-RuTube/' . self::VERSION . ')',
            ], 5);

            $html = $response->getStatusCode() === 200 ? (string) $response->getBody() : '';
        } catch (\Throwable) {
            $html = '';
        }

        if (!preg_match('~charset=["\']?utf-8~i', substr($html, 0, 2000)) && !mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1251');
        }

        $video = self::vkVideo($html, $oid, $vid);
        $title = html_entity_decode((string) ($video['title'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($title === '') {
            return ['error' => true, 'time' => time()];
        }

        // The largest poster image
        $images = is_array($video['image'] ?? null) ? $video['image'] : [];
        usort($images, static fn($a, $b) => ($b['width'] ?? 0) <=> ($a['width'] ?? 0));

        // Embedding needs the hash for some videos
        if ($key === '' && preg_match('~[?&]hash=([0-9a-f]+)~', (string) ($video['player'] ?? ''), $m)) {
            $key = $m[1];
        }

        return [
            'title' => $title,
            'description' => html_entity_decode((string) ($video['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'thumbnail' => (string) ($images[0]['url'] ?? ''),
            'duration' => (int) ($video['duration'] ?? 0),
            'created' => !empty($video['date']) ? gmdate('c', (int) $video['date']) : '',
            'key' => $key,
            'time' => time(),
        ];
    }

    /**
     * Yandex Cloud Video: Open Graph tags of the player page.
     */
    private static function fetchYandex(string $id): array
    {
        try {
            $response = HttpFactory::getHttp()->get(self::embedUrl($id, '', [], 'yandex'), [
                'Accept' => 'text/html',
                'User-Agent' => 'Mozilla/5.0 (compatible; YT-RuTube/' . self::VERSION . ')',
            ], 5);

            $html = $response->getStatusCode() === 200 ? (string) $response->getBody() : '';
        } catch (\Throwable) {
            $html = '';
        }

        $meta = static fn(string $name) => preg_match('~<meta\s+property="' . preg_quote($name, '~') . '"\s+content="([^"]*)"~i', $html, $m)
            ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')
            : '';

        $title = $meta('og:title');

        if ($title === '') {
            return ['error' => true, 'time' => time()];
        }

        return [
            'title' => $title,
            'description' => $meta('og:description'),
            'thumbnail' => $meta('og:image'),
            'duration' => (int) $meta('video:duration'),
            'created' => '',
            'time' => time(),
        ];
    }

    /**
     * The video object of the "video.get" response embedded in the page.
     * Other objects there (the owner, ads) have titles and images too.
     */
    private static function vkVideo(string $html, string $oid, string $vid): array
    {
        $pos = strpos($html, '"id":' . $vid . ',"owner_id":' . $oid . ',');
        $start = $pos === false ? false : strrpos(substr($html, 0, $pos), '"items":[{');

        if ($start === false) {
            return [];
        }

        // Cut out the object by matching braces outside strings
        $start += strlen('"items":[');
        $depth = 0;
        $string = false;

        for ($i = $start, $length = strlen($html); $i < $length; $i++) {
            $char = $html[$i];

            if ($string) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $string = false;
                }
            } elseif ($char === '"') {
                $string = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                $video = json_decode(substr($html, $start, $i - $start + 1), true);

                return is_array($video) ? $video : [];
            }
        }

        return [];
    }

    /**
     * Resolves the video and prepares everything the template needs.
     * Without a RuTube video the builder shows a placeholder and the site shows nothing.
     */
    public static function prepareRender(object $node): bool
    {
        $props = &$node->props;
        $video = self::parse((string) ($props['video'] ?? ''));
        $customizer = (bool) app(Config::class)('app.isCustomizer');

        if ($customizer) {
            $node->attrs['data-preview'] = 'reload';
        }

        if (!$video) {
            if (!$customizer) {
                return false;
            }

            $props['ytrutube'] = ['stub' => true, 'ratio' => self::parseRatio(self::ratio($props))];
            self::loadAssets();

            return true;
        }

        $vk = $video['provider'] === 'vk';
        $yandex = $video['provider'] === 'yandex';
        $meta = !empty($props['fetch_meta']) ? self::meta($video['id'], $video['key'], $video['provider']) : [];
        $key = $video['key'] ?: (string) ($meta['key'] ?? '');

        $start = trim((string) ($props['start'] ?? '')) !== ''
            ? self::parseTime((string) $props['start'])
            : $video['start'];

        // Poster: custom image, API thumbnail or the thumbnail redirect endpoint.
        $poster = (string) ($props['image'] ?? '');

        if ($poster !== '') {
            $poster = explode('#', $poster, 2)[0];

            if (!preg_match('~^(https?:)?//~i', $poster)) {
                $poster = Uri::root(true) . '/' . ltrim($poster, '/');
            }
        } else {
            $poster = ($meta['thumbnail'] ?? '') ?: ($vk || $yandex ? '' : self::thumbnailUrl($video['id']));
        }

        // Player options are passed as URL params and applied by ytrutube.js via the
        // postMessage API of the player: https://github.com/rutube/RutubePlayerJSAPI,
        // https://dev.vk.com/ru/widgets/video (VK.VideoPlayer, needs js_api=1), Yandex Cloud
        // Video player in an iframe (play, pause, seek, mute and unmute methods)
        $skin = ltrim((string) ($props['skin_color'] ?? ''), '#');
        $skin = preg_match('~^[0-9a-f]{6}$~i', $skin) ? $skin : null;
        $params = $yandex ? [
            't' => $start,
            'mute' => !empty($props['mute']) ? 1 : null,
            'primary_color' => $skin,
        ] : ($vk ? [
            'hd' => 2,
            't' => $start ? $start . 's' : null,
            'js_api' => 1,
        ] : [
            'bmstart' => $start,
            't' => $start,
            'mute' => !empty($props['mute']) ? 1 : null,
            'skinColor' => $skin,
        ]);

        $props['ytrutube'] = [
            'stub' => false,
            'provider' => $video['provider'],
            'id' => $video['id'],
            'embed' => self::embedUrl($video['id'], $key, $params, $video['provider']),
            'player' => self::embedUrl($video['id'], $key, [], $video['provider']),
            'url' => self::videoUrl($video['id'], $key, $video['provider']),
            'start' => $start,
            'mute' => !empty($props['mute']),
            'poster' => $poster,
            'title' => trim((string) ($props['title'] ?? '')) ?: ($meta['title'] ?? ''),
            'ratio' => self::parseRatio(self::ratio($props)),
            'duration' => !empty($meta['duration']) ? self::formatDuration($meta['duration']) : '',
            'seconds' => (int) ($meta['duration'] ?? 0),
            'meta' => $meta,
            'ui' => self::playerOptions($props),
        ];

        self::loadAssets();

        return true;
    }

    private static function ratio(array $props): string
    {
        return ($props['ratio'] ?? '16:9') === 'custom' ? (string) ($props['ratio_custom'] ?? '') : (string) ($props['ratio'] ?? '16:9');
    }

    /**
     * Player options for ytrutube.js. With own controls the RuTube player is covered
     * and only the enabled controls are available: no links to RuTube, menus or recommendations.
     */
    public static function playerOptions(array $props): array
    {
        $custom = ($props['controls'] ?? 'rutube') === 'custom';
        $flag = static fn(string $name) => $custom && !empty($props[$name]);

        return [
            'custom' => $custom,
            'play' => $flag('ctrl_play'),
            'progress' => $flag('ctrl_progress'),
            'seek' => $flag('ctrl_progress') && $flag('ctrl_seek'),
            'time' => $flag('ctrl_time'),
            'volume' => $flag('ctrl_volume'),
            'fullscreen' => $flag('ctrl_fullscreen'),
            'click' => $flag('ctrl_click'),
            'autohide' => $flag('ctrl_autohide'),
            'endPoster' => !empty($props['end_poster']),
            'labels' => [
                'play' => Text::_('PLG_SYSTEM_YTRUTUBE_PLAY'),
                'pause' => Text::_('PLG_SYSTEM_YTRUTUBE_PAUSE'),
                'mute' => Text::_('PLG_SYSTEM_YTRUTUBE_MUTE'),
                'unmute' => Text::_('PLG_SYSTEM_YTRUTUBE_UNMUTE'),
                'fullscreen' => Text::_('PLG_SYSTEM_YTRUTUBE_FULLSCREEN'),
                'exitFullscreen' => Text::_('PLG_SYSTEM_YTRUTUBE_EXIT_FULLSCREEN'),
            ],
        ];
    }

    public static function loadAssets(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;
        $metadata = app(Metadata::class);

        $metadata->set('style:ytrutube', ['href' => Path::get('../assets/css/ytrutube.css?v=' . self::VERSION, __DIR__)]);
        $metadata->set('script:ytrutube', [
            'src' => Path::get('../assets/js/ytrutube.js?v=' . self::VERSION, __DIR__),
            'defer' => true,
        ]);
    }
}
