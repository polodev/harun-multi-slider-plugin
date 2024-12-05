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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_slider_scripts']);
        
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
        // Handle slider and slide deletions
        $this->handle_slider_deletion();
        $this->handle_slide_deletion();
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
            slide_id VARCHAR(255),
            slide_order mediumint(9) NULL,
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
        <h1>
            <a href="<?php echo admin_url('admin.php?page=multi-slider'); ?>">Multi Slider Management</a>
        </h1>

        <!-- Slider Creation Section -->

        <!-- Edit Slider Section -->
        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
            <?php
                $slider_id = intval($_GET['id']);
                $slider = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->sliders_table} WHERE id = %d", $slider_id));
            ?>
            <div class="slider-edit-section">
                <h2>Edit Slider</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('multi_slider_edit_nonce', 'multi_slider_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="slider_title">Slider Title</label></th>
                            <td><input type="text" name="slider_title" value="<?php echo esc_attr($slider->title); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="slider_slug">Slider Slug</label></th>
                            <td><input type="text" name="slider_slug" value="<?php echo esc_attr($slider->slug); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="slider_description">Description</label></th>
                            <td><textarea name="slider_description"><?php echo esc_textarea($slider->description); ?></textarea></td>
                        </tr>
                    </table>

                    <a class="button" href="<?php echo admin_url('admin.php?page=multi-slider'); ?>">Back to Regular Page</a>

                    <?php submit_button('Update Slider', 'primary', 'edit_slider'); ?>
                </form>
            </div>
        <?php else: ?>
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
        <?php endif; ?>

        <!-- Edit Slide Section -->
        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit_slide' && isset($_GET['id'])): ?>
            <?php
                $slide_id = intval($_GET['id']);
                $slide = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->slides_table} WHERE id = %d", $slide_id));
            ?>
            <div class="slide-edit-section">
                <h2>Edit Slide</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('multi_slide_edit_nonce', 'multi_slide_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="slider_id">Select Slider</label></th>
                            <td>
                                <select name="slider_id" required>
                                    <option value="">Choose a Slider</option>
                                    <?php foreach ($sliders as $slider): ?>
                                        <option value="<?php echo $slider->id; ?>" <?php selected($slide->slider_id, $slider->id); ?>>
                                            <?php echo esc_html($slider->title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="slide_title">Slide Title</label></th>
                            <td><input type="text" name="slide_title" value="<?php echo esc_attr($slide->title); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="slide_id">Slide ID</label></th>
                            <td><input type="text" name="slide_id" value="<?php echo esc_attr($slide->slide_id); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="slide_order">Slide Order</label></th>
                            <td><input type="text" name="slide_order" value="<?php echo esc_attr($slide->slide_order); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="slide_image">Slide Image</label></th>
                            <td>
                                <input type="hidden" name="slide_image_id" id="slide_image_id" value="<?php echo esc_attr($slide->image_id); ?>">
                                <img id="slide_image_preview" src="<?php echo esc_url(wp_get_attachment_image_url($slide->image_id, 'thumbnail')); ?>" style="max-width: 300px; display: <?php echo $slide->image_id ? 'block' : 'none'; ?>;">
                                <button type="button" class="button" id="upload_image_button">
                                    Select Image
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="slide_link">Link URL</label></th>
                            <td><input type="text" name="slide_link" value="<?php echo esc_url($slide->link_url); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="slide_description">Description</label></th>
                            <td><textarea name="slide_description"><?php echo esc_textarea($slide->description); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="slide_alt">Alternative Text</label></th>
                            <td><input type="text" name="slide_alt" value="<?php echo esc_attr($slide->alt_text); ?>"></td>
                        </tr>
                    </table>

                    <a class="button" href="<?php echo admin_url('admin.php?page=multi-slider'); ?>">Back to Regular Page</a>


                    <?php submit_button('Update Slide', 'primary', 'edit_slide'); ?>
                </form>
            </div>
        <?php else: ?>
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
                            <th><label for="slide_id">Slide ID</label></th>
                            <td><input type="text" name="slide_id"></td>
                        </tr>
                        <tr>
                            <th><label for="slide_order">Slide Order</label></th>
                            <td><input type="text" name="slide_order"></td>
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
        <?php endif; ?>


        <!-- Existing Sliders and Slides List -->
        <?php $this->render_existing_sliders(); ?>
    </div>
    <?php
}

private function handle_slider_submission() {
    global $wpdb;

    // Slider Create
    if (
        isset($_POST['multi_slider_nonce']) &&
        wp_verify_nonce($_POST['multi_slider_nonce'], 'multi_slider_create_nonce') &&
        isset($_POST['create_slider'])
    ) {
        $data = [
            'title' => sanitize_text_field($_POST['slider_title']),
            'slug' => sanitize_title($_POST['slider_slug']),
            'description' => sanitize_textarea_field($_POST['slider_description'])
        ];
        $wpdb->insert($this->sliders_table, $data);
    }

    // Slider Edit
    if (
        isset($_POST['multi_slider_nonce']) &&
        wp_verify_nonce($_POST['multi_slider_nonce'], 'multi_slider_edit_nonce') &&
        isset($_POST['edit_slider']) &&
        isset($_GET['action']) && $_GET['action'] === 'edit'
    ) {
        $slider_id = intval($_GET['id']);
        $data = [
            'title' => sanitize_text_field($_POST['slider_title']),
            'slug' => sanitize_title($_POST['slider_slug']),
            'description' => sanitize_textarea_field($_POST['slider_description'])
        ];
        $wpdb->update($this->sliders_table, $data, ['id' => $slider_id]);
    }
}


    /**
     * Handle slide submission
     */
    private function handle_slide_submission() {
    global $wpdb;

    // Slide Create
    if (
        isset($_POST['multi_slide_nonce']) &&
        wp_verify_nonce($_POST['multi_slide_nonce'], 'multi_slide_create_nonce') &&
        isset($_POST['create_slide'])
    ) {
        $data = [
            'slider_id' => intval($_POST['slider_id']),
            'title' => sanitize_text_field($_POST['slide_title']),
            'slide_id' => sanitize_text_field($_POST['slide_id']),
            'slide_order' => sanitize_text_field($_POST['slide_order']),
            'image_id' => intval($_POST['slide_image_id']),
            'link_url' => esc_url($_POST['slide_link']),
            'description' => sanitize_textarea_field($_POST['slide_description']),
            'alt_text' => sanitize_text_field($_POST['slide_alt'])
        ];

        $wpdb->insert($this->slides_table, $data);
    }

    // Slide Edit
    if (
        isset($_POST['multi_slide_nonce']) &&
        wp_verify_nonce($_POST['multi_slide_nonce'], 'multi_slide_edit_nonce') &&
        isset($_POST['edit_slide']) &&
        isset($_GET['action']) && $_GET['action'] === 'edit_slide' &&
        isset($_GET['id'])
    ) {
        $slide_id = intval($_GET['id']);
        $data = [
            'slider_id' => intval($_POST['slider_id']),
            'title' => sanitize_text_field($_POST['slide_title']),
            'slide_id' => sanitize_text_field($_POST['slide_id']),
            'slide_order' => sanitize_text_field($_POST['slide_order']),
            'image_id' => intval($_POST['slide_image_id']),
            'link_url' => esc_url($_POST['slide_link']),
            'description' => sanitize_textarea_field($_POST['slide_description']),
            'alt_text' => sanitize_text_field($_POST['slide_alt'])
        ];

        $wpdb->update($this->slides_table, $data, ['id' => $slide_id]);
    }
}


    
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
                    <a href="?page=multi-slider&action=delete_slider&id=' . intval($slider->id) . '" onclick="return confirm(\'Are you sure you want to delete this slider?\');">Delete Slider</a>
                </td>';
                echo '</tr>';

                // Optional: Display slides for each slider
                 if ($slides) {
                    foreach ($slides as $slide) {
                        $image_url = wp_get_attachment_image_src($slide->image_id, 'thumbnail');
                        echo '<tr class="slide-row">';
                        echo '<td colspan="5">';
                        echo '&nbsp;&nbsp;- ' . esc_html($slide->title);
                        echo '&nbsp;&nbsp;- ' . esc_html($slide->slide_id);
                        echo '&nbsp;&nbsp;- ' . esc_html($slide->slide_order);
                        echo $image_url 
                            ? ' <img src="' . esc_url($image_url[0]) . '" width="50">' 
                            : '';
                        echo ' | <a href="?page=multi-slider&action=edit_slide&id=' . intval($slide->id) . '">Edit</a> | 
                              <a href="?page=multi-slider&action=delete_slide&id=' . intval($slide->id) . '" onclick="return confirm(\'Are you sure you want to delete this slide?\');">Delete Slide</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
            }

            echo '</tbody></table>';
        }
    }
