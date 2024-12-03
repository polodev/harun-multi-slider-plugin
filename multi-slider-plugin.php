<?php
/*
Plugin Name: Multi Slider Plugin
Plugin URI: https://yourwebsite.com/multi-slider
Description: A versatile WordPress slider plugin with multiple slide capabilities
Version: 1.0.0
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL2
Text Domain: multi-slider
*/

// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MULTI_SLIDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MULTI_SLIDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload class files
spl_autoload_register(function ($class) {
    // Only autoload classes in our namespace
    if (strpos($class, 'MultiSliderPlugin\\') === 0) {
        $class_name = str_replace('MultiSliderPlugin\\', '', $class);
        $file_path = MULTI_SLIDER_PLUGIN_DIR . 'includes/' . $class_name . '.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

// Include the MultiSliderPlugin class
require_once MULTI_SLIDER_PLUGIN_DIR . 'includes/MultiSliderPlugin.php';

// Initialize the plugin
use MultiSliderPlugin\MultiSlider;
$multi_slider = MultiSlider::get_instance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, [$multi_slider, 'activate']);
register_deactivation_hook(__FILE__, [$multi_slider, 'deactivate']);