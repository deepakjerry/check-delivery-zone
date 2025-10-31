<?php
/*
Plugin Name: Check Delivery Zone
Description: Check expected delivery date using WooCommerce shipping zones and postcodes.
Version: 1.3.0
Author: Deepak Jerry
Author URI: https://deepakjerry.com
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: check delivery zone
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 7.2
WC requires at least: 4.0
WC tested up to: 10.3


*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add action links to plugin page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cdz_add_action_links');

function cdz_add_action_links($links) {
	$settings_link = '<a href="' . admin_url('admin.php?page=check-delivery-zone-import') . '">Settings</a>';
	$support_link = '<a href="mailto:deepakjerry123@gmail.com" style="color: #ea4335; font-weight: 600;">Contact Support</a>';
	array_unshift($links, $settings_link, $support_link);
	return $links;
}

/**
 * Add row meta links
 */
add_filter('plugin_row_meta', 'cdz_add_row_meta', 10, 2);

function cdz_add_row_meta($links, $file) {
	if (plugin_basename(__FILE__) !== $file) {
		return $links;
	}
	
	$row_meta = [
		'support' => '<a href="mailto:deepakjerry123@gmail.com" style="color: #ea4335;">Contact Support</a>',
		'docs' => '<a href="' . admin_url('admin.php?page=check-delivery-zone-import') . '">Documentation</a>',
	];
	
	return array_merge($links, $row_meta);
}

/**
 * Enqueue script globally (for shortcode support)
 */
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'check-delivery-zone',
        plugin_dir_url(__FILE__) . 'check-delivery-zone.js',
        ['jquery'],
        '1.3.0',
        true
    );
    wp_localize_script('check-delivery-zone', 'cdz_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('cdz_nonce')
    ]);
});

/**
 * Shortcode for pincode checker
 * Usage: [check_delivery_zone] or [check_delivery_zone title="Check Delivery"]
 */
add_shortcode('check_delivery_zone', 'cdz_delivery_checker_shortcode');

