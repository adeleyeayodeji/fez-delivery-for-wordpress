<?php

/**
 * Fez Core Session
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
	 * Set session
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function set($key, $value)
	{
		//check if session is available
		if (!session_id()) {
			session_start();
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
		//check if session is available
		if (!session_id()) {
			session_start();
		}
		return isset($_SESSION['fez_delivery_plugin'][$key]) ? $_SESSION['fez_delivery_plugin'][$key] : null;
	}

	/**
	 * Get all session
	 * @return array
	 */
	public function getAll()
	{
		return $_SESSION['fez_delivery_plugin'];
	}

	/**
	 * Unset session
	 * @param string $key
	 * @return void
	 */
	public function unset($key)
	{
		//check if session is available
		if (!session_id()) {
			session_start();
		}
		//check if we have the session
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
		//check if session is available
		if (!session_id()) {
			session_start();
		}

		//check if we have the session
		if (isset($_SESSION['fez_delivery_plugin'])) {
			unset($_SESSION['fez_delivery_plugin']);
		}
	}
}
