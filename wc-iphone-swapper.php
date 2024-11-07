<?php
/*
Plugin Name: WC iPhone Swapper
Description: A WooCommerce plugin for calculating iPhone swap top-up amounts.
Version: 1.0
Author: Imokol Faith Ruth, Kasirye Arthur
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Enqueue any necessary styles or scripts
function wcis_iphone_swapper_enqueue_scripts() {
	wp_enqueue_style('wc-iphone-swapper-style', plugins_url('assets/css/style.css', __FILE__));

    wp_enqueue_script( 'wc-iphone-swapper-script', plugins_url('assets/js/script.js', __FILE__));
    wp_localize_script(
            'wc-iphone-swapper-script',
        'wcis_params',
        array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'checkout_url' => wc_get_checkout_url(),
        )
    );
}
add_action('wp_enqueue_scripts', 'wcis_iphone_swapper_enqueue_scripts');

// Function to create the hidden "iPhone Swap Top-Up" product
function wcis_create_hidden_swap_product( $title ) {
	$args = array(
		'title'     => $title,
		'post_type' => 'product',
		'post_status' => 'private',
		'numberposts' => 1,
	);
	$existing_product = get_posts($args);

	if (empty($existing_product)) {
		$product = new WC_Product_Simple();
		$product->set_name('iPhone Swap Top-Up');
		$product->set_status('private'); // Hidden from the public catalog
		$product->set_catalog_visibility('hidden'); // Hidden visibility
		$product->set_price(0);
		$product->set_regular_price(0); // Ensure regular price is set to avoid issues
		$product->set_stock_status('instock');
		$product->set_manage_stock(false);
		$product->set_backorders('no');
		$product->save();

		return $product->get_id();
	}

	return $existing_product[0]->ID;
}


// Register the shortcode
function wcis_iphone_swap_calculator() {
	ob_start();

	// Ensure the hidden product is created and get its ID
	$swap_product_id = wcis_create_hidden_swap_product('iPhone Swap Top-Up' );

	// WooCommerce product query for iPhone products (replace 'iphone' with your iPhone category slug)
	$args = array(
		'post_type' => 'product',
		'posts_per_page' => -1,
		'orderby' => 'title',
		'order' => 'ASC',
        'post_status' => 'publish',
	);
	$iphones = new WP_Query($args);

	if ($iphones->have_posts()) : ?>
        <div class="container">
            <h1>iPhone Swap Calculator</h1>
            <form id="swapForm">
                <div class="form-group">
                    <label for="currentPhone">Current iPhone:</label>
                    <select id="currentPhone" name="currentPhone">
						<?php while ($iphones->have_posts()) : $iphones->the_post(); ?>
							<?php
							$product = wc_get_product(get_the_ID());
							?>
                            <option value="<?php echo esc_attr($product->get_price()); ?>">
								<?php echo esc_html($product->get_name()); ?>
                            </option>
						<?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="desiredPhone">Desired iPhone:</label>
                    <select id="desiredPhone" name="desiredPhone">
						<?php
						$iphones->rewind_posts();
						while ($iphones->have_posts()) : $iphones->the_post();
							$product = wc_get_product(get_the_ID());
							?>
                            <option value="<?php echo esc_attr($product->get_price()); ?>">
								<?php echo esc_html($product->get_name()); ?>
                            </option>
						<?php endwhile; ?>
                    </select>
                </div>
                <input type="hidden" id="product_id" value="<?php echo $swap_product_id; ?>">
                <div class="form-group form-group-btn">
                    <button type="button" onclick="calculateDifference()">Get Estimate</button>
                    <button type="button" id="checkoutButton" style="display:none;" onclick="goToCheckout(event)">Proceed to Checkout</button>
                </div>
            </form>

            <h2 id="result"></h2>
        </div>
	<?php endif;

	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode('wc_iphone_swap_calculator', 'wcis_iphone_swap_calculator');

// AJAX handler to add the hidden product to the cart with the calculated top-up amount
function wcis__add_swap_product_to_cart() {
	if (!empty($_POST['product_id']) && !empty($_POST['top_up_amount'])) {
		$product_id = intval($_POST['product_id']);
		$top_up_amount = floatval($_POST['top_up_amount']);

		WC()->cart->empty_cart(); // Clear the cart
		WC()->cart->add_to_cart($product_id, 1, '', '', array('top_up_amount' => $top_up_amount));

		wp_send_json_success(array('checkout_page' => wc_get_checkout_url()));
	}
	wp_die();
}
add_action('wp_ajax_add_swap_product_to_cart', 'wcis_add_swap_product_to_cart');
add_action('wp_ajax_nopriv_add_swap_product_to_cart', 'wcis_add_swap_product_to_cart');


// Ensure the hidden product is purchasable
add_filter('woocommerce_is_purchasable', 'wcis_make_hidden_product_purchasable', 10, 2);
function wcis_make_hidden_product_purchasable($purchasable, $product) {
	// Replace with your hidden product's title or ID for more specificity
	if ($product->get_name() === 'iPhone Swap Top-Up') {
		$purchasable = true;
	}
	return $purchasable;
}

add_action('woocommerce_before_calculate_totals', 'wcis_apply_custom_top_up_price', 10, 1);
function wcis_apply_custom_top_up_price($cart) {
	if (is_admin() && !defined('DOING_AJAX')) return;

	foreach ($cart->get_cart() as $cart_item) {
		if (isset($cart_item['top_up_amount'])) {
			// Set the cart item price to the custom top-up amount
			$cart_item['data']->set_price($cart_item['top_up_amount']);
		}
	}
}
