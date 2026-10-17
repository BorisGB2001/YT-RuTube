<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

namespace YtRutube;

defined('_JEXEC') or die();

use YOOtheme\Builder;
use YOOtheme\Path;

include_once Path::get('./src/TranslationListener.php', __DIR__);

return [
    'events' => ['customizer.init' => [TranslationListener::class => ['translate', 10]]],
    'extend' => [
        Builder::class => static function (Builder $builder) {
            $builder->addTypePath(Path::get('./element/*/element.json', __DIR__));
        },
    ],
];
