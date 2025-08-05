<?php

/**
 * Handles the loading and interaction with the woocommerce/action-scheduler package.
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */

namespace SolidAffiliate\Lib;

/**
 * Class Action_Scheduler
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */
class Action_Scheduler
{

	/**
	 * Loads, either from WooCommerce or from the embedded package.
	 *
	 * @since TBD
	 *
	 * @return void The function will load the Action Scheduler package if required.
	 *
	 * @throws \RuntimeException If the Action Scheduler package could not be loaded.
	 */
	public static function load()
	{
		if (function_exists('as_enqueue_async_action')) {
			return;
		}

		if (defined('WP_INSTALLING') && WP_INSTALLING) {
			return;
		}

		if (class_exists('WooCommerce')) {
			// If WooCommerce is installed require its version.
			$as_path = \WooCommerce::instance()->plugin_path() . '/packages/action-scheduler/action-scheduler.php';

			if (is_file($as_path)) {
				require_once $as_path;

				return;
			}
		}

		require_once SOLID_AFFILIATE_DIR . '/libraries/action-scheduler/action-scheduler.php';
	}

	/**
	 * @param string $hook
	 * @param array $args
	 * @param string $group
	 * 
	 * @return int
	 */
	public static function enqueue_async_action($hook, $args = [], $group = '')
	{
		$action_id = (int)as_enqueue_async_action($hook, $args, $group);

		if (empty($action_id)) {
			// Log a notice for the admin that email send could not be enqueued for the batch.
			do_action("solid_affiliate/log", 'error', $hook, 'Failed to enqueue async action.', $args);

			// It's unlikely there will be any more success, bail.
			return 0;
		}

		return $action_id;
	}

	/**
	 * @param array $args
	 * @return array
	 */
	public static function get_pending_scheduled_actions($args = [])
	{
		$args = array_merge([
			'status' => 'pending',
		], $args);

		return as_get_scheduled_actions($args, 'ARRAY_A');
	}
}
