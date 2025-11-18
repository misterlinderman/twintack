<?php
/**
 * Class Loader
 * Handles autoloading of theme classes
 */
class Theme_Class_Loader {
    public static function init() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload($class_name) {
        // Convert class name to file path
        $class_path = strtolower(str_replace('_', '-', $class_name));
        $class_path = str_replace('\\', '/', $class_path);
        
        // Define class directories to check
        $directories = [
            get_template_directory() . '/inc/',
            get_template_directory() . '/inc/header/',
        ];

        foreach ($directories as $directory) {
            $file = $directory . 'class-' . $class_path . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}

Theme_Class_Loader::init(); 