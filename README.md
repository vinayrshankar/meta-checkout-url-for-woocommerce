# Meta Checkout URL for WooCommerce

A lightweight WordPress plugin that turns Meta Shop checkout URL parameters into a WooCommerce cart and then loads the normal WooCommerce checkout page.

This is useful when Facebook, Instagram, or Meta Shops sends a buyer back to your website with a URL containing product IDs, quantities, and an optional coupon code.

## What it does

Given a URL such as:

```text
https://example.com/checkout/?products=123:2,456:1&coupon=SUMMERSALE20
```

the plugin will:

1. Parse the `products` parameter.
2. Resolve each catalog identifier to a WooCommerce product or variation.
3. Clear the buyer's older local cart.
4. Add the selected products and quantities.
5. Apply the optional WooCommerce coupon.
6. Preserve supported Meta/UTM attribution parameters.
7. Redirect to the clean WooCommerce checkout URL.

## Supported parameters

| Parameter | Purpose |
| --- | --- |
| `products` | Required. Comma-separated `PRODUCT_ID:QUANTITY` pairs. |
| `coupon` | Optional WooCommerce coupon code. |
| `fbclid` | Preserved for attribution. |
| `cart_origin` | Preserved when supplied by Meta. |
| `utm_source` | Preserved. |
| `utm_medium` | Preserved. |
| `utm_campaign` | Preserved. |
| `utm_content` | Preserved. |
| `utm_term` | Preserved. |

## Requirements

- WordPress 6.5 or newer
- WooCommerce installed and active
- PHP 7.4 or newer
- HTTPS strongly recommended for production stores

## Installation

### WordPress Admin

1. Download the plugin ZIP from this repository's releases or build a ZIP containing the plugin folder.
2. Go to **WordPress Admin > Plugins > Add New Plugin > Upload Plugin**.
3. Upload the ZIP.
4. Activate **Meta Checkout URL for WooCommerce**.
5. Use your normal WooCommerce checkout URL as the Meta website checkout endpoint, for example:

```text
https://example.com/checkout/
```

### Manual installation

Copy this repository to:

```text
wp-content/plugins/meta-checkout-url-for-woocommerce/
```

Then activate it in **WordPress Admin > Plugins**.

## Testing

### One product

```text
https://example.com/checkout/?products=123:2
```

### Multiple products

```text
https://example.com/checkout/?products=123:2,456:1
```

### Coupon

```text
https://example.com/checkout/?products=123:2,456:1&coupon=SUMMERSALE20
```

### URL-encoded form

```text
https://example.com/checkout/?products=123%3A2%2C456%3A1&coupon=SUMMERSALE20
```

## Product ID matching

The plugin attempts to resolve a catalog ID using:

1. A numeric WooCommerce product or variation ID.
2. An exact WooCommerce SKU.
3. A prefixed identifier ending in a WooCommerce numeric ID, such as `woocommerce_123`.
4. The `mcuwc_resolved_product_id` WordPress filter for custom mappings.

WooCommerce currently normalizes a variation ID supplied to `WC_Cart::add_to_cart()` to its parent product plus variation internally, so variation IDs can be supplied directly.

### Custom catalog ID mapping

If your Meta catalog uses custom IDs, add a mapping in a site-specific plugin or your custom code:

```php
add_filter(
    'mcuwc_resolved_product_id',
    function ( $product_id, $catalog_id ) {
        $map = array(
            'META-SKU-ABC' => 123,
            'META-SKU-XYZ' => 456,
        );

        return isset( $map[ $catalog_id ] ) ? $map[ $catalog_id ] : $product_id;
    },
    10,
    2
);
```

## Security and behavior

This endpoint intentionally changes the current WooCommerce cart when a buyer opens a valid Meta checkout URL. A WordPress nonce is not used because the request originates from an external commerce platform and must work for shoppers who do not already have a WordPress session.

The plugin still applies several defensive controls:

- WordPress sanitization is applied to incoming scalar values.
- Product IDs are resolved only to actual WooCommerce products/variations.
- WooCommerce's own `add_to_cart()` validation remains in the path.
- Requests are limited to 50 product entries.
- Individual quantities are capped at 100 per URL entry.
- Redirects use `wp_safe_redirect()`.
- Only a defined allow-list of attribution parameters is forwarded.
- The raw `products` and `coupon` parameters are removed after processing to avoid redirect loops.

For production use, keep WordPress, WooCommerce, PHP, your theme, and all extensions updated.

## Cart replacement behavior

By default, a Meta Shop checkout request **replaces the shopper's existing WooCommerce cart**. This prevents products left over from an earlier website session from being mixed into the Meta Shop checkout.

## Troubleshooting

### Products do not appear

Confirm that the IDs sent by your Meta catalog match a WooCommerce product ID, variation ID, SKU, or one of your custom mappings.

### Variable product does not add

Use the specific variation ID or a SKU that resolves to the variation, rather than only the variable parent product when the product requires options.

### Coupon does not apply

Confirm that the coupon exists in WooCommerce, is not expired, and satisfies its usage restrictions.

### Checkout keeps redirecting

Make sure no cache/CDN rule is caching URLs containing `products` or `coupon`. Checkout and cart URLs should normally be excluded from full-page caching.

## Development

Run PHP syntax checking locally:

```bash
php -l meta-checkout-url-for-woocommerce.php
```

A GitHub Actions workflow is included to lint the plugin on supported PHP versions.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

**Vinay R Shankar**  
https://tfaworld.org/

## Disclaimer

This is an independent open-source project. It is not endorsed by or affiliated with Meta Platforms, Inc. or Automattic/WooCommerce.
