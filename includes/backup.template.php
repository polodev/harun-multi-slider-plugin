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