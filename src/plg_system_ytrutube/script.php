<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\DatabaseInterface;

/** @noinspection AutoloadingIssuesInspection, PhpUnused */
class PlgSystemYtrutubeInstallerScript
{
    private const MIN_PHP = '8.1.0';
    private const MIN_JOOMLA = '5.0.0';

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $app = Factory::getApplication();

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $app->enqueueMessage(sprintf('YT RuTube requires PHP %s or later.', self::MIN_PHP), 'error');

            return false;
        }

        if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
            $app->enqueueMessage(sprintf('YT RuTube requires Joomla %s or later.', self::MIN_JOOMLA), 'error');

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): void
    {
        if ($type !== 'install') {
            return;
        }

        // Enable the plugin right away, the element is useless otherwise.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('ytrutube'));
        $db->setQuery($query)->execute();
    }
}
