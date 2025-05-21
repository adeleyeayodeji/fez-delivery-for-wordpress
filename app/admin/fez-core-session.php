<?php

/**
 * Fez Core Session
 *
 * @package FezDelivery\App\Admin
 */

namespace Fez_Delivery\Admin;

use Fez_Delivery\Base;

//check for security
if (!defined('ABSPATH')) {
	exit("You are not allowed to access this file.");
}

class FezCoreSession extends Base
{
	/**
	 * Whether the session has been started
	 * @var bool
	 */
	private $session_started = false;

	/**
	 * Initialize session only if we're in admin or specific AJAX endpoints
	 * @return bool
	 */
	private function maybe_start_session()
	{
		// Don't start session if already started
		if ($this->session_started) {
			return true;
		}

		// Start session if not already started
		if (!session_id()) {
			@session_start();
			$this->session_started = true;
		}

		return true;
	}

	/**
	 * Set session
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function set($key, $value)
	{
		if (!$this->maybe_start_session()) {
			return;
		}
		$_SESSION['fez_delivery_plugin'][$key] = $value;
	}

	/**
	 * Get session
	 * @param string $key
	 * @return mixed
	 */
	public function get($key)
	{
		if (!$this->maybe_start_session()) {
			return null;
		}
		return isset($_SESSION['fez_delivery_plugin'][$key]) ? $this->sanitizeDynamic($_SESSION['fez_delivery_plugin'][$key]) : null;
	}

	/**
	 * Get all session
	 * @return array|null
	 */
	public function getAll()
	{
		if (!$this->maybe_start_session()) {
			return null;
		}
		return isset($_SESSION['fez_delivery_plugin']) ? $this->sanitize_array($_SESSION['fez_delivery_plugin']) : null;
	}

	/**
	 * Unset session
	 * @param string $key
	 * @return void
	 */
	public function unset($key)
	{
		if (!$this->maybe_start_session()) {
			return;
		}
		if (isset($_SESSION['fez_delivery_plugin'][$key])) {
			unset($_SESSION['fez_delivery_plugin'][$key]);
		}
	}

	/**
	 * Delete session
	 * @param string $key
	 * @return void
	 */
	public function delete($key)
	{
		$this->unset($key);
	}

	/**
	 * Destroy session
	 * @return void
	 */
	public function destroy()
	{
		if (!$this->maybe_start_session()) {
			return;
		}
		if (isset($_SESSION['fez_delivery_plugin'])) {
			unset($_SESSION['fez_delivery_plugin']);
		}
		$this->session_started = false;
	}

	//sanitize_array
	public function sanitize_array($array)
	{
		//check if array is not empty
		if (!empty($array)) {
			//loop through array
			foreach ($array as $key => $value) {
				//check if value is array
				if (is_array($array)) {
					//sanitize array
					$array[$key] = is_array($value) ? $this->sanitize_array($value) : $this->sanitizeDynamic($value);
				} else {
					//check if $array is object
					if (is_object($array)) {
						//sanitize object
						$array->$key = $this->sanitizeDynamic($value);
					} else {
						//sanitize mixed
						$array[$key] = $this->sanitizeDynamic($value);
					}
				}
			}
		}
		//return array
		return $array;
	}

	//sanitize_object
	public function sanitize_object($object)
	{
		//check if object is not empty
		if (!empty($object)) {
			//loop through object
			foreach ($object as $key => $value) {
				//check if value is array
				if (is_array($value)) {
					//sanitize array
					$object->$key = $this->sanitize_array($value);
				} else {
					//sanitize mixed
					$object->$key = $this->sanitizeDynamic($value);
				}
			}
		}
		//return object
		return $object;
	}

	//dynamic sanitize
	public function sanitizeDynamic($data)
	{
		$type = gettype($data);
		switch ($type) {
			case 'array':
				return $this->sanitize_array($data);
				break;
			case 'object':
				return $this->sanitize_object($data);
				break;
			default:
				return sanitize_text_field($data);
				break;
		}
	}
}
