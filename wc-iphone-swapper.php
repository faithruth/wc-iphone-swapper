<?php
/**
 * Plugin Name:       WC iPhone Swapper
 * Plugin URI:        hhttps://github.com/faithruth/wc-iphone-swapper
 * Description:       A WooCommerce plugin for calculating iPhone swap top-up amounts.
 * Author:            Imokol Faith Ruth & Kasirye Arthur
 * Author URI:        https://github.com/faithruth/wc-iphone-swapper
 * Version:           1.0
 * Requires PHP:      8.0
 * Requires at least: 6.0
 * Domain Path:       /languages/
 * Text Domain:       wc-iphone-swapper
 *
 * @package WC_Iphone_Swapper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enqueue any necessary styles or scripts
 *
 * @return void
 */
function wcis_iphone_swapper_enqueue_scripts() {
	$plugin_data = get_plugin_data( __FILE__ );
	wp_enqueue_style(
		'wc-iphone-swapper-style',
		plugins_url( 'assets/css/style.css', __FILE__ ),
		'',
		$plugin_data['Version'],
		'all'
	);

	wp_enqueue_script(
		'wc-iphone-swapper-script',
		plugins_url( 'assets/js/script.js', __FILE__ ),
		array(),
		$plugin_data['Version'],
		true
	);
	wp_localize_script(
		'wc-iphone-swapper-script',
		'wcis_params',
		array(
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'checkout_url' => wc_get_checkout_url(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'wcis_iphone_swapper_enqueue_scripts' );

/**
 * Function to create the hidden "iPhone Swap Top-Up" product
 *
 * @param string $title Swap product title.
 *
 * @return int
 */
function wcis_create_hidden_swap_product( $title ) {
	$args             = array(
		'title'       => $title,
		'post_type'   => 'product',
		'post_status' => 'private',
		'numberposts' => 1,
	);
	$existing_product = get_posts( $args );

	if ( empty( $existing_product ) ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'iPhone Swap Top-Up' );
		$product->set_status( 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_stock_status( 'instock' );
		$product->set_manage_stock( false );
		$product->set_backorders( 'no' );
		$product->save();

		return $product->get_id();
	}

	return $existing_product[0]->ID;
}

/**
 * Register the shortcode
 *
 * @return false|string
 */
function wcis_iphone_swap_calculator() {
	ob_start();

	// Ensure the hidden product is created and get its ID.
	$swap_product_id = wcis_create_hidden_swap_product( 'iPhone Swap Top-Up' );

	$args    = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
	);
	$iphones = new WP_Query( $args );

	if ( $iphones->have_posts() ) : ?>
		<div class="container">
			<h1>iPhone Swap Calculator</h1>
			<form id="swapForm">
				<div class="form-group">
					<label for="currentPhone">Current iPhone:</label>
					<select id="currentPhone" name="currentPhone">
						<?php
						while ( $iphones->have_posts() ) :
							$iphones->the_post();
							?>
							<?php
							$product = wc_get_product( get_the_ID() );
							if ( $product && $product->is_in_stock() ) :
								?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>" data-price="<?php echo esc_attr( $product->get_price() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</option>
								<?php endif; ?>
						<?php endwhile; ?>
					</select>
				</div>

				<div class="form-group">
					<label for="desiredPhone">Desired iPhone:</label>
					<select id="desiredPhone" name="desiredPhone">
						<?php
						$iphones->rewind_posts();
						while ( $iphones->have_posts() ) :
							$iphones->the_post();
							$product = wc_get_product( get_the_ID() );
							if ( $product && $product->is_in_stock() ) :
								?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>" data-price="<?php echo esc_attr( $product->get_price() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</option>
							<?php endif; ?>
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
		<?php
	endif;

	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'wc_iphone_swap_calculator', 'wcis_iphone_swap_calculator' );

/**
 * AJAX handler to add the hidden product to the cart with the calculated top-up amount
 *
 * @return void
 */
function wcis_add_swap_product_to_cart() {
	if ( ! empty( $_POST['product_id'] ) && ! empty( $_POST['top_up_amount'] ) ) {
		$product_id        = intval( $_POST['product_id'] );
		$top_up_amount     = floatval( $_POST['top_up_amount'] );
		$current_iphone_id = sanitize_text_field( $_POST['current_iphone'] );
		$desired_iphone_id = sanitize_text_field( $_POST['desired_iphone'] );

		$current_iphone = wc_get_product( $current_iphone_id );
		$desired_iphone = wc_get_product( $desired_iphone_id );

		WC()->cart->add_to_cart(
			$product_id,
			1,
			'',
			'',
			array(
				'top_up_amount'  => $top_up_amount,
				'current_iphone' => $current_iphone->get_name(),
				'desired_iphone' => $desired_iphone->get_name(),
				'unique_key'     => md5( $current_iphone . $desired_iphone . microtime() ),

			)
		);

		wp_send_json_success( array( 'checkout_page' => wc_get_checkout_url() ) );
	}
	wp_die();
}
add_action( 'wp_ajax_add_swap_product_to_cart', 'wcis_add_swap_product_to_cart' );
add_action( 'wp_ajax_nopriv_add_swap_product_to_cart', 'wcis_add_swap_product_to_cart' );


add_filter( 'woocommerce_is_purchasable', 'wcis_make_hidden_product_purchasable', 10, 2 );

/**
 * Ensure the hidden product is purchasable
 *
 * @param boolean $purchasable Product purchase status.
 * @param object  $product Swap product.
 *
 * @return mixed|true
 */
function wcis_make_hidden_product_purchasable( $purchasable, $product ) {
	if ( $product->get_name() === 'iPhone Swap Top-Up' ) {
		$purchasable = true;
	}
	return $purchasable;
}

add_action( 'woocommerce_before_calculate_totals', 'wcis_apply_custom_top_up_price', 10, 1 );

/**
 * Apply custom topup price on checkout.
 *
 * @param mixed $cart Checkout cart.
 *
 * @return void
 */
function wcis_apply_custom_top_up_price( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['top_up_amount'] ) ) {
			$cart_item['data']->set_price( $cart_item['top_up_amount'] );
		}
	}
}

add_action( 'woocommerce_checkout_create_order', 'wcis_save_iphone_swap_details_to_order', 10, 2 );
/**
 * Save selected iPhones to order meta
 *
 * @param mixed $order checkout order item.
 * @param mixed $data product item data.
 *
 * @return void
 */
function wcis_save_iphone_swap_details_to_order( $order, $data ) { // phpcs:ignore
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['current_iphone'] ) && isset( $cart_item['desired_iphone'] ) ) {
			$order->update_meta_data( '_current_iphone', $cart_item['current_iphone'] );
			$order->update_meta_data( '_desired_iphone', $cart_item['desired_iphone'] );
		}
	}
}


