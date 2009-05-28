<?php
/**
 * @version		$Id$
 * @package		Joomla.Site
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.helper');

/**
* Joomla! Application class
*
* Provide many supporting API functions
*
 * @package		Joomla.Site
* @final
 */
class JSite extends JApplication
{
	/**
	* Class constructor
	*
	* @access protected
	* @param	array An optional associative array of configuration settings.
	* Recognized key values include 'clientId' (this list is not meant to be comprehensive).
	*/
	function __construct($config = array())
	{
		$config['clientId'] = 0;
		parent::__construct($config);
	}

	/**
	* Initialise the application.
	*
	* @access public
	*/
	function initialise($options = array())
	{
		// if a language was specified it has priority
		// otherwise use user or default language settings
		if (empty($options['language']))
		{
			$user = & JFactory::getUser();
			$lang	= $user->getParam('language');

			// Make sure that the user's language exists
			if ($lang && JLanguage::exists($lang)) {
				$options['language'] = $lang;
			} else {
				$params =  JComponentHelper::getParams('com_languages');
				$client	= &JApplicationHelper::getClientInfo($this->getClientId());
				$options['language'] = $params->get($client->name, 'en-GB');
			}

		}

		// One last check to make sure we have something
		if (! JLanguage::exists($options['language'])) {
			$options['language'] = 'en-GB';
		}

		parent::initialise($options);
	}

	/**
	* Route the application
	*
	* @access public
	*/
	function route() {
		parent::route();
	}

	/**
	* Dispatch the application
	*
	* @access public
	*/
	function dispatch($component = null)
	{
		// Get the component if not set.
		if (!$component) {
			$component = JRequest::getCmd('option');
		}

		$document	= &JFactory::getDocument();
		$user		= &JFactory::getUser();
		$router     = &$this->getRouter();
		$params     = &$this->getParams();

		switch($document->getType())
		{
			case 'html':
			{
				//set metadata
				$document->setMetaData('keywords', $this->getCfg('MetaKeys'));

				if ($router->getMode() == JROUTER_MODE_SEF) {
					$document->setBase(JURI::current());
				}
			} break;

			case 'feed':
			{
				$document->setBase(JURI::current());
			} break;

			default: break;
		}


		$document->setTitle($params->get('page_title'));
		$document->setDescription($params->get('page_description'));

		$contents = JComponentHelper::renderComponent($component);
		$document->setBuffer($contents, 'component');

		// Trigger the onAfterDispatch event.
		JPluginHelper::importPlugin('system');
		$this->triggerEvent('onAfterDispatch');
	}

	/**
	* Display the application.
	*
	* @access public
	*/
	function render()
	{
		$document = &JFactory::getDocument();
		$user     = &JFactory::getUser();

		// get the format to render
		$format = $document->getType();

		switch($format)
		{
			case 'feed' :
			{
				$params = array();
			} break;

			case 'html' :
			default     :
			{
				$template	= $this->getTemplate(true);
				$file 		= JRequest::getCmd('tmpl', 'index');

				if ($this->getCfg('offline') && $user->get('gid') < '23') {
					$file = 'offline';
				}
				if (!is_dir(JPATH_THEMES.DS.$template->template) && !$this->getCfg('offline')) {
					$file = 'component';
				}
				$params = array(
					'template' 	=> $template->template,
					'file'		=> $file.'.php',
					'directory'	=> JPATH_THEMES,
					'params'	=> $template->params
				);
			} break;
 		}

		// Parse the document.
		$document = &JFactory::getDocument();
		$document->parse($params);

		// Trigger the onBeforeRender event.
		JPluginHelper::importPlugin('system');
		$this->triggerEvent('onBeforeRender');

		// Render the document.
		JResponse::setBody($document->render($this->getCfg('caching'), $params));

		// Trigger the onAfterRender event.
		$this->triggerEvent('onAfterRender');
	}

   /**
	* Login authentication function
	*
	* @param	array 	Array('username' => string, 'password' => string)
	* @param	array 	Array('remember' => boolean)
	* @access public
	* @see JApplication::login
	*/
	function login($credentials, $options = array())
	{
		 // Set the application login entry point
		if (!array_key_exists('entry_url', $options)) {
			$options['entry_url'] = JURI::base().'index.php?option=com_users&task=user.login';
		}

		// Set the access control action to check.
		$options['action'] = 'core.site.login';

		return parent::login($credentials, $options);
	}