public function handle_slider_deletion() {
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete_slider' && isset($_GET['id'])) {
        $slider_id = intval($_GET['id']);

        // Delete all slides for this slider first (cascade delete)
        $wpdb->delete(
            $this->slides_table,
            ['slider_id' => $slider_id]
        );

        // Now delete the slider itself
        $wpdb->delete(
            $this->sliders_table,
            ['id' => $slider_id]
        );

        // Redirect back to the sliders page after deletion
        wp_redirect(admin_url('admin.php?page=multi-slider'));
        exit;
    }
}
public function handle_slide_deletion() {
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete_slide' && isset($_GET['id'])) {
        $slide_id = intval($_GET['id']);

        // Delete the slide
        $wpdb->delete(
            $this->slides_table,
            ['id' => $slide_id]
        );

        // Redirect back to the sliders page after deletion
        wp_redirect(admin_url('admin.php?page=multi-slider'));
        exit;
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
        

        <?php $style = 'style-1'; ?>

        <?php if( $style =='style-1') : ?>
            <div class="tenets-container">
                <h1 class="heading-6"></h1>
                <div class="page-container">
                    <div class="arrow-container" id="arrowPrev">
                        <div class="arrow">
                            <svg height="12px" version="1.1" viewBox="0 0 9 12" width="9px">
                                <g fill-rule="evenodd" id="Page-1" stroke="none" stroke-width="1">
                                    <g fid="Core" transform="translate(-218.000000, -90.000000)">
                                        <g id="chevron-left" transform="translate(218.500000, 90.000000)">
                                            <path d="M7.4,1.4 L6,0 L-8.8817842e-16,6 L6,12 L7.4,10.6 L2.8,6 L7.4,1.4 Z" id="Shape" />
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </div>
                    </div>
                    <div class="arrow-container right" id="arrowNext">
                        <div class="arrow right">
                            <svg height="12px" version="1.1" viewBox="0 0 9 12" width="9px">
                                <g fill-rule="evenodd" id="Page-1" stroke="none" stroke-width="1">
                                    <g fid="Core" transform="translate(-218.000000, -90.000000)">
                                        <g id="chevron-left" transform="translate(218.500000, 90.000000)">
                                            <path d="M7.4,1.4 L6,0 L-8.8817842e-16,6 L6,12 L7.4,10.6 L2.8,6 L7.4,1.4 Z" id="Shape" />
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </div>
                    </div>
                    <div class="carousel center">
                        <?php foreach ($slides as $slide): 
                            $image = wp_get_attachment_image_src($slide->image_id, 'large');
                            if (!$image) continue;
                        ?>
                        <a class="carousel-item" href="#<?php echo $slide->slide_id ?>" name="<?php echo $slide->slide_order ?>">
                            <img src="<?php echo esc_url($image[0]); ?>" />
                        </a>
                        <?php endforeach;?>
                    </div>
                </div>
                <div class="slider-container" id="slideNav">
                    <div class="overflow-wrapper swiper mySwiper">
                        <div class="carousel-nav swiper-wrapper">

                        <?php foreach ($slides as $slide): 
                            $image = wp_get_attachment_image_src($slide->image_id, 'large');
                            if (!$image) continue;
                        ?>
                            <div class="swiper-slide">
                                <a href="#<?php echo $slide->slide_id ?>" name="<?php echo $slide->slide_order ?>"><?php echo $slide->title ?></a>
                            </div>
                        <?php endforeach;?>
                        </div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            </div>











        <?php endif;?>


        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue slider scripts and styles
     */
    public function enqueue_slider_scripts() {
        
        // Load Materialize CSS
        wp_enqueue_style(
            'materialize-css', // Handle
            'https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css', // Source
            [], // Dependencies
            '1.0.0' // Version
        );

        // Load Swiper CSS
        wp_enqueue_style(
            'swiper-css', // Handle
            'https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css', // Source
            [], // Dependencies
            'latest' // Version
        );

        // Load Materialize JS
        wp_enqueue_script(
            'materialize-js', // Handle
            'https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js', // Source
            ['jquery'], // Dependencies
            '1.0.0', // Version
            true // Load in footer
        );

        // Load Swiper JS
        wp_enqueue_script(
            'swiper-js', // Handle
            'https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js', // Source
            [], // Dependencies
            'latest', // Version
            true // Load in footer
        );
        wp_enqueue_script(
            'multi-slider-script', 
            MULTI_SLIDER_PLUGIN_URL . 'assets/js/multi-slider.js', 
            ['jquery'], 
            '1.0.0', 
            true
        );
        wp_enqueue_script(
            'multi-slider-user-script', 
            MULTI_SLIDER_PLUGIN_URL . 'assets/js/multi-slider-user.js', 
            ['jquery'], 
            '1.0.0', 
            true
        );
        wp_enqueue_style(
            'multi-slider-style', 
            MULTI_SLIDER_PLUGIN_URL . 'assets/css/multi-slider.css'
        );
        wp_enqueue_style(
            'multi-slider-user-style', 
            MULTI_SLIDER_PLUGIN_URL . 'assets/css/multi-slider-user.css'
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