function cdz_delivery_checker_shortcode($atts) {
    $atts = shortcode_atts([
        'title' => 'Check Delivery Date by Pincode',
        'placeholder' => 'Enter pincode (e.g. 110001)',
        'button_text' => 'Check'
    ], $atts);
    
    // Generate unique IDs for multiple instances
    static $instance_count = 0;
    $instance_count++;
    $unique_id = $instance_count > 1 ? '_' . $instance_count : '';
    
    ob_start();
    ?>
    <div class="check-delivery-box" style="margin-top:16px;">
        <label style="display:block;margin-bottom:6px;"><strong><?php echo esc_html($atts['title']); ?></strong></label>
        <input type="text" id="cdz_pincode<?php echo esc_attr($unique_id); ?>" placeholder="<?php echo esc_attr($atts['placeholder']); ?>" style="padding:8px;width:160px;">
        <button id="cdz_check_btn<?php echo esc_attr($unique_id); ?>" class="button" style="margin-left:6px;"><?php echo esc_html($atts['button_text']); ?></button>
        <div id="cdz_result<?php echo esc_attr($unique_id); ?>" style="margin-top:10px;color:#333;"></div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Add pincode field on product detail page (uses shortcode)
 */
add_action('woocommerce_after_add_to_cart_form', function() {
    echo do_shortcode('[check_delivery_zone]');
}, 10);

/**
 * AJAX handler for delivery check
 */
add_action('wp_ajax_check_delivery_zone', 'cdz_check_delivery_zone');
add_action('wp_ajax_nopriv_check_delivery_zone', 'cdz_check_delivery_zone');

function cdz_check_delivery_zone() {
    check_ajax_referer('cdz_nonce', 'security');

    $postcode = sanitize_text_field($_POST['pincode'] ?? '');
    if (empty($postcode)) {
        wp_send_json_error('Please enter a valid pincode.');
    }

    if ( ! function_exists('wc_get_shipping_zone') && ! class_exists('WC_Shipping_Zones') ) {
        wp_send_json_error('WooCommerce shipping is unavailable.');
    }

    // Build a proper shipping package and use wc_get_shipping_zone for accurate matching
	$postcode_clean = strtoupper( preg_replace('/[^0-9A-Z-]/', '', $postcode) );

	// Resolve detailed location from Indian pincode (best-effort)
	$location_details = cdz_get_pincode_location_details($postcode_clean);
    $package = [
        'destination' => [
            'country'   => 'IN',
            'state'     => '',
            'postcode'  => $postcode_clean,
            'city'      => '',
            'address'   => '',
            'address_2' => '',
        ],
    ];

    $zone = function_exists('wc_get_shipping_zone') ? wc_get_shipping_zone($package) : null;
    if ( ! $zone && class_exists('WC_Shipping_Zones') ) {
        // Fallback direct matching by location
        $zone = WC_Shipping_Zones::get_zone_matching_location('IN', '', $postcode_clean, '');
    }
    if (!$zone || !is_a($zone, 'WC_Shipping_Zone')) {
        wp_send_json_error('No delivery available for this pincode.');
    }

	$zone_name = $zone->get_zone_name();
    $shipping_methods = $zone->get_shipping_methods(true);

    if (empty($shipping_methods)) {
        wp_send_json_error("No active shipping methods found for $zone_name.");
    }

    $methods_info = [];
    $expected_days = 5; // default

    foreach ($shipping_methods as $method) {
        if (! $method->is_enabled()) {
            continue;
        }
        $title = method_exists($method, 'get_title') ? (string) $method->get_title() : '';
        $desc  = method_exists($method, 'get_method_description') ? (string) $method->get_method_description() : '';
        $methods_info[] = [
            'title' => $title,
            'description' => $desc,
        ];

        // Determine expected days by method title keywords
        if (preg_match('/fast|express/i', $title)) {
            $expected_days = min($expected_days, 3);
        } elseif (preg_match('/free\s*shipping/i', $title)) {
            $expected_days = min($expected_days, 7);
        }
    }

    // Compute expected date string using site timezone and format
    $timestamp_now = current_time('timestamp');
    $expected_ts   = strtotime("+{$expected_days} days", $timestamp_now);
    $expected_date = date_i18n( get_option('date_format', 'M j, Y'), $expected_ts );

	wp_send_json_success([
        'zone' => $zone_name,
        'methods' => $methods_info,
        'expected_days' => $expected_days,
        'expected_date' => $expected_date,
		'location' => $location_details,
    ]);
}

/**
 * Decode PIN code structure and get detailed location information
 */
function cdz_decode_pincode_structure($pincode) {
	$pincode_numeric = preg_replace('/[^0-9]/', '', (string) $pincode);
	if (strlen($pincode_numeric) !== 6) {
		return null;
	}
	
	$structure = [
		'pincode' => $pincode_numeric,
		'first_digit' => substr($pincode_numeric, 0, 1),
		'first_two' => substr($pincode_numeric, 0, 2),
		'first_three' => substr($pincode_numeric, 0, 3),
		'last_three' => substr($pincode_numeric, 3, 3),
	];
	
	// Map first digit to zone
	$zones = [
		'1' => 'Northern',
		'2' => 'Northern',
		'3' => 'Western',
		'4' => 'Western',
		'5' => 'Southern',
		'6' => 'Southern',
		'7' => 'Eastern',
		'8' => 'Eastern',
		'9' => 'Army Postal Service',
	];
	
	$structure['zone'] = isset($zones[$structure['first_digit']]) ? $zones[$structure['first_digit']] : 'Unknown';
	
	// Map first two digits to common states/regions (simplified)
	$sub_zones = [
		'11' => 'Delhi',
		'12' => 'Haryana',
		'13' => 'Haryana/Punjab',
		'20' => 'Uttar Pradesh',
		'30' => 'Rajasthan',
		'36' => 'Gujarat',
		'40' => 'Maharashtra',
		'50' => 'Telangana/Andhra Pradesh',
		'56' => 'Karnataka',
		'60' => 'Tamil Nadu',
		'70' => 'West Bengal',
		'80' => 'Bihar',
	];
	
	$structure['sub_zone'] = isset($sub_zones[$structure['first_two']]) ? $sub_zones[$structure['first_two']] : 'Region ' . $structure['first_two'];
	$structure['district_code'] = $structure['first_three'];
	$structure['post_office_code'] = $structure['last_three'];
	
	return $structure;
}

/**
 * Get detailed location information for Indian pincode using API
 */
function cdz_get_pincode_location_details($pincode) {
	$pincode_numeric = preg_replace('/[^0-9]/', '', (string) $pincode);
	if (strlen($pincode_numeric) < 6) {
		return [
			'city' => '',
			'district' => '',
			'state' => '',
			'post_office' => '',
			'village' => '',
			'block' => '',
			'division' => '',
			'region' => '',
			'circle' => '',
			'full_address' => '',
			'pin_structure' => null,
		];
	}
	
	// Get PIN structure
	$pin_structure = cdz_decode_pincode_structure($pincode_numeric);
	
	// Cache key
	$cache_key = 'cdz_location_' . $pincode_numeric;
	$cached = get_transient($cache_key);
	if ($cached !== false && is_array($cached)) {
		$cached['pin_structure'] = $pin_structure;
		return $cached;
	}
	
	// Fetch from API
	$url = 'https://api.postalpincode.in/pincode/' . rawurlencode($pincode_numeric);
	$response = wp_remote_get($url, [ 'timeout' => 6 ]);
	
	$result = [
		'city' => '',
		'district' => '',
		'state' => '',
		'post_office' => '',
		'village' => '',
		'block' => '',
		'division' => '',
		'region' => '',
		'circle' => '',
		'full_address' => '',
		'pin_structure' => $pin_structure,
	];
	
	if (is_wp_error($response)) {
		return $result;
	}
	
	$code = wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);
	if ($code !== 200 || empty($body)) {
		return $result;
	}
	
	$data = json_decode($body, true);
	if (!is_array($data) || empty($data[0]) || !is_array($data[0])) {
		return $result;
	}
	
	$entry = $data[0];
	if (!isset($entry['Status']) || strcasecmp($entry['Status'], 'Success') !== 0) {
		return $result;
	}
	
	if (empty($entry['PostOffice']) || !is_array($entry['PostOffice'])) {
		return $result;
	}
	
	// Get first post office (most relevant)
	$po = $entry['PostOffice'][0];
	
	// Extract all available location fields
	$district = isset($po['District']) ? trim((string) $po['District']) : '';
	$state = isset($po['State']) ? trim((string) $po['State']) : '';
	$post_office = isset($po['Name']) ? trim((string) $po['Name']) : '';
	$block = isset($po['Block']) ? trim((string) $po['Block']) : '';
	$division = isset($po['Division']) ? trim((string) $po['Division']) : '';
	$region = isset($po['Region']) ? trim((string) $po['Region']) : '';
	$circle = isset($po['Circle']) ? trim((string) $po['Circle']) : '';
	
	// City is same as district
	$city = $district;
	
	// Build full address components
	$address_parts = [];
	if ($post_office) $address_parts[] = $post_office;
	if ($block && $block !== $post_office) $address_parts[] = $block;
	if ($district) $address_parts[] = $district;
	if ($division && $division !== $district) $address_parts[] = $division;
	if ($state) $address_parts[] = $state;
	
	$result = [
		'city' => $city,
		'district' => $district,
		'state' => $state,
		'post_office' => $post_office,
		'village' => $post_office, // Alias for village/area
		'block' => $block,
		'division' => $division,
		'region' => $region,
		'circle' => $circle,
		'full_address' => implode(', ', $address_parts),
		'pin_structure' => $pin_structure,
	];
	
	// Cache for 24 hours
	set_transient($cache_key, $result, DAY_IN_SECONDS);
	
	return $result;
}

