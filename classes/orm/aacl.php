<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Base class for access controlled ORM Models
 *
 * @see          http://github.com/banks/aacl
 * @package      AACL
 * @uses         Auth
 * @uses         ORM
 * @author       Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license      MIT
 */
abstract class ORM_AACL extends ORM implements AACL_Resource {

	/**
	 * @var array
	 * model specific overrides
	 */
	protected static $_acl_actions = array();

	/**
	 * @var array
	 */
	protected static $_acl_orm_actions = array('create', 'read', 'update', 'delete');

	/**
	 * @var string
	 */
	protected $_acl_id = '';

	/**
	 * AACL_Resource::acl_id() implementation
	 *
	 * Note: keeps a cache of the acl_id and returns it if the model hasn't changed
	 *
	 * @return    string
	 */
	public function acl_id()
	{
		if ( ! empty($this->_acl_id) and ! $this->changed())
		{
			return $this->_acl_id;
		}

		// Create unique id from primary key if it is set
		$id = (string) $this->pk();

		if ( ! empty($id))
		{
			$id = '.'.$id;
		}

		// Model namespace, model name, pk
		$this->_acl_id = 'm:'.strtolower($this->object_name()).$id;
		return $this->_acl_id;
	}

	/**
	 * AACL_Resource::acl_actions() implementation
	 *
	 * @param    bool $return_current [optional]
	 * @return    mixed
	 */
	public function acl_actions($return_current = FALSE)
	{
		if ($return_current)
		{
			// We don't know anything about what the user intends to do with us!
			return NULL;
		}

		// Return default model actions
		return array_merge(static::$_acl_actions, static::$_acl_orm_actions);
	}

	/**
	 * AACL_Resource::acl_conditions() implementation
	 *
	 * @param    Model_User $user [optional] logged in user model
	 * @param    string     $condition [optional] condition to test
	 * @return    mixed
	 */
	public function acl_conditions(Model_User $user = NULL, $condition = NULL)
	{
		if (is_null($user) AND is_null($condition))
		{
			// We have no conditions - they will be model specific
			return array();
		}

		// We have no conditions so this test should fail!
		return FALSE;
	}

	/**
	 * AACL_Resource::acl_instance() implementation
	 *
	 * Note that the object instance returned should not be used for anything except querying the acl_* methods
	 *
	 * @param    string $class_name Class name of object required
	 * @return    Object
	 */
	public static function acl_instance($class_name)
	{
		$model_name = strtolower(substr($class_name, 6));

		return ORM::factory($model_name);
	}

} // End ORM_AACL
