<?php
/**
 * Plugin Name: Meta Checkout URL for WooCommerce
 * Plugin URI: https://github.com/vinayrshankar/meta-checkout-url-for-woocommerce
 * Description: Builds a WooCommerce cart from Meta Shop checkout URL parameters and sends the buyer to checkout.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Vinay R Shankar
 * Author URI: https://tfaworld.org/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: meta-checkout-url-for-woocommerce
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a Meta catalog product identifier to a WooCommerce product or variation ID.
 *
 * Resolution order:
 * 1. Numeric WooCommerce product/variation ID.
 * 2. Exact WooCommerce SKU.
 * 3. A conservative trailing numeric ID, e.g. "woocommerce_123".
 * 4. Developer filter for custom catalog mappings.
 *
 * @param string $catalog_id Meta catalog product identifier.
 * @return int WooCommerce product/variation ID, or 0 when unresolved.
 */
function mcuwc_resolve_product_id( $catalog_id ) {
	$catalog_id = trim( (string) $catalog_id );
	$product_id = 0;

	if ( '' === $catalog_id ) {
		return 0;
	}

	if ( ctype_digit( $catalog_id ) ) {
		$candidate = absint( $catalog_id );
		if ( wc_get_product( $candidate ) ) {
			$product_id = $candidate;
		}
	}

	if ( ! $product_id ) {
		$candidate = wc_get_product_id_by_sku( $catalog_id );
		if ( $candidate ) {
			$product_id = absint( $candidate );
		}
	}

	if ( ! $product_id && preg_match( '/(?:^|_)(\d+)$/', $catalog_id, $matches ) ) {
		$candidate = absint( $matches[1] );
		if ( wc_get_product( $candidate ) ) {
			$product_id = $candidate;
		}
	}

	/**
	 * Filter the WooCommerce ID resolved from a Meta catalog ID.
	 *
	 * This is useful when a catalog feed uses custom retailer/content IDs that do
	 * not equal WooCommerce product IDs or SKUs.
	 *
	 * @param int    $product_id Resolved WooCommerce product/variation ID, or 0.
	 * @param string $catalog_id Original Meta catalog identifier.
	 */
	$product_id = absint(
		apply_filters(
			'mcuwc_resolved_product_id',
			$product_id,
			$catalog_id
		)
	);

	return $product_id && wc_get_product( $product_id ) ? $product_id : 0;
}

/**
 * Return a sanitized subset of attribution parameters that may accompany Meta traffic.
 *
 * @return array<string,string>
 */
function mcuwc_get_tracking_parameters() {
	$allowed_keys = array(
		'fbclid',
		'cart_origin',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_content',
		'utm_term',
	);
	$tracking     = array();

	foreach ( $allowed_keys as $key ) {
		if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) {
			$tracking[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
	}

	return $tracking;
}

/**
 * Build the WooCommerce cart from Meta's checkout URL parameters.
 */
function mcuwc_handle_checkout_request() {
	if (
		is_admin() ||
		wp_doing_ajax() ||
		! function_exists( 'WC' ) ||
		! function_exists( 'wc_get_checkout_url' ) ||
		! function_exists( 'is_checkout' ) ||
		! is_checkout() ||
		is_wc_endpoint_url( 'order-pay' ) ||
		is_wc_endpoint_url( 'order-received' ) ||
		! isset( $_GET['products'] ) ||
		! is_scalar( $_GET['products'] )
	) {
		return;
	}

	$products_raw = sanitize_text_field( wp_unslash( $_GET['products'] ) );
	$entries      = array_filter( array_map( 'trim', explode( ',', $products_raw ) ) );
	$items        = array();

	// Prevent excessively large checkout URLs from consuming unnecessary resources.
	$entries = array_slice( $entries, 0, 50 );

	foreach ( $entries as $entry ) {
		$parts = explode( ':', $entry, 2 );

		if ( 2 !== count( $parts ) ) {
			continue;
		}

		$catalog_id = sanitize_text_field( trim( $parts[0] ) );
		$quantity   = absint( trim( $parts[1] ) );

		if ( '' === $catalog_id || $quantity < 1 ) {
			continue;
		}

		$product_id = mcuwc_resolve_product_id( $catalog_id );
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product || ! $product->exists() ) {
			continue;
		}

		$items[] = array(
			'product_id' => $product_id,
			'quantity'   => min( $quantity, 100 ),
		);
	}

	if ( ! WC()->cart ) {
		wc_load_cart();
	}

	if ( empty( $items ) ) {
		wc_add_notice(
			__( 'We could not match the selected shop products to this store. Please return to the shop and try again.', 'meta-checkout-url-for-woocommerce' ),
			'error'
		);

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	// A Meta Shop cart should replace older local WooCommerce cart contents.
	WC()->cart->empty_cart();

	$added_count = 0;

	foreach ( $items as $item ) {
		try {
			$cart_item_key = WC()->cart->add_to_cart(
				$item['product_id'],
				$item['quantity']
			);

			if ( $cart_item_key ) {
				$added_count++;
			}
		} catch ( Exception $exception ) {
			wc_add_notice( $exception->getMessage(), 'error' );
		}
	}

	if ( isset( $_GET['coupon'] ) && is_scalar( $_GET['coupon'] ) ) {
		$coupon = wc_format_coupon_code(
			sanitize_text_field( wp_unslash( $_GET['coupon'] ) )
		);

		if ( '' !== $coupon && ! WC()->cart->has_discount( $coupon ) ) {
			WC()->cart->apply_coupon( $coupon );
		}
	}

	WC()->cart->calculate_totals();

	if ( 0 === $added_count ) {
		wc_add_notice(
			__( 'The selected products could not be added to your cart. They may be unavailable or require product options.', 'meta-checkout-url-for-woocommerce' ),
			'error'
		);
	}

	$redirect_url = wc_get_checkout_url();
	$tracking     = mcuwc_get_tracking_parameters();

	if ( ! empty( $tracking ) ) {
		$redirect_url = add_query_arg( $tracking, $redirect_url );
	}

	// products/coupon are intentionally omitted to prevent a redirect loop.
	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'template_redirect', 'mcuwc_handle_checkout_request', 5 );
