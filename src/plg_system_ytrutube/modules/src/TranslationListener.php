<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

namespace YtRutube;

defined('_JEXEC') or die();

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Translator;

class TranslationListener
{
    /**
     * Adds builder UI translations (modules/languages/<locale>.json) for the customizer.
     */
    public static function translate(Config $config, Translator $translator): void
    {
        $locale = basename((string) $config->get('locale.code'));

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $locale)) {
            return;
        }

        $file = Path::join(__DIR__, '..', 'languages', "{$locale}.json");

        if (!is_file($file)) {
            return;
        }

        try {
            json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $translator->addResource($file);
        } catch (\JsonException) {
            // Broken translation file — keep English labels.
        }
    }
}
