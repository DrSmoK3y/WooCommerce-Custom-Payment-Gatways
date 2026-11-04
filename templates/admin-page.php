<?php
// File: templates/admin-page.php (Version 1.7.0 with QR & Discounts)
defined('ABSPATH') || exit;
?>
<div class="wrap wc-custom-payment-wrap">
    <h1><?php _e('Custom Payment Gateways', 'wc-custom-payment'); ?></h1>
    <p><?php _e('Create and manage custom payment gateways for WooCommerce with ease.', 'wc-custom-payment'); ?></p>

    <!-- Add/Edit Gateway Form -->
    <div class="gateway-form-container">
        <h2 id="form-title"><?php _e('Add New Gateway', 'wc-custom-payment'); ?></h2>
        <form id="gateway-form">
            <input type="hidden" id="gateway_db_id" value="">

            <table class="form-table">
                <!-- Gateway ID -->
                <tr valign="top">
                    <th scope="row"><label for="gateway_id"><?php _e('Gateway ID', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <input type="text" id="gateway_id" name="gateway_id" class="regular-text" required placeholder="e.g., bank_transfer_abc" />
                        <p class="description"><?php _e('Unique ID, no spaces or special characters. Cannot be changed after creation.', 'wc-custom-payment'); ?></p>
                    </td>
                </tr>
                <!-- Title -->
                <tr valign="top">
                    <th scope="row"><label for="title"><?php _e('Gateway Title', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <input type="text" id="title" name="title" class="regular-text" required placeholder="What customers see at checkout" />
                        <p class="description"><?php _e('The title displayed to customers during checkout process.', 'wc-custom-payment'); ?></p>
                    </td>
                </tr>
                <!-- Account Number -->
                <tr valign="top">
                    <th scope="row"><label for="account_number"><?php _e('Account Number', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <input type="text" id="account_number" name="account_number" class="regular-text" placeholder="e.g., 123456789" />
                    </td>
                </tr>
                <!-- Account Title -->
                <tr valign="top">
                    <th scope="row"><label for="account_title"><?php _e('Account Title', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <input type="text" id="account_title" name="account_title" class="regular-text" placeholder="e.g., John Doe Business Account" />
                    </td>
                </tr>
                <!-- Instructions -->
                <tr valign="top">
                    <th scope="row"><label for="instructions"><?php _e('Payment Instructions', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <textarea id="instructions" name="instructions" rows="5" class="large-text" placeholder="Enter detailed payment instructions..."></textarea>
                    </td>
                </tr>
                <!-- Gateway Icon -->
                <tr valign="top">
                    <th scope="row"><label for="icon_url"><?php _e('Gateway Icon', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="text" id="icon_url" name="icon_url" class="regular-text" placeholder="Icon URL" />
                            <button type="button" class="button upload-btn" data-target="#icon_url" data-preview="#icon-preview"><?php _e('Upload Icon', 'wc-custom-payment'); ?></button>
                        </div>
                        <div id="icon-preview" class="icon-preview"></div>
                    </td>
                </tr>

                <!-- === QR CODE SECTION === -->
                <tr valign="top" style="border-top: 2px dashed #eee;">
                    <th scope="row"><label for="qr_enabled_check"><?php _e('Enable QR Code', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <input type="hidden" name="qr_enabled" id="qr_enabled" value="0">
                        <label class="switch">
                            <input type="checkbox" id="qr_enabled_check">
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Enable to show a separate tab for QR code scanning on checkout.', 'wc-custom-payment'); ?></p>
                    </td>
                </tr>
                <tr valign="top" id="qr-upload-row" style="display:none;">
                    <th scope="row"><label for="qr_code_url"><?php _e('QR Code Image', 'wc-custom-payment'); ?></label></th>
                    <td>
                         <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="text" id="qr_code_url" name="qr_code_url" class="regular-text" placeholder="QR Code Image URL" />
                            <button type="button" class="button upload-btn" data-target="#qr_code_url" data-preview="#qr-preview"><?php _e('Upload QR', 'wc-custom-payment'); ?></button>
                        </div>
                        <div id="qr-preview" class="icon-preview"></div>
                        <p class="description"><?php _e('Recommended size: 250x250px.', 'wc-custom-payment'); ?></p>
                    </td>
                </tr>
                
                <!-- === NEW DISCOUNT SECTION === -->
                <tr valign="top" style="border-top: 2px dashed #eee;">
                    <th scope="row"><label for="discount_enabled_check"><?php _e('Enable Discount', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <input type="hidden" name="discount_enabled" id="discount_enabled" value="0">
                        <label class="switch">
                            <input type="checkbox" id="discount_enabled_check">
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Offer a discount when customer pays via this gateway.', 'wc-custom-payment'); ?></p>
                    </td>
                </tr>
                <tr valign="top" id="discount-settings-row" style="display:none;">
                    <th scope="row"><label><?php _e('Discount Settings', 'wc-custom-payment'); ?></label></th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div style="flex:1;">
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Discount Type</label>
                                <select id="discount_type" name="discount_type" class="regular-text" style="width:100%;">
                                    <option value="fixed">Fix Amount (Currency)</option>
                                    <option value="percent">Percentage (%)</option>
                                </select>
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Value (Number Only)</label>
                                <input type="number" step="0.01" min="0" id="discount_value" name="discount_value" class="regular-text" style="width:100%;" placeholder="e.g. 5 or 50.00">
                            </div>
                        </div>
                        <p class="description"><?php _e('If Percentage selected: Max value is 100. If Fix selected: Flat amount deducted.', 'wc-custom-payment'); ?></p>
                    </td>
                </tr>
                <!-- ============================ -->

            </table>

            <div id="custom-fields-section">
                <h3><?php _e('Additional Payment Details', 'wc-custom-payment'); ?></h3>
                <div id="custom-fields-container"></div>
                <button type="button" id="add-custom-field" class="button"><?php _e('+ Add Payment Detail Field', 'wc-custom-payment'); ?></button>
            </div>
            
            <p class="submit">
                <button type="submit" id="submit-gateway" class="button button-primary"><?php _e('Save Gateway', 'wc-custom-payment'); ?></button>
                <button type="button" id="cancel-edit" class="button" style="display:none;"><?php _e('Cancel Edit', 'wc-custom-payment'); ?></button>
            </p>
        </form>
    </div>

    <!-- List of Existing Gateways -->
    <div class="gateways-list-container">
        <h2><?php _e('Your Payment Gateways', 'wc-custom-payment'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:60px;"><?php _e('Icon', 'wc-custom-payment'); ?></th>
                    <th><?php _e('Gateway Name', 'wc-custom-payment'); ?></th>
                    <th><?php _e('Features', 'wc-custom-payment'); ?></th>
                    <th><?php _e('Details', 'wc-custom-payment'); ?></th>
                    <th style="width: 80px; text-align:center;"><?php _e('Status', 'wc-custom-payment'); ?></th>
                    <th style="width: 200px; text-align:center;"><?php _e('Actions', 'wc-custom-payment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($gateways)): ?>
                    <tr><td colspan="6" style="text-align:center;"><?php _e('No custom gateways found.', 'wc-custom-payment'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($gateways as $gateway): ?>
                        <tr>
                            <td>
                                <?php if($gateway->icon_url): ?>
                                    <img src="<?php echo esc_url($gateway->icon_url); ?>" class="gateway-icon-list">
                                <?php else: ?>
                                    <div class="no-icon">No Icon</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($gateway->title); ?></strong><br><code><?php echo esc_html($gateway->gateway_id); ?></code></td>
                            <td>
                                <?php if($gateway->qr_enabled): ?>
                                    <span style="background:#e3f2fd; color:#1976d2; padding:2px 6px; border-radius:4px; font-size:11px;">QR Code</span>
                                <?php endif; ?>
                                <?php if(isset($gateway->discount_enabled) && $gateway->discount_enabled): ?>
                                    <span style="background:#e8f5e9; color:#2e7d32; padding:2px 6px; border-radius:4px; font-size:11px; margin-left:3px;">
                                        <?php echo ($gateway->discount_type === 'percent' ? esc_html($gateway->discount_value).'%' : 'Fix') . ' Off'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><strong><?php _e('Acc:', 'wc-custom-payment'); ?></strong> <?php echo esc_html($gateway->account_number ?: '-'); ?></small>
                            </td>
                            <td style="text-align:center;">
                                <label class="switch">
                                    <input type="checkbox" class="toggle-enabled" data-id="<?php echo esc_attr($gateway->id); ?>" <?php checked($gateway->enabled, 1); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td style="text-align:center;">
                                <button class="button edit-gateway"
                                    data-id="<?php echo esc_attr($gateway->id); ?>"
                                    data-gateway_id="<?php echo esc_attr($gateway->gateway_id); ?>"
                                    data-title="<?php echo esc_attr($gateway->title); ?>"
                                    data-account_number="<?php echo esc_attr($gateway->account_number); ?>"
                                    data-account_title="<?php echo esc_attr($gateway->account_title); ?>"
                                    data-instructions="<?php echo esc_attr($gateway->instructions); ?>"
                                    data-icon_url="<?php echo esc_attr($gateway->icon_url); ?>"
                                    data-qr_enabled="<?php echo esc_attr($gateway->qr_enabled); ?>"
                                    data-qr_code_url="<?php echo esc_attr($gateway->qr_code_url); ?>"
                                    
                                    /* Discount Data Attributes */
                                    data-discount_enabled="<?php echo isset($gateway->discount_enabled) ? esc_attr($gateway->discount_enabled) : '0'; ?>"
                                    data-discount_type="<?php echo isset($gateway->discount_type) ? esc_attr($gateway->discount_type) : 'fixed'; ?>"
                                    data-discount_value="<?php echo isset($gateway->discount_value) ? esc_attr($gateway->discount_value) : '0'; ?>"
                                    
                                    data-custom_fields='<?php echo esc_attr($gateway->custom_fields); ?>'
                                >
                                    <?php _e('Edit', 'wc-custom-payment'); ?>
                                </button>
                                <button class="button delete-gateway" data-id="<?php echo esc_attr($gateway->id); ?>">
                                    <?php _e('Delete', 'wc-custom-payment'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.no-icon { width: 40px; height: 40px; background: #eee; border-radius: 4px; display:flex; align-items:center; justify-content:center; font-size:9px; color:#999;}
</style>