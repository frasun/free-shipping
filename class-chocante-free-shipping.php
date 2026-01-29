<?php
/**
 * Fired during plugin activation
 *
 * @package Chocante_Free_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Chocante_Free_Shipping class.
 */
class Chocante_Free_Shipping {
	/**
	 * This class instance.
	 *
	 * @var \Chocante_Free_Shipping Single instance of this class.
	 */
	private static $instance;

	/**
	 * The current version of the plugin.
	 *
	 * @var string The current version of the plugin.
	 */
	protected $version;

	const SHIPPING_ZONE_LOCATIONS = 'woocommerce_shipping_zone_locations';
	const SHIPPING_COOKIE         = 'chocante_shipping_country';
	const NONCE                   = 'chocante_free_shipping';

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( defined( 'CHOCANTE_FREE_SHIPPING_VERSION' ) ) {
			$this->version = CHOCANTE_FREE_SHIPPING_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		$this->init();
	}

	/**
	 * Cloning is forbidden
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'chocante-free-shipping' ), $this->version );
	}

	/**
	 * Unserializing instances of this class is forbidden
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'chocante-free-shipping' ), $this->version );
	}

	/**
	 * Gets the main instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \Chocante_Free_Shipping
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks
	 */
	public function init() {
		// Set shipping country on page load.
		add_action( 'wp', array( $this, 'set_shipping_country' ) );

		// Modify shipping calculator.
		add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
		add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_false' );
		add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );

		// Add cache vary for shpping country.
		if ( has_action( 'litespeed_load_thirdparty' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-chocante-free-shipping-lscache.php';
			add_action( 'litespeed_load_thirdparty', 'Chocante_Free_Shipping_LSCache::detect' );
		}

		// Modify shipping cost when free is available.
		add_filter( 'woocommerce_package_rates', array( $this, 'handle_free_shipping_methods' ) );

		// Display free shipping label.
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'display_free_shipping_label' ), 10, 2 );

		// Display free shipping notice in cart.
		add_action( 'woocommerce_before_cart', array( $this, 'display_cart_notice' ), 9 );

		// Display free shipping notice in checkout.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'display_cart_notice' ), 9 );

		// Exclude shipping method from free shipping calculations.
		add_action( 'woocommerce_init', array( $this, 'exclude_from_free_shipping' ) );
	}

	/**
	 * Set cutomer shipping location
	 */
	public function set_shipping_country() {
		if ( ! isset( WC()->customer ) || headers_sent() ) {
			return;
		}

		$shipping_country = $this->get_customer_shipping_country();
		$this->manage_location_cookie( $shipping_country );
	}

	/**
	 * Manage setting and unsetting location cookie
	 *
	 * @param string $shipping_country Shipping country code.
	 */
	private function manage_location_cookie( $shipping_country ) {
		$default_customer_location = wc_get_customer_default_location();
		$default_shipping_country  = $default_customer_location['country'];

		if ( $shipping_country !== $default_shipping_country ) {
			setcookie( self::SHIPPING_COOKIE, $shipping_country, time() + 60 * 60 * 48, '/' );
		} else {
			// Hack: set cookie expiration in the past.
			setcookie( self::SHIPPING_COOKIE, '', time() - 3600, '/' );
		}
	}

	/**
	 * Get shipping location
	 *
	 * @return string
	 */
	private function get_customer_shipping_country() {
		return WC()->customer->get_shipping_country();
	}

	/**
	 * Get shipping zone id of given country
	 *
	 * @param string $country Country code.
	 */
	private function get_shipping_zone( $country ) {
		$shipping_zone_id = wp_cache_get( "chocante_zone_id_{$country}", 'chocante_free_shipping', false, $zone_found );

		if ( false === $zone_found ) {
			global $wpdb;

			$table_name = $wpdb->prefix . self::SHIPPING_ZONE_LOCATIONS;
			$query      = $wpdb->prepare( "SELECT zone_id FROM {$table_name} WHERE location_type = 'country' AND location_code = %s LIMIT 1;", $country ); // @codingStandardsIgnoreLine.
			$results = $wpdb->get_results( $query ); // @codingStandardsIgnoreLine.

			$shipping_zone_id = count( $results ) > 0 ? $results[0]->zone_id : 0;
			wp_cache_set( "chocante_zone_id_{$country}", $shipping_zone_id, 'chocante_free_shipping' );
		}

		return $shipping_zone_id;
	}

	/**
	 * Get free shipping amount for a given country
	 *
	 * @param string $country Country code.
	 */
	private function get_free_shipping_amount( $country ) {
		$free_shipping_amount = null;
		$zone_id              = $this->get_shipping_zone( $country );
		$shipping_zone        = new WC_Shipping_Zone( $zone_id );
		$shipping_methods     = $shipping_zone->get_shipping_methods( true, 'json' );

		if ( ! empty( $shipping_methods ) ) {
			foreach ( $shipping_methods as $method ) {
				if ( 'free_shipping' === $method->id && 'min_amount' === $method->requires ) {
					$free_shipping_amount = isset( $method->min_amount ) ? $method->min_amount : null;
					break;
				}

				// Handle Flexible Shipping plugin.
				if ( isset( $method->instance_settings['method_free_shipping'] ) ) {
					if ( is_numeric( $method->instance_settings['method_free_shipping'] ) ) {
						$free_shipping_amount = $method->instance_settings['method_free_shipping'];

						// WCML.
						if ( function_exists( 'wcml_is_multi_currency_on' ) && wcml_is_multi_currency_on() ) {
							$free_shipping_amount = apply_filters( 'wcml_raw_price_amount', $free_shipping_amount );
						}

						// Curcy.
						// Curcy free version.
						if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
							$currency_setting = WOOMULTI_CURRENCY_F_Data::get_ins();
						}

						// Curcy premium version.
						if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
							$currency_setting = WOOMULTI_CURRENCY_Data::get_ins();
						}

						if ( isset( $currency_setting ) ) {
							$current_currency     = $currency_setting->get_current_currency();
							$free_shipping_amount = wmc_get_price( $free_shipping_amount, $current_currency, true );
						}

						break;
					}
				}
			}
		}

		return $free_shipping_amount;
	}

	/**
	 * Get free shipping amount from cache
	 *
	 * @param string $country Country code.
	 * @return float|string
	 */
	private function get_free_shipping_from_cache( $country ) {
		if ( ! isset( $country ) ) {
			return null;
		}

		$currency      = get_woocommerce_currency();
		$free_shipping = wp_cache_get( "chocante_free_shipping_{$country}_{$currency}", 'chocante_free_shipping', false, $found );

		if ( false === $found ) {
			$free_shipping = $this->get_free_shipping_amount( $country );

			if ( ! isset( $free_shipping ) ) {
				return null;
			}

			wp_cache_set( "chocante_free_shipping_{$country}_{$currency}", $free_shipping, 'chocante_free_shipping' );
		}

		return $free_shipping;
	}

	/**
	 * Get country name based on country code
	 *
	 * @param string $country_code Country name.
	 * @return string
	 */
	private function get_country_name( $country_code ) {
		$wc_countries = new WC_Countries();
		$countries    = $wc_countries->get_shipping_countries();
		$country_name = isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : null;

		return $country_name;
	}

	/**
	 * Display information about free shipping
	 *
	 * @param bool $return_content Whether to return or echo content.
	 * @return string
	 */
	public function display_free_shipping_info( $return_content = false ) {
		$country       = $this->get_customer_shipping_country();
		$country_name  = $this->get_country_name( $country );
		$free_shipping = $this->get_free_shipping_from_cache( $country );

		if ( ! isset( $country_name ) ) {
			// translators: No free shipping available.
			$message = sprintf( __( 'No free shipping available', 'chocante-free-shipping' ) );
		} elseif ( isset( $free_shipping ) ) {
			$free_shipping_limit = wc_price( $free_shipping );
			// translators: Free shipping info.
			$message = sprintf( __( '<strong>Free shipping</strong> to <strong>%1$s</strong> for orders starting from <strong>%2$s</strong>', 'chocante-free-shipping' ), $country_name, $free_shipping_limit );
		} else {
			// translators: No free shipping to country available.
			$message = sprintf( __( 'No free shipping to <strong>%s</strong> available', 'chocante-free-shipping' ), $country_name );
		}

		if ( $return_content ) {
			return wp_kses_post( $message );
		}

		echo '<div class="chocante-free-shipping">' . wp_kses_post( $message ) . '</div>';
	}

	/**
	 * Hide free shipping option, add free shipping cost to other methods
	 *
	 * @param array $rates Package rates.
	 * @return array
	 */
	public function handle_free_shipping_methods( $rates ) {
		$rate_keys = array_keys( $rates );

		foreach ( $rate_keys as $key ) {
			if ( str_contains( $key, 'free_shipping' ) ) {
				$free_shipping_index = $key;
				break;
			}
		}

		if ( isset( $free_shipping_index ) ) {
			unset( $rates[ $free_shipping_index ] );

			foreach ( $rates as $rate ) {
				$shipping_method       = WC_Shipping_Zones::get_shipping_method( $rate->get_instance_id() );
				$free_shipping_exclude = $shipping_method->get_instance_option( 'chocante_free_shipping_exclude' );

				if ( ! isset( $free_shipping_exclude ) || 'yes' !== $free_shipping_exclude ) {
					$rate->cost = 0;
				}
			}
		}

		return $rates;
	}

	/**
	 * Append free shipping label to shipping methods
	 *
	 * @param string           $label Shipping method label html.
	 * @param WC_Shipping_Rate $method Shipping method rate data.
	 * @return string
	 */
	public function display_free_shipping_label( $label, $method ) {
		$has_free_shipping = 0 === (int) $method->cost;

		if ( $has_free_shipping ) {
			$free_shipping_label = '<small class="chocante-free-shipping">' . __( 'Free shipping', 'woocommerce' ) . '</small>';

			return "{$label} {$free_shipping_label}";
		}

		return $label;
	}

	/**
	 * Add notice about free shipping in cart
	 */
	public function display_cart_notice() {
		$country                  = $this->get_customer_shipping_country();
		$free_shipping            = $this->get_free_shipping_from_cache( $country );
		$cart_total               = WC()->cart->get_subtotal() + ( wc_prices_include_tax() ? WC()->cart->get_subtotal_tax() : 0 );
		$free_shipping_difference = floatval( $free_shipping ) - $cart_total;

		if ( $free_shipping_difference > 0 ) {
			$formatted_difference = wc_price( $free_shipping_difference );
			$shop_page_id         = wc_get_page_id( 'shop' );

			// translators: The amount left for free shipping.
			$message  = sprintf( __( 'You only need %s more to get free shipping!', 'chocante-free-shipping' ), $formatted_difference );
			$message .= ' ';
			$message .= '<a href="' . get_permalink( $shop_page_id ) . '">';
			// translators: Continue shopping.
			$message .= __( 'Continue shopping', 'chocante-free-shipping' );
			$message .= '</a>';

			wc_add_notice( $message, 'notice', array( 'type' => 'chocante-free-shipping' ) );
		}
	}

	/**
	 * Add setting to exclude method from free shipping to all shipping methods.
	 */
	public function exclude_from_free_shipping() {
		$shipping_methods = WC()->shipping->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {
			if ( 'free_shipping' !== $shipping_method->id ) {
				add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method->id, array( $this, 'add_exclude_field' ) );
			}
		}
	}

	/**
	 * Add checkox exluding method from free shipping.
	 *
	 * @param array $fields Shipping method form fields.
	 * @return array
	 */
	public function add_exclude_field( $fields ) {
		$add_fields = array(
			'chocante_free_shipping_exclude' => array(
				'title'   => __( 'Free shipping calculations', 'chocante-free-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Exclude from free shipping', 'chocante-free-shipping' ),
				'default' => 'no',
			),
		);

		return array_merge( $fields, $add_fields );
	}
}
