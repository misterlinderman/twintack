<?php

namespace SolidAffiliate\Lib;

/**
 * Solid Logger
 *
 * A simple logging class for WordPress plugins that appends log entries
 * to a text file in the uploads directory.
 *
 * @author Your Name
 * @version 1.0.0
 */
class SolidLogger
{
    const MAX_LINES = 10000;
    const LINES_TO_REMOVE_WHEN_OVER_LIMIT = 1000;

    /**
     * THIS IS ALL IT DOES, JUST LOG.
     * 
     * Append a log entry to the log file.
     *
     * @param string $message The log message to be recorded.
     *
     * @return void
     */
    public static function log(string $message)
    {
        return;

        // if ((bool)Settings::get(Settings::KEY_IS_LOGGING_DISABLED)) {
        //     return;
        // }

        # The solid_transcient stuff "simply" makes it so we only truncate the log file every 10 minutes.
        Utils::solid_transient([self::class, 'maybe_truncate_log_file'], 'solidlogger_maybe_truncate_log_file', 60 * 10);

        $log_entry = self::process_log_message($message);

        self::write_to_log($log_entry);
    }

    /**
     * Adds hooks for the observed actions.
     * 
     * @return void
     */
    public static function add_hooks()
    {
        // Delete the log file if it exists
        if (file_exists(self::log_file_path())) {
            unlink(self::log_file_path());
        }
        return;
        // if ((bool)Settings::get(Settings::KEY_IS_LOGGING_DISABLED)) {
        //     return;
        // }

        // Define the observed actions array
        $observed_actions = [
            'woocommerce_checkout_update_order_meta',
            'woocommerce_process_shop_order_meta',
            'woocommerce_store_api_checkout_update_order_meta',
            'woocommerce_subscription_renewal_payment_complete',
        ];


        // Register the callback function for each observed action
        foreach ($observed_actions as $action) {
            add_action($action, [self::class, 'solidlogger_observed_action_callback'], 10, 3);
        }
    }

    /**
     * Callback function for the observed actions.
     *
     * @param array<int, mixed> $args Arguments passed to the action
     * @return void
     */
    public static function solidlogger_observed_action_callback(...$args)
    {
        $action_name = current_filter();
        $message = sprintf('Action triggered: %s', $action_name);

        // Now, safely append the arguments to the message, if any
        /**
         * @psalm-suppress MixedAssignment
         */
        foreach ($args as $index => $arg) {
            if (!empty($arg)) {
                $message .= sprintf("\nAction argument %d: %s", (int)$index + 1, print_r($arg, true));
            }
        }

        // Make a single call to SolidLogger::log
        SolidLogger::log($message);
    }




    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    // Private methods
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////
    ///////////////////////////////////////////

    /**
     * @param string $log_entry
     * @return void
     */
    private static function write_to_log($log_entry)
    {
        $log_file_path = self::log_file_path();
        file_put_contents($log_file_path, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param string $message
     * @return string
     */
    private static function process_log_message($message)
    {
        $current_time = date('Y-m-d H:i:s');
        // Get the calling function so we can log where this was called from
        $backtrace = debug_backtrace();

        /** @psalm-suppress PossiblyUndefinedArrayOffset */
        $log_entry = 'Called from: ' . $backtrace[1]['function'] . ' in ' . $backtrace[1]['file'] . ' on line ' . $backtrace[1]['line'] . PHP_EOL;

        $log_entry .= $current_time . ' - ' . 'solid_affiliate - ' . $message . PHP_EOL;

        $log_entry .= '----------------------------------------' . PHP_EOL;

        return $log_entry;
    }

    /**
     * @return string
     */
    private static function log_file_path()
    {
        // Get the path to the uploads directory
        $uploads_dir = wp_upload_dir();
        $base_dir = trailingslashit($uploads_dir['basedir']);

        // Define the path to the log file
        $log_file_path = $base_dir . 'solid-log.txt';

        return $log_file_path;
    }


    /**
     * Checks if the log file exceeds the maximum number of lines and truncates it if necessary.
     *
     * @return void
     */
    public static function maybe_truncate_log_file()
    {
        // Get the path to the uploads directory
        $log_file_path = self::log_file_path();

        // Check if the log file exists
        if (file_exists($log_file_path)) {
            // First pass: Count the total number of lines
            $line_count = 0;
            $handle = fopen($log_file_path, "r");
            while (!feof($handle)) {
                fgets($handle);
                $line_count++;
            }
            fclose($handle);

            // Check if the log file exceeds the maximum number of lines
            if ($line_count > self::MAX_LINES) {
                // Lines that we want to keep
                $lines_to_keep = self::MAX_LINES - self::LINES_TO_REMOVE_WHEN_OVER_LIMIT;
                $start_line = $line_count - $lines_to_keep;

                // Second pass: Copy the last $lines_to_keep lines to a new file
                $handle = fopen($log_file_path, "r");
                $temp_file_path = $log_file_path . '.tmp';
                $temp_file = fopen($temp_file_path, 'w');

                $current_line = 0;
                while (!feof($handle)) {
                    $line = fgets($handle);
                    $current_line++;

                    if ($current_line > $start_line) {
                        fwrite($temp_file, $line);
                    }
                }

                fclose($handle);
                fclose($temp_file);

                // Replace the original file with the temporary file
                rename($temp_file_path, $log_file_path);
            }
        }
    }



    /**
     * Display the contents of the log file on the custom admin page.
     * 
     * @return void
     */
    public static function display_admin_page()
    {
        // Get the path to the uploads directory
        $uploads_dir = wp_upload_dir();
        $base_dir = trailingslashit($uploads_dir['basedir']);

        // Define the path to the log file
        $log_file = $base_dir . 'solid-log.txt';
        $log_url = trailingslashit($uploads_dir['baseurl']) . 'solid-log.txt';


        // Print out a helpful top message about where the log file is located
        echo '<div class="wrap">';
        echo '<h1>Solid Logger</h1>';
        echo '<p>The log file is located at <code>' . esc_html($log_file) . '</code>.</p>';
        echo '<p>The maximum number of lines in the log file is <code>' . esc_html((string)self::MAX_LINES) . '</code>. The logs are automatically trimmed. </p>';
        echo '</div>';

        // add a download link
        echo '<div class="wrap">';
        echo '<p><a href="' . esc_url($log_url) . '" download>Download Log File</a></p>';

        echo '</div>';



        // Check if the log file exists
        if (file_exists($log_file)) {
            // Read the contents of the log file
            $log_contents = file_get_contents($log_file);
            // reverse the order of the lines
            // $log_contents = implode(PHP_EOL, array_reverse(explode(PHP_EOL, $log_contents)));

            // Display the log contents in a textarea element
            echo '<div class="wrap">';
            echo '<h1>Solid Logger</h1>';
            echo '<textarea readonly style="width: 100%; height: 500px;">' . esc_textarea($log_contents) . '</textarea>';
            echo '</div>';
        } else {
            // Display a message if the log file does not exist
            echo '<div class="wrap">';
            echo '<h1>Solid Logger</h1>';
            echo '<p>No log entries found.</p>';
            echo '</div>';
        }
    }
}
