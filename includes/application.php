<?php
/**
* @version		$Id$
* @package		Joomla
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters, Inc. All rights reserved.
* @license		GNU General Public License, see LICENSE.php
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.helper');

/**
* Joomla! Application class
*
* Provide many supporting API functions
*
* @package		Joomla
* @final
*/
class JSite extends JApplication
{

	protected $setTemplate = null;

	/**
	* Class constructor
	*
	* @access protected
	* @param	array An optional associative array of configuration settings.
	* Recognized key values include 'clientId' (this list is not meant to be comprehensive).
	*/
	protected function __construct($config = array())
	{
		$config['clientId'] = 0;
		parent::__construct($config);
	}

	/**
	* Initialise the application.
	*
	* @access public
	*/
	public function initialise( $options = array())
	{
		// if a language was specified it has priority
		// otherwise use user or default language settings
		if (empty($options['language']))
		{
			$user = & JFactory::getUser();
			$lang	= $user->getParam( 'language' );

			// Make sure that the user's language exists
			if ( $lang && JLanguage::exists($lang) ) {
				$options['language'] = $lang;
			} else {
				$params =  JComponentHelper::getParams('com_languages');
				$client	=& JApplicationHelper::getClientInfo($this->getClientId());
				$options['language'] = $params->get($client->name, 'en-GB');
			}

		}

		// One last check to make sure we have something
		if ( ! JLanguage::exists($options['language']) ) {
			$options['language'] = 'en-GB';
		}

		parent::initialise($options);
	}

	/**
	* Route the application
	*
	* @access public
	*/
	public function route() {
		parent::route();
	}

	/**
	* Dispatch the application
	*
	* @access public
	*/
	public function dispatch($component = null)
	{
		if ( ! $component )
		{
			$component = JRequest::getCmd('option');
		}

		$document	=& JFactory::getDocument();
		$user		=& JFactory::getUser();
		$router	 =& $this->getRouter();
		$params	 =& $this->getParams();

		switch($document->getType())
		{
			case 'html':
			{
				//set metadata
				$document->setMetaData( 'keywords', $this->getCfg('MetaKeys') );

				if ( $user->get('id') ) {
					$document->addScript( JURI::root(true).'/media/system/js/legacy.js');
				}

				if($router->getMode() == JROUTER_MODE_SEF) {
					$document->setBase(JURI::current());
				}
			} break;

			case 'feed':
			{
				$document->setBase(JURI::current());
			} break;

			default: break;
		}


		$document->setTitle( $params->get('page_title') );
		$document->setDescription( $params->get('page_description') );

		$contents = JComponentHelper::renderComponent($component);
		$document->setBuffer( $contents, 'component');
	}

	/**
	* Display the application.
	*
	* @access public
	*/
	public function render()
	{
		$document =& JFactory::getDocument();
		$user	 =& JFactory::getUser();

		// get the format to render
		$format = $document->getType();

		switch($format)
		{
			case 'feed' :
			{
				$params = array();
			} break;

			case 'html' :
			default	 :
			{
				$template	= $this->getTemplate();
				$file 		= JRequest::getCmd('tmpl', 'index');

				if ($this->getCfg('offline') && $user->get('gid') < '23' ) {
					$file = 'offline';
				}
				if (!is_dir( JPATH_THEMES.DS.$template ) && !$this->getCfg('offline')) {
					$file = 'component';
				}
				$params = array(
					'template' 	=> $template,
					'file'		=> $file.'.php',
					'directory'	=> JPATH_THEMES
				);
			} break;
 		}

		$data = $document->render( $this->getCfg('caching'), $params);
		JResponse::setBody($data);
	}

   /**
	* Login authentication function
	*
	* @param	array 	Array( 'username' => string, 'password' => string )
	* @param	array 	Array( 'remember' => boolean )
	* @access public
	* @see JApplication::login
	*/
	public function login($credentials, $options = array())
	{
		 //Set the application login entry point
		 if(!array_key_exists('entry_url', $options)) {
			 $options['entry_url'] = JURI::base().'index.php?option=com_user&task=login';
		 }

		return parent::login($credentials, $options);
	}