	/**
	* Check if the user can access the application
	*
	* @access public
	*/
	function authorize($itemid)
	{
		$menus	= &JSite::getMenu();
		$user	= &JFactory::getUser();

		if (!$menus->authorise($itemid))
		{
			if ($user->get('id') == 0)
			{
				// Redirect to login
				$uri		= JFactory::getURI();
				$return		= $uri->toString();

				$url  = 'index.php?option=com_users&view=login';
				$url .= '&return='.base64_encode($return);;

				//$url	= JRoute::_($url, false);
				$this->redirect($url, JText::_('You must login first'));
			}
			else
			{
				JError::raiseError(403, JText::_('ALERTNOTAUTH'));
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
	function &getParams($option = null)
	{
		static $params = array();
		$hash = '__default';
		if (!empty($option)) $hash = $option;
		if (!isset($params[$hash]))
		{
			// Get component parameters
			if (!$option) {
				$option = JRequest::getCmd('option');
			}
			$params[$hash] = &JComponentHelper::getParams($option);

			// Get menu parameters
			$menus	= &JSite::getMenu();
			$menu	= $menus->getActive();

			$title       = htmlspecialchars_decode($this->getCfg('sitename'));
			$description = $this->getCfg('MetaDesc');

			// Lets cascade the parameters if we have menu item parameters
			if (is_object($menu))
			{
				$params[$hash]->merge(new JParameter($menu->params));
				$title = $menu->name;

			}

			$params[$hash]->def('page_title'      , $title);
			$params[$hash]->def('page_description', $description);
		}

		return $params[$hash];
	}

	/**
	 * Get the appliaction parameters
	 *
	 * @param	string	The component option
	 * @return	object	The parameters object
	 * @since	1.5
	 */
	function &getPageParameters($option = null)
	{
		return $this->getParams($option);
	}

	/**
	 * Get the template
	 *
	 * @return string The template name
	 * @since 1.0
	 */
	function getTemplate()
	{
		// Allows for overriding the active template from a component, and caches the result of this function
		// e.g. $mainframe->setTemplate('solar-flare-ii');
		if ($template = $this->get('setTemplate')) {
			if ($params)
			{
				return $template;
			}
			return $template->template;
		}

		// Get the id of the active menu item
		$menu = &$this->getMenu();
		$item = $menu->getActive();

		$id = 0;
		if (is_object($item)) { // valid item retrieved
			$id = $item->template_id;
		}
		$condition = '';
		
		$tid = JRequest::getInt('template', 0);
		if ((int) $tid > 0)
		{
			$id = (int) $tid;
		}
		if ($id == 0)
		{
			$condition = 'home = 1';
		} else {
			$condition = 'id = '.(int) $id;
		}

		// Load template entries for the active menuid and the default template
		$db = &JFactory::getDbo();
		$query = 'SELECT template, params'
			. ' FROM #__menu_template'
			. ' WHERE client_id = 0 AND '.$condition
			;
		$db->setQuery($query, 0, 1);
		$template = $db->loadObject();

		// Allows for overriding the active template from the request
		$template->template = JRequest::getCmd('template', $template->template);
		$template->template = JFilterInput::_($template->template, 'cmd'); // need to filter the default value as well

		// Fallback template
		if (!file_exists(JPATH_THEMES.DS.$template->template.DS.'index.php')) {
			$template->template = 'rhuk_milkyway';
		}

		// Cache the result
		$this->set('setTemplate', $template);
		if ($params)
		{
			return $template;
		}
		return $template->template;
	}

	/**
	 * Overrides the default template that would be used
	 *
	 * @param string The template name
	 */
	function setTemplate($template)
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
	function &getMenu()
	{
		$options = array();
		$menu = &parent::getMenu('site', $options);
		return $menu;
	}

	/**
	 * Return a reference to the JPathway object.
	 *
	 * @access public
	 * @return object JPathway.
	 * @since 1.5
	 */
	function &getPathWay()
	{
		$options = array();
		$pathway = &parent::getPathway('site', $options);
		return $pathway;
	}

	/**
	 * Return a reference to the JRouter object.
	 *
	 * @access	public
	 * @return	JRouter.
	 * @since	1.5
	 */
	function &getRouter()
	{
		$config = &JFactory::getConfig();
		$options['mode'] = $config->getValue('config.sef');
		$router = &parent::getRouter('site', $options);
		return $router;
	}
}
