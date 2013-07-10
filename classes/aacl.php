<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Another ACL
 *
 * @see            http://github.com/banks/aacl
 * @package        AACL
 * @uses        Auth
 * @uses        ORM
 * @author        Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license        MIT
 */
class AACL {

	/**
	 * All rules that apply to the currently logged in user
	 *
	 * @var    array    contains Model_AACL_Rule objects
	 */
	protected static $_rules;

	/**
	 * @var array
	 */
	protected static $_resources;

	/**
	 * Returns the currently logged in user
	 *
	 * @return Model_User logged in user's instance or NULL pointer
	 * @return NULL
	 */
	public static function get_loggedin_user()
	{
		return Auth::instance()->get_user();
	}


	/**
	 * Grant access to $role for resource
	 *
	 * @param string|Model_Role $role string role name or Model_Role object [optional]
	 * @param string            $resource resource identifier [optional]
	 * @param string            $action action [optional]
	 * @param string            $condition condition [optional]
	 * @throws AACL_Exception
	 * @return void
	 */
	public static function grant($role = NULL, $resource = NULL, $action = NULL, $condition = NULL)
	{
		// if $role is null — we grant this to everyone
		if ( ! is_null($role))
		{
			// Normalize $role
			$role = AACL::normalize_role($role);

			// Check role exists
			if ( ! $role->loaded())
			{
				throw new AACL_Exception('Unknown role :role passed to AACL::grant()',
					array(':role' => $role->name));
			}

		}

		// Create rule
		AACL::create_rule(
			array(
				'role_id' => $role,
				'resource' => $resource,
				'action' => $action,
				'condition' => $condition,
			)
		);
	}

	/**
	 * Revoke access to $role for resource
	 * CHANGED: now accepts NULL role
	 *
	 * @param    string|Model_Role $role role name or Model_Role object [optional]
	 * @param    string            $resource resource identifier [optional]
	 * @param    string            $action action [optional]
	 * @param    string            $condition condition [optional]
	 * @return    void
	 */
	public static function revoke($role = NULL, $resource = NULL, $action = NULL, $condition = NULL)
	{
		$model = ORM::factory('AACL_Rule');

		if (is_null($role))
		{
			$model->where('role_id', 'IS', NULL);
		}
		else
		{
			// Normalize $role
			$role = AACL::normalize_role($role);

			// Check role exists
			if ( ! $role->loaded())
			{
				// Just return without deleting anything
				return;
			}

			$model->where('role_id', '=', $role->id);
		}

		if ( ! is_null($resource))
		{
			// Add normal resources, resource NULL will delete all rules
			$model->and_where('resource', '=', $resource);

			if ( ! is_null($action))
			{
				$model->and_where('action', '=', $action);
			}

			if ( ! is_null($condition))
			{
				$model->and_where('condition', '=', $condition);
			}
		}

		$rules = $model->find_all();

        foreach ($rules as $rule)
        {
            $rule->delete();
        }
	}

