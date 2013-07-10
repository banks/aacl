<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * 401 "User requires authentication" exception
 *
 * @see            http://github.com/banks/aacl
 * @package        AACL
 * @uses        Auth
 * @uses        Sprig
 * @author        Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license        MIT
 */
class AACL_Exception_401 extends AACL_Exception {

	/**
	 * @var   integer    HTTP 401 Unauthorized
	 */
	protected $_code = 401;

}
