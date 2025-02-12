<?php
/**
 * Plugin Name: Free Shipping by Location
 * Description: Calculate free shipping rate based on customer location.
 * Version: 1.0.0
 * Author: Chocante
 * Text Domain: chocante-free-shipping
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Chocante_Free_Shipping
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

/**
 * Current plugin version.
 */
define( 'CHOCANTE_FREE_SHIPPING_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . 'class-chocante-free-shipping.php';
add_action( 'plugins_loaded', 'chocante_free_shipping_init', 10 );

/**
 * Load text domain
 */
function chocante_free_shipping_init() {
	load_plugin_textdomain( 'chocante-free-shipping', false, plugin_basename( __DIR__ ) . '/languages' );

	Chocante_Free_Shipping::instance();
}

register_activation_hook( __FILE__, 'chocante_free_shipping_activate' );

/**
 * Activation hook
 */
function chocante_free_shipping_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'chocante_free_shipping_missing_wc_notice' );
		return;
	}
}

/**
 * WooCommerce fallback notice
 */
function chocante_free_shipping_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Free Shipping by Location requires WooCommerce to be installed and active. You can download %s here.', 'chocante-free-shipping' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * Public method to display information about free shipping
 */
function chocante_free_shipping_display_info() {
	Chocante_Free_Shipping::instance()->display_free_shipping_info();
}
