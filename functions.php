<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_VERSION', '3.4.4' );
define( 'EHP_THEME_SLUG', 'hello-elementor' );

define( 'HELLO_THEME_PATH', get_template_directory() );
define( 'HELLO_THEME_URL', get_template_directory_uri() );
define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets/' );
define( 'HELLO_THEME_ASSETS_URL', HELLO_THEME_URL . '/assets/' );
define( 'HELLO_THEME_SCRIPTS_PATH', HELLO_THEME_ASSETS_PATH . 'js/' );
define( 'HELLO_THEME_SCRIPTS_URL', HELLO_THEME_ASSETS_URL . 'js/' );
define( 'HELLO_THEME_STYLE_PATH', HELLO_THEME_ASSETS_PATH . 'css/' );
define( 'HELLO_THEME_STYLE_URL', HELLO_THEME_ASSETS_URL . 'css/' );
define( 'HELLO_THEME_IMAGES_PATH', HELLO_THEME_ASSETS_PATH . 'images/' );
define( 'HELLO_THEME_IMAGES_URL', HELLO_THEME_ASSETS_URL . 'images/' );

if ( ! isset( $content_width ) ) {
	$content_width = 800; // Pixels.
}

if ( ! function_exists( 'hello_elementor_setup' ) ) {
	/**
	 * Set up theme support.
	 *
	 * @return void
	 */
	function hello_elementor_setup() {
		if ( is_admin() ) {
			hello_maybe_update_theme_version_in_db();
		}

		if ( apply_filters( 'hello_elementor_register_menus', true ) ) {
			register_nav_menus( [ 'menu-1' => esc_html__( 'Header', 'hello-elementor' ) ] );
			register_nav_menus( [ 'menu-2' => esc_html__( 'Footer', 'hello-elementor' ) ] );
		}

		if ( apply_filters( 'hello_elementor_post_type_support', true ) ) {
			add_post_type_support( 'page', 'excerpt' );
		}

		if ( apply_filters( 'hello_elementor_add_theme_support', true ) ) {
			add_theme_support( 'post-thumbnails' );
			add_theme_support( 'automatic-feed-links' );
			add_theme_support( 'title-tag' );
			add_theme_support(
				'html5',
				[
					'search-form',
					'comment-form',
					'comment-list',
					'gallery',
					'caption',
					'script',
					'style',
					'navigation-widgets',
				]
			);
			add_theme_support(
				'custom-logo',
				[
					'height'      => 100,
					'width'       => 350,
					'flex-height' => true,
					'flex-width'  => true,
				]
			);
			add_theme_support( 'align-wide' );
			add_theme_support( 'responsive-embeds' );

			/*
			 * Editor Styles
			 */
			add_theme_support( 'editor-styles' );
			add_editor_style( 'editor-styles.css' );

			/*
			 * WooCommerce.
			 */
			if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
				// WooCommerce in general.
				add_theme_support( 'woocommerce' );
				// Enabling WooCommerce product gallery features (are off by default since WC 3.0.0).
				// zoom.
				add_theme_support( 'wc-product-gallery-zoom' );
				// lightbox.
				add_theme_support( 'wc-product-gallery-lightbox' );
				// swipe.
				add_theme_support( 'wc-product-gallery-slider' );
			}
		}
	}
}
add_action( 'after_setup_theme', 'hello_elementor_setup' );

function hello_maybe_update_theme_version_in_db() {
	$theme_version_option_name = 'hello_theme_version';
	// The theme version saved in the database.
	$hello_theme_db_version = get_option( $theme_version_option_name );

	// If the 'hello_theme_version' option does not exist in the DB, or the version needs to be updated, do the update.
	if ( ! $hello_theme_db_version || version_compare( $hello_theme_db_version, HELLO_ELEMENTOR_VERSION, '<' ) ) {
		update_option( $theme_version_option_name, HELLO_ELEMENTOR_VERSION );
	}
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
	/**
	 * Check whether to display header footer.
	 *
	 * @return bool
	 */
	function hello_elementor_display_header_footer() {
		$hello_elementor_header_footer = true;

		return apply_filters( 'hello_elementor_header_footer', $hello_elementor_header_footer );
	}
}

