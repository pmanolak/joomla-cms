<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_fields
 *
 * @copyright   (C) 2016 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Fields\Administrator\View\Groups;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Button\DropdownButton;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Component\Fields\Administrator\Model\GroupsModel;
use Joomla\Filesystem\Path;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Groups View
 *
 * @since  3.7.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var    \Joomla\CMS\Form\Form
     *
     * @since  3.7.0
     */
    public $filterForm;

    /**
     * @var    array
     *
     * @since  3.7.0
     */
    public $activeFilters;

    /**
     * @var    array
     *
     * @since  3.7.0
     */
    protected $items;

    /**
     * @var    \Joomla\CMS\Pagination\Pagination
     *
     * @since  3.7.0
     */
    protected $pagination;

    /**
     * @var   \Joomla\Registry\Registry
     *
     * @since  3.7.0
     */
    protected $state;

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @see     HtmlView::loadTemplate()
     *
     * @since   3.7.0
     */
    public function display($tpl = null)
    {
        /** @var GroupsModel $model */
        $model = $this->getModel();

        $this->state         = $model->getState();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        // Check for errors.
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Display a warning if the fields system plugin is disabled
        if (!PluginHelper::isEnabled('system', 'fields')) {
            $link = Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . FieldsHelper::getFieldsPluginId());
            Factory::getApplication()->enqueueMessage(Text::sprintf('COM_FIELDS_SYSTEM_PLUGIN_NOT_ENABLED', $link), 'warning');
        }

        $this->addToolbar();

        // We do not need to filter by language when multilingual is disabled
        if (!Multilanguage::isEnabled()) {
            unset($this->activeFilters['language']);
            $this->filterForm->removeField('language', 'filter');
        }

        parent::display($tpl);
    }

    /**
     * Adds the toolbar.
     *
     * @return  void
     *
     * @since   3.7.0
     */
    protected function addToolbar()
    {
        $toolbar   = $this->getDocument()->getToolbar();
        $groupId   = $this->state->get('filter.group_id');
        $component = '';
        $parts     = FieldsHelper::extract($this->state->get('filter.context'));

        if ($parts) {
            $component = $parts[0];
        }

        $canDo     = ContentHelper::getActions($component, 'fieldgroup', $groupId);

        // Avoid nonsense situation.
        if ($component == 'com_fields') {
            return;
        }

        // Load component language file
        $lang = $this->getLanguage();
        $lang->load($component, JPATH_ADMINISTRATOR)
        || $lang->load($component, Path::clean(JPATH_ADMINISTRATOR . '/components/' . $component));

        $title = Text::sprintf('COM_FIELDS_VIEW_GROUPS_TITLE', Text::_(strtoupper($component)));

        // Prepare the toolbar.
        ToolbarHelper::title($title, 'puzzle-piece fields ' . substr($component, 4) . '-groups');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('group.add');
        }

        if ($canDo->get('core.edit.state') || $this->getCurrentUser()->authorise('core.admin')) {
            /** @var DropdownButton $dropdown */
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if ($canDo->get('core.edit.state')) {
                $childBar->publish('groups.publish')->listCheck(true);
                $childBar->unpublish('groups.unpublish')->listCheck(true);
                $childBar->archive('groups.archive')->listCheck(true);
            }

            if ($this->getCurrentUser()->authorise('core.admin')) {
                $childBar->checkin('groups.checkin')->listCheck(true);
            }

            if ($canDo->get('core.edit.state') && !$this->state->get('filter.state') == -2) {
                $childBar->trash('groups.trash')->listCheck(true);
            }

            // Add a batch button
            if ($canDo->get('core.create') && $canDo->get('core.edit') && $canDo->get('core.edit.state')) {
                $childBar->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_FIELDS_VIEW_GROUPS_BATCH_OPTIONS'))
                    ->url('#joomla-dialog-batch')
                    ->modalWidth('800px')
                    ->modalHeight('fit-content')
                    ->listCheck(true);
            }
        }

        if ($this->state->get('filter.state') == -2 && $canDo->get('core.delete', $component)) {
            $toolbar->delete('groups.delete', 'JTOOLBAR_DELETE_FROM_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences($component);
        }

        $toolbar->help('Field_Groups');
    }
}
