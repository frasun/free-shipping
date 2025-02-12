<?php
/**
 * LiteSpeed Cache integration
 *
 * @package     Chocnate_Free_Shipping
 * @subpackage  Chocnate_Free_Shipping/LSCache
 */

defined( 'WPINC' ) || exit();

/**
 * Caching rules for free shipping info
 */
class Chocante_Free_Shipping_LSCache {
	/**
	 * Shipping cookie
	 *
	 * @var string Shipping country cookie name.
	 */
	private static $cookie = Chocante_Free_Shipping::SHIPPING_COOKIE;

	/**
	 * Regirsters cookie to LSCache
	 */
	public static function detect() {
		add_filter( 'litespeed_vary_curr_cookies', __CLASS__ . '::check_cookies' ); // this is for vary response headers, only add when needed.
		add_filter( 'litespeed_vary_cookies', __CLASS__ . '::register_cookies' ); // this is for rewrite rules, so always add.
	}

	/**
	 * Regirsters cookie to LSCache
	 *
	 * @param array $cookies List of cookie varies.
	 */
	public static function register_cookies( $cookies ) {
		// NOTE: is_cart should also be checked, but will be checked by woocommerce anyway.
		$cookies[] = self::$cookie;

		return $cookies;
	}

	/**
	 * If the page is not a product page, ignore the logic.
	 * Else check cookies. If cookies are set, set the vary headers, else do not cache the page.
	 *
	 * @param array $cookies List of cookie varies.
	 */
	public static function check_cookies( $cookies ) {
		// NOTE: is_cart should also be checked, but will be checked by woocommerce anyway.
		if ( ! is_product() ) {
			return $cookies;
		}

		$cookies[] = self::$cookie;

		return $cookies;
	}
}
