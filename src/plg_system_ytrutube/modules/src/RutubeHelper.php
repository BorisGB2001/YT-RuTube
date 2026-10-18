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
    public const VERSION = '1.1.3';

    /** Retry interval in seconds after a failed API request. */
    private const RETRY_AFTER = 3600;

    private static ?Registry $params = null;

    public static function setParams(Registry $params): void
    {
        self::$params = $params;
    }

    /**
     * Parses a RuTube link, embed code or bare video ID.
     *
     * Supported: rutube.ru/video/{id}/, rutube.ru/video/private/{id}/?p=KEY,
     * rutube.ru/shorts/{id}/, rutube.ru/play/embed/{id}, <iframe src="...">, {id}.
     *
     * @return array{id: string, key: string, start: int}|null
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
            return ['id' => strtolower($input), 'key' => '', 'start' => 0];
        }

        if (!preg_match('~rutube\.ru/(?:play/embed|video/private|video|shorts|embed)/([a-f0-9]{32}|\d+)~i', $input, $m)) {
            return null;
        }

        $query = [];
        parse_str((string) parse_url(str_contains($input, '//') ? $input : 'https://' . $input, PHP_URL_QUERY), $query);

        return [
            'id' => strtolower($m[1]),
            'key' => isset($query['p']) ? preg_replace('~[^\w-]~', '', (string) $query['p']) : '',
            'start' => isset($query['t']) ? self::parseTime((string) $query['t']) : 0,
        ];
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

    public static function embedUrl(string $id, string $key = '', array $params = []): string
    {
        if ($key !== '') {
            $params = ['p' => $key] + $params;
        }

        $params = array_filter($params, static fn($value) => $value !== null && $value !== '' && $value !== 0);

        return 'https://rutube.ru/play/embed/' . $id . ($params ? '?' . http_build_query($params) : '');
    }

    public static function videoUrl(string $id, string $key = ''): string
    {
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
     * Video metadata from the RuTube API, cached.
     *
     * @return array{title?: string, description?: string, thumbnail?: string, duration?: int, created?: string}
     */
    public static function meta(string $id, string $key = ''): array
    {
        $cache = null;
        $cacheId = md5($id . '|' . $key);

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

        $data = self::fetch($id, $key);

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

        $meta = !empty($props['fetch_meta']) ? self::meta($video['id'], $video['key']) : [];

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
            $poster = ($meta['thumbnail'] ?? '') ?: self::thumbnailUrl($video['id']);
        }

        // Player options are passed as URL params and applied by ytrutube.js
        // via the postMessage API: https://github.com/rutube/RutubePlayerJSAPI
        $skin = ltrim((string) ($props['skin_color'] ?? ''), '#');
        $params = [
            'bmstart' => $start,
            't' => $start,
            'mute' => !empty($props['mute']) ? 1 : null,
            'skinColor' => preg_match('~^[0-9a-f]{6}$~i', $skin) ? $skin : null,
        ];

        $props['ytrutube'] = [
            'stub' => false,
            'id' => $video['id'],
            'embed' => self::embedUrl($video['id'], $video['key'], $params),
            'url' => self::videoUrl($video['id'], $video['key']),
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
        $custom = ($props['controls'] ?? 'custom') !== 'rutube';
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
