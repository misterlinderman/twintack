<?php

/**
 * Handles the logging of the plugin events.
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */

namespace SolidAffiliate\Lib;

/**
 * Class Logger
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */
class Logger
{
	/**
	 * Logs using the PHP error_log function.
	 *
	 * @since TBD
	 *
	 * @param string|int $level   The type or level of the log.
	 * @param string     $source  The source of the log message.
	 * @param string     $message The log message body.
	 * @param mixed      $data    The optional data attached to the log.
	 *
	 * @return void
	 */
	public static function log($level = 'debug', $source = 'SA', $message = '', $data = [])
	{
		/**
		 * Allows filtering the log channels the plugin will use depending on the level and source of the log message.
		 *
		 * @since TBD
		 *
		 * @param array<string,array<callable>> A map that will relate each log level to a list of log callbacks.
		 * @param string|int $level  The level of the log message.
		 * @param string     $source The source of the message.
		 */
		$channels = apply_filters("solid_affiliate/Logger/channels", [
			'error' => [
				[static::class, 'error_log']
			],
		], $level, $source);

		$callbacks = isset($channels[$level]) ? (array) $channels[$level] : [];
		$callbacks = array_filter($callbacks, 'is_callable');

		if (!count($callbacks)) {
			return;
		}

		foreach ($callbacks as $callback) {
			try {
				$callback($level, $source, $message, $data);
			} catch (\Exception $e) {
				// Failure to log should not generate a cascading failure, it should show up in the PHP log.
			}
		}
	}

	/**
	 * Logs using the PHP error_log function.
	 *
	 * @since TBD
	 *
	 * @param string|int $level   The type or level of the log.
	 * @param string     $source  The source of the log message.
	 * @param string     $message The log message body.
	 * @param mixed      $data    The optional data attached to the log.
	 *
	 * @return void
	 */
	public static function error_log($level = 'debug', $source = 'SA', $message = '', $data = [])
	{
		$data_string = json_encode($data);
		$message     = sprintf(
			"SA type='%s' source='%s' message='%s' data='%s'",
			strtolower(trim((string) $level)),
			trim((string) $source),
			trim((string) $message),
			$data_string ? $data_string : ''
		);
		error_log($message);
	}
}