/**
 * Add admin menu under WooCommerce
 */
add_action('admin_menu', 'cdz_add_admin_menu', 99);

function cdz_add_admin_menu() {
	if (!class_exists('WooCommerce')) {
		return;
	}
	
	add_submenu_page(
		'woocommerce',
		'Pincode Import',
		'Pincode Import',
		'manage_woocommerce',
		'check-delivery-zone-import',
		'cdz_admin_page_content'
	);
}

/**
 * Admin page content for CSV import
 */
function cdz_admin_page_content() {
	if (!current_user_can('manage_woocommerce')) {
		wp_die('You do not have permission to access this page.');
	}
	
	// Enqueue Font Awesome
	wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
	
	// Handle CSV upload
	$message = '';
	$message_type = 'info';
	
	// Get all zones for dropdown
	$all_zones = [];
	if (class_exists('WC_Shipping_Zones')) {
		$zones = WC_Shipping_Zones::get_zones();
		foreach ($zones as $zone_data) {
			$zone_obj = WC_Shipping_Zones::get_zone($zone_data['zone_id']);
			if ($zone_obj) {
				$all_zones[$zone_obj->get_id()] = $zone_obj->get_zone_name();
			}
		}
		// Add Rest of the World zone
		$rest_zone = WC_Shipping_Zones::get_zone(0);
		if ($rest_zone) {
			$all_zones[0] = $rest_zone->get_zone_name() . ' (Rest of the World)';
		}
	}
	
	// Handle manual pincode addition
	if (isset($_POST['cdz_add_manual']) && !empty($_POST['manual_pincode']) && !empty($_POST['manual_zone'])) {
		check_admin_referer('cdz_import_nonce');
		
		$manual_pincode = sanitize_text_field($_POST['manual_pincode']);
		$manual_zone_name = sanitize_text_field($_POST['manual_zone']);
		
		// Validate pincode format (6 digits or range format)
		if (preg_match('/^[0-9]{6}$/', $manual_pincode) || preg_match('/^[0-9]{6}\.\.\.[0-9]{6}$/', $manual_pincode)) {
			$zone = cdz_find_or_create_zone($manual_zone_name);
			if ($zone) {
				$result = cdz_add_postcode_to_zone($zone, $manual_pincode);
				if ($result === true) {
					$message = sprintf('✅ Pincode <strong>%s</strong> successfully added to zone <strong>%s</strong>!', esc_html($manual_pincode), esc_html($manual_zone_name));
					$message_type = 'success';
				} elseif ($result === 'duplicate') {
					$message = sprintf('⚠️ Pincode <strong>%s</strong> already exists in zone <strong>%s</strong>.', esc_html($manual_pincode), esc_html($manual_zone_name));
					$message_type = 'warning';
				} else {
					$message = '❌ Failed to add pincode. Please try again.';
					$message_type = 'error';
				}
			} else {
				$message = sprintf('❌ Could not find or create zone <strong>%s</strong>.', esc_html($manual_zone_name));
				$message_type = 'error';
			}
		} else {
			$message = '❌ Invalid pincode format. Please enter a 6-digit pincode (e.g., 110001) or range (e.g., 110001...110099).';
			$message_type = 'error';
		}
	}
	
	// Handle CSV import
	if (isset($_POST['cdz_import_csv']) && isset($_FILES['csv_file']) && !empty($_FILES['csv_file']['tmp_name'])) {
		check_admin_referer('cdz_import_nonce');
		
		$default_zone_name = sanitize_text_field($_POST['default_zone'] ?? '');
		if (empty($default_zone_name)) {
			$message = 'Please select a zone for the pincodes.';
			$message_type = 'error';
		} else {
			$file = $_FILES['csv_file'];
			if ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel' && !preg_match('/\.csv$/i', $file['name'])) {
				$message = 'Please upload a valid CSV file.';
				$message_type = 'error';
					} else {
						$handle = fopen($file['tmp_name'], 'r');
						if ($handle !== false) {
							$imported = 0;
							$skipped = 0;
							$errors = [];
							$line_num = 0;
							$total_lines = 0;
							
							// Find or create zone once
							$zone = cdz_find_or_create_zone($default_zone_name);
							if (!$zone) {
								$message = "Could not find or create zone '$default_zone_name'. Please check the zone name.";
								$message_type = 'error';
							} else {
								// Collect all pincodes first (for grouping into ranges)
								$pincodes = [];
								$is_first_line = true;
								
								while (($data = fgetcsv($handle, 1000, ',')) !== false) {
									$line_num++;
									$total_lines++;
									
									$pincode = trim($data[0] ?? '');
									
									// Skip empty rows
									if (empty($pincode)) {
										continue;
									}
									
									// Check if first row is header (contains non-numeric text like "pincode", "pin", etc.)
									if ($is_first_line) {
										$is_first_line = false;
										if (preg_match('/pincode|pin|postcode|zip|code/i', $pincode)) {
											// It's a header, skip it
											continue;
										}
									}
									
									// Support both single column (just pincode) and two columns (pincode, zone)
									if (count($data) >= 2 && !empty(trim($data[1] ?? ''))) {
										// Two column format: pincode, zone
										$zone_name = trim($data[1]);
										$temp_zone = cdz_find_or_create_zone($zone_name);
										if ($temp_zone) {
											if (preg_match('/^[0-9]{6}$/', $pincode)) {
												$result = cdz_add_postcode_to_zone($temp_zone, $pincode);
												if ($result === true) {
													$imported++;
												} elseif ($result !== 'duplicate') {
													$skipped++;
												}
											} else {
												$skipped++;
											}
										} else {
											$skipped++;
										}
									} else {
										// Single column format: just pincode
										// Accept both 6-digit pincodes and longer numbers (take first 6 digits)
										$pincode_clean = preg_replace('/[^0-9]/', '', $pincode);
										if (strlen($pincode_clean) >= 6) {
											$pincode_6 = substr($pincode_clean, 0, 6);
											if (preg_match('/^[0-9]{6}$/', $pincode_6)) {
												$pincodes[] = $pincode_6;
											}
										}
									}
								}
								
								// Process single-column pincodes (group into ranges for efficiency)
								if (!empty($pincodes)) {
									// Remove duplicates and sort
									$pincodes = array_unique($pincodes);
									sort($pincodes, SORT_NUMERIC);
									
									$batch = [];
									$prev = null;
									
									foreach ($pincodes as $pincode) {
										$pincode_int = intval($pincode);
										
										// Limit range size to 999 pincodes to avoid issues
										$max_range_size = 999;
										
										if ($prev === null || $pincode_int - $prev > 1 || count($batch) >= $max_range_size) {
											// Save previous batch
											if (!empty($batch)) {
												if (count($batch) == 1) {
													$result = cdz_add_postcode_to_zone($zone, $batch[0]);
												} else {
													$range_str = $batch[0] . '...' . end($batch);
													$result = cdz_add_postcode_to_zone($zone, $range_str);
												}
												if ($result === true) {
													$imported += count($batch);
												} elseif ($result === 'duplicate') {
													// Don't count duplicates as skipped
												} else {
													// If batch save fails, try saving individually
													foreach ($batch as $single_pincode) {
														$result_single = cdz_add_postcode_to_zone($zone, $single_pincode);
														if ($result_single === true) {
															$imported++;
														} elseif ($result_single !== 'duplicate') {
															$skipped++;
														}
													}
												}
											}
											$batch = [$pincode];
										} else {
											$batch[] = $pincode;
										}
										$prev = $pincode_int;
									}
									
									// Save last batch
									if (!empty($batch)) {
										if (count($batch) == 1) {
											$result = cdz_add_postcode_to_zone($zone, $batch[0]);
										} else {
											$range_str = $batch[0] . '...' . end($batch);
											$result = cdz_add_postcode_to_zone($zone, $range_str);
										}
										if ($result === true) {
											$imported += count($batch);
										} elseif ($result === 'duplicate') {
											// Don't count duplicates as skipped
										} else {
											// If batch save fails, try saving individually
											foreach ($batch as $single_pincode) {
												$result_single = cdz_add_postcode_to_zone($zone, $single_pincode);
												if ($result_single === true) {
													$imported++;
												} elseif ($result_single !== 'duplicate') {
													$skipped++;
												}
											}
										}
									}
								}
								
								fclose($handle);
								
								$message = sprintf('Import completed! <strong>%d pincodes imported</strong> to zone "%s" from %d total lines, %d skipped.', $imported, $zone->get_zone_name(), $total_lines, $skipped);
								$message_type = $imported > 0 ? 'success' : 'warning';
							}
						} else {
							$message = 'Unable to read CSV file.';
							$message_type = 'error';
						}
					}
		}
	}
	
	// Enqueue admin styles
	wp_enqueue_style(
		'check-delivery-zone-admin',
		plugin_dir_url(__FILE__) . 'style.css',
		[],
		'1.3.0'
	);
	?>
	<div class="wrap cdz-admin-page">
		<div class="cdz-header">
			<h1>📍 Pincode Import for Delivery Zones</h1>
			<p>Import and manage pincodes for your WooCommerce shipping zones easily</p>
		</div>
		
		<div class="cdz-country-notice">
			<p><strong>🇮🇳 India-Specific Plugin:</strong> This plugin is designed specifically for Indian pincodes (6-digit postal codes). If you are from another country and need support or customization, please <a href="mailto:deepakjerry123@gmail.com" style="color: #667eea; font-weight: 600;">contact us via email</a> for assistance.</p>
		</div>
		
		<?php if ($message): ?>
			<div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
				<p><?php echo wp_kses_post($message); ?></p>
			</div>
		<?php endif; ?>
		
		<div class="card cdz-card cdz-form-card" style="max-width: 800px;">
			<h2>Import Pincodes from CSV</h2>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field('cdz_import_nonce'); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="csv_file">Select CSV File</label>
						</th>
						<td>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required>
							<p class="description">
								Upload a CSV file with pincodes (one per line, no header needed).
								<br>
								<a href="https://github.com/deepakjerry/check-delivery-zone/raw/Master/pincode.csv" target="_blank" rel="noopener noreferrer" style="color: #667eea; text-decoration: none; font-weight: 600; margin-top: 8px; display: inline-block;">
									⬇️ Download Sample CSV from GitHub
								</a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="default_zone">Assign to Zone</label>
						</th>
						<td>
							<select name="default_zone" id="default_zone" required style="width: 300px;">
								<option value="">-- Select a Zone --</option>
								<?php foreach ($all_zones as $zone_id => $zone_name): ?>
									<option value="<?php echo esc_attr($zone_name); ?>"><?php echo esc_html($zone_name); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">All pincodes from the CSV will be added to this zone.</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="cdz_import_csv" class="button button-primary" value="Import CSV">
				</p>
			</form>
		</div>
		
		<div class="card cdz-card cdz-form-card" style="max-width: 800px; margin-top: 20px; border-left-color: #28a745;">
			<h2>➕ Add Pincode Manually</h2>
			<form method="post">
				<?php wp_nonce_field('cdz_import_nonce'); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="manual_pincode">Pincode</label>
						</th>
						<td>
							<input type="text" name="manual_pincode" id="manual_pincode" placeholder="e.g., 110001 or 110001...110099" style="width: 250px;" pattern="[0-9]{6}|[0-9]{6}\.{3}[0-9]{6}" required>
							<p class="description">Enter a single pincode (e.g., 110001) or a range (e.g., 110001...110099)</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="manual_zone">Assign to Zone</label>
						</th>
						<td>
							<select name="manual_zone" id="manual_zone" required style="width: 300px;">
								<option value="">-- Select a Zone --</option>
								<?php foreach ($all_zones as $zone_id => $zone_name): ?>
									<option value="<?php echo esc_attr($zone_name); ?>"><?php echo esc_html($zone_name); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Select the zone where this pincode will be added.</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="cdz_add_manual" class="button button-primary" value="Add Pincode">
				</p>
			</form>
		</div>
		
		<div class="card cdz-card cdz-info-card" style="max-width: 800px; margin-top: 20px; padding: 25px;">
			<h2>📖 How to Import CSV File</h2>
			<div style="line-height: 1.8;">
				<h3>CSV Format Requirements:</h3>
				<p>Your CSV file should have a simple format - just pincodes, one per line:</p>
				<ol>
					<li><strong>Simple Format (Recommended):</strong> One column with pincodes only</li>
					<li><strong>No Header Needed:</strong> Just list pincodes starting from the first line</li>
					<li><strong>Pincode Format:</strong> 6-digit Indian pincodes (e.g., 110001, 400001)</li>
				</ol>
				
				<h3>Example CSV Content (Simple Format):</h3>
				<pre style="background: #000; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
