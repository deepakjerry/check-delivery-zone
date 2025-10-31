=== Check Delivery Zone ===
Contributors: deepakjerry
Donate link: https://deepakjerry.com
Tags: woocommerce, delivery, pincode, shipping, india, postcode, delivery-date, shipping-zones
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Check delivery availability and expected delivery dates for Indian pincodes using WooCommerce shipping zones.

== Description ==

**Check Delivery Zone** is a powerful WordPress plugin designed specifically for Indian e-commerce stores using WooCommerce. It allows customers to check delivery availability and expected delivery dates by entering their 6-digit Indian pincode directly on product pages.

= Key Features =

* **Easy Pincode Checking**: Simple pincode input on product pages
* **Automatic Zone Detection**: Uses your existing WooCommerce shipping zones
* **Expected Delivery Date**: Shows delivery dates based on shipping methods (3 days for fast, 7 for free)
* **City & Location Lookup**: Automatically fetches and displays exact location (village, city, state)
* **CSV Bulk Import**: Import thousands of pincodes at once
* **Manual Pincode Addition**: Add individual pincodes or ranges manually
* **Shortcode Support**: Use `[check_delivery_zone]` anywhere on your site
* **Page Builder Compatible**: Works with Elementor, Gutenberg, Beaver Builder, etc.
* **Guest & Logged-in Support**: Works for both users via AJAX

= How It Works =

1. Customer enters their 6-digit Indian pincode on product page
2. Plugin detects matching WooCommerce shipping zone
3. Shows exact location (village, city, district, state)
4. Displays expected delivery date based on shipping methods
5. Lists available shipping methods for that zone

= Perfect For =

* Indian e-commerce stores
* WooCommerce websites
* Stores with multiple shipping zones
* Businesses that want to show delivery dates upfront

= Installation =

1. Upload the `check-delivery-zone` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **WooCommerce → Pincode Import** to import pincodes
4. Set up your shipping zones in **WooCommerce → Settings → Shipping**
5. The pincode checker automatically appears on product pages

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 4.0 or higher (tested with 10.3.3+)
* PHP 7.2 or higher

= CSV Import Format =

Simple one-column CSV format:
* One pincode per line
* No header needed
* Example: `110001`, `400001`, `560001`

Or with zone assignment:
* Two columns: Pincode, Zone Name
* Example: `110001,DELHI`

= Shortcode Usage =

Basic:
`[check_delivery_zone]`

With options:
`[check_delivery_zone title="Check Delivery" placeholder="Enter pincode" button_text="Check Now"]`

= Support =

For support, feature requests, or customizations, please email: deepakjerry123@gmail.com

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Check Delivery Zone"
4. Click "Install Now"
5. Click "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/`
3. Extract the files
4. Activate through WordPress Plugins menu

= Setup Instructions =

1. Go to **WooCommerce → Settings → Shipping**
2. Create shipping zones (e.g., DELHI, MUMBAI, BANGALORE)
3. Add shipping methods to each zone
4. Go to **WooCommerce → Pincode Import**
5. Import your pincodes via CSV or add manually
6. Done! Pincode checker appears automatically on product pages

== Frequently Asked Questions ==

= Do I need to set up shipping zones? =

Yes, this plugin uses your existing WooCommerce shipping zones. You need to create zones in WooCommerce → Settings → Shipping.

= Can I use this outside India? =

This plugin is designed specifically for Indian 6-digit pincodes. For other countries, contact support for customization.

= How does it calculate delivery dates? =

* Fast/Express methods: 3 days
* Free Shipping methods: 7 days
* Default: 5 days

Uses the fastest method if multiple methods are available.

= Can I import pincodes from CSV? =

Yes! Go to WooCommerce → Pincode Import and upload your CSV file. Format: one pincode per line.

= Does it work with page builders? =

Yes, use the shortcode `[check_delivery_zone]` in any page builder that supports shortcodes.

= Is it free? =

Yes, this plugin is completely free and open source (GPL-2.0+).

== Screenshots ==

1. Pincode checker on product page
2. CSV import interface
3. Manual pincode addition
4. Location details display
5. Admin settings page

== Changelog ==

= 1.3.0 =
* Added CSV bulk import functionality
* Added manual pincode addition
* Added shortcode support for page builders
* Enhanced location lookup with village, city, state details
* Improved admin page design
* Fixed import issues for large CSV files
* Added Indian pincode structure breakdown
* Enhanced error handling

= 1.2.0 =
* Initial release with basic pincode checking

== Upgrade Notice ==

= 1.3.0 =
Major update with CSV import, shortcode support, and enhanced location details. Upgrade for new features.

= 1.2.0 =
Initial release. Install to start checking delivery zones by pincode.

== Support & Contact ==

* Email: deepakjerry123@gmail.com
* Website: https://deepakjerry.com
* WordPress Profile: https://wordpress.org/support/users/deepakjerry/

== Developer ==

Developed by Deepak Kumar (Deepak Jerry)

== Credits ==

Uses Indian Pincode API (api.postalpincode.in) for location lookup.