	/**
	* Check if the user can access the application
	*
	* @access public
	*/
	public function authorize($itemid)
	{
		$menus	=& $this->getMenu();
		$user	=& JFactory::getUser();
		$aid	= $user->get('aid');

		if(!$menus->authorize($itemid, $aid))
		{
			if ( ! $aid )
			{
				// Redirect to login
				$uri		= JFactory::getURI();
				$return		= $uri->toString();

				$url  = 'index.php?option=com_user&view=login';
				$url .= '&return='.base64_encode($return);;

				//$url	= JRoute::_($url, false);
				$this->redirect($url, JText::_('You must login first') );
			}
			else
			{
				JError::raiseError( 403, JText::_('Not Authorised') );
			}
		}
	}

	/**
	 * Get the appliaction parameters
	 *
	 * @param	string	The component option
	 * @return	object	The parameters object
	 * @since	1.5
	 */
	public function &getParams($option = null)
	{
		static $params;

		if (!is_object($params))
		{
			// Get component parameters
			if (!$option) {
				$option = JRequest::getCmd('option');
			}
			$params = &JComponentHelper::getParams($option);

			// Get menu parameters
			$menus	= & $this->getMenu();
			$menu	= $menus->getActive();

			$title			= htmlspecialchars_decode($this->getCfg('sitename' ));
			$description	= $this->getCfg('MetaDesc');

			// Lets cascade the parameters if we have menu item parameters
			if (is_object($menu))
			{
				$params->merge(new JParameter($menu->params));
				$title = $menu->name;
			}

			$params->def('page_title',		$title);
			$params->def('page_description',$description);
		}

		return $params;
	}

	/**
	 * Get the appliaction parameters
	 *
	 * @param	string	The component option
	 * @return	object	The parameters object
	 * @since	1.5
	 */
	public function &getPageParameters( $option = null )
	{
		return $this->getParams( $option );
	}

	/**
	 * Get the template
	 *
	 * @return string The template name
	 * @since 1.0
	 */
	public function getTemplate()
	{
		// Allows for overriding the active template from a component, and caches the result of this function
		// e.g. $mainframe->setTemplate('solar-flare-ii');
		if ($template = $this->get('setTemplate')) {
			return $template;
		}

		// Get the id of the active menu item
		$menu =& $this->getMenu();
		$item = $menu->getActive();

		$id = 0;
		if(is_object($item)) { // valid item retrieved
			$id = $item->id;
		}

		// Load template entries for the active menuid and the default template
		$db =& JFactory::getDBO();
		$query = 'SELECT template'
			. ' FROM #__templates_menu'
			. ' WHERE client_id = 0 AND (menuid = 0 OR menuid = '.(int) $id.')'
			. ' ORDER BY menuid DESC'
			;
		$db->setQuery($query, 0, 1);
		$template = $db->loadResult();

		// Allows for overriding the active template from the request
		$template = JRequest::getCmd('template', $template);
		$template = JFilterInput::_($template, 'cmd'); // need to filter the default value as well

		// Fallback template
		if (!file_exists(JPATH_THEMES.DS.$template.DS.'index.php')) {
			$template = 'rhuk_milkyway';
		}

		// Cache the result
		$this->set('setTemplate', $template);
		return $template;
	}

	/**
	 * Overrides the default template that would be used
	 *
	 * @param string The template name
	 */
	public function setTemplate( $template )
	{
		if (is_dir(JPATH_THEMES.DS.$template)) {
			$this->set('setTemplate', $template);
		}
	}

	/**
	 * Return a reference to the JPathway object.
	 *
	 * @access public
	 * @return object JPathway.
	 * @since 1.5
	 */
	public function &getMenu($name = null, $options = array())
	{
		$menu =& parent::getMenu('site', $options);
		return $menu;
	}

	/**
	 * Return a reference to the JPathway object.
	 *
	 * @access public
	 * @return object JPathway.
	 * @since 1.5
	 */
	public function &getPathWay($name = null, $options = array())
	{
		$pathway =& parent::getPathway('site', $options);
		return $pathway;
	}

	/**
	 * Return a reference to the JRouter object.
	 *
	 * @access	public
	 * @return	JRouter.
	 * @since	1.5
	 */
	public function &getRouter($name = null, $options = array())
	{
		$config =& JFactory::getConfig();
		$options['mode'] = $config->getValue('config.sef');
		$router =& parent::getRouter('site', $options);
		return $router;
	}
}
