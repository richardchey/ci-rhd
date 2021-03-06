<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Loader extends CI_Loader
{
	/**
	 * List of loaded sercices
	 *
	 * @var array
	 * @access protected
	 */
	protected $_ci_services = array();
	/**
	 * List of paths to load sercices from
	 *
	 * @var array
	 * @access protected
	 */
	protected $_ci_service_paths		= array();

	/**
	 * Constructor
	 *
	 * Set the path to the Service files
	 */
	public function __construct()
	{

		parent::__construct();
		load_class('Service','core');
		$this->_ci_service_paths = array(APPPATH);
	}

	/**
	 * Service Loader
	 *
	 * This function lets users load and instantiate classes.
	 * It is designed to be called from a user's app controllers.
	 *
	 * @param	string	the name of the class
	 * @param	mixed	the optional parameters
	 * @param	string	an optional object name
	 * @return	void
	 */
	public function service($service = '', $params = NULL, $object_name = NULL)
	{
		if(is_array($service))
		{
			foreach($service as $class)
			{
				$this->service($class, $params);
			}

			return;
		}

		if($service == '' or isset($this->_ci_services[$service])) {
			return FALSE;
		}

		if(! is_null($params) && ! is_array($params)) {
			$params = NULL;
		}

		$subdir = '';

		// Is the service in a sub-folder? If so, parse out the filename and path.
		if (($last_slash = strrpos($service, '/')) !== FALSE)
		{
			// The path is in front of the last slash
			$subdir = substr($service, 0, $last_slash + 1);

			// And the service name behind it
			$service = substr($service, $last_slash + 1);
		}

		foreach($this->_ci_service_paths as $path)
		{

			$filepath = $path .'services/'.$subdir.$service.'.php';

			if ( ! file_exists($filepath))
			{
				continue;
			}

			include_once($filepath);

			$service = strtolower($service);

			if (empty($object_name))
			{
				$object_name = $service;
			}

			$service = ucfirst($service);
			$CI = &get_instance();
			if($params !== NULL)
			{
				$CI->$object_name = new $service($params);
			}
			else
			{
				$CI->$object_name = new $service();
			}

			$this->_ci_services[] = $object_name;

			return;
		}
	}

	/**
	 * Model Loader
	 *
	 * Loads and instantiates models.
	 *
	 * @param	string	     $model   Model name
	 * @param	string|array $name    An optional object name to assign to
	 * @param	bool	     $db_conn An optional database connection configuration to initialize
	 * @param	array	     $params
	 * @return	object
	 */
	public function model($model, $name = '', $db_conn = FALSE, $params = null)
	{
		if (empty($model))
		{
			return $this;
		}
		elseif (is_array($model))
		{
			foreach ($model as $key => $value)
			{
				is_int($key) ? $this->model($value, '', $db_conn) : $this->model($key, $value, $db_conn);
			}

			return $this;
		}

		$path = '';

		// Is the model in a sub-folder? If so, parse out the filename and path.
		if (($last_slash = strrpos($model, '/')) !== FALSE)
		{
			// The path is in front of the last slash
			$path = substr($model, 0, ++$last_slash);

			// And the model name behind it
			$model = substr($model, $last_slash);
		}

		if (is_null($params) && (! is_string($name) || is_numeric($name))) {
			$params = $name;
			$name = '';
		}

		if (empty($name))
		{
			$name = $model;
		}

		if (in_array($name, $this->_ci_models, TRUE))
		{
			return $this;
		}

		$CI =& get_instance();
		if (isset($CI->$name))
		{
			throw new RuntimeException('The model name you are loading is the name of a resource that is already being used: '.$name);
		}

		if ($db_conn !== FALSE && ! class_exists('CI_DB', FALSE))
		{
			if ($db_conn === TRUE)
			{
				$db_conn = '';
			}

			$this->database($db_conn, FALSE, TRUE);
		}

		// Note: All of the code under this condition used to be just:
		//
		//       load_class('Model', 'core');
		//
		//       However, load_class() instantiates classes
		//       to cache them for later use and that prevents
		//       MY_Model from being an abstract class and is
		//       sub-optimal otherwise anyway.
		if ( ! class_exists('CI_Model', FALSE))
		{
			$app_path = APPPATH.'core'.DIRECTORY_SEPARATOR;
			if (file_exists($app_path.'Model.php'))
			{
				require_once($app_path.'Model.php');
				if ( ! class_exists('CI_Model', FALSE))
				{
					throw new RuntimeException($app_path."Model.php exists, but doesn't declare class CI_Model");
				}
			}
			elseif ( ! class_exists('CI_Model', FALSE))
			{
				require_once(BASEPATH.'core'.DIRECTORY_SEPARATOR.'Model.php');
			}

			$class = config_item('subclass_prefix').'Model';
			if (file_exists($app_path.$class.'.php'))
			{
				require_once($app_path.$class.'.php');
				if ( ! class_exists($class, FALSE))
				{
					throw new RuntimeException($app_path.$class.".php exists, but doesn't declare class ".$class);
				}
			}
		}

		$model = ucfirst($model);
		if ( ! class_exists($model))
		{
			foreach ($this->_ci_model_paths as $mod_path)
			{
				if ( ! file_exists($mod_path.'models/'.$path.$model.'.php'))
				{
					continue;
				}

				require_once($mod_path.'models/'.$path.$model.'.php');
				if ( ! class_exists($model, FALSE))
				{
					throw new RuntimeException($mod_path."models/".$path.$model.".php exists, but doesn't declare class ".$model);
				}

				break;
			}

			if ( ! class_exists($model, FALSE))
			{
				throw new RuntimeException('Unable to locate the model you have specified: '.$model);
			}
		}
		elseif ( ! is_subclass_of($model, 'CI_Model'))
		{
			throw new RuntimeException("Class ".$model." already exists and doesn't extend CI_Model");
		}

		$this->_ci_models[] = $name;
		if (is_null($params)) {
			$CI->$name = new $model();
		} else {
			$CI->$name = new $model($params);
		}
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Database Loader
	 *
	 * @param	mixed	$params		Database configuration options
	 * @param	bool	$return 	Whether to return the database object
	 * @param	bool	$query_builder	Whether to enable Query Builder
	 *					(overrides the configuration setting)
	 *
	 * @return	object|bool	Database object if $return is set to TRUE,
	 *					FALSE on failure, CI_Loader instance in any other case
	 */
	public function database($params = '', $return = FALSE, $query_builder = NULL)
	{
		// Grab the super object
		$CI =& get_instance();

		// Do we even need to load the database class?
		if ($return === FALSE && $query_builder === NULL && isset($CI->db) && is_object($CI->db) && ! empty($CI->db->conn_id))
		{
			return FALSE;
		}

		/** richard begin */
		if (file_exists(APPPATH . 'database/DB.php')) {
			require_once(APPPATH . 'database/DB.php');
		} else {
			require_once(BASEPATH.'database/DB.php');
		}
		/** richard end */

		if ($return === TRUE)
		{
			return DB($params, $query_builder);
		}

		// Initialize the db variable. Needed to prevent
		// reference errors with some configurations
		$CI->db = '';

		// Load the DB class
		$CI->db =& DB($params, $query_builder);
		return $this;
	}

}
