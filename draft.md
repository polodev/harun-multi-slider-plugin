You are expert in wordpress plugin development. 
my website required multiple sliders. 
each slider can have multiple slide > each slide can have -> title, image, url, text, alternative text
Each slider will have a shortcode id which can be used anywhere to render the slider. I just have slider html, css, js
please write plugin for me. use php namespace, and class based approach.

my plugin code 
```php
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
```

```php file=includes/MultiSliderPlugin.php
<?php
namespace MultiSliderPlugin;

// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class for Multi Slider
 */
class MultiSlider {
    // Singleton instance
    private static $instance = null;

    // Database table names
    private $sliders_table;
    private $slides_table;

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->sliders_table = $wpdb->prefix . 'multi_slider_groups';
        $this->slides_table = $wpdb->prefix . 'multi_slider_slides';

        // Hook initialization
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_shortcode('multi_slider', [$this, 'render_slider_shortcode']);
        // add_action('wp_enqueue_scripts', [$this, 'enqueue_slider_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_multi_slider_upload_image', [$this, 'handle_image_upload']);
    }

    /**
     * Singleton instance getter
     * 
     * @return MultiSlider
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin initialization
     */
    public function init() {
        $this->create_database_tables();
    }

    /**
     * Create database tables for sliders and slides
     */
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Sliders table
        $sliders_sql = "CREATE TABLE IF NOT EXISTS {$this->sliders_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Slides table
        $slides_sql = "CREATE TABLE IF NOT EXISTS {$this->slides_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slider_id mediumint(9) NOT NULL,
            title VARCHAR(255),
            image_id mediumint(9) NOT NULL,
            link_url VARCHAR(255),
            description TEXT,
            alt_text VARCHAR(255),
            sort_order mediumint(9) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            FOREIGN KEY (slider_id) REFERENCES {$this->sliders_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sliders_sql);
        dbDelta($slides_sql);
    }

    /**
     * Enqueue admin scripts for media uploader
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_multi-slider') {
            return;
        }

        // WordPress media uploader scripts
        wp_enqueue_media();


        // Enqueue your custom admin script
        wp_enqueue_script(
            'multi-slider-admin-script',
            MULTI_SLIDER_PLUGIN_URL . '/assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );


        // Localize script with ajax url
        wp_localize_script('multi-slider-admin', 'multiSliderAdmin', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }

    /**
     * Add admin menu for slider management
     */
    public function add_admin_menu() {
        add_menu_page(
            'Multi Slider',
            'Multi Slider',
            'manage_options',
            'multi-slider',
            [$this, 'render_admin_page'],
            'dashicons-images-alt2',
            20
        );
    }

    /**
     * Render admin page for slider management
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle slider and slide submissions
        $this->handle_slider_submission();
        $this->handle_slide_submission();

        // Get existing sliders
        global $wpdb;
        $sliders = $wpdb->get_results("SELECT * FROM {$this->sliders_table}");

        ?>
        <div class="wrap">
            <h1>Multi Slider Management</h1>

            <!-- Slider Creation Section -->
            <div class="slider-creation-section">
                <h2>Create New Slider</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('multi_slider_create_nonce', 'multi_slider_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="slider_title">Slider Title</label></th>
                            <td><input type="text" name="slider_title" required placeholder="Enter slider name"></td>
                        </tr>
                        <tr>
                            <th><label for="slider_slug">Slider Slug</label></th>
                            <td><input type="text" name="slider_slug" required placeholder="unique-identifier"></td>
                        </tr>
                        <tr>
                            <th><label for="slider_description">Description</label></th>
                            <td><textarea name="slider_description"></textarea></td>
                        </tr>
                    </table>

                    <?php submit_button('Create Slider', 'primary', 'create_slider'); ?>
                </form>
            </div>

            <!-- Slide Addition Section -->
            <div class="slide-addition-section">
                <h2>Add Slide to Slider</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('multi_slide_create_nonce', 'multi_slide_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="slider_id">Select Slider</label></th>
                            <td>
                                <select name="slider_id" required>
                                    <option value="">Choose a Slider</option>
                                    <?php foreach ($sliders as $slider): ?>
                                        <option value="<?php echo $slider->id; ?>">
                                            <?php echo esc_html($slider->title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="slide_title">Slide Title</label></th>
                            <td><input type="text" name="slide_title"></td>
                        </tr>
                        <tr>
                            <th><label for="slide_image">Slide Image</label></th>
                            <td>
                                <input type="hidden" name="slide_image_id" id="slide_image_id">
                                <img id="slide_image_preview" src="" style="max-width: 300px; display: none;">
                                <button type="button" class="button" id="upload_image_button">
                                    Select Image
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="slide_link">Link URL</label></th>
                            <td><input type="text" name="slide_link"></td>
                        </tr>
                        <tr>
                            <th><label for="slide_description">Description</label></th>
                            <td><textarea name="slide_description"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="slide_alt">Alternative Text</label></th>
                            <td><input type="text" name="slide_alt"></td>
                        </tr>
                    </table>

                    <?php submit_button('Add Slide', 'secondary', 'create_slide'); ?>
                </form>
            </div>

            <!-- Existing Sliders and Slides List -->
            <?php $this->render_existing_sliders(); ?>
        </div>
        <?php
    }

    /**
     * Handle slider submission
     */
    private function handle_slider_submission() {
        if (
            isset($_POST['multi_slider_nonce']) && 
            wp_verify_nonce($_POST['multi_slider_nonce'], 'multi_slider_create_nonce') &&
            isset($_POST['create_slider'])
        ) {
            global $wpdb;

            $data = [
                'title' => sanitize_text_field($_POST['slider_title']),
                'slug' => sanitize_title($_POST['slider_slug']),
                'description' => sanitize_textarea_field($_POST['slider_description'])
            ];

            $wpdb->insert($this->sliders_table, $data);
        }
    }

    /**
     * Handle slide submission
     */
    private function handle_slide_submission() {
        if (
            isset($_POST['multi_slide_nonce']) && 
            wp_verify_nonce($_POST['multi_slide_nonce'], 'multi_slide_create_nonce') &&
            isset($_POST['create_slide'])
        ) {
            global $wpdb;

            $data = [
                'slider_id' => intval($_POST['slider_id']),
                'title' => sanitize_text_field($_POST['slide_title']),
                'image_id' => intval($_POST['slide_image_id']),
                'link_url' => esc_url($_POST['slide_link']),
                'description' => sanitize_textarea_field($_POST['slide_description']),
                'alt_text' => sanitize_text_field($_POST['slide_alt'])
            ];

            $wpdb->insert($this->slides_table, $data);
        }
    }

    /**
     * Render list of existing sliders and their slides
     */
    private function render_existing_sliders() {
        global $wpdb;
        $sliders = $wpdb->get_results(
            "SELECT s.*, 
                    COUNT(sl.id) as slide_count 
             FROM {$this->sliders_table} s
             LEFT JOIN {$this->slides_table} sl ON s.id = sl.slider_id
             GROUP BY s.id
             ORDER BY s.created_at DESC"
        );
        
        if ($sliders) {
            echo '<h2>Existing Sliders</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Title</th><th>Slug</th><th>Slides</th><th>Shortcode</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($sliders as $slider) {
                // Fetch slides for this slider
                $slides = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->slides_table} WHERE slider_id = %d ORDER BY sort_order",
                        $slider->id
                    )
                );

                echo '<tr>';
                echo '<td>' . esc_html($slider->title) . '</td>';
                echo '<td>' . esc_html($slider->slug) . '</td>';
                echo '<td>' . intval($slider->slide_count) . '</td>';
                echo '<td><code>[multi_slider id="' . esc_html($slider->slug) . '"]</code></td>';
                echo '<td>
                    <a href="?page=multi-slider&action=edit&id=' . intval($slider->id) . '">Edit</a> | 
                    <a href="?page=multi-slider&action=delete&id=' . intval($slider->id) . '">Delete</a>
                </td>';
                echo '</tr>';

                // Optional: Display slides for each slider
                if ($slides) {
                    foreach ($slides as $slide) {
                        $image_url = wp_get_attachment_image_src($slide->image_id, 'thumbnail');
                        echo '<tr class="slide-row">';
                        echo '<td colspan="5">';
                        echo '&nbsp;&nbsp;- ' . esc_html($slide->title);
                        echo $image_url 
                            ? ' <img src="' . esc_url($image_url[0]) . '" width="50">' 
                            : '';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
            }
            
            echo '</tbody></table>';
        }
    }

    /**
     * Render slider shortcode
     */
    public function render_slider_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => ''
        ], $atts);

        global $wpdb;
        $slider = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->sliders_table} WHERE slug = %s",
                $atts['id']
            )
        );

        if (!$slider) {
            return '';
        }

        $slides = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->slides_table} WHERE slider_id = %d ORDER BY sort_order",
                $slider->id
            )
        );

        if (empty($slides)) {
            return '';
        }

        // Start output buffering
        ob_start();
        ?>
        <div class="multi-slider" data-slider-id="<?php echo esc_attr($atts['id']); ?>">
            <?php foreach ($slides as $slide): 
                $image = wp_get_attachment_image_src($slide->image_id, 'large');
                if (!$image) continue;
            ?>
                <div class="slide">
                    <a href="<?php echo esc_url($slide->link_url); ?>">
                        <img 
                            src="<?php echo esc_url($image[0]); ?>" 
                            alt="<?php echo esc_attr($slide->alt_text); ?>"
                        >
                    </a>
                    <div class="slide-content">
                        <h3><?php echo esc_html($slide->title); ?></h3>
                        <p><?php echo esc_html($slide->description); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue slider scripts and styles
     */
    public function enqueue_slider_scripts() {
        wp_enqueue_script(
            'multi-slider-script', 
            MULTI_SLIDER_PLUGIN_URL . 'assets/multi-slider.js', 
            ['jquery'], 
            '1.0.0', 
            true
        );
        wp_enqueue_style(
            'multi-slider-style', 
            MULTI_SLIDER_PLUGIN_URL . 'assets/multi-slider.css'
        );
    }

    /**
     * Plugin activation hook
     */
    public function activate() {
        $this->create_database_tables();
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Optional: Add cleanup logic if needed
    }
}

```

I have just generated using ai. but need to update this. don't work media choice/update. please give me proper file to work. in reality I don't have any js yet