	/**
	 * Method, that allows to check any rule from database in any place of project.
	 * Works with string presentations of resources, actions, roles and conditions
	 *
	 * @param AACL_Resource $resource
	 * @param string        $action
	 * @return bool
	 */
	public static function access(AACL_Resource $resource, $action = NULL)
	{
		try
		{
			AACL::check($resource, $action);
		}
		catch (AACL_Exception $e)
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Checks user has permission to access resource
	 * works with unauthenticated users (role_id = NULL)
	 *
	 * @param    AACL_Resource $resource AACL_Resource object being requested
	 * @param    string        $action action identifier [optional]
	 * @throws   AACL_Exception_401 To identify permission or authentication failure
	 * @throws   AACL_Exception_403 To identify permission or authentication failure
	 * @return   void
	 */
	public static function check(AACL_Resource $resource, $action = NULL)
	{
		$user = AACL::get_loggedin_user();

		// User is logged in, check rules
		$rules = AACL::_get_rules($user);

		/**
		 * @var Model_AACL_Rule $rule
		 */
		foreach ($rules as $rule)
		{
			if ($rule->allows_access_to($resource, $action, $user))
			{
				// Access granted, just return
				return;
			}
		}

		// No access rule matched
		if ($user)
		{
			throw new AACL_Exception_403;
		}
		else
		{
			throw new AACL_Exception_401;
		}
	}

    /**
     * Almost the same as check() but doesn't throw exceptions and answer is boolean
	 *
	 * @param    AACL_Resource $resource AACL_Resource object being requested
	 * @param    string        $action action identifier [optional]
	 * @return   boolean 
	 */
	public static function check_if(AACL_Resource $resource, $action = NULL)
	{
		$user = AACL::get_loggedin_user();

		// User is logged in, check rules
		$rules = AACL::_get_rules($user);

		/**
		 * @var Model_AACL_Rule $rule
		 */
		foreach ($rules as $rule)
		{
			if ($rule->allows_access_to($resource, $action, $user))
			{
				return TRUE;
			}
		}

        return FALSE;
	}

	/**
	 * Create an AACL rule
	 *
	 * @param array $fields optional fields' values
	 *
	 * @return void
	 */
	public static function create_rule(array $fields = array())
	{
		ORM::factory('AACL_Rule')->values($fields)->create();
	}

	/**
	 * Get all rules that apply to user
	 *
	 * CHANGED
	 *
	 * @param mixed $user Model_User|Model_Role|bool User, role or everyone
	 * @param bool  $force_load [optional] Force reload from DB default FALSE
	 * @return Database_Result
	 */
	public static function _get_rules($user = FALSE, $force_load = FALSE)
	{
		if ( ! isset(AACL::$_rules) || $force_load)
		{
			$select_query = ORM::factory('AACL_Rule')
				// User is guest
				->where('role_id', '=', NULL);

			// Get rules for user
			if ($user instanceof Model_User and $user->loaded())
			{
				AACL::$_rules = $select_query->or_where('role_id', 'IN', $user->roles->find_all()->as_array());
			}
			// Get rules for role
			elseif ($user instanceof Model_Role and $user->loaded())
			{
				AACL::$_rules = $select_query->or_where('role_id', '=', $user->id);
			}

			AACL::$_rules = $select_query
				->order_by('LENGTH("resource")', 'ASC')
				->find_all()->as_array();
		}

		return AACL::$_rules;
	}

	/**
	 * Returns a list of all valid resource objects based on the filesstem adn
	 * FIXED
	 *
	 * @param    string|bool string resource_id [optional] if provided, the info for that specific resource ID is returned,
	 *                    if TRUE a flat array of just the ids is returned
	 * @return    array
	 */
	public static function list_resources($resource_id = FALSE)
	{
		if ( ! isset(AACL::$_resources))
		{
			// Find all classes in the application and modules
			$classes = AACL::_list_classes();

			// Loop through classes and see if they implement AACL_Resource
			foreach ($classes as $class_name)
			{
				$class = new ReflectionClass($class_name);

				if ($class->implementsInterface('AACL_Resource'))
				{
					// Ignore interfaces and abstract classes
					if ($class->isInterface() || $class->isAbstract())
					{
						continue;
					}

					// Create an instance of the class
					$resource = $class->getMethod('acl_instance')->invoke($class_name, $class_name);

					// Get resource info
					AACL::$_resources[$resource->acl_id()] = array(
						'actions' => $resource->acl_actions(),
						'conditions' => $resource->acl_conditions(),
					);
				}

				unset($class);
			}
		}

		if ($resource_id === TRUE)
		{
			return array_keys(AACL::$_resources);
		}
		elseif ($resource_id)
		{
			return isset(AACL::$_resources[$resource_id]) ? AACL::$_resources[$resource_id] : NULL;
		}

		return AACL::$_resources;
	}

	protected static function _list_classes($files = NULL)
	{
		if (is_null($files))
		{
			// Remove core module paths form search
			$loaded_modules = Kohana::modules();

			$exclude_modules = array(
				'database',
				'orm',
				'auth',
				'userguide',
				'image',
				'codebench',
				'unittest',
				'pagination',
				'cache',
			);

			$paths = Kohana::include_paths();

			// Remove known core module paths
			foreach ($loaded_modules as $module => $path)
			{
				if (in_array($module, $exclude_modules))
				{
					// Doesn't works properly — double slash on the end
					//	unset($paths[array_search($path.DIRECTORY_SEPARATOR, $paths)]);
					unset($paths[array_search($path, $paths)]);
				}
			}

			// Remove system path
			unset($paths[array_search(SYSPATH, $paths)]);
			$files = array_merge(Kohana::list_files('classes'.DIRECTORY_SEPARATOR.'controller', $paths), Kohana::list_files('classes'.DIRECTORY_SEPARATOR.'model', $paths));
		}

		$classes = array();

		foreach ($files as $name => $path)
		{
			if (is_array($path))
			{
				$classes = array_merge($classes, AACL::_list_classes($path));
			}
			else
			{
				// Strip 'classes/' off start
				$name = substr($name, 8);

				// Strip '.php' off end
				$name = substr($name, 0, 0 - strlen(EXT));

				// Convert to class name
				$classes[] = str_replace(DIRECTORY_SEPARATOR, '_', $name);
			}
		}

		return $classes;
	}

	/**
	 * Normalize role
	 *
	 * @param Model_Role|string $role role instance or role identifier
	 *
	 * @return Model_Role role instance
	 */
	protected static function normalize_role($role)
	{
		if ( ! $role instanceof Model_Role)
		{
			return ORM::factory('role')->where('name', '=', $role)->find();
		}

		return $role;
	}

	/**
	 * Force static access
	 */
	protected function __construct() {}

	/**
	 * Force static access
	 *
	 * @return void
	 */
	protected function __clone() {}

} // End  AACL_Core
