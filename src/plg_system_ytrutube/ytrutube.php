<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die();

use Joomla\CMS\Plugin\CMSPlugin;
use YOOtheme\Application;
use YtRutube\RutubeHelper;

require_once __DIR__ . '/modules/src/RutubeHelper.php';

/** @noinspection AutoloadingIssuesInspection, PhpUnused */
final class PlgSystemYtrutube extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Registers the builder element once YOOtheme Pro is initialised.
     */
    public function onAfterInitialise(): void
    {
        RutubeHelper::setParams($this->params);

        if (!class_exists(Application::class, false)) {
            return;
        }

        Application::getInstance()->load(__DIR__ . '/modules/bootstrap.php');
    }
}
