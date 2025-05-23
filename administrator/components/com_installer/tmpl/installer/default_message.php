<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_installer
 *
 * @copyright   (C) 2007 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/** @var \Joomla\Component\Installer\Administrator\View\Installer\HtmlView $this */

$state    = $this->state;
$message1 = $state->get('message');
$message2 = $state->get('extension_message');
?>

<?php if ($message1) : ?>
    <div class="alert alert-info">
        <strong><?php echo $message1; ?></strong>
    </div>
<?php endif; ?>
<?php if ($message2) : ?>
    <div class="alert alert-info">
        <?php echo $message2; ?>
    </div>
<?php endif; ?>
