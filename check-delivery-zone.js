jQuery(document).ready(function($) {
    // Use event delegation to support multiple instances on the same page
    $(document).on('click', '[id^="cdz_check_btn"]', function() {
        var $btn = $(this);
        var btnId = $btn.attr('id');
        var suffix = btnId.replace('cdz_check_btn', '');
        
        // Find corresponding input and result elements
        var $pincodeInput = $('#cdz_pincode' + suffix);
        var $resultDiv = $('#cdz_result' + suffix);
        
        const pincode = $pincodeInput.val().trim();
        if (!pincode) {
            $resultDiv.html('⚠️ Please enter a pincode.');
            return;
        }
        $resultDiv.html('⏳ Checking...');

        $.ajax({
            url: cdz_ajax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'check_delivery_zone',
                security: cdz_ajax.nonce,
                pincode: pincode
            },
            success: function(response) {
                if (response.success) {
                    const location = response.data.location || {};
                    
                    let html = `<strong style="font-size:16px;color:#28a745;">✅ Delivery available.</strong><br><br>`;
					
					// Exact Location Information - Show prominently at top
					// Always show location box if we have any location data
					const hasLocation = location.post_office || location.district || location.state || location.city;
					if (hasLocation) {
						html += `<div style="background:linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%);padding:15px;border-radius:8px;margin-bottom:15px;border-left:5px solid #2196f3;box-shadow:0 2px 8px rgba(0,0,0,0.1);">`;
						html += `<strong style="color:#1976d2;font-size:15px;display:block;margin-bottom:10px;">📍 Exact Location:</strong>`;
						
						// Village/Post Office (Most specific) - show first
						const village = location.village || location.post_office || '';
						if (village) {
							html += `<div style="margin:6px 0;font-size:14px;"><strong style="color:#333;">🏘️ Village/Area:</strong> <span style="color:#1565c0;font-weight:600;">${village}</span></div>`;
						}
						
						// City/District - show prominently
						const cityName = location.district || location.city || '';
						if (cityName) {
							html += `<div style="margin:6px 0;font-size:14px;"><strong style="color:#333;">🏙️ City/District:</strong> <span style="color:#1565c0;font-weight:600;">${cityName}</span></div>`;
						}
						
						// State
						if (location.state) {
							html += `<div style="margin:6px 0;font-size:14px;"><strong style="color:#333;">🗺️ State:</strong> <span style="color:#1565c0;font-weight:600;">${location.state}</span></div>`;
						}
						
						// Block/Division if available
						if (location.block && location.block !== village) {
							html += `<div style="margin:6px 0;font-size:13px;color:#666;"><strong>Block:</strong> ${location.block}</div>`;
						}
						
						// Full address line - complete exact location
						let fullAddress = location.full_address || '';
						if (!fullAddress) {
							let addressParts = [];
							if (village) addressParts.push(village);
							if (cityName) addressParts.push(cityName);
							if (location.state) addressParts.push(location.state);
							fullAddress = addressParts.join(', ');
						}
						
						if (fullAddress) {
							html += `<div style="margin-top:12px;padding-top:12px;border-top:2px solid #90caf9;background:#ffffff;padding:10px;border-radius:4px;">`;
							html += `<div style="font-size:12px;color:#666;margin-bottom:4px;"><strong>📍 Complete Exact Location:</strong></div>`;
							html += `<div style="font-size:15px;color:#1565c0;font-weight:700;">${fullAddress}</div>`;
							html += `</div>`;
						}
						
						html += `</div>`;
					} else {
						// If no location data, show message
						html += `<div style="background:#fff3cd;padding:12px;border-radius:6px;margin-bottom:15px;border-left:4px solid #ffc107;">`;
						html += `<span style="color:#856404;font-size:13px;">📍 Location details are being fetched. Please wait...</span>`;
						html += `</div>`;
					}
					
                    if (response.data.methods && response.data.methods.length) {
                        html += `<div style="margin-top:10px;"><strong style="color:#666;">Shipping methods:</strong><ul style="margin:8px 0 0 20px;color:#555;">`;
                        response.data.methods.forEach(method => {
                            html += `<li style="margin:4px 0;">${method.title}</li>`;
                        });
                        html += `</ul></div>`;
                    }
                    $resultDiv.html(html);
                } else {
                    $resultDiv.html(`⚠️ ${response.data}`);
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                $resultDiv.html('⚠️ Server error while checking delivery.');
            }
        });
    });
});

