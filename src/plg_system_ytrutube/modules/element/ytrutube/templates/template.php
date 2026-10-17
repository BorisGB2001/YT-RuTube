<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use YtRutube\RutubeHelper;

/** @var array $props */
/** @var array $attrs */

$video = $props['ytrutube'];
$title = $video['title'];
$mode = $props['load_mode'] ?: 'click';
$lightbox = $mode === 'lightbox';
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$el = $this->el('div', [
    'class' => [
        'ytrutube',
        "ytrutube--{$mode}",
    ],
    'style' => implode(' ', array_filter([
        "--ytrutube-ratio: {$video['ratio']};",
        $props['play_color'] ? "--ytrutube-play-bg: {$props['play_color']};" : '',
        $props['play_icon_color'] ? "--ytrutube-play-color: {$props['play_icon_color']};" : '',
    ])),
    'data-ytrutube-src' => $lightbox ? false : $video['embed'],
    'data-ytrutube-title' => $title ?: false,
    'data-ytrutube-pause-others' => !$lightbox && $props['pause_others'],
    'uk-lightbox' => $lightbox ? 'video-autoplay: true' : false,
]);

$box = $this->el('div', [
    'class' => [
        'ytrutube__box',
        'ytrutube__box--radius-{border_radius}',
        'uk-box-shadow-{box_shadow}',
    ],
]);

// Poster: a button for in-place playback, a link for the lightbox.
$poster = $this->el($lightbox ? 'a' : 'button', [
    'class' => [
        'ytrutube__poster',
        'ytrutube__poster--zoom {@image_hover}',
        'ytrutube__poster--overlay-{overlay}',
    ],
    'type' => $lightbox ? false : 'button',
    'href' => $lightbox ? $video['lightbox'] : false,
    'data-type' => $lightbox ? 'iframe' : false,
    'data-caption' => $lightbox && $title ? $title : false,
    'data-ytrutube-ratio' => $lightbox ? $video['ratio'] : false,
    'aria-label' => $title ? Text::sprintf('PLG_SYSTEM_YTRUTUBE_WATCH_TITLE', $title) : Text::_('PLG_SYSTEM_YTRUTUBE_WATCH'),
]);

?>

<?= $el($props, $attrs) ?>

    <?= $box($props) ?>

        <?= $poster($props) ?>

            <img class="el-image ytrutube__image" src="<?= $e($video['poster']) ?>" alt="<?= $e($props['image_alt'] ?: $title) ?>" loading="lazy" decoding="async">

            <?php if ($props['show_title'] && $title !== '') : ?>
            <span class="el-title ytrutube__title ytrutube__title--<?= $e($props['title_position'] ?: 'bottom') ?>"><?= $e($title) ?></span>
            <?php endif ?>

            <?php if ($props['show_duration'] && $video['duration']) : ?>
            <span class="ytrutube__duration"><?= $e($video['duration']) ?></span>
            <?php endif ?>

            <?php if ($props['play_style'] !== 'none') : ?>
            <span class="el-play ytrutube__play ytrutube__play--<?= $e($props['play_style'] ?: 'rutube') ?> ytrutube__play--<?= $e($props['play_size'] ?: 'medium') ?>" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="24" height="24"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.04-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14z" fill="currentColor"/></svg>
            </span>
            <?php endif ?>

        <?= $poster->end() ?>

        <noscript>
            <iframe class="ytrutube__iframe" src="<?= $e($video['embed']) ?>" title="<?= $e($title ?: 'RuTube') ?>" allow="clipboard-write; autoplay" allowfullscreen loading="lazy"></iframe>
        </noscript>

    <?= $box->end() ?>

<?php if ($props['schema'] && !empty($video['meta']['title'])) :
    $meta = $video['meta'];
    $created = (string) ($meta['created'] ?? '');
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => $title,
        'description' => mb_substr(trim($meta['description'] ?? '') ?: $title, 0, 500),
        'thumbnailUrl' => $video['poster'],
        // RuTube returns Moscow time without an offset.
        'uploadDate' => $created !== '' ? $created . (preg_match('~(Z|[+-]\d\d:?\d\d)$~', $created) ? '' : '+03:00') : null,
        'duration' => !empty($meta['duration']) ? RutubeHelper::isoDuration($meta['duration']) : null,
        'embedUrl' => 'https://rutube.ru/play/embed/' . $video['id'],
        'contentUrl' => $video['url'],
    ]);
?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif ?>

<?= $el->end() ?>
