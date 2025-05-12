<?php
/**
 * Plugin Name: MemberPress Categories Accordion
 * Plugin URI: https://codeable.io/developers/ben-vining/?ref=VETV1
 * Description: Displays MemberPress memberships in an accordion grouped by categories. Displays "Book Now" button or "SOLD OUT" message based on availability.
 * Version: 1.0.0
 * Author: Ben Vining
 * Author URI: https://codeable.io/developers/ben-vining/?ref=VETV1
 * Text Domain: memberpress-categories-accordion
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display MemberPress memberships in accordions grouped by categories
 * [display_memberships_by_category]
 */
function display_memberships_by_category_shortcode() {
    // Start output buffering
    ob_start();
    
    // Add accordion styles and scripts
    ?>
    <style>
        .membership-accordion {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .accordion-section {
            border: 1px solid #ddd;
            margin-bottom: 10px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .accordion-header {
            background-color: #f5f5f5;
            padding: 15px;
            cursor: pointer;
            position: relative;
            font-weight: bold;
            font-size: 18px;
        }
        
        .accordion-header:after {
            content: '\002B';
            color: #777;
            font-weight: bold;
            float: right;
            margin-left: 5px;
            transition: transform 0.3s ease;
        }
        
        .accordion-header.active:after {
            content: '\2212';
        }
        
        .accordion-content {
            padding: 0 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .accordion-content.show {
            max-height: 2000px;
            padding: 15px;
        }
        
        .membership-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .membership-item:last-child {
            border-bottom: none;
        }
        
        .membership-item h3 {
            margin-bottom: 10px;
            text-transform:capitalize;
        }
        
        .book-now-button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
            margin-top: 5px;
            transition: background-color 0.3s ease;
        }
        
        .book-now-button:hover {
            background-color: #3e8e41;
            color:#FFF;
        }
        
        .sold-out-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
            margin-top: 5px;
            max-width:115px;
        }
        
        @media (max-width: 768px) {
            .accordion-header {
                padding: 12px;
                font-size: 16px;
            }
            
            .accordion-content.show {
                padding: 12px;
            }
        }
    </style>
    
    <div class="membership-accordion">
    <?php
    
    // Get all membership categories sorted alphabetically
    $categories = get_terms(array(
        'taxonomy' => 'mepr-product-category',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (empty($categories) || is_wp_error($categories)) {
        echo '<p>No membership categories found.</p>';
        return ob_get_clean();
    }
    
    $first_section = true;
    
    // Loop through each category
    foreach ($categories as $category) {
        // Get memberships in this category
        $args = array(
            'post_type' => 'memberpressproduct',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'mepr-product-category',
                    'field' => 'term_id',
                    'terms' => $category->term_id,
                ),
            ),
        );
        
        $memberships = new WP_Query($args);
        
        if ($memberships->have_posts()) {
            // Open the accordion section
            $active_class = $first_section ? 'active' : '';
            $show_class = $first_section ? 'show' : '';
            ?>
            <div class="accordion-section">
                <div class="accordion-header <?php echo $active_class; ?>"><?php echo esc_html($category->name); ?></div>
                <div class="accordion-content <?php echo $show_class; ?>">
                    <?php
                    while ($memberships->have_posts()) {
                        $memberships->the_post();
                        $product_id = get_the_ID();
                        $membership = new MeprProduct($product_id);
                        
                        // Check if we need to get registration restrictions
                        $is_sold_out = false;
                        $post_content = get_the_content();
                        
                        // Check if text indicates the membership is full
                        if (strpos($post_content, 'Registration is full') !== false || 
                            strpos(get_the_excerpt(), 'Registration is full') !== false) {
                            $is_sold_out = true;
                        }
                        
                        // Also check registration limits using the MemberPress function
                        if (!$is_sold_out && class_exists('MeprRegistrationsAddOn')) {
                            $max_registrations = get_post_meta($product_id, '_mepr_registrations_limit', true);
                            
                            if (!empty($max_registrations) && $max_registrations > 0) {
                                // Get current active members count
                                $active_members_count = MeprUtils::get_active_members_count_by_product_id($product_id);
                                
                                // Check if sold out
                                if ($active_members_count >= $max_registrations) {
                                    $is_sold_out = true;
                                }
                            }
                        }
                        
                        ?>
                        <div class="membership-item">
                            <h3><?php the_title(); ?></h3>
                            
                            <?php
                            // Display either "Book Now" button or "SOLD OUT" message
                            if ($is_sold_out) {
                                echo '<div class="sold-out-message">SOLD OUT</div>';
                            } else {
                                echo '<a href="' . get_permalink() . '" class="book-now-button">Book Now</a>';
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
            $first_section = false; // Only the first section starts open
            wp_reset_postdata();
        }
    }
    
    // Add JavaScript for accordion functionality
    ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const accordionHeaders = document.querySelectorAll('.accordion-header');
        
        accordionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                this.classList.toggle('active');
                const content = this.nextElementSibling;
                content.classList.toggle('show');
            });
        });
    });
    </script>
    <?php
    
    // Return the buffered output
    return ob_get_clean();
}
add_shortcode('display_memberships_by_category', 'display_memberships_by_category_shortcode');

/**
 * Check if MemberPress is active
 */
function mepr_categories_accordion_check_dependencies() {
    if (!class_exists('MeprProduct')) {
        add_action('admin_notices', 'mepr_categories_accordion_dependency_notice');
    }
}
add_action('admin_init', 'mepr_categories_accordion_check_dependencies');

/**
 * Display admin notice if MemberPress is not active
 */
function mepr_categories_accordion_dependency_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('MemberPress Categories Accordion requires MemberPress to be installed and activated.', 'memberpress-categories-accordion'); ?></p>
    </div>
    <?php
}