add_action( 'woocommerce_admin_order_data_after_order_details', 'wcis_display_iphone_swap_details_in_order_admin' );
/**
 * Display iPhone details in the order admin page
 *
 * @param mixed $order Order item.
 *
 * @return void
 */
function wcis_display_iphone_swap_details_in_order_admin( $order ) {
	$current_iphone = $order->get_meta( '_current_iphone' );
	$desired_iphone = $order->get_meta( '_desired_iphone' );

	if ( $current_iphone && $desired_iphone ) {
		echo '<p><strong>Old iPhone:</strong> ' . esc_html( $current_iphone ) . '</p>';
		echo '<p><strong>New iPhone:</strong> ' . esc_html( $desired_iphone ) . '</p>';
	}
}

add_filter( 'woocommerce_get_item_data', 'wcis_display_iphone_swap_details_in_cart', 10, 2 );
/**
 * Display iphone swap details in cart.
 *
 * @param array $item_data cart item meta.
 * @param mixed $cart_item Cart item.
 *
 * @return mixed
 */
function wcis_display_iphone_swap_details_in_cart( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['current_iphone'] ) ) {
		$item_data[] = array(
			'key'     => __( 'Old iPhone', 'wc-iphone-swapper' ),
			'value'   => wc_clean( $cart_item['current_iphone'] ),
			'display' => wc_clean( $cart_item['current_iphone'] ),
		);
	}
	if ( ! empty( $cart_item['desired_iphone'] ) ) {
		$item_data[] = array(
			'key'     => __( 'New iPhone', 'wc-iphone-swapper' ),
			'value'   => wc_clean( $cart_item['desired_iphone'] ),
			'display' => wc_clean( $cart_item['desired_iphone'] ),
		);
	}
	return $item_data;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'wcis_add_iphone_swap_details_to_order_items', 10, 4 );
/**
 * Add swap details to order items
 *
 * @param mixed  $item Item meta data.
 * @param string $cart_item_key Cart key.
 * @param array  $values Item values.
 * @param mixed  $order Order item.
 *
 * @return void
 */
function wcis_add_iphone_swap_details_to_order_items( $item, $cart_item_key, $values, $order ) { // phpcs:ignore
	if ( ! empty( $values['current_iphone'] ) ) {
		$item->add_meta_data( __( 'Old iPhone', 'wc-iphone-swapper' ), $values['current_iphone'], true );
	}
	if ( ! empty( $values['desired_iphone'] ) ) {
		$item->add_meta_data( __( 'New iPhone', 'wc-iphone-swapper' ), $values['desired_iphone'], true );
	}
}
