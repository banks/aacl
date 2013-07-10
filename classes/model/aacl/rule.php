<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Access rule model
 *
 * @see            http://github.com/banks/aacl
 * @package        AACL
 * @uses        Auth
 * @uses        ORM
 * @author        Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license        MIT
 */
class Model_AACL_Rule extends ORM_AACL {

	protected static $_acl_actions = array(
		'grant',
		'revoke',
	);

	/**
	 * Override Default model actions
	 */
	protected static $_acl_orm_actions = array();

	protected $_table_name = 'acl';

	protected $_primary_key = 'id';

	protected $_table_columns = array(
		'id' => array('type' => 'int'),
		'role_id' => array('type' => 'int', 'null' => TRUE),
		'resource' => array('type' => 'varchar'),
		'action' => array('type' => 'varchar'),
		'condition' => array('type' => 'varchar'),
	);

	protected $_belongs_to = array(
		'role' => array(
			'model' => 'Role',
			'foreign_key' => 'role_id',
		),
	);

	// TODO: validation

	/**
	 * AACL action
	 * grant access / create rule
	 *
	 * @param array $data
	 * @return $this
	 * @throws Exception
	 */
	public function grant(array $data)
	{
		if ($this->loaded())
		{
			throw new Exception('called grant on loaded rule');
		}

		$this->values($data);
		$this->check();
		AACL::grant($this->role, $this->resource, $this->action, $this->condition);
		return $this;
	}

	/**
	 * AACL action
	 * revoke access / delete rule
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function revoke()
	{
		if ( ! $this->loaded())
		{
			throw new Exception('rule doesn\'t exist');
		}

		AACL::revoke($this->role, $this->resource, $this->action, $this->condition);

		return TRUE;
	}

	/**
	 * Check if rule matches current request
	 *
	 * @param AACL_Resource $resource AACL_Resource object or it's id that user requested access to
	 * @param string               $action action requested [optional]
	 * @param Model_User           $user AACL instance
	 * @return bool
	 */
	public function allows_access_to(AACL_Resource $resource, $action = NULL, Model_User $user = NULL)
	{
		if (empty($this->resource))
		{
			// No point checking anything else!
			return TRUE;
		}

		if (is_null($action))
		{
			// Check to see if Resource wants to define it's own action
			$action = $resource->acl_actions(TRUE);
		}

		// Make sure action matches
		if ( ! is_null($action) AND ! empty($this->action) AND $action !== $this->action)
		{
			// This rule has a specific action and it doesn't match the specific one passed
			return FALSE;
		}

		$resource_id = $resource->acl_id();

		$matches = FALSE;

		// Make sure rule resource is the same as requested resource, or is an ancestor
		while ( ! $matches)
		{
			// Attempt match
			if ($this->resource === $resource_id)
			{
				// Stop loop
				$matches = TRUE;
			}
			else
			{
				// Find last occurence of '.' separator
				$last_dot_pos = strrpos($resource_id, '.');

				if ($last_dot_pos !== FALSE)
				{
					// This rule might match more generally, try the next level of specificity
					$resource_id = substr($resource_id, 0, $last_dot_pos);
				}
				else
				{
					// We can't make this any more general as there are no more dots
					// And we haven't managed to match the resource requested
					return FALSE;
				}
			}
		}

		// Now we know this rule matches the resource, check any match condition
		if ( ! empty($this->condition) AND ! $resource->acl_conditions($user, $this->condition))
		{
			// Condition wasn't met (or doesn't exist)
			return FALSE;
		}

		// All looks rosy!
		return TRUE;
	}

	/**
	 * Override create to remove less specific rules when creating a rule
	 *
	 * @param Validation $validation
	 * @return $this
	 */
	public function create(Validation $validation = NULL)
	{
		// Delete all more specific rules for this role
		$delete = DB::delete($this->_table_name);
		if (isset($this->_changed['role']))
		{
			$delete->where('role_id', '=', $this->_changed['role']);
		}
		else
		{
			$delete->where('role_id', 'IS', NULL);
		}

		// If resource is NULL we don't need any more rules - we just delete every rule for this role

		// Otherwise
		if ( ! is_null($this->resource))
		{
			// Need to restrict to roles with equal or more specific resource id
			$delete->where_open()
				->where('resource', '=', $this->resource)
				->or_where('resource', 'LIKE', $this->resource.'.%')
				->where_close();
		}

		if ( ! is_null($this->action))
		{
			// If this rule has an action, only remove other rules with the same action
			$delete->where('action', '=', $this->action);
		}

		if ( ! is_null($this->condition))
		{
			// If this rule has a condition, only remove other rules with the same condition
			$delete->where('condition', '=', $this->condition);
		}

		// Do the delete
		$delete->execute();

		// Create new rule
		return parent::create($validation);
	}

} // End Model_AACL_Core_Rule
