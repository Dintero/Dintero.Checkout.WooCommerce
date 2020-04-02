jQuery( function( $ ) {

	/**
	 * Object to handle cart UI.
	 */
	var dhpHP = {
		/**
		 * Initialize cart UI events.
		 */
		init: function() {
			$( document ).ready(function() {
			    $('footer').before($('.dhp_footer_logo'));
			});			
		}
	};

	dhpHP.init();
} );
