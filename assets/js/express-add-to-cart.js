(function($) {
    $(document).on('click', 'button[name="dhp-add-to-cart"]', function(e) {
        e.preventDefault();
        $('body').block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: .6
            }
        });
        var _this = $(this),
            $form = _this.closest('form.cart'),
            postData = {
                action: 'woocommerce_ajax_add_to_cart',
                quantity: $form.find('input[name=quantity]').val() || 1,
                product_id: $form.find('input[name=product_id]').val() || _this.val(),
                variation_id: ($form.find('input[name=variation_id]').val() || 0),
                checkout_type: 'dhp'
        };
        $.post(
            wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ),
            postData,
            function(response) {
                if (response && response.error) {
                    window.location = response.product_url;
                    return;
                }

                $.post(
                    dhp_express_cart.dhp_ajax_url.toString().replace( '%%endpoint%%', 'create_order' ),
                    {
                        action: 'create_order',
                        is_express: 1,
                    },
                    function (result) {
                        if (result.redirect) {
                            window.location = result.redirect;
                            $('body').unblock();
                            return;
                        }
                        console.log('Error creating session, contact integration@dintero.com', result);
                        alert('Error creating session, contact integration@dintero.com');
                    }
                );
            }
        );
    })
})(jQuery);