110001
110002
110003
110004
110005
400001
400002
400003</pre>
				
				<h3>Alternative Format (with Zone Column):</h3>
				<p>You can also use a two-column format if you want to assign different zones:</p>
				<pre style="background: #000; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
Pincode,Zone Name
110001,DELHI
110002,DELHI
400001,MUMBAI
400002,MUMBAI</pre>
				<p><strong>Note:</strong> The simple one-column format is recommended. Just select the zone from the dropdown above when importing.</p>
				
				<h3>Step-by-Step Instructions:</h3>
				<ol>
					<li><strong>Prepare your CSV file:</strong>
						<ul>
							<li>Open Excel, Google Sheets, or any text editor</li>
							<li>List pincodes, one per line (one column only)</li>
							<li>No header row needed - just start with the first pincode</li>
							<li>Save the file as CSV format (.csv)</li>
						</ul>
					</li>
					<li><strong>Upload and Import:</strong>
						<ul>
							<li>Go to WooCommerce → Pincode Import (this page)</li>
							<li>Select your CSV file using "Choose File"</li>
							<li><strong>Select a Zone</strong> from the dropdown (all pincodes will be added to this zone)</li>
							<li>Click "Import CSV" button</li>
							<li>The pincodes will be automatically grouped into ranges and added to the selected zone</li>
						</ul>
					</li>
					<li><strong>Verify Import:</strong>
						<ul>
							<li>Go to <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>">WooCommerce → Settings → Shipping</a></li>
							<li>Edit the zone you selected to verify the pincodes were added correctly</li>
							<li>You'll see pincodes grouped as ranges (e.g., 110001...110099) for efficiency</li>
						</ul>
					</li>
				</ol>
				
				<h3>Important Notes:</h3>
				<ul>
					<li><strong>Simple Format:</strong> Just pincodes in one column - no zone names needed in CSV</li>
					<li><strong>Zone Selection:</strong> Select the target zone from the dropdown before importing</li>
					<li><strong>Automatic Grouping:</strong> Consecutive pincodes are automatically grouped into ranges (e.g., 110001...110099) for better performance</li>
					<li><strong>City Names:</strong> City names are automatically fetched via API when customers check delivery - no need to include in CSV</li>
					<li>Make sure your CSV file uses comma (,) as the delimiter</li>
					<li>Indian pincodes must be 6 digits (e.g., 110001, 400001)</li>
					<li>If a pincode already exists in a zone, it will be skipped to avoid duplicates</li>
					<li><strong>Large Files:</strong> The import handles large CSV files efficiently by grouping pincodes</li>
				</ul>
				
				<h3>Shortcode Usage:</h3>
				<p>You can add the pincode checker anywhere using the shortcode:</p>
				<ul>
					<li><strong>Basic usage:</strong> <code>[check_delivery_zone]</code></li>
					<li><strong>With custom title:</strong> <code>[check_delivery_zone title="Check Your Delivery"]</code></li>
					<li><strong>With all options:</strong> <code>[check_delivery_zone title="Check Delivery" placeholder="Enter 6-digit pincode" button_text="Check Now"]</code></li>
				</ul>
				<p><strong>For Page Builders:</strong> Most page builders support shortcodes. Simply add a "Shortcode" widget/block and paste <code>[check_delivery_zone]</code> in it.</p>
				
				<h3>Quick Links:</h3>
				<ul>
					<li><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" target="_blank">Manage Shipping Zones →</a></li>
					<li><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=new'); ?>" target="_blank">Create New Shipping Zone →</a></li>
				</ul>
			</div>
		</div>
		
		<div class="cdz-social-footer">
			<h3>Developed by Deepak jerry</h3>
			<div class="cdz-social-links">
				<a href="https://medium.com/@deepakjerry" target="_blank" rel="noopener noreferrer" class="medium" title="Medium">
					<i class="fab fa-medium"></i>
				</a>
				<a href="https://wordpress.org/support/users/deepakjerry/" target="_blank" rel="noopener noreferrer" class="wordpress" title="WordPress">
					<i class="fab fa-wordpress"></i>
				</a>
				<a href="https://www.instagram.com/deepakjerry786/" target="_blank" rel="noopener noreferrer" class="instagram" title="Instagram">
					<i class="fab fa-instagram"></i>
				</a>
				<a href="https://www.facebook.com/deepakjerry78/" target="_blank" rel="noopener noreferrer" class="facebook" title="Facebook">
					<i class="fab fa-facebook-f"></i>
				</a>
				<a href="https://www.linkedin.com/m/in/deepak-kumar-1aaa06146/" target="_blank" rel="noopener noreferrer" class="linkedin" title="LinkedIn">
					<i class="fab fa-linkedin-in"></i>
				</a>
				<a href="mailto:deepakjerry123@gmail.com" class="email" title="Email Support">
					<i class="fas fa-envelope"></i>
				</a>
			</div>
			<p>Plugin developed with ❤️ | For support, email: <a href="mailto:deepakjerry123@gmail.com" style="color: #667eea; text-decoration: none;">deepakjerry123@gmail.com</a></p>
		</div>
	</div>
	<?php
}

