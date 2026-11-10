ARK Woo Product Promo Slider
Version 1.1.1

INSTALL
1. WordPress Admin > Plugins > Add New Plugin > Upload Plugin.
2. Upload ark-woo-product-promo-slider.zip and click Install Now, then Activate.
3. WooCommerce must be active.

CREATE A CAMPAIGN
1. Open Product Promo Slider in WordPress admin.
2. Add Promo Campaign and give it an internal name.
3. Tick Enable this campaign.
4. To show ads only on selected products, leave Target product pages empty: the promoted products become the exact page allow-list. Or select explicit Target individual product pages. Category-wide display requires enabling the separate category-wide checkbox.
5. Search and add the products to show in the slider.
6. Set heading, card limit, auto-slide interval and optional start/end time.
7. Publish the campaign.

SAFETY / BEHAVIOUR
- Only users with the appropriate WordPress capabilities can edit campaigns or use the product search endpoint.
- Admin AJAX search uses a nonce, capability checks, sanitized search text, and returns only published WooCommerce products.
- Campaign data is sanitized and output is escaped.
- The slider only runs on single WooCommerce product pages.
- It excludes the current product and skips unpublished, invisible or non-purchasable products.
- If no target category/product is selected, the campaign is not shown anywhere.
- Explicit target products always take precedence. Category-wide matching is disabled unless the campaign owner opts in. Without explicit target pages or category-wide mode, only the promoted products are allowed as page targets.
- Campaign dates use the WordPress site timezone.
- No external scripts, tracking, or remote calls are used.
- Deactivating the plugin stops rendering; deleting the plugin does not automatically delete saved campaign posts/meta.

NOTES
- For category-wise promos, select the category and explicitly enable the category-wide checkbox. For ads limited to two product pages, leave category-wide mode off and promote those two products.
- For a specific product page, add that product in Target individual product pages.
- Slider is inserted immediately before the single product content using WooCommerce's `woocommerce_before_single_product_summary` hook. Themes that replace that hook may need a small placement adjustment.
