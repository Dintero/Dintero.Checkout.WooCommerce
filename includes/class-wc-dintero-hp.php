<?php
/**
 * Core Dintero WooCommerce Extension
 *
 * @class   WC_Dintero_HP
 * @package Dintero/Classes
 */

final class WC_Dintero_HP {

	/**
	 * Version.
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
	 */
	private function init_hooks() {
		// Override template if Checkout page.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );

        if('yes' == $this->setting()->get('branding_enable')){
            add_action( 'wp_footer', array( $this, 'init_footer') );
        }


		$embed_enable = $this->setting()->get('embed_enable');

		if ($this->setting()->get('express_product') == 'yes') { //express
			add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'add_custom_style' ), 1, 1 );
			add_action( 'woocommerce_after_add_to_cart_button', array($this, 'render_product_express_button'));
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'init_script' ));
		add_action( 'dhp_after_checkout_form', array( $this, 'init_checkout' ), 50);
		add_action( 'woocommerce_pay_order_after_submit', array( $this, 'init_pay' ), 50);

		add_action( 'woocommerce_cancelled_order', array( $this, 'cancel_order' ) );

		add_action( 'woocommerce_applied_coupon', array( $this, 'applied_coupon' ), 10, 3 );
		add_action( 'woocommerce_removed_coupon', array( $this, 'removed_coupon' ), 10, 3 );

		add_action( 'template_redirect', array( $this, 'check_thankyou' ), 10, 3 );
		add_action( 'dhp_payment_tab', array( $this, 'create_checkout_nav' ));

		//if ( 'no' == $express_enable || ( 'yes' == $express_enable && 'no' == $embed_enable ) ) {
			add_action( 'dhp_checkout_billing', array( $this, 'checkout_form_billing' ) );
			add_action( 'dhp_checkout_shipping', array( $this, 'checkout_form_shipping' ) );
		//}

		if ( 'yes' == $embed_enable ) {
			//make billing fields not required in checkout
			add_filter( 'woocommerce_billing_fields', array( $this, 'wc_npr_filter_billing_fields' ), 10, 1 );

			//make shipping fields not required in checkout
			add_filter( 'woocommerce_shipping_fields', array( $this, 'wc_npr_filter_shipping_fields' ), 10, 1 );
		}

		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'check_dintero_shipping' ));
		add_action( 'dhp_business_customer', array( $this, 'check_dintero_shipping' ));


		// Added By Ritesh - After Cart update hook to check if cart is updated
		 // add_action( 'woocommerce_update_cart_action_cart_updated',  array( $this, 'destroy_dintero_ongoing_session' ));

		 // add_action('woocommerce_checkout_update_order_review',  array( $this, 'on_action_update_review' ),10,1);

		 // add_action( 'woocommerce_applied_coupon',array( $this, 'destroy_dintero_ongoing_session' ) );
		 // add_action( 'woocommerce_removed_coupon',array( $this, 'destroy_dintero_ongoing_session' ) );
		 // add_action( 'woocommerce_add_to_cart',array( $this, 'destroy_dintero_ongoing_session' ) );
		 // add_action( 'woocommerce_remove_cart_item'  ,array( $this, 'destroy_dintero_ongoing_session' ) );

		 // add_action( 'woocommerce_after_shipping_calculator'  ,array( $this, 'destroy_dintero_ongoing_session' ) );
        add_action('woocommerce_checkout_update_order_review',  array( $this, 'on_action_update_review' ),10,1);


		 // Template integrations
        add_action( 'woocommerce_cart_actions', array($this, 'cart_express_checkout_button'));
        add_action( 'woocommerce_widget_shopping_cart_buttons', array($this, 'cart_express_checkout_button'), 30);


       	add_action('woocommerce_before_cart' , array($this, 'cart_express_checkout_loader'));

         // Special pages and callbacks handled by template_redirect
        add_action('template_redirect', array($this,'template_redirect'));
        // Allow overriding their templates
        add_filter('template_include', array($this,'template_include'), 10, 1);

        add_action( 'wp_footer', array( $this, 'maybe_submit_wc_checkout' ), 999 );

        $this->add_shortcodes();

		add_filter('woocommerce_order_note_class', array($this, 'woo_process_order_note_classes'), 10, 2);

		add_filter( 'manage_edit-shop_order_columns', array($this, 'add_order_dintero_status_column_header'), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array($this,'add_order_dintero_status_column_content'));

	}


	public function woo_process_order_note_classes($note_classes, $note){
		if (strpos( $note->content, 'Payment capture failed') !== false) {
			$note_classes[] = 'failed-note';
			return $note_classes;
		}

		return $note_classes;
	}

	public function wc_npr_filter_billing_fields( $address_fields ) {
		$address_fields['billing_first_name']['required'] = false;
		$address_fields['billing_last_name']['required'] = false;
		$address_fields['billing_company']['required'] = false;
		$address_fields['billing_address_1']['required'] = false;
		$address_fields['billing_address_2']['required'] = false;
		$address_fields['billing_country']['required'] = false;
		$address_fields['billing_city']['required'] = false;
		$address_fields['billing_state']['required'] = false;
		$address_fields['billing_postcode']['required'] = false;
		$address_fields['billing_phone']['required'] = false;
		$address_fields['billing_email']['required'] = false;

		return $address_fields;
	}

	public function wc_npr_filter_shipping_fields( $address_fields ) {
		$address_fields['shipping_first_name']['required'] = false;
		$address_fields['shipping_last_name']['required'] = false;
		$address_fields['shipping_company']['required'] = false;
		$address_fields['shipping_address_1']['required'] = false;
		$address_fields['shipping_address_2']['required'] = false;
		$address_fields['shipping_city']['required'] = false;
		$address_fields['shipping_state']['required'] = false;
		$address_fields['shipping_postcode']['required'] = false;
		$address_fields['shipping_country']['required'] = false;

		return $address_fields;
	}

	/**
	 * Adds 'Dintero status' column header to 'Orders' page.
	 *
	 * @param string[] $columns
	 * @return string[] $new_columns
	 */
	public function add_order_dintero_status_column_header( $columns ) {

		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {

			$new_columns[ $column_name ] = $column_info;

			if ( 'order_status' === $column_name ) {
				$new_columns['dintero_status'] = __( 'Dintero status', 'my-textdomain' );
			}
		}

		return $new_columns;
	}

	public function add_order_dintero_status_column_content( $column ) {
		global $post;

		if ( 'dintero_status' === $column ) {

			$order = wc_get_order( $post->ID );
			$notes = wc_get_order_notes([
					'order_id' => $order->get_id(),
					'type' => 'internal',
			]);
			$txn_id = $order->get_transaction_id();
			$account_id = explode( '.', $txn_id )[0];
			$backoffice_url = 'https://backoffice.dintero.com/' . $account_id . '/payments/transactions/' . $txn_id;
			$backoffice_link_start = '<a href="'. $backoffice_url . '" target="_blank" rel="noopener">';
			$last_authorize_succeeded = -1;
			$last_capture_failed = -1;
			$last_capture_succeeded = -1;
			$last_refund_succeeded = -1;
			$last_refund_failed = -1;
			$last_on_hold = -1;


			foreach($notes as $note) {
				if (strpos( $note->content, 'Payment capture failed') !== false) {
					$last_capture_failed = $note->id;
				} else if (strpos( $note->content, 'Payment captured via Dintero') !== false) {
					$last_capture_succeeded = $note->id;
				} else if (strpos( $note->content, 'Payment auto captured via Dintero') !== false) {
					$last_capture_succeeded = $note->id;
				} else if (strpos( $note->content, 'Transaction authorized via Dintero') !== false) {
					$last_authorize_succeeded = $note->id;
				} else if (strpos( $note->content, 'Payment refunded via Dintero.') !== false) {
					$last_refund_succeeded = $note->id;
				} else if (strpos( $note->content, 'Payment refund failed') !== false) {
					$last_refund_failed = $note->id;
				} else if (strpos( $note->content, 'The payment is put on on-hold') !== false) {
					$last_on_hold = $note->id;
				}
			}
			if ($last_refund_succeeded > -1) {
				echo $backoffice_link_start . '<mark class="order-status status-refunded"><span>' . __('Refunded') . '</span></mark></a>';
			} else if ($last_refund_failed > -1) {
				echo $backoffice_link_start . '<mark class="order-status status-failed"><span>' . __('Refund failed') . '</span></mark></a>';
			} else if ($last_capture_succeeded > -1) {
				echo $backoffice_link_start . '<mark class="order-status status-completed"><span>' . __('Captured') . '</span></mark></a>';
			} else if ($last_capture_failed > $last_capture_succeeded) {
				echo $backoffice_link_start . '<mark class="order-status status-failed"><span>' . __('Capture failed') . '</span></mark></a>';
			} else if ($last_authorize_succeeded && $order->get_status() == 'completed') {
				echo $backoffice_link_start . '<mark class="order-status status-failed"><span>' . __('Authorized') . '</span></mark></a>';
			} else if ($last_authorize_succeeded > -1) {
				echo $backoffice_link_start . '<mark class="order-status status-processing"><span>' . __('Authorized') . '</span></mark></a>';
			} else if ($last_on_hold > -1) {
				echo $backoffice_link_start . '<mark class="order-status status-on-hold"><span>' . __('On hold') . '</span></mark></a>';
			}
		}
	}

    public function add_shortcodes() {
        add_shortcode('woo_dintero_buy_now', array($this, 'buy_now_button_shortcode'));
        add_shortcode('woo_dintero_express_checkout_button', array($this, 'express_checkout_button_shortcode'));
        add_shortcode('woo_dintero_express_checkout_banner', array($this, 'express_checkout_banner_shortcode'));
    }

    // A shortcode for a single buy now button. Express checkout must be active; but I don't check for this here, as this button may be
    // cached. Therefore stock, purchasability etc will be done later. IOK 2018-10-02
    public function buy_now_button_shortcode ($atts) {
        $args = shortcode_atts( array( 'id' => '','variant'=>'','sku' => '',), $atts );
        return $this->get_buy_now_button($args['id'], $args['variant'], $args['sku'], false);
    }

    // The express checkout shortcode implementation. It does not need to check if we are to show the button, obviously, but needs to see if the cart works
    public function express_checkout_button_shortcode() {

        if ( WCDHP()->setting()->get('express_enable') !='yes') return;
        ob_start();
        $this->cart_express_checkout_button_html();
        return ob_get_clean();
    }
    // Show a banner normally shown for non-logged-in-users at the checkout page.  It does not need to check if we are to show the button, obviously, but needs to see if the cart works
    public function express_checkout_banner_shortcode() {
        if ( WCDHP()->setting()->get('express_enable') !='yes') return;
        ob_start();
        $this->express_checkout_banner_html();
        return ob_get_clean();
    }
     // Show the express button if reasonable to do so
    public function cart_express_checkout_button() {


        if ( WCDHP()->setting()->get('express_enable') =='yes'){
            return $this->cart_express_checkout_button_html();
        }
    }

    public function maybe_submit_wc_checkout(){

        if ( ! $this->is_dintero_confirmation() ) {
            return;
        }
        ?>
        <script>
            var dintero_text = '<?php echo __( 'Vent mens vi behandler bestillingen.', 'collector-checkout-for-woocommerce' ); ?>';
            jQuery(function ($) {
                $( 'body' ).append( $( '<div class="dintero-modal"><div class="dintero-modal-content">' + dintero_text + '</div></div>' ) );


                setTimeout(checkIfOrderNowExists, 2000);

                function checkIfOrderNowExists(){
                    var url = 'dhp-ajax';
                    var home_url = '<?php echo home_url()?>';
                    var transactionId = '<?php echo sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) ); ?>';
                    // Dont need to user create_order as we are using default WooCommerce Checout form
                    var data = {
                            action: 'check_order_status',
                            transaction_id : transactionId
                        };
                                    var url ='?dhp-ajax=check_order_status';

                    jQuery.ajax({
                         type:       'POST',
                         url:        url,
                         data:       data,

                         success:    function( result ) {

                                         if(result.data.redirect_url){
                                            window.location = result.data.redirect_url ;
                                         }

                                     }
                         });

                }




                console.log('processing class added to form');
            });
        </script>
        <?php

        $transaction_id = sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) );

        $transaction = WCDHP()->checkout()->get_transaction( $transaction_id );

        $transaction_order_id = trim($transaction['merchant_reference']);
        if($transaction_order_id == '' && isset($transaction['merchant_reference_2'])){
            $transaction_order_id = trim($transaction['merchant_reference_2']);
        }

        $order                = wc_get_order( $transaction_order_id );

        if($order){
            $location = $order->get_checkout_order_received_url();
            $location = $location.'&merchant_reference='.$transaction_order_id.'&transaction_id='.$transaction_id;
            wp_safe_redirect( $location );
            exit;
        }
    }

    /**
     * Checks if in  confirmation page.
     *
     * @return bool
     */
    private function is_dintero_confirmation() {

        if ( is_checkout() && is_wc_endpoint_url( 'order-received' ) ) {
            if (strpos(sanitize_text_field( wp_unslash($_SERVER['REQUEST_URI'])), "error=cancelled") !== false){ // order Cancelled
                   $location = wc_get_cart_url();
                    wp_safe_redirect( $location );
                    exit;
                }
           if ( isset( $_GET['transaction_id'] )  && !isset($_GET['key'])) {
                return true;
            }
        }

        return false;


    }
     // Code that will generate various versions of the 'buy now with Vipps' button IOK 2018-09-27
    public function get_buy_now_button($product_id,$variation_id=null,$sku=null,$disabled=false, $classes='') {
        $disabled = $disabled ? 'disabled' : '';
        $data = array();
        if ($sku) $data['product_sku'] = $sku;
        if ($product_id) $data['product_id'] = $product_id;
        if ($variation_id) $data['variation_id'] = $variation_id;

        $buttoncode = "<a href='javascript:void(0)' $disabled ";
        foreach($data as $key=>$value) {
            $value = sanitize_text_field($value);
            $buttoncode .= " data-$key='$value' ";
        }
        $buynow = __('Buy now with','dintero-hp');
        $title = __('Buy now with dintero', 'dintero-hp');
        $logo = plugins_url('dintero-hp/assets/images/dintero.png');
        $message = "<span class='dinterobuynow'>" . $buynow . "</span>" . " <img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>";

# Extra classes, if passed IOK 2019-02-26
        if (is_array($classes)) {
            $classes = join(" ", $classes);
        }
        if ($classes) $classes = " $classes";

        $buttoncode .=  " class='single-product button vipps-buy-now $disabled$classes' title='$title'>$message</a>";
        return apply_filters('woo_dintero_buy_now_button', $buttoncode, $product_id, $variation_id, $sku, $disabled);
    }
    public function cart_express_checkout_loader(){
    	 echo '<div class="loader">
			  <div class="loader-inner" style="top:0">
			    <!--?xml version="1.0" encoding="utf-8"?-->
			      <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="margin: auto; background: rgba(255, 255, 255, 0); display: block; shape-rendering: auto;" width="200px" height="200px" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid">
			      <circle cx="50" cy="50" fill="none" stroke="#00bc6d" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138" transform="rotate(157.594 50 50)">
			        <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"></animateTransform>
			      </circle>
			      </svg>
			  </div>
			</div>';
    }
    public function cart_express_checkout_button_html() {
        $url = $this->express_checkout_url();

        $url = wp_nonce_url($url,'express','sec');
        $className = 'button dintero-express-checkout';
        $imgurl = '';
        //$imgurl = plugins_url('dintero-hp/assets/images/dintero-express-btn.png');
        $title = __('Buy now with Dintero!', 'dintero-hp');
        $imageType  = WCDHP()->setting()->get('express_button_type');
        if($imageType == 0){
            // DARK
            $imgurl = 'https://assets.dintero.com/logo/dintero-express-btn-dark.svg';
            $className = $className.' dark';
        }else{
            // LIGHT
            $imgurl = 'https://assets.dintero.com/logo/dintero-express-btn-light.svg';
            $className = $className.' light';

        }
        $button = "<a href='#' class='".$className."' onclick='dintero_express_checkout();' title='$title' style='background-image: url(".$imgurl.");'></a>";
        $button = apply_filters('woo_vipps_cart_express_checkout_button', $button, $url);

        echo $button;
        	echo( "<script type=\"text/javascript\">
				function dintero_express_checkout(){
        			jQuery('.loader').css('display','block');
					jQuery('.loader').css('opacity','1');
        			var data = {
        				action: 'create_order',
						is_express: 1
					};
					var url = \"".home_url().'?dhp-ajax=create_order'."\";
							    
					jQuery.ajax({
						type: 'POST',
						url: url,
						data: data,
						success: function( result ) {
							if (result.redirect) {
								window.location = result.redirect ;
							} else {
								console.log('Error creating session, contact integration@dintero.com', result);
								alert('Error creating session, contact integration@dintero.com');
							}
						}
					});
        		}
        	</script>
            <style>
                .widget_shopping_cart a.button.dintero-express-checkout {
                    background-color: #03435d;
                    background-repeat: no-repeat;
                    height: 54px;
                    border: 1px solid #00e590;
                    background-size: contain;
                    background-position: center;
                }

                .widget_shopping_cart a.button.dintero-express-checkout.light {
                    background-color: #ffffff;
                    background-repeat: no-repeat;
                    height: 54px;
                    border: 1px solid #03435d;
                    background-size: 168px;
                    background-position: center;
                }
                .widget_shopping_cart a.button.dintero-express-checkout:hover {
                  background-color:#03435d;
                }

                .widget_shopping_cart a.button.dintero-express-checkout.light:hover{
                  background-color:#ffffff;
                }

                @media (max-width: 768px){
                    .actions a.button.dintero-express-checkout{
                        display: inline-block !important;
                        width: auto !important;
                    }
                }
                @media (max-width: 375px){
                    .actions a.button.dintero-express-checkout{margin-top: 10px;}
                }
                form.checkout.woocommerce-checkout.dhp-exp {
                    display: block;
                    width: 100%;
                }
            </style>

            " );


    }

     public function express_checkout_url() {
        return $this->make_return_url('dintero-express-checkout');
    }

    // The various return URLs for special pages of the Vipps stuff depend on settings and pretty-URLs so we supply them from here
    // These are for the "fallback URL" mostly. IOK 2018-05-18
    private function make_return_url($what) {
        $url = '';
        if ( !get_option('permalink_structure')) {
            $url = "/?DinteroSpecialPage=$what";
        } else {
            $url = "/$what/";
        }
        return untrailingslashit(set_url_scheme(home_url(),'https')) . $url;
    }


    public function express_checkout_banner_html() {
        $url = $this->express_checkout_url();
        $url = wp_nonce_url($url,'express','sec');
        $text = __('Skip entering your address and just checkout using', 'dintero-hp');
        $linktext = __('express checkout','dintero-hp');
        $logo = plugins_url('dintero-hp/assets/images/dintero.png');

        $message = $text . "<a href='$url'> <img class='inline dintero-logo negative' border=0 src='$logo' alt='Vipps'/> $linktext!</a>";
        $message = apply_filters('woo_vipps_express_checkout_banner', $message, $url);
        ?>
            <div class="woocommerce-info dntero-info"><?php echo $message;?></div>
            <?php
    }


    // Special pages, and some callbacks. IOK 2018-05-18
    public function template_redirect() {
        // Handle special callbacks
        $special = $this->is_special_page() ;


        if ($special) return $this->$special();

        $consentremoval = $this->is_consent_removal();
        if ($consentremoval) return  $this->dintero_consent_removal_callback($consentremoval);

    }
    // Template handling for special pages. IOK 2018-11-21
    public function template_include($template) {
        $special = $this->is_special_page() ;
        if ($special) {
            // Get any special template override from the options IOK 2020-02-18
            $specific = $this->gateway()->get_option('vippsspecialpagetemplate');
            $found = locate_template($specific,false,false);
            if ($found) $template=$found;

            return apply_filters('woo_dintero_special_page_template', $template, $special);
        }
        return $template;
    }


    // Can't use wc-api for this, as that does not support DELETE . IOK 2018-05-18
    private function is_consent_removal () {
        if (sanitize_text_field( wp_unslash($_SERVER['REQUEST_METHOD'])) != 'DELETE') return false;
        if ( !get_option('permalink_structure')) {
            if (sanitize_text_field( wp_unslash($_REQUEST['dintero-consent-removal']))){
                return sanitize_text_field( wp_unslash($_REQUEST['callback']));
            }
            return false;
        }
        if (preg_match("!/dintero-consent-removal/([^/]*)!", sanitize_text_field( wp_unslash($_SERVER['REQUEST_URI'])), $matches)) {
            return sanitize_text_field( wp_unslash($_REQUEST['callback']));
        }
        return false;
    }

    // Handle DELETE on a Dintero consent removal callback
    public function dintero_consent_removal_callback ($callback) {
        wc_nocache_headers();
        // This feature is disabled - no customers are created by express checkout
        // so there is nothing to do. IOK 2018-06-06
        print "1";
        exit();
    }

       // Return the method in the dintero
    public function is_special_page() {
        $specials = array('dintero-betaling' => 'dintero_wait_for_payment', 'dintero-express-checkout'=>'dintero_express_checkout', 'dintero-buy-product'=>'dintero_buy_product');
        $method = null;
        if ( get_option('permalink_structure')) {
            foreach($specials as $special=>$specialmethod) {
                // IOK 2018-06-07 Change to add any prefix from home-url for better matching IOK 2018-06-07
                if (preg_match("!/$special/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) {
                    $method = $specialmethod; break;
                }
            }
        } else {
            if (isset($_GET['DinteroSpecialPage'])) {
                $data = sanitize_text_field( wp_unslash($_GET['DinteroSpecialPage']));
                $method = @$specials[$data];

                echo $method;
                exit;
            }
        }
        return $method;
    }
	/**
	 * Define variable
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}


	  //  This is a landing page for the express checkout of then normal cart - it is done like this because this could take time on slower hosts.
    public function dintero_express_checkout() {
        status_header(200,'OK');
        wc_nocache_headers();
        // We need a nonce to get here, but we should only get here when we have a cart, so this will not be cached.
        // IOK 2018-05-28
        $ok = wp_verify_nonce($_REQUEST['sec'],'express');

        $backurl = wp_validate_redirect(@$_SERVER['HTTP_REFERER']);
        if (!$backurl) $backurl = home_url();

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wc_add_notice(__('Your shopping cart is empty','woo-vipps'),'error');
            wp_redirect($backurl);
            exit();
        }

        do_action('woo_dintero_express_checkout_page');

        $this->print_express_checkout_page($ok,'do_express_checkout');
    }
    // Used as a landing page for launching express checkout - borh for the cart and for single products. IOK 2018-09-28
    protected function print_express_checkout_page($execute,$action,$productinfo=null) {

    }





	/**
	 * Define constant variable
	 */
	public function define_constants() {
		$this->define( 'DHP_ABSPATH', dirname( DHP_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Include classes
	 */
	public function includes() {
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-setting.php';
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-helper.php';
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-ajax.php';
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-checkout.php';
		include_once DHP_ABSPATH . 'includes/admin/class-wc-dintero-hp-admin-menus.php';
		include_once DHP_ABSPATH . 'includes/admin/class-wc-dintero-hp-admin-settings.php';
	}

	/**
	 * Include script and style
	 */
	public function init_script() {
		// first check that woo exists to prevent fatal errors
		if (!function_exists('is_woocommerce')) {
			return;
		}

		if ( is_cart() || is_checkout() ) {
			wp_enqueue_style( 'style', plugin_dir_url(__DIR__) . 'assets/css/style.css', array(), '1.0.07', 'all' );

			$handle = 'dhp-hp';
			$src = plugin_dir_url(__DIR__) . 'assets/js/dintero_hp.js';
			$deps = array( 'jquery' );
			$version = false;

			// Register the script
			wp_register_script( $handle, $src, $deps, $version, true );
			wp_enqueue_script( $handle);

			$handle = 'dintero-checkout-web-sdk';
			$src = plugin_dir_url(__DIR__) . 'assets/js/checkout-web-sdk.umd.js';
			$deps = array( 'jquery' );
			$version = false;
			wp_register_script( $handle, $src, $deps, $version, true );
			wp_enqueue_script( $handle);
		}

		if (is_product() && WCDHP()->setting()->get('express_enable')) {
			wp_enqueue_style( 'style', plugin_dir_url(__DIR__) . 'assets/css/product.css', array(), '1.0.07', 'all' );
			wp_register_script(
					'dhp-add-to-cart',
					plugin_dir_url(__DIR__) . 'assets/js/express-add-to-cart.js',
					array('jquery'),
					true
			);
			wp_localize_script('dhp-add-to-cart', 'dhp_express_cart', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'dhp_ajax_url' => home_url() . '?dhp-ajax=%%endpoint%%',
			));
			wp_enqueue_script('dhp-add-to-cart');
		}
	}

	/**
	 * Render checkout page
	 */
	public function init_checkout() {
		WCDHP()->checkout()->init_checkout();
	}

	/**
	 * Render payment page
	 */
	public function init_pay() {
		WCDHP()->checkout()->init_pay();
	}

	/**
	 * Render footer line
	 */
	public function init_footer() {
		echo( '<div class="dhp_footer_logo">' . wp_kses_post( WCDHP()->checkout()->get_icon_footer() ) . '</div>' );
	}

	/**
	 * Print out inline style
	 */
	public function add_custom_style() {
		$custom_css = '<style type="text/css">
	                #customer_details { display: none; }
					</style>';

		wp_kses_post( $custom_css );
	}

	/**
	 * Cancel the order by order id
	 * TODO: Call new cancel-method
	 */
	public function cancel_order( $order_id) {
		WCDHP()->checkout()->cancel($order_id);
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

	/**
	 * Apply coupon from order compare to cart
	 */
	public function applied_coupon() {
		$order_id = WC()->session->get( 'order_awaiting_payment' );

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$used_coupons = $order->get_used_coupons();

			$coupons = WC()->cart->get_coupons();

			foreach ($coupons as $coupon_code=>$cdata) {
				if (!in_array($coupon_code, $used_coupons)) {
					$order->apply_coupon($coupon_code);
				}
			}
			$order->calculate_totals();
		}
	}

	/**
	 * Remove coupon from order compare to cart
	 */
	public function removed_coupon() {
		$order_id = WC()->session->get( 'order_awaiting_payment' );
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$used_coupons = $order->get_used_coupons();

			$coupons = WC()->cart->get_coupons();

			foreach ($used_coupons as $coupon_code) {
				if (!isset($coupons[$coupon_code])) {
					//remove
					$order->remove_coupon($coupon_code);
				}
			}
			$order->calculate_totals();
		}
	}

	/**
	 * Check the order before display thank you page
	 */
	public function check_thankyou( $order_id ) {
		if ( isset( $_SERVER['SERVER_NAME'] ) && isset( $_SERVER['REQUEST_URI'] ) && isset( $_REQUEST['key'] ) ) {
			$url = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			$template_name = strpos( $url, '/order-received/' ) === false ? '/view-order/' : '/order-received/';

			if ( strpos( $url, $template_name ) !== false ) {
				$start = strpos( $url, $template_name );
				$first_part = substr( $url, $start + strlen( $template_name ) );
                $orderUrl =  explode('?',$first_part);
				//$order_id = substr( $first_part, 0, strpos( $first_part, '/' ) );
                $order_id = $orderUrl[0];


				$order = wc_get_order( $order_id );

				if ( ! empty( $order ) && $order instanceof WC_Order ) {
					$order_key = get_post_meta( $order_id, '_order_key', true );

					if ( sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) == $order_key ) {
						if ( isset( $_REQUEST['error'] ) && 'cancelled' == $_REQUEST['error'] ) {
							$order_status = $order->get_status();
							if ( 'pending' == $order_status ) {
								//$order->update_status( 'failed' );

								$url = home_url() . '/my-account/view-order/' . $order_id . '/';
								wp_redirect ( $url );
								exit;
							}
						}
					}
				}
			}
		}elseif( isset( $_SERVER['SERVER_NAME'] ) && isset( $_SERVER['REQUEST_URI'] ) && isset( $_REQUEST['error'] ) ) {
            // if There is an error in payment, redirect to cart
            wp_redirect ( wc_get_cart_url() );
            exit;
        }
	}

	/**
	 * Override checkout form template if Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {

		if ( is_checkout() && WCDHP()->setting()->get('enabled') == 'yes') {
			// Fallback Order Received, used when WooCommerce checkout form submission fails.
			if ( 'checkout/thankyou.php' === $template_name ) {
				if ( isset( $_GET['dhp_checkout_error'] ) && 'true' === $_GET['dhp_checkout_error'] ) {
					$template = DHP_ABSPATH . 'templates/dhp-checkout-order-received.php';
				}
			}

			// Don't display template if we have a cart that doesn't needs payment.

			if ( apply_filters( 'dhp_check_if_needs_payment', true ) ) {
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
			}

			// Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$embed_enable = WCDHP()->setting()->get('embed_enable');
				$express_enable = WCDHP()->setting()->get('express_enable');

				/*if ( 'yes' == $express_enable ) {
					return $template;
					// return DHP_ABSPATH . 'templates/dhp-checkout-noembed-express.php';
					// There SHD be NO Change In Checkout page


				}else */
				if ( 'yes' == $embed_enable ) {
					if ( locate_template( 'woocommerce/dhp-checkout-embed-express.php' ) ) {
						return locate_template( 'woocommerce/dhp-checkout-embed-express.php' );
					} else {
						return DHP_ABSPATH . 'templates/dhp-checkout-embed-express.php';
					}
				}else {
					return $template;
				}
			}

			// Pay.
			if ( 'checkout/form-pay.php' === $template_name ) {
				return DHP_ABSPATH . 'templates/dhp-pay.php';
			}
		}

		// Order detail customer info
		if ( 'order/order-details-customer.php' === $template_name ) {
			return DHP_ABSPATH . 'templates/order/order-details-customer.php';
		}

		return $template;
	}

	/**
	 * Output the billing form.
	 */
	public function checkout_form_billing() {
		WC()->checkout()->checkout_form_billing();
	}

	/**
	 * Output the shipping form.
	 */
	public function checkout_form_shipping() {
		WC()->checkout()->checkout_form_shipping();
	}

	/**
	 * Clears the ongoing Dintero Checkout session, when cart is updated
	 * Added by Ritesh | MooGruppen
	 */
	public function on_action_cart_updated($cart_updated ){
		// Cart Updated
		// If Cart is updated we need to create new Dintero Checkout sension, hence clear the ongoing checkout session
		WC()->session->__unset('dintero_wc_order_id');
	}


	public function destroy_dintero_ongoing_session(){
		WC()->session->reload_checkout = true;
		WC()->session->__unset('dintero_wc_order_id');
	}

	public function remove_coupon_code(){
		WC()->session->reload_checkout = true;
		WC()->session->__unset('dintero_wc_order_id');
	}

	public function on_action_update_review($postData){
        $data = array();
        foreach(explode('&', $postData) as $value)
        {
            $value1 = explode('=', $value);

            $data[$value1[0]] = urldecode($value1[1]);
        }
        $customerData = array(
                'billing_first_name'   => $data['billing_first_name'],
                'billing_last_name'     => $data['billing_last_name'],
                'billing_email'  => $data['billing_email'],
                'billing_phone' => $data['billing_phone']

            );
        if(isset($data['billing_vat'])){
            WC()->session->set( 'dintero_billing_vat',$data['billing_vat'] );
            $customerData['billing_vat'] =  $data['billing_vat'];
            $customerData['billing_company'] = $data['billing_company'];

        }

        WC()->customer->set_props($customerData);
        WC()->customer->save();

		// $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

  //   	$posted_shipping_methods = isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : array();
  //   	$newMethod = array();


  //   	if ( is_array( $posted_shipping_methods ) && count($posted_shipping_methods)>0 ) {
		//     foreach ( $posted_shipping_methods as $i => $value ) {
		//         $newMethod[ $i ] = $value;
		//     }

	 //    }


  //   	if($chosen_shipping_methods[0] != $newMethod[0]){

	 //    	WC()->session->reload_checkout = true;

	 //    	WC()->session->__unset('dintero_wc_order_id');
	 //    }
	}
	public function update_shipping_method_in_order(){
		// $order_id = WC()->session->get( 'order_awaiting_payment');
	 //    $order = wc_get_order( $order_id );

	 //    $order->set_shipping_total('5');
	 //    $order->save();

	}
	public function create_checkout_nav() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$enabled_gateways = array();

		if ( $gateways ) {
			foreach ( $gateways as $gateway ) {
				if ( 'yes' == $gateway->enabled ) {
					$enabled_gateways[] = $gateway;
				}
			}
		}

		if ( count( $enabled_gateways ) > 1) {
			$tab_w = 100 / count( $enabled_gateways );

			echo( '<div class="dhp-checkout-tab">' );
			foreach ( $enabled_gateways as $gateway ) {
				$title = $gateway->settings['title'] ? $gateway->settings['title'] : '';
				$id = $gateway->id ? $gateway->id : '';
				$rel = 'dintero-hp' == $id ? 'dhp-embed' : 'dhp-others';

				if ( 'dintero-hp' == $id ) {
					echo( '<div id="' . esc_attr( $id ) . '" rel="' . esc_attr ( $rel ) . '" style="width:' . esc_attr( $tab_w ) . '%;background-image: url(\'' . wp_kses_post( WCDHP()->checkout()->get_icon_tab() ) . '\');"></div>' );
				} else {
					if(!$paymentMethods->icon){
                       echo( '<div id="' . esc_attr( $id ) . '" rel="' . esc_attr ( $rel ) . '" style="width:' . esc_attr( $tab_w ) . '%;">' . esc_html ( $title ) . '</div>' );
                    }else{
                          echo( '<div id="' . esc_attr( $id ) . '" rel="' . esc_attr ( $rel ) . '" style="width:' . esc_attr( $tab_w ) . '%;background-image: url(\'' . $paymentMethods->icon . '\');"></div>' );
                    }
				}
			}
			echo( '</div>' );
		}
	}

	public function check_dintero_shipping( $order ) {
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$payment_method = $order->get_payment_method();

			if ( 'dintero-hp' == $payment_method ) { // && $order->get_transaction_id()
				$transaction_id = $order->get_transaction_id();
				if ( !$transaction_id && isset($_GET['transaction_id']) ) {
					$transaction_id = sanitize_text_field( $_GET['transaction_id'] );
				}

				$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );
				if ( isset ( $transaction['shipping_address'] ) ) {
					$shipping_addr = $transaction['shipping_address'];
					$organization_number = isset ( $shipping_addr['organization_number'] ) ? $shipping_addr['organization_number'] : '';
					$business_name = isset ( $shipping_addr['business_name'] ) ? $shipping_addr['business_name'] : '';
					$co_address = isset ( $shipping_addr['co_address'] ) ? $shipping_addr['co_address'] : '';
					$customer_reference = isset ( $shipping_addr['customer_reference'] ) ? $shipping_addr['customer_reference'] : '';
					$cost_center = isset ( $shipping_addr['cost_center'] ) ? $shipping_addr['cost_center'] : '';

					if ( $organization_number || $customer_reference || $cost_center ) {
						if ( $organization_number ) {
							echo ( '<p><strong>Organization Number:</strong><br />' . esc_attr( $organization_number ) . '</p>' );
						}
						if ( $organization_number ) {
							echo ( '<p><strong>Business Name:</strong><br />' . esc_attr( $business_name ) . '</p>' );
						}
						if ( $co_address ) {
							echo ( '<p><strong>C/O:</strong><br />' . esc_attr( $co_address ) . '</p>' );
						}
						if ( $customer_reference ) {
							echo ( '<p><strong>Reference:</strong><br />' . esc_attr( $customer_reference ) . '</p>' );
						}
						if ( $cost_center ) {
							echo ( '<p><strong>Cost Center:</strong><br />' . esc_attr( $cost_center ) . '</p>' );
						}
					}
				}
			}
		}
	}

	/**
	 * Rendering product express button
	 */
	public function render_product_express_button()
	{
		if (!$template = locate_template( 'woocommerce/dhp-checkout-embed-express.php' )) {
			$template = DHP_ABSPATH . 'templates/dhp-add-to-cart.php';
		}

		if (file_exists($template)) {
			load_template($template);
		}
	}
}
