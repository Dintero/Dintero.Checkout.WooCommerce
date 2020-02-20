<?php
/**
 * Core Dintero WooCommerce Extension
 *
 * @class   WC_Dintero_HP
 * @package Dintero/Classes
 */

final class WC_Dintero_HP {

	/**
	 * version.
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * The single instance of the class.
	 *
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woocommerce' ), '2.1' );
	}

	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '2.1' );
	}

    /**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

    /**
     * Register all of the hooks related to plugin functionality.
     *
     * @access   private
     */
    private function init_hooks() {
    	$express_enable = $this->setting()->get('express_enable');
		if($express_enable == "yes"){ //express
		    add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'add_custom_style' ), 1, 1 );
		}

        add_action( 'wp_enqueue_scripts', array( $this, 'init_script' ));
        add_action( 'woocommerce_checkout_order_review', array( $this, 'init_checkout' ), 50);
        add_action( 'woocommerce_pay_order_after_submit', array( $this, 'init_pay' ), 50);

        add_action( 'woocommerce_cancelled_order', array( $this, 'cancel_order' ) );
        add_action( 'woocommerce_order_status_changed', array( $this, 'check_status' ), 10, 3 );
        add_action( 'wp_footer', array( $this, 'init_footer') );

        add_action( 'woocommerce_applied_coupon', array( $this, 'applied_coupon' ), 10, 3 ); 
        add_action( 'woocommerce_removed_coupon', array( $this, 'removed_coupon' ), 10, 3 ); 
    }

    private function define( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }

    public function define_constants(){
    	$this->define( 'DHP_ABSPATH', dirname( DHP_PLUGIN_FILE ) . '/' );
    }

    public function includes() {
    	include_once DHP_ABSPATH .'includes/class-wc-dintero-hp-setting.php';
        include_once DHP_ABSPATH .'includes/class-wc-dintero-hp-ajax.php';
        include_once DHP_ABSPATH .'includes/class-wc-dintero-hp-checkout.php';
        include_once DHP_ABSPATH .'includes/admin/class-wc-dintero-hp-admin-menus.php';
        include_once DHP_ABSPATH .'includes/admin/class-wc-dintero-hp-admin-settings.php';
    }

    public function init_script(){
    	wp_enqueue_style( 'style', plugin_dir_url(__DIR__).'assets/css/style.css' );

    	$handle = "dhp-hp";
        $src = plugin_dir_url(__DIR__).'assets/js/dintero_hp.js';
        $deps = array( 'jquery' );
        $version = false;

        // Register the script
        wp_register_script( $handle, $src, $deps, $version, true );
        wp_enqueue_script( $handle);
    }

    public function init_checkout(){
    	WCDHP()->checkout()->init_checkout();
    }

    public function init_pay(){
    	WCDHP()->checkout()->init_pay();
    }

    public function init_footer(){
    	echo('<div class="dhp_footer_logo">'.WCDHP()->checkout()->get_icon_footer()."</div>");
    }

    public function add_custom_style(){
    	$custom_css = "<style type=\"text/css\">
	                #customer_details { display: none; }
					#order_review #payment { display: none; }
					</style>";

	    echo($custom_css);
    }

    public function cancel_order($order_id){
    	WCDHP()->checkout()->cancel($order_id);
    }

    public function check_status($order_id, $previous_status, $current_status){
    	WCDHP()->checkout()->check_status( $order_id, $previous_status, $current_status );
    }

    /**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', DHP_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( DHP_PLUGIN_FILE ) );
	}

    /**
	 * Get Checkout Class.
	 *
	 * @return WC_Dintero_HP_Checkout
	 */
	public function checkout() {
		return WC_Dintero_HP_Checkout::instance();
	}

	/**
	 * Get Setting Class.
	 *
	 * @return WC_Dintero_HP_Setting
	 */
	public function setting() {
		return WC_Dintero_HP_Setting::instance();
	}

	public function applied_coupon() {
		$order_id = WC()->session->get( 'order_awaiting_payment' );

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) AND $order instanceof WC_Order ) {
			$used_coupons = $order->get_used_coupons();
	    
			$coupons = WC()->cart->get_coupons();

			foreach($coupons as $coupon_code=>$cdata){
				if(!in_array($coupon_code, $used_coupons)){
					$order->apply_coupon($coupon_code);
				}
			}
			$order->calculate_totals();
		}
	}

	public function removed_coupon() {
		$order_id = WC()->session->get( 'order_awaiting_payment' );
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) AND $order instanceof WC_Order ) {
			$used_coupons = $order->get_used_coupons();

			$coupons = WC()->cart->get_coupons();

			foreach($used_coupons as $coupon_code){
				if(!isset($coupons[$coupon_code])){
					//remove
					$order->remove_coupon($coupon_code);
				}
			}
			$order->calculate_totals();
		}		
	}	
}
