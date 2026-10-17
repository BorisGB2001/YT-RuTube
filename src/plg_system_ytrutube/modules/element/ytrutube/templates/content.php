<?php
/**
 * @package     YT RuTube — lazy RuTube video element for YOOtheme Pro
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die();

/** @var array $props */

?>

<?php if (!empty($props['video'])) : ?>
<p><a href="<?= htmlspecialchars((string) $props['video'], ENT_QUOTES, 'UTF-8') ?>"><?= $props['title'] ?: 'RuTube' ?></a></p>
<?php endif ?>
