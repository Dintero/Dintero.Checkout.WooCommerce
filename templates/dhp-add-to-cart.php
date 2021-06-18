<?php
$className = 'button dintero-express-checkout';
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
global $product;
?>
<div class="dhp-add-to-cart-button-wrapper">
	<button type="submit" name="dhp-add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
		<img src="<?php echo esc_url($imgurl); ?>" width="100%" />
	</button>
</div>