if ( ! function_exists( 'hello_elementor_scripts_styles' ) ) {
	/**
	 * Theme Scripts & Styles.
	 *
	 * @return void
	 */
	function hello_elementor_scripts_styles() {
		if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor',
				HELLO_THEME_STYLE_URL . 'reset.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( apply_filters( 'hello_elementor_enqueue_theme_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor-theme-style',
				HELLO_THEME_STYLE_URL . 'theme.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( hello_elementor_display_header_footer() ) {
			wp_enqueue_style(
				'hello-elementor-header-footer',
				HELLO_THEME_STYLE_URL . 'header-footer.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_scripts_styles' );

/**
 * Enqueue Signals RTL Styles
 */
function hello_elementor_signals_rtl_styles() {
    if ( is_post_type_archive( 'signal' ) || is_singular( 'signal' ) ) {
        wp_enqueue_style(
            'signals-rtl',
            HELLO_THEME_STYLE_URL . 'signals-rtl.css',
            [],
            HELLO_ELEMENTOR_VERSION
        );
    }
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_signals_rtl_styles' );

if ( ! function_exists( 'hello_elementor_register_elementor_locations' ) ) {
	/**
	 * Register Elementor Locations.
	 *
	 * @param ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager $elementor_theme_manager theme manager.
	 *
	 * @return void
	 */
	function hello_elementor_register_elementor_locations( $elementor_theme_manager ) {
		if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
			$elementor_theme_manager->register_all_core_location();
		}
	}
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_elementor_locations' );

if ( ! function_exists( 'hello_elementor_content_width' ) ) {
	/**
	 * Set default content width.
	 *
	 * @return void
	 */
	function hello_elementor_content_width() {
		$GLOBALS['content_width'] = apply_filters( 'hello_elementor_content_width', 800 );
	}
}
add_action( 'after_setup_theme', 'hello_elementor_content_width', 0 );

if ( ! function_exists( 'hello_elementor_add_description_meta_tag' ) ) {
	/**
	 * Add description meta tag with excerpt text.
	 *
	 * @return void
	 */
	function hello_elementor_add_description_meta_tag() {
		if ( ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( empty( $post->post_excerpt ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );

// Settings page
require get_template_directory() . '/includes/settings-functions.php';

// Header & footer styling option, inside Elementor
require get_template_directory() . '/includes/elementor-functions.php';

if ( ! function_exists( 'hello_elementor_customizer' ) ) {
	// Customizer controls
	function hello_elementor_customizer() {
		if ( ! is_customize_preview() ) {
			return;
		}

		if ( ! hello_elementor_display_header_footer() ) {
			return;
		}

		require get_template_directory() . '/includes/customizer-functions.php';
	}
}
add_action( 'init', 'hello_elementor_customizer' );

if ( ! function_exists( 'hello_elementor_check_hide_title' ) ) {
	/**
	 * Check whether to display the page title.
	 *
	 * @param bool $val default value.
	 *
	 * @return bool
	 */
	function hello_elementor_check_hide_title( $val ) {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$current_doc = Elementor\Plugin::instance()->documents->get( get_the_ID() );
			if ( $current_doc && 'yes' === $current_doc->get_settings( 'hide_title' ) ) {
				$val = false;
			}
		}
		return $val;
	}
}
add_filter( 'hello_elementor_page_title', 'hello_elementor_check_hide_title' );

/**
 * BC:
 * In v2.7.0 the theme removed the `hello_elementor_body_open()` from `header.php` replacing it with `wp_body_open()`.
 * The following code prevents fatal errors in child themes that still use this function.
 */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	function hello_elementor_body_open() {
		wp_body_open();
	}
}

require HELLO_THEME_PATH . '/theme.php';

HelloTheme\Theme::instance();

/**
 * WooCommerce Checkout Customization for React Blocks
 * Higher priority implementation to override plugin settings
 */
class WooCommerce_Checkout_Customizer {
    
    public function __construct() {
        // Use higher priority hooks to override plugin customizations
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 999);
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_fields'), 999);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_fields'), 999);
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_meta'), 999);
        
        // Block checkout specific hooks
        add_action('woocommerce_blocks_checkout_block_registration', array($this, 'register_checkout_blocks'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'), 999);
    }
    
    /**
     * Customize WooCommerce checkout fields for React blocks
     */
    public function customize_checkout_fields($fields) {
        // Remove default billing fields
        unset($fields['billing']['billing_first_name']);
        unset($fields['billing']['billing_last_name']);
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_country']);
        unset($fields['billing']['billing_state']);
        unset($fields['billing']['billing_phone']);
        
        // Remove shipping fields
        unset($fields['shipping']);
        
        // Remove order notes
        unset($fields['order']['order_comments']);
        
        // Add custom Hebrew fields
        $fields['billing']['billing_full_name'] = array(
            'label' => 'שם מלא',
            'placeholder' => 'הזן שם מלא',
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 10,
            'type' => 'text'
        );
        
        // Modify email field
        if (isset($fields['billing']['billing_email'])) {
            $fields['billing']['billing_email']['label'] = 'אימייל';
            $fields['billing']['billing_email']['placeholder'] = 'הזן כתובת אימייל';
            $fields['billing']['billing_email']['priority'] = 20;
        }
        
        $fields['billing']['billing_identification'] = array(
            'label' => 'ח.פ/ת.ז',
            'placeholder' => 'הזן מספר תעודת זהות או חברה',
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 30,
            'type' => 'text'
        );
        
        $fields['billing']['billing_mobile_phone'] = array(
            'label' => 'טלפון נייד',
            'placeholder' => 'הזן מספר טלפון נייד',
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 40,
            'type' => 'tel'
        );
        
        return $fields;
    }
    
    /**
     * Validate custom checkout fields
     */
    public function validate_checkout_fields() {
        if (empty($_POST['billing_full_name'])) {
            wc_add_notice(__('אנא הזן שם מלא.'), 'error');
        }
        
        if (empty($_POST['billing_identification'])) {
            wc_add_notice(__('אנא הזן מספר תעודת זהות או חברה.'), 'error');
        }
        
        if (empty($_POST['billing_mobile_phone'])) {
            wc_add_notice(__('אנא הזן מספר טלפון נייד.'), 'error');
        }
    }
    
    /**
     * Save custom checkout fields to order meta
     */
    public function save_checkout_fields($order_id) {
        if (!empty($_POST['billing_full_name'])) {
            update_post_meta($order_id, '_billing_full_name', sanitize_text_field($_POST['billing_full_name']));
        }
        
        if (!empty($_POST['billing_identification'])) {
            update_post_meta($order_id, '_billing_identification', sanitize_text_field($_POST['billing_identification']));
        }
        
        if (!empty($_POST['billing_mobile_phone'])) {
            update_post_meta($order_id, '_billing_mobile_phone', sanitize_text_field($_POST['billing_mobile_phone']));
        }
    }
    
    /**
     * Display custom fields in admin order details
     */
    public function display_admin_order_meta($order) {
        $full_name = get_post_meta($order->get_id(), '_billing_full_name', true);
        $identification = get_post_meta($order->get_id(), '_billing_identification', true);
        $mobile_phone = get_post_meta($order->get_id(), '_billing_mobile_phone', true);
        
        echo '<h3>פרטי לקוח מותאמים</h3>';
        
        if ($full_name) {
            echo '<p><strong>שם מלא:</strong> ' . esc_html($full_name) . '</p>';
        }
        
        if ($identification) {
            echo '<p><strong>ח.פ/ת.ז:</strong> ' . esc_html($identification) . '</p>';
        }
        
        if ($mobile_phone) {
            echo '<p><strong>טלפון נייד:</strong> ' . esc_html($mobile_phone) . '</p>';
        }
    }
    
    /**
     * Register custom blocks for WooCommerce checkout
     */
    public function register_checkout_blocks() {
        // This method can be used to register custom React blocks if needed
    }
    
    /**
     * Enqueue scripts for checkout customization
     */
    public function enqueue_checkout_scripts() {
        if (is_checkout() || is_wc_endpoint_url('order-received')) {
            wp_enqueue_script(
                'custom-checkout-blocks',
                get_template_directory_uri() . '/assets/js/checkout-blocks.js',
                array('wp-element', 'wp-components', 'wc-blocks-checkout'),
                '1.0.0',
                true
            );
            
            // Add inline script for React blocks customization
            $custom_js = "
            jQuery(document).ready(function($) {
                // Hide unwanted checkout blocks for React-based checkout
                function hideCheckoutBlocks() {
                    $('.wp-block-woocommerce-checkout-shipping-method-block').hide();
                    $('.wp-block-woocommerce-checkout-pickup-options-block').hide();
                    $('.wp-block-woocommerce-checkout-shipping-address-block').hide();
                    $('.wp-block-woocommerce-checkout-shipping-methods-block').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__company').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__address_1').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__address_2').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__city').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__postcode').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__country').hide();
                    $('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__state').hide();
                    $('.wp-block-woocommerce-checkout-additional-information-block').hide();
                    $('.wp-block-woocommerce-checkout-order-note-block').hide();
                }
                
                // Run immediately and on DOM changes
                hideCheckoutBlocks();
                
                // Observer for dynamic content changes
                const observer = new MutationObserver(function(mutations) {
                    hideCheckoutBlocks();
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });";
            
            wp_add_inline_script('custom-checkout-blocks', $custom_js);
        }
    }
}

// Initialize the checkout customizer
new WooCommerce_Checkout_Customizer();
