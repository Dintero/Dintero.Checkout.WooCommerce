jQuery( function( $ ) {

	// wc_checkout_params is required to continue, ensure the object exists
	if ( typeof wc_checkout_params === 'undefined' ) {
		return false;
	}

	var dhp_get_url = function( endpoint ) {
		var url = wc_checkout_params.wc_ajax_url.toString();
		url = url.replace('wc-ajax', 'dhp-ajax');
		return url.replace('%%endpoint%%', endpoint);
	};

	if(typeof(is_blocked) == "undefined"){
		/**
		 * Check if a node is blocked for processing.
		 *
		 * @param {JQuery Object} $node
		 * @return {bool} True if the DOM Element is UI Blocked, false if not.
		 */
		var is_blocked = function( $node ) {
			return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
		};
	}

	if(typeof(block) == "undefined"){
		/**
		 * Block a node visually for processing.
		 *
		 * @param {JQuery Object} $node
		 */
		var block = function( $node ) {
			if ( ! is_blocked( $node ) ) {
				$node.addClass( 'processing' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		};
	}

	if(typeof(unblock) == "undefined"){
		/**
		 * Unblock a node after processing is complete.
		 *
		 * @param {JQuery Object} $node
		 */
		var unblock = function( $node ) {
			$node.removeClass( 'processing' ).unblock();
		};
	}

	/**
	 * Object to handle cart UI.
	 */
	var dhpPay = {
		/**
		 * Initialize cart UI events.
		 */
		init: function() {
			this.embedClicked = this.embedClicked.bind( this );
			this.expressClicked = this.expressClicked.bind( this );

			$( document ).on('click', '.dhp_ebch a', this.embedClicked);
			$( document ).on('click', '.dhp_exch a', this.expressClicked);
		},

		embedClicked: function() {
			dhpPay.submit();
		},

		expressClicked: function() {
			dhpPay.submit(true);
		},

		$order_review: $( '#order_review' ),
		$checkout_form: $( 'form.checkout' ),

		get_payment_method: function() {
			return dhpPay.$checkout_form.find( 'input[name="payment_method"]:checked' ).val();
		},
		blockOnSubmit: function( $form ) {
			/*
			var form_data = $form.data();

			if ( 1 !== form_data['blockUI.isBlocked'] ) {
				$form.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
			*/
		},
		submit: function(express) {
			express = typeof(express) == "undefined" || express !== true ? false: true;

			var $form = $('#order_review');

			var valid = true;
			if(valid){
				if ( $form.is( '.processing' ) ) {
					return false;
				}

				$form.addClass( 'processing' );
				dhpPay.blockOnSubmit( $form );

				var f = $form.serialize();

				var valid = true;
				var terms_field = $form.find( 'input[name=terms-field]' );
				var terms = $form.find( '#terms' );
				if(terms_field.length > 0) {
					if( terms_field.val() == 1 ) {
						if(!terms.is(':checked')) {
							valid = false;
							dhpPay.submit_error( '<div class="woocommerce-error">Please read and accept the terms and conditions to proceed with your order.</div>' );
						}else{
							f += '&terms=1';
						}
					}
				}

				if( valid ) {
					var url = express ? dhp_get_url('express_pay') : dhp_get_url('embed_pay');
					window.location.href = url + "&key=" + dhpPay.getParameter('key') + "&" + f;
				}
			}

			return false;
		},
		submit_error: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			dhpPay.$checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			dhpPay.$checkout_form.removeClass( 'processing' ).unblock();
			dhpPay.$checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			dhpPay.scroll_to_notices();
			$( document.body ).trigger( 'checkout_error' );
		},
		scroll_to_notices: function() {
			var scrollElement           = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

			if ( ! scrollElement.length ) {
				scrollElement = $( '.form.checkout' );
			}
			$.scroll_to_notices( scrollElement );
		},
		is_valid_json: function( raw_json ) {
			try {
				var json = $.parseJSON( raw_json );

				return ( json && 'object' === typeof json );
			} catch ( e ) {
				return false;
			}
		},
		getParameter: function( k ) {
			var queryString = window.location.search;
			var urlParams = new URLSearchParams(queryString);
			var value = urlParams.get(k);
			return value;
		}
	};

	dhpPay.init();
} );
