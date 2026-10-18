<?php
// Standalone check of RutubeHelper URL, time and ratio parsing: php tests/parse_url_test.php
define('_JEXEC', 1);
require __DIR__ . '/../src/plg_system_ytrutube/modules/src/RutubeHelper.php';

use YtRutube\RutubeHelper;

$id = '5c3731210a7b462285456a4fb90e9009';
$video = static fn($key = '', $start = 0) => ['provider' => 'rutube', 'id' => $id, 'key' => $key, 'start' => $start];
$vk = static fn($key = '', $start = 0) => ['provider' => 'vk', 'id' => '-220018529_456249432', 'key' => $key, 'start' => $start];

$cases = [
    // RutubeHelper::parse()
    ['parse', "https://rutube.ru/video/{$id}/?r=plwd", $video()],
    ['parse', "https://rutube.ru/video/{$id}/", $video()],
    ['parse', "rutube.ru/video/{$id}", $video()],
    ['parse', "https://rutube.ru/video/{$id}/?t=125", $video('', 125)],
    ['parse', "https://rutube.ru/shorts/{$id}/", $video()],
    ['parse', "https://rutube.ru/video/private/{$id}/?p=AbC-12_x", $video('AbC-12_x')],
    ['parse', "https://rutube.ru/play/embed/{$id}", $video()],
    ['parse', "https://rutube.ru/play/embed/7163336", ['provider' => 'rutube', 'id' => '7163336', 'key' => '', 'start' => 0]],
    ['parse', "<iframe width=\"720\" height=\"405\" src=\"https://rutube.ru/play/embed/{$id}\" style=\"border: none;\" allow=\"clipboard-write; autoplay\" allowFullScreen></iframe>", $video()],
    ['parse', strtoupper($id), $video()],
    ['parse', "  {$id}  ", $video()],
    ['parse', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', null],
    ['parse', 'https://rutube.ru/channel/123/', null],
    ['parse', '', null],
    ['parse', 'https://vkvideo.ru/video-220018529_456249432', $vk()],
    ['parse', 'https://vk.com/video-220018529_456249432?t=1m30s', $vk('', 90)],
    ['parse', 'https://vk.com/video?z=video-220018529_456249432%2Fpl_cat_trend', $vk()],
    ['parse', 'https://vk.com/clip-220018529_456249432', $vk()],
    ['parse', 'https://m.vk.com/video-220018529_456249432', $vk()],
    ['parse', 'https://vk.com/video_ext.php?oid=-220018529&id=456249432&hash=88288bdb6c5ab44e', $vk('88288bdb6c5ab44e')],
    ['parse', '<iframe src="https://vkvideo.ru/video_ext.php?oid=-220018529&id=456249432&hd=2" width="853" height="480"></iframe>', $vk()],
    ['parse', 'video-220018529_456249432', $vk()],
    ['parse', 'https://vkvideo.ru/video-89823557_456239091', ['provider' => 'vk', 'id' => '-89823557_456239091', 'key' => '', 'start' => 0]],
    ['parse', 'https://runtime.video.cloud.yandex.net/player/video/vplvwagyjhngyet6wydx?autoplay=0&mute=0', ['provider' => 'yandex', 'id' => 'video/vplvwagyjhngyet6wydx', 'key' => '', 'start' => 0]],
    ['parse', '<iframe src="https://runtime.video.cloud.yandex.net/player/video/vplvwagyjhngyet6wydx?t=30" allowfullscreen></iframe>', ['provider' => 'yandex', 'id' => 'video/vplvwagyjhngyet6wydx', 'key' => '', 'start' => 30]],
    ['parse', 'https://vk.com/id123', null],
    ['parse', 'https://notvk.com/video-1_2', null],

    // RutubeHelper::parseTime()
    ['parseTime', '90', 90],
    ['parseTime', '1:30', 90],
    ['parseTime', '1:02:03', 3723],
    ['parseTime', '1h2m3s', 3723],
    ['parseTime', '2m', 120],
    ['parseTime', '', 0],

    // RutubeHelper::parseRatio()
    ['parseRatio', '16:9', '16 / 9'],
    ['parseRatio', '9:16', '9 / 16'],
    ['parseRatio', '2.35:1', '2.35 / 1'],
    ['parseRatio', '2,35/1', '2.35 / 1'],
    ['parseRatio', '4x3', '4 / 3'],
    ['parseRatio', '16:0', '16 / 9'],
    ['parseRatio', 'wide', '16 / 9'],

    // RutubeHelper::formatDuration()
    ['formatDuration', 10801, '3:00:01'],
    ['formatDuration', 75, '1:15'],
    ['formatDuration', 5, '0:05'],
];

$failed = 0;
foreach ($cases as [$method, $input, $expected]) {
    $actual = RutubeHelper::$method($input);
    $ok = $actual === $expected;
    $failed += $ok ? 0 : 1;
    printf("%s %-15s %-40s => %s%s\n", $ok ? 'ok  ' : 'FAIL', $method, mb_strimwidth(var_export($input, true), 0, 40, '…'), json_encode($actual), $ok ? '' : ' (expected ' . json_encode($expected) . ')');
}

$embed = RutubeHelper::embedUrl($id, 'KEY', ['bmstart' => 90, 't' => 90, 'mute' => null, 'autoplay' => 1]);
$ok = $embed === "https://rutube.ru/play/embed/{$id}?p=KEY&bmstart=90&t=90&autoplay=1"
    && RutubeHelper::embedUrl('-220018529_456249432', 'abc', ['hd' => 2, 't' => null, 'js_api' => 1], 'vk') === 'https://vkvideo.ru/video_ext.php?oid=-220018529&id=456249432&hash=abc&hd=2&js_api=1';
$failed += $ok ? 0 : 1;
printf("%s embedUrl => %s\n", $ok ? 'ok  ' : 'FAIL', $embed);

echo $failed ? "\n{$failed} failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
