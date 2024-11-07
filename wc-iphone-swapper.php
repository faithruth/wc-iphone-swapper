<?php
/*
Plugin Name: WC iPhone Swapper
Description: A WooCommerce plugin for calculating iPhone swap top-up amounts.
Version: 1.2
Author: Your Name
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Function to create the hidden "iPhone Swap Top-Up" product
function wc_create_hidden_swap_product() {
	$args = array(
		'title'     => 'iPhone Swap Top-Up',
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
		$product->set_price(0); // Initial price is set to 0
		$product->save();
		return $product->get_id();
	}

	return $existing_product[0]->ID;
}

// Register the shortcode
function wc_iphone_swap_calculator() {
	ob_start();

	// Ensure the hidden product is created and get its ID
	$swap_product_id = wc_create_hidden_swap_product();

	// WooCommerce product query for iPhone products (replace 'iphone' with your iPhone category slug)
	$args = array(
		'post_type' => 'product',
		'posts_per_page' => -1,
		'product_cat' => 'iphone', // Adjust the category slug as needed
		'orderby' => 'title',
		'order' => 'ASC'
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

                <button type="button" onclick="calculateDifference()">Get Estimate</button>
            </form>

            <h2 id="result"></h2>
            <button id="checkoutButton" style="display:none;" onclick="goToCheckout()">Proceed to Checkout</button>
        </div>
	<?php endif;

	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode('wc_iphone_swap_calculator', 'wc_iphone_swap_calculator');

// AJAX handler to add the hidden product to the cart with the calculated top-up amount
function wc_add_swap_product_to_cart() {
	if (!empty($_POST['product_id']) && !empty($_POST['top_up_amount'])) {
		$product_id = intval($_POST['product_id']);
		$top_up_amount = floatval($_POST['top_up_amount']);

		WC()->cart->empty_cart(); // Clear the cart
		WC()->cart->add_to_cart($product_id, 1, '', '', array('top_up_amount' => $top_up_amount));

		// Set the price of the product in the cart dynamically
		add_filter('woocommerce_before_calculate_totals', function($cart) use ($product_id, $top_up_amount) {
			foreach ($cart->get_cart() as $cart_item) {
				if ($cart_item['product_id'] == $product_id) {
					$cart_item['data']->set_price($top_up_amount);
				}
			}
		});
	}
	wp_die();
}
add_action('wp_ajax_add_swap_product_to_cart', 'wc_add_swap_product_to_cart');
add_action('wp_ajax_nopriv_add_swap_product_to_cart', 'wc_add_swap_product_to_cart');
