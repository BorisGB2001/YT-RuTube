<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

namespace YOOtheme;

defined('_JEXEC') or die();

use YtRutube\RutubeHelper;

return [
    'transforms' => [
        'render' => static function ($node) {
            // Nothing to show without a RuTube video.
            if (!RutubeHelper::prepareRender($node)) {
                return false;
            }
        },
    ],
];
