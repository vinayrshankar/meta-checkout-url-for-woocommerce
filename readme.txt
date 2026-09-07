=== Meta Checkout URL for WooCommerce ===
Contributors: vinayrshankar
Tags: woocommerce, meta, facebook, instagram, checkout, ecommerce
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a WooCommerce cart from Meta Shop checkout URL parameters and redirect buyers to your normal checkout.

== Description ==

Meta Checkout URL for WooCommerce converts incoming Meta Shop product, quantity, and coupon URL parameters into a WooCommerce cart.

Features:

* Supports WooCommerce numeric product IDs and variation IDs.
* Supports exact WooCommerce SKUs.
* Supports common prefixed IDs ending in a numeric WooCommerce ID.
* Provides a filter for custom catalog-ID mappings.
* Applies an optional WooCommerce coupon.
* Replaces stale local cart contents for Meta checkout requests.
* Preserves supported Meta and UTM attribution parameters.
* Redirects to the clean WooCommerce checkout URL after processing.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress Admin.
2. Activate the plugin.
3. Configure Meta to use your normal WooCommerce checkout URL as the website checkout endpoint.
4. Test product IDs, quantities, coupons, and variable products before enabling the flow publicly.

== Frequently Asked Questions ==

= What URL should I give Meta? =

Use your normal WooCommerce checkout URL, such as `https://example.com/checkout/`.

= Will the plugin clear an existing cart? =

Yes. A Meta Shop checkout request replaces existing local cart contents so unrelated products are not mixed into the Meta checkout.

= Can I use custom catalog IDs? =

Yes. Use the `mcuwc_resolved_product_id` filter to map a custom catalog ID to a WooCommerce product or variation ID.

== Changelog ==

= 1.0.0 =
* Initial public release.