/**
 * Add postcode/postcode range to a shipping zone
 */
function cdz_add_postcode_to_zone($zone, $postcode_range) {
	if (!is_a($zone, 'WC_Shipping_Zone')) {
		return false;
	}
	
	// Get existing locations
	$locations = $zone->get_zone_locations();
	$existing_postcodes = [];
	
	// Collect existing postcodes to avoid duplicates
	foreach ($locations as $location) {
		if (is_object($location) && isset($location->type) && $location->type === 'postcode') {
			$code = isset($location->code) ? trim($location->code) : '';
			if (!empty($code)) {
				$existing_postcodes[] = $code;
			}
		}
	}
	
	// Normalize the postcode range
	$postcode_range = trim($postcode_range);
	
	if (empty($postcode_range)) {
		return false;
	}
	
	// Check if already exists
	if (in_array($postcode_range, $existing_postcodes, true)) {
		return 'duplicate'; // Already exists
	}
	
	// Add new postcode location
	try {
		$zone->add_location('postcode', $postcode_range);
		$saved = $zone->save();
		
		if ($saved !== false) {
			return true;
		}
		return false;
	} catch (Exception $e) {
		return false;
	}
}

/**
 * Helper function to find or create a shipping zone
 */
function cdz_find_or_create_zone($zone_name) {
	if (!class_exists('WC_Shipping_Zones')) {
		return false;
	}
	
	$zones = WC_Shipping_Zones::get_zones();
	foreach ($zones as $zone_data) {
		if (isset($zone_data['zone_name']) && strcasecmp($zone_data['zone_name'], $zone_name) === 0) {
			return WC_Shipping_Zones::get_zone($zone_data['zone_id']);
		}
	}
	
	// Check the "Rest of the World" zone (ID 0)
	$rest_of_world = WC_Shipping_Zones::get_zone(0);
	if ($rest_of_world && strcasecmp($rest_of_world->get_zone_name(), $zone_name) === 0) {
		return $rest_of_world;
	}
	
	// Create new zone if not found
	try {
		$new_zone = new WC_Shipping_Zone();
		$new_zone->set_zone_name($zone_name);
		$new_zone->save();
		return $new_zone;
	} catch (Exception $e) {
		return false;
	}
}
