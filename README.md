# Check Delivery Zone - WooCommerce Plugin

A powerful WordPress plugin for WooCommerce that allows customers to check delivery dates by entering their Indian pincode on product pages. The plugin automatically detects shipping zones and displays expected delivery dates based on your WooCommerce shipping settings.

## 📋 Description

**Check Delivery Zone** is designed specifically for Indian pincodes (6-digit postal codes) and integrates seamlessly with WooCommerce shipping zones. Customers can easily check delivery availability and expected delivery dates directly on product pages without adding items to cart.

### Key Features

- ✅ **Easy Pincode Checking**: Customers can enter pincodes to check delivery availability
- ✅ **Automatic Zone Detection**: Uses existing WooCommerce shipping zones and postcodes
- ✅ **Expected Delivery Date**: Calculates and displays expected delivery dates (3 days for fast, 7 for free, 5 default)
- ✅ **City Lookup**: Automatically fetches and displays city names using Indian pincode API
- ✅ **CSV Import**: Bulk import pincodes from CSV files
- ✅ **Manual Pincode Addition**: Add individual pincodes or ranges manually
- ✅ **Shortcode Support**: Use `[check_delivery_zone]` anywhere in your site
- ✅ **Page Builder Compatible**: Works with Elementor, Gutenberg, and other page builders
- ✅ **Guest & Logged-in Support**: Works for both logged-in and guest users via AJAX

## 📦 Installation

1. Download the plugin files
2. Upload the `check-delivery-zone` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **WooCommerce → Pincode Import** to start importing pincodes

## 🚀 Quick Start

### Step 1: Set Up Shipping Zones

1. Go to **WooCommerce → Settings → Shipping**
2. Create shipping zones (e.g., DELHI, MUMBAI, BANGALORE)
3. Add shipping methods to each zone (e.g., "Fast Delivery (3-4 days)", "Free Shipping (6-7 days)")

### Step 2: Import Pincodes

1. Go to **WooCommerce → Pincode Import**
2. Prepare a CSV file with pincodes (one per line, no header needed)
3. Select your shipping zone from the dropdown
4. Upload and import your CSV file

**CSV Format Example:**
```
110001
110002
110003
400001
400002
```

### Step 3: Add to Product Pages

The pincode checker automatically appears below the "Add to Cart" button on all product pages. You can also use the shortcode:

```
[check_delivery_zone]
```

### Customize Shortcode

```
[check_delivery_zone title="Check Delivery Date" placeholder="Enter your pincode" button_text="Check Now"]
```

## 📖 Usage

### For Customers

1. Visit any product page
2. Enter your 6-digit Indian pincode (e.g., 110001)
3. Click "Check" button
4. View delivery availability, zone, city name, and expected delivery date

### For Administrators

#### Import Pincodes via CSV

1. **Prepare CSV File**: Create a simple CSV with one pincode per line
2. **Go to Admin**: WooCommerce → Pincode Import
3. **Select Zone**: Choose the shipping zone from dropdown
4. **Upload & Import**: Select your CSV file and click "Import CSV"

#### Add Pincode Manually

1. Go to **WooCommerce → Pincode Import**
2. Enter a single pincode (e.g., `110001`) or range (e.g., `110001...110099`)
3. Select the shipping zone
4. Click "Add Pincode"

#### Pincode Range Format

- Single pincode: `110001`
- Range: `110001...110099` (three dots between start and end)

## 🎨 Customization

### Shortcode Options

| Parameter | Default | Description |
|-----------|---------|-------------|
| `title` | "Check Delivery Date by Pincode" | Custom title for the checker |
| `placeholder` | "Enter pincode (e.g. 110001)" | Input field placeholder |
| `button_text` | "Check" | Button label |

### Example

```
[check_delivery_zone title="Verify Delivery" placeholder="Enter 6-digit pincode" button_text="Verify Now"]
```

## 📊 Delivery Date Calculation

The plugin automatically calculates expected delivery dates based on shipping method titles:

- **Fast/Express methods**: 3 days
- **Free Shipping methods**: 7 days
- **Default**: 5 days

If multiple methods are available, it uses the fastest option.

## 🌍 India-Specific Plugin

**Important**: This plugin is designed specifically for Indian pincodes (6-digit postal codes). If you are from another country and need support or customization, please contact us via email for assistance.

## 📧 Support

For support, feature requests, or customizations:

- **Email**: [deepakjerry123@gmail.com](mailto:deepakjerry123@gmail.com)
- **Website**: [https://deepakjerry.com](https://deepakjerry.com)

## 🔗 Connect with Developer

Follow and connect with the developer:

- 📝 **Medium**: [@deepakjerry](https://medium.com/@deepakjerry)
- 💬 **WordPress Profile**: [deepakjerry](https://wordpress.org/support/users/deepakjerry/)
- 📸 **Instagram**: [@deepakjerry786](https://www.instagram.com/deepakjerry786/)
- 👥 **Facebook**: [deepakjerry78](https://www.facebook.com/deepakjerry78/)
- 💼 **LinkedIn**: [Deepak Kumar](https://www.linkedin.com/m/in/deepak-kumar-1aaa06146/)

## 🛠️ Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher (tested with WooCommerce 10.3.3+)
- PHP 7.2 or higher

## 📝 Changelog

### Version 1.3.0
- Added CSV import functionality
- Added manual pincode addition
- Added shortcode support for page builders
- Improved admin page design
- Added city lookup via Indian pincode API
- Fixed import issues for large CSV files
- Enhanced error handling

### Version 1.2.0
- Initial release with basic pincode checking

## ⚙️ Technical Details

- **AJAX Powered**: Uses WordPress `admin-ajax.php` for seamless checking
- **Nonce Security**: All AJAX requests are secured with nonces
- **Zone Matching**: Uses WooCommerce's native zone matching API
- **Caching**: City lookups are cached for 24 hours
- **Performance**: Efficient grouping of pincodes into ranges for bulk imports

## 📄 License

This plugin is licensed under the GPL-2.0+ license.

## 🙏 Credits

Developed with ❤️ by **Deepak jerry (Deepak Jerry)**

---

**Note**: This plugin is specifically designed for Indian e-commerce stores using Indian pincodes. For international support or customization, please contact the developer.

