<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_languages
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Languages\Administrator\View\Language;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Languages\Administrator\Model\LanguageModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HTML View class for the Languages component.
 *
 * @since  1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The active item
     *
     * @var  object
     */
    public $item;

    /**
     * The Form object
     *
     * @var  \Joomla\CMS\Form\Form
     */
    public $form;

    /**
     * The model state
     *
     * @var  \Joomla\Registry\Registry
     */
    public $state;

    /**
     * The actions the user is authorised to perform
     *
     * @var    \Joomla\Registry\Registry
     *
     * @since  4.0.0
     */
    protected $canDo;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        /** @var LanguageModel $model */
        $model = $this->getModel();

        $this->item  = $model->getItem();
        $this->form  = $model->getForm();
        $this->state = $model->getState();
        $this->canDo = ContentHelper::getActions('com_languages');

        // Check for errors.
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function addToolbar()
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', 1);
        $isNew   = empty($this->item->lang_id);
        $canDo   = $this->canDo;
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(
            Text::_($isNew ? 'COM_LANGUAGES_VIEW_LANGUAGE_EDIT_NEW_TITLE' : 'COM_LANGUAGES_VIEW_LANGUAGE_EDIT_EDIT_TITLE'),
            'comments-2 langmanager'
        );

        if (($isNew && $canDo->get('core.create')) || (!$isNew && $canDo->get('core.edit'))) {
            $toolbar->apply('language.apply');
        }

        $saveGroup = $toolbar->dropdownButton('save-group');

        $saveGroup->configure(
            function (Toolbar $childBar) use ($canDo, $isNew) {
                if (($isNew && $canDo->get('core.create')) || (!$isNew && $canDo->get('core.edit'))) {
                    $childBar->save('language.save');
                }

                // If an existing item, can save to a copy only if we have create rights.
                if ($canDo->get('core.create')) {
                    $childBar->save2new('language.save2new');
                }
            }
        );

        if ($isNew) {
            $toolbar->cancel('language.cancel', 'JTOOLBAR_CANCEL');
        } else {
            $toolbar->cancel('language.cancel');
        }

        $toolbar->divider();
        $toolbar->help('Languages:_Edit_Content_Language');
    }
}
