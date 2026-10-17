<?php
// Standalone check of RutubeHelper URL, time and ratio parsing: php tests/parse_url_test.php
define('_JEXEC', 1);
require __DIR__ . '/../src/plg_system_ytrutube/modules/src/RutubeHelper.php';

use YtRutube\RutubeHelper;

$id = '2a55da78983c76a6fcfbfb324313fc4b';
$video = static fn($key = '', $start = 0) => ['id' => $id, 'key' => $key, 'start' => $start];

$cases = [
    // RutubeHelper::parse()
    ['parse', "https://rutube.ru/video/{$id}/?r=plwd", $video()],
    ['parse', "https://rutube.ru/video/{$id}/", $video()],
    ['parse', "rutube.ru/video/{$id}", $video()],
    ['parse', "https://rutube.ru/video/{$id}/?t=125", $video('', 125)],
    ['parse', "https://rutube.ru/shorts/{$id}/", $video()],
    ['parse', "https://rutube.ru/video/private/{$id}/?p=AbC-12_x", $video('AbC-12_x')],
    ['parse', "https://rutube.ru/play/embed/{$id}", $video()],
    ['parse', "https://rutube.ru/play/embed/7163336", ['id' => '7163336', 'key' => '', 'start' => 0]],
    ['parse', "<iframe width=\"720\" height=\"405\" src=\"https://rutube.ru/play/embed/{$id}\" style=\"border: none;\" allow=\"clipboard-write; autoplay\" allowFullScreen></iframe>", $video()],
    ['parse', strtoupper($id), $video()],
    ['parse', "  {$id}  ", $video()],
    ['parse', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', null],
    ['parse', 'https://rutube.ru/channel/123/', null],
    ['parse', '', null],

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
$ok = $embed === "https://rutube.ru/play/embed/{$id}?p=KEY&bmstart=90&t=90&autoplay=1";
$failed += $ok ? 0 : 1;
printf("%s embedUrl => %s\n", $ok ? 'ok  ' : 'FAIL', $embed);

echo $failed ? "\n{$failed} failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
