<?php
/**
 * Woocommerce Multiple Addresses plugin.
 *
 * @package   WC_Multiple_addresses
 * @author    Alexander Tinyaev <alexander.tinyaev@n3wnormal.com>
 * @license   GPL-2.0+
 * @link      http://n3wnormal.com
 * @copyright 2015 N3wNormal
 */

/**
 * @todo Don't store shipping addresses but just general addresses that can be used for either
 * @todo Add checkbox that allows me to defin weather a new address I'm entering as a repeated customer should be saved to the address book
 */

/**
 * Plugin class.
 *
 * @package WC_Multiple_addresses
 * @author  Alexander Tinyaev <alexander.tinyaev@n3wnormal.com>
 * @see https://github.com/woocommerce/woocommerce/wiki/2.6-Tabbed-My-Account-page Tutorial on how to add new account tabs
 */
class WC_Multiple_addresses {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.7.1
	 * @var     string
	 */
	const VERSION = '1.0.8.1';

	/**
	 * Unique identifier for the plugin.
	 *
	 * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	 * match the Text Domain file header in the main plugin file.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	protected static $plugin_slug = 'woocommerce-multiple-addresses';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting filters and administration functions.
	 *
	 * @since     1.0.4
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin for newly added blog on multisite
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Change 'edit' link on My Account page to lead on our 'edit address' page
		add_action( 'woocommerce_get_endpoint_url', array( $this, 'rewrite_woocommerce_get_endpoint_url' ), 25, 4 );

		// Create a shortcode to show content on 'Manage addresses' page
//		add_shortcode( 'woocommerce_multiple_shipping_addresses', array($this, 'woocommerce_addresses_endpoint_content') );

		// Process saving on 'Manage addresses' page
		add_action( 'template_redirect', array( $this, 'save_multiple_shipping_addresses' ) );

		// Show a 'configure addresses' button on checkout
		add_action( 'woocommerce_before_checkout_form', array( $this, 'before_checkout_form' ) );

		// Save billing and shipping addresses as default when creating a new customer aco
		add_action( 'woocommerce_created_customer', array( $this, 'created_customer_save_shipping_as_default' ), 10, 3 );

		// Add a dropdown to choose an address
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_dd_to_checkout_fields' ) );

        // Add ajax handler for choosing shipping address on checkout
		add_action( 'wp_ajax_alt_change', array( $this, 'ajax_checkout_change_shipping_address' ) );
		add_action( 'wp_ajax_nopriv_alt_change', array( $this, 'ajax_checkout_change_shipping_address' ) );

		// Filter shipping country value
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'wma_checkout_get_value' ), 10, 2 );

		 add_filter( 'woocommerce_account_menu_items', array($this, 'add_my_account_menu_items') );

		add_action( 'init', array($this, 'woocommerce_addresses_endpoints'));

		add_filter( 'query_vars', array($this, 'woocommerce_addresses_query_vars'), 0 );

		add_action( 'woocommerce_account_manage-addresses_endpoint', array($this, 'woocommerce_addresses_endpoint_content'));

		add_filter( 'the_title', array($this, 'woocommerce_addresses_endpoint_title'));

        add_action( 'wp_loaded', array($this, 'my_custom_flush_rewrite_rules'));

	}

	/**
	 * Change endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function woocommerce_addresses_endpoint_title( $title ) {
		global $wp_query;
		$is_endpoint = isset( $wp_query->query_vars['manage-addresses'] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = __( 'Manage Addresses', 'woocommerce' );

			remove_filter( 'the_title', 'manage-addresses_title' );
		}

		return $title;
	}

	/**
	 * Add new query var.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function woocommerce_addresses_query_vars( $vars ) {
		$vars[] = 'manage-addresses';

		return $vars;
	}


	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function woocommerce_addresses_endpoints() {
		add_rewrite_endpoint( 'manage-addresses', EP_ROOT | EP_PAGES );
//        update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes',  true);
	}


	/**
	 * Custom help to add new items into an array after a selected item.
	 *
	 * @param array $items
	 * @param array $new_items
	 * @param string $after
	 * @return array
	 */
	public function menuitems_insert_after_helper( $items, $new_items, $after ) {
		// Search for the item position and +1 since is after the selected item key.
		$position = array_search( $after, array_keys( $items ) ) + 1;

		// Insert the new item.
		$array = array_slice( $items, 0, $position, true );
		$array += $new_items;
		$array += array_slice( $items, $position, count( $items ) - $position, true );

		return $array;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function add_my_account_menu_items( $items ) {
		$new_items = array();
		$new_items['manage-addresses'] = __( 'Manage Addresses', 'woocommerce' );

		// Add the new item after `orders`.
		return $this->menuitems_insert_after_helper( $items, $new_items, 'orders' );
	}

	/**
	 * [rewrite_woocommerce_get_endpoint_url description]
	 *
	 * @param  [type] $url       [description]
	 * @param  [type] $endpoint  [description]
	 * @param  [type] $value     [description]
	 * @param  [type] $permalink [description]
	 * @return [type]            [description]
	 */
	public function rewrite_woocommerce_get_endpoint_url( $url, $endpoint, $value, $permalink ) {
		if( trim($value) === 'shipping' && $endpoint === 'edit-address' ) {

            return wc_get_endpoint_url('manage-addresses');
		}

		return $url;
	}

    public function my_custom_flush_rewrite_rules() {
        flush_rewrite_rules();
    }



	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.4
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}
	}

	/**
	 * HTLM Content for My Account Endpoints
	 *
	 * @return void
	 */


	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.4
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.4
	 * @param    int $blog_id ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.4
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.4
	 */
	private static function single_activate() {
		// Nothing here for now...
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.4
	 */
	private static function single_deactivate() {
		// Nothing here for now...
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.4
	 */
	public function load_plugin_textdomain() {

		$domain = self::$plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, basename( plugin_dir_path( __FILE__ ) ) . '/languages/' );

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.4
	 */
	public function enqueue_styles() {
		wp_enqueue_style( self::$plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.6
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'wc-country-select', WP_CONTENT_URL . '/plugins/woocommerce/assets/js/frontend/country-select.min.js', array( 'jquery' ), self::VERSION, true );
		wp_enqueue_script( self::$plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_localize_script( self::$plugin_slug . '-plugin-script', 'WCMA_Ajax', array(
				'ajaxurl'               => admin_url( 'admin-ajax.php' ),
				'id'                    => 0,
				'wc_multiple_addresses' => wp_create_nonce( 'wc-multiple-addresses-ajax-nonce' )
			)
		);
	}

	/**
	 * Filter shipping country value
	 *
	 * @param $null
	 * @param $input
	 * @since    1.0.6
	 * @return mixed
	 */
	public function wma_checkout_get_value($null, $input) {
		global $wma_current_address;

		if ( ! empty( $wma_current_address ) ) {
			foreach ($wma_current_address as $key => $value) {
				if ( $input == $key ) {
					return $value;
				}
			}
		}
	}

	/**
	 * Multiple shipping addresses page
	 *
	 * @since    1.0.7.1
	 */
	public function woocommerce_addresses_endpoint_content() {
		global $woocommerce;

		$GLOBALS['wma_current_address'] = '';

		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
			require_once $woocommerce->plugin_path() .'/classes/class-wc-checkout.php';
		} else {
			require_once $woocommerce->plugin_path() . '/includes/class-wc-checkout.php';
		}

		$user     = wp_get_current_user();
		$checkout = WC()->checkout();
		$shipFields = $checkout->checkout_fields['shipping'];

		if ( $user->ID == 0 ) {
			return;
		}

		$otherAddr = get_user_meta( $user->ID, 'wc_multiple_shipping_addresses', true );
        require $this->plugin_path() . '/templates/myaccount/manage-addresses.php';

	}


	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

    /**
	 * Save multiple shipping addresses
	 *
	 * @since    1.0.3
	 */
	public function save_multiple_shipping_addresses() {

		if ( isset( $_POST['shipping_account_address_action'] ) && $_POST['shipping_account_address_action'] == 'save' ) {
			unset( $_POST['shipping_account_address_action'] );

			$addresses  = array();
			$is_default = false;
			foreach ( $_POST as $key => $values ) {

                if ( $key == 'shipping_address_is_default' ) {
					foreach ( $values as $idx => $val ) {
						if ( $val == 'true' ) {
							$is_default = $idx;
						}
					}
				}
				if ( ! is_array( $values ) ) {
					continue;
				}

				foreach ( $values as $idx => $val ) {
					$addresses[ $idx ][ $key ] = $val;
				}
			}

			$user = wp_get_current_user();

			if ( $is_default !== false ) {
				$default_address = $addresses[ $is_default ];
				foreach ( $default_address as $key => $field ) :
					if ( $key == 'shipping_address_is_default' ) {
						continue;
					}
					update_user_meta( $user->ID, $key, $field );
				endforeach;
			}

			update_user_meta( $user->ID, 'wc_multiple_shipping_addresses', $addresses );

			if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
				global $woocommerce;
				$woocommerce->add_message( __( 'Addresses have been saved', self::$plugin_slug ) );
			} else {
				wc_add_notice( __( 'Addresses have been saved', self::$plugin_slug ), $notice_type = 'success' );
			}

			$page_id = wc_get_page_id( 'myaccount' );
			wp_redirect( get_permalink( $page_id )   );
			exit;
		}
	}

	/**
	 * Add possibility to configure addresses on checkout page
	 *
	 * @since    1.0.4
	 */
	public function before_checkout_form() {
		global $woocommerce;

        $page_url = wc_get_page_permalink( 'myaccount' );

        if(stristr($page_url, 'page_id=') === FALSE) {
            $page_url = wc_get_page_permalink( 'myaccount' ) . 'manage-addresses/';
        }else{
            $page_url = wc_get_page_permalink( 'myaccount' ) . '&manage-addresses';
        }

		if ( is_user_logged_in() ) {
			echo '<p class="woocommerce-info woocommerce_message">
	                ' . __( 'If you have more than one shipping address, then you may choose a default one here.', self::$plugin_slug ) . '
	                <a class="button" href="' . $page_url . '">' . __( 'Configure Address', self::$plugin_slug ) . '</a>
	              </p>';
		}

	}

	/**
	 * Helper function to prepend value to an array with custom key
	 *
	 * @param $arr
	 * @param $key
	 * @param $val
	 * @since    1.0.4
	 * @return array
	 */
	public function array_unshift_assoc( &$arr, $key, $val ) {
		$arr         = array_reverse( $arr, true );
		$arr[ $key ] = $val;

		return array_reverse( $arr, true );
	}

	/**
	 * Creating the same default shipping for newly created customer
	 *
	 * @since    1.0.0
	 * @param    integer $customer_id
	 */
	public function created_customer_save_shipping_as_default( $customer_id, $new_customer_data, $password_generated ) {
		global $woocommerce;

		if ( $customer_id == 0 ) {
			return;
		}

		$checkout        = $woocommerce->checkout->posted;
		$default_address = array();
		if ( $checkout['ship_to_different_address'] == 0 ) {
			$default_address[0]['shipping_country']    = $checkout['billing_country'];
			$default_address[0]['shipping_first_name'] = $checkout['billing_first_name'];
			$default_address[0]['shipping_last_name']  = $checkout['billing_last_name'];
			$default_address[0]['shipping_company']    = $checkout['billing_company'];
			$default_address[0]['shipping_address_1']  = $checkout['billing_address_1'];
			$default_address[0]['shipping_address_2']  = $checkout['billing_address_2'];
			$default_address[0]['shipping_city']       = $checkout['billing_city'];
			$default_address[0]['shipping_state']      = $checkout['billing_state'];
			$default_address[0]['shipping_postcode']   = $checkout['billing_postcode'];
		} else {
			$default_address[0]['shipping_country']    = $checkout['shipping_country'];
			$default_address[0]['shipping_first_name'] = $checkout['shipping_first_name'];
			$default_address[0]['shipping_last_name']  = $checkout['shipping_last_name'];
			$default_address[0]['shipping_company']    = $checkout['shipping_company'];
			$default_address[0]['shipping_address_1']  = $checkout['shipping_address_1'];
			$default_address[0]['shipping_address_2']  = $checkout['shipping_address_2'];
			$default_address[0]['shipping_city']       = $checkout['shipping_city'];
			$default_address[0]['shipping_state']      = $checkout['shipping_state'];
			$default_address[0]['shipping_postcode']   = $checkout['shipping_postcode'];
		}
		$default_address[0]['shipping_address_is_default'] = 'true';
		update_user_meta( $customer_id, 'wc_multiple_shipping_addresses', $default_address );
	}

	/**
	 * Add dropdown above shipping address at checkout
	 *
	 * @param    $fields
	 * @since    1.0.7
	 * @return   mixed
	 */
	public function add_dd_to_checkout_fields( $fields ) {
		global $current_user;

		$otherAddrs = get_user_meta( $current_user->ID, 'wc_multiple_shipping_addresses', true );
		if ( ! $otherAddrs ) {
			return $fields;
		}

		$addresses    = array();
		$addresses[0] = __( 'Choose an address...', self::$plugin_slug );
		for ( $i = 1; $i <= count( $otherAddrs ); ++$i ) {
			if (!empty($otherAddrs[$i - 1]['label'])) {
				$addresses[ $i ] = $otherAddrs[$i - 1]['label'] . ' ' . $otherAddrs[ $i - 1 ]['shipping_postcode'];
			} else {
				$addresses[ $i ] = $otherAddrs[ $i - 1 ]['shipping_first_name'] . ' ' . $otherAddrs[ $i - 1 ]['shipping_last_name'] . ', ' . $otherAddrs[ $i - 1 ]['shipping_postcode'] . ' ' . $otherAddrs[ $i - 1 ]['shipping_city'];
			}
		}

		$alt_field = array(
			'label'    => __( 'Predefined addresses', self::$plugin_slug ),
			'required' => false,
			'class'    => array( 'form-row' ),
			'clear'    => true,
			'type'     => 'select',
			'options'  => $addresses
		);

		$fields['shipping'] = $this->array_unshift_assoc( $fields['shipping'], 'shipping_alt', $alt_field );
		$fields['billing'] = $this->array_unshift_assoc( $fields['billing'], 'billing_alt', $alt_field );

		return $fields;
	}


	/**
	 * Handles ajax action call on choosing shipping address on checkout
	 *
	 * @since    1.0.4
	 */
	public function ajax_checkout_change_shipping_address() {

		// check nonce
		$nonce = $_POST['wc_multiple_addresses'];
		if ( ! wp_verify_nonce( $nonce, 'wc-multiple-addresses-ajax-nonce' ) ) {
			die ( 'Busted!' );
		}

		$address_id = $_POST['id'] - 1;
		if ( $address_id < 0 ) {
			return;
		}

		// get address
		global $current_user;
		$otherAddr = get_user_meta( $current_user->ID, 'wc_multiple_shipping_addresses', true );

		global $woocommerce;
		$addr                          = $otherAddr[ $address_id ];
		$addr['shipping_country_text'] = $woocommerce->countries->countries[ $addr['shipping_country'] ];
		$response                      = json_encode( $addr );

		// response output
		header( "Content-Type: application/json" );
		echo $response;

		exit;
	}
}