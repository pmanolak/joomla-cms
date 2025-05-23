<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_tags
 *
 * @copyright   (C) 2013 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Tags\Site\View\Tags;

use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Component\Tags\Site\Model\TagsModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HTML View class for the Tags component all tags view
 *
 * @since  3.1
 */
class FeedView extends BaseHtmlView
{
    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $app                       = Factory::getApplication();
        $this->getDocument()->link = Route::_('index.php?option=com_tags&view=tags');
        $params                    = $app->getParams();

        // If the feed has been disabled, we want to bail out here
        if ($params->get('show_feed_link', 1) == 0) {
            throw new \Exception(Text::_('JGLOBAL_RESOURCE_NOT_FOUND'), 404);
        }

        $app->getInput()->set('limit', $app->get('feed_limit'));
        $siteEmail = $app->get('mailfrom');
        $fromName  = $app->get('fromname');
        $feedEmail = $app->get('feed_email', 'none');

        $this->getDocument()->editor = $fromName;

        if ($feedEmail !== 'none') {
            $this->getDocument()->editorEmail = $siteEmail;
        }

        /** @var TagsModel $model */
        $model = $this->getModel();
        $items = $model->getItems();

        foreach ($items as $item) {
            // Strip HTML from feed item title
            $title = $this->escape($item->title);
            $title = html_entity_decode($title, ENT_COMPAT, 'UTF-8');

            // Strip HTML from feed item description text
            $description = $item->description;
            $author      = $item->created_by_alias ?: $item->created_by_user_name;
            $date        = $item->created_time ? date('r', strtotime($item->created_time)) : '';

            // Load individual item creator class
            $feeditem              = new FeedItem();
            $feeditem->title       = $title;
            $feeditem->link        = '/index.php?option=com_tags&view=tag&id=' . (int) $item->id;
            $feeditem->description = $description;
            $feeditem->date        = $date;
            $feeditem->category    = 'All Tags';
            $feeditem->author      = $author;

            if ($feedEmail === 'site') {
                $feeditem->authorEmail = $siteEmail;
            }

            if ($feedEmail === 'author') {
                $feeditem->authorEmail = $item->email;
            }

            // Loads item info into RSS array
            $this->getDocument()->addItem($feeditem);
        }
    }
}
