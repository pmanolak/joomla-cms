<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_templates
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Templates\Administrator\View\Styles;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Templates\Administrator\Model\StylesModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View class for a list of template styles.
 *
 * @since  1.6
 */
class HtmlView extends BaseHtmlView
{
    /**
     * An array of items
     *
     * @var  array
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var  \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var    \Joomla\CMS\Form\Form
     *
     * @since  4.0.0
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var    array
     * @since  4.0.0
     */
    public $activeFilters;

    /**
     * Is the parameter enabled to show template positions in the frontend?
     *
     * @var    boolean
     * @since  4.0.0
     */
    public $preview;

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        /** @var StylesModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->total         = $model->getTotal();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->preview       = ComponentHelper::getParams('com_templates')->get('template_positions_display');

        // Remove the menu item filter for administrator styles.
        if ((int) $this->state->get('client_id') !== 0) {
            unset($this->activeFilters['menuitem']);
            $this->filterForm->removeField('menuitem', 'filter');
        }

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
        $canDo    = ContentHelper::getActions('com_templates');
        $clientId = (int) $this->state->get('client_id');
        $toolbar  = $this->getDocument()->getToolbar();

        // Add a shortcut to the templates list view.
        $toolbar->linkButton('templates', 'COM_TEMPLATES_MANAGER_TEMPLATES')
            ->url('index.php?option=com_templates&view=templates&client_id=' . $clientId)
            ->icon('icon-code thememanager');

        // Set the title.
        if ($clientId === 1) {
            ToolbarHelper::title(Text::_('COM_TEMPLATES_MANAGER_STYLES_ADMIN'), 'paint-brush thememanager');
        } else {
            ToolbarHelper::title(Text::_('COM_TEMPLATES_MANAGER_STYLES_SITE'), 'paint-brush thememanager');
        }

        if ($canDo->get('core.edit.state')) {
            $toolbar->makeDefault('styles.setDefault', 'COM_TEMPLATES_TOOLBAR_SET_HOME');
            $toolbar->divider();
        }

        if ($canDo->get('core.create')) {
            $toolbar->standardButton('duplicate', 'JTOOLBAR_DUPLICATE', 'styles.duplicate')
                ->listCheck(true)
                ->icon('icon-copy');
            $toolbar->divider();
        }

        if ($canDo->get('core.delete')) {
            $toolbar->delete('styles.delete')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
            $toolbar->divider();
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_templates');
            $toolbar->divider();
        }

        $toolbar->help('Templates:_Styles');
    }
}
