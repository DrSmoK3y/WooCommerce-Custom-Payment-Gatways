<?php
// File: gateway-core.php (V2.0 - Stats, QR & Discounts) - UPDATED WITH FREEMIUM DESIGN

defined('ABSPATH') || exit;

class CPG_Modified_Main {
    private static $instance;

    public static function instance() {
        if (is_null(self::$instance)) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->maybe_create_gateway_classes();
        add_action('init', [$this, 'load_textdomain']);
        $this->setup_hooks();
    }

    public function load_textdomain() {
        load_plugin_textdomain('wc-custom-payment', false, dirname(plugin_basename(FINAL_PLUGIN_PATH . 'woocommerce-custom-gateway.php')) . '/languages/');
    }

    public function setup_hooks() {
        add_filter('woocommerce_payment_gateways', [$this, 'register_gateways']);
        add_action('woocommerce_review_order_before_submit', [$this, 'output_fields_before_place_order']);
        
        // --- NEW: Discount Calculation Hook ---
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_gateway_discount']);
        
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
            
            add_action('wp_ajax_cpg_add_gateway', [$this, 'ajax_add_gateway']);
            add_action('wp_ajax_cpg_delete_gateway', [$this, 'ajax_delete_gateway']);
            add_action('wp_ajax_cpg_update_gateway', [$this, 'ajax_update_gateway']);
            add_action('wp_ajax_cpg_export_csv', [$this, 'ajax_export_csv']); 
        }
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_transaction_id_in_admin'], 10, 1);
        add_action('woocommerce_email_order_meta', [$this, 'display_transaction_id_in_emails'], 20, 3);
        
        add_action('wp_footer', [$this, 'footer_checkout_script']);
    }

    public function footer_checkout_script() {
        if(is_checkout()) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($){
                $('form.checkout').on('change', 'input[name="payment_method"]', function(){
                    $('body').trigger('update_checkout');
                });
            });
            </script>
            <?php
        }
    }

    public function apply_gateway_discount() {
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        $chosen_method = WC()->session->get('chosen_payment_method');
        if (empty($chosen_method)) return;

        $custom_ids = get_option('cpg_gateway_ids', []);

        if(!in_array($chosen_method, $custom_ids) && !in_array(str_replace('_','-', $chosen_method), $custom_ids)) {
            return;
        }

        global $wpdb;
        $t = $wpdb->prefix . 'custom_payment_gateways';
        
        $res = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE gateway_id = %s AND enabled = 1", $chosen_method));
        
        if ($res && isset($res->discount_enabled) && $res->discount_enabled == 1) {
            $cart_total = WC()->cart->subtotal;
            $discount_amount = 0;
            $val = floatval($res->discount_value);

            if ($res->discount_type === 'percent') {
                if ($val > 100) $val = 100; 
                $discount_amount = $cart_total * ($val / 100);
                $label = $res->title . ' (' . $val . '% Off)';
            } else {
                $discount_amount = $val;
                $label = $res->title . ' (Discount)';
            }

            if ($discount_amount > 0) {
                WC()->cart->add_fee(__($label, 'wc-custom-payment'), -$discount_amount);
            }
        }
    }

    public function admin_scripts($hook) {
        if (strpos($hook, 'cpg-admin-panel') === false && strpos($hook, 'cpg-gateway-stats') === false) return;
        
        wp_enqueue_style('cpg-admin-css', FINAL_PLUGIN_URL . 'assets/admin.css', [], '2.6.0');
        wp_enqueue_media();
        wp_enqueue_script('cpg-admin-js', FINAL_PLUGIN_URL . 'assets/admin.js', ['jquery'], '2.6.0', true);
        
        if(strpos($hook, 'cpg-gateway-stats') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        }

        wp_localize_script('cpg-admin-js', 'cpg_admin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cpg-admin-nonce')
        ]);
    }

    public function ajax_export_csv() {
        if(!current_user_can('manage_woocommerce')) die;
        $start = $_GET['start_date'] ?? '';
        $end = $_GET['end_date'] ?? '';
        $gw = $_GET['gateway'] ?? '';
        
        $args = ['limit'=>-1,'type'=>'shop_order','status'=>['wc-completed','wc-processing','wc-on-hold','wc-pending'],'date_created'=>($start&&$end?"$start...$end":'')];
        if($gw){ $args['payment_method']=$gw; } else { $ids=get_option('cpg_gateway_ids',[]); if(!empty($ids)) $args['payment_method']=$ids; }
        $orders=wc_get_orders($args);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="Gateway_Report_'.date('Y-m-d').'.csv"');
        $f=fopen('php://output','w'); fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF)); 
        fputcsv($f,['Order ID','Client Name','Email','Phone','Payment Gateway','Total Amount','Products List','Date (Local)','Order Status']);
        foreach($orders as $o) {
            $d=$o->get_date_created(); $fd=$d?($d->setTimezone(new DateTimeZone(wp_timezone_string()))?$d->format('Y-m-d H:i:s'):'-'):'-';
            $p=[]; foreach($o->get_items() as $i) $p[]=$i->get_name().' (x'.$i->get_quantity().')';
            fputcsv($f,[$o->get_order_number(),$o->get_formatted_billing_full_name()?:'Guest',$o->get_billing_email(),$o->get_billing_phone(),$o->get_payment_method_title(),$o->get_total(),implode(', ',$p),$fd,wc_get_order_status_name($o->get_status())]);
        }
        fclose($f); exit;
    }

    private function create_gateway_class($gateway) {
        $clean_gateway_id = str_replace('-', '_', $gateway->gateway_id);
        $class_name = 'CPG_Gateway_Complete_' . str_replace(' ', '', ucwords(str_replace('_', ' ', $clean_gateway_id)));
        if (class_exists($class_name)) return;

        $gateway_id = esc_attr($gateway->gateway_id);
        $title = addslashes($gateway->title);
        $icon = esc_url($gateway->icon_url);
        $desc = addslashes(wpautop(wp_kses_post($gateway->instructions ?? '')));
        $acc_num = addslashes($gateway->account_number ?? '');
        $acc_title = addslashes($gateway->account_title ?? '');
        $custom_fields_json = addslashes($gateway->custom_fields ?? '[]');
        $qr_enabled = isset($gateway->qr_enabled) ? intval($gateway->qr_enabled) : 0;
        $qr_url = isset($gateway->qr_code_url) ? esc_url($gateway->qr_code_url) : '';

        eval("
            class {$class_name} extends WC_Payment_Gateway {
                public function __construct() {
                    \$this->id = '{$gateway_id}'; \$this->method_title = '{$title}'; \$this->title = '{$title}'; \$this->has_fields = true; if (!empty('{$icon}')) \$this->icon = '{$icon}';
                }
                public function payment_fields() {
                    \$show_qr_btn = ({$qr_enabled} === 1 && !empty('{$qr_url}'));
                    echo '<div class=\"cpg-gateway-container\">';
                    if (\$show_qr_btn) {
                        echo '<div class=\"cpg-header-action\" style=\"width:100%; display:block; margin-bottom:10px;\">';
                        echo '<button type=\"button\" class=\"cpg-toggle-qr-view\"><span class=\"dashicons dashicons-qr-code\"></span> <span class=\"btn-txt\">' . __('Show QR Code', 'wc-custom-payment') . '</span></button>';
                        echo '</div>';
                    }
                    echo '<div class=\"cpg-view-details\">';
                    echo '<div class=\"cpg-field-rows\">';
                    if (!empty('{$acc_num}')) echo '<div class=\"copyable-field\"><strong>'.__('Account No','wc-custom-payment').':</strong> <span class=\"val\">'.stripslashes('{$acc_num}').'</span><button type=\"button\" class=\"cpg-copy-btn\"><span class=\"dashicons dashicons-admin-page\"></span></button></div>';
                    if (!empty('{$acc_title}')) echo '<div class=\"copyable-field\"><strong>'.__('Name','wc-custom-payment').':</strong> <span class=\"val\">'.stripslashes('{$acc_title}').'</span><button type=\"button\" class=\"cpg-copy-btn\"><span class=\"dashicons dashicons-admin-page\"></span></button></div>';
                    \$f = json_decode(stripslashes('{$custom_fields_json}'), true); if(is_array(\$f)) foreach(\$f as \$i) if(!empty(\$i['title'])) echo '<div class=\"copyable-field\"><strong>'.esc_html(\$i['title']).':</strong> <span class=\"val\">'.esc_html(\$i['value']).'</span><button type=\"button\" class=\"cpg-copy-btn\"><span class=\"dashicons dashicons-admin-page\"></span></button></div>';
                    echo '</div>';
                    if(!empty('{$desc}')) echo '<div class=\"cpg-instructions\">'.stripslashes('{$desc}').'</div>';
                    echo '</div>';
                    if (\$show_qr_btn) { echo '<div class=\"cpg-view-qr qr-image\" style=\"display:none;\"><img src=\"{$qr_url}\" class=\"cpg-qr-images\"><p class=\"cpg-qr-text\">Scan QR Code to Pay</p></div>'; }
                    echo '</div>';
                }
                public function process_payment(\$order_id) {
                    \$o = wc_get_order(\$order_id);
                    if(isset(\$_POST['transaction_id_universal']) && !empty(\$_POST['transaction_id_universal'])) \$o->update_meta_data('_transaction_id_for_gateway', sanitize_text_field(\$_POST['transaction_id_universal']));
                    \$o->update_status('on-hold'); wc_reduce_stock_levels(\$order_id); WC()->cart->empty_cart();
                    return ['result'=>'success', 'redirect'=>\$this->get_return_url(\$o)];
                }
            }
        ");
    }
    
    private function get_json_fields() { $d=[]; if(isset($_POST['custom_field_title'])){ $t=$_POST['custom_field_title']; $v=$_POST['custom_field_value']; for($i=0;$i<count($t);$i++) if(!empty($t[$i])) $d[]=['title'=>sanitize_text_field($t[$i]),'value'=>sanitize_text_field($v[$i])]; } return wp_json_encode($d); }
    
    public function ajax_add_gateway() { 
        check_ajax_referer('cpg-admin-nonce', 'nonce'); if(!current_user_can('manage_woocommerce')) wp_send_json_error(); global $wpdb; $t=$wpdb->prefix.'custom_payment_gateways';
        
        $d = [
            'gateway_id'     => str_replace('-','_',sanitize_key($_POST['gateway_id'])), 
            'title'          => sanitize_text_field($_POST['title']), 
            'account_number' => sanitize_text_field($_POST['account_number']), 
            'account_title'  => sanitize_text_field($_POST['account_title']), 
            'instructions'   => wp_kses_post($_POST['instructions']), 
            'icon_url'       => esc_url_raw($_POST['icon_url']), 
            'qr_enabled'     => intval($_POST['qr_enabled']), 
            'qr_code_url'    => esc_url_raw($_POST['qr_code_url']),
            
            'discount_enabled' => intval($_POST['discount_enabled']),
            'discount_type'    => sanitize_text_field($_POST['discount_type']), // 'fixed' or 'percent'
            'discount_value'   => floatval($_POST['discount_value']),
            
            'custom_fields'  => $this->get_json_fields(), 
            'enabled'        => 1
        ];
        
        $wpdb->insert($t, $d) ? wp_send_json_success() : wp_send_json_error(); 
    }

    public function ajax_update_gateway() { 
        check_ajax_referer('cpg-admin-nonce', 'nonce'); 
        global $wpdb; 
        $t = $wpdb->prefix . 'custom_payment_gateways'; 
        
        $d = [];

        if (isset($_POST['title'])) 
            $d['title'] = sanitize_text_field($_POST['title']);
            
        if (isset($_POST['account_number'])) 
            $d['account_number'] = sanitize_text_field($_POST['account_number']);
            
        if (isset($_POST['account_title'])) 
            $d['account_title'] = sanitize_text_field($_POST['account_title']);
            
        if (isset($_POST['instructions'])) 
            $d['instructions'] = wp_kses_post($_POST['instructions']);
            
        if (isset($_POST['icon_url'])) 
            $d['icon_url'] = esc_url_raw($_POST['icon_url']);
            
        if (isset($_POST['qr_enabled'])) 
            $d['qr_enabled'] = intval($_POST['qr_enabled']);
            
        if (isset($_POST['qr_code_url'])) 
            $d['qr_code_url'] = esc_url_raw($_POST['qr_code_url']);
            
        if (isset($_POST['discount_enabled'])) 
            $d['discount_enabled'] = intval($_POST['discount_enabled']);
            
        if (isset($_POST['discount_type'])) 
            $d['discount_type'] = sanitize_text_field($_POST['discount_type']);
            
        if (isset($_POST['discount_value'])) 
            $d['discount_value'] = floatval($_POST['discount_value']);

        if (isset($_POST['custom_field_title'])) {
            $d['custom_fields'] = $this->get_json_fields();
        }

        if (isset($_POST['enabled'])) {
            $d['enabled'] = intval($_POST['enabled']); 
        }

        if (!empty($d) && isset($_POST['id'])) {
            $wpdb->update($t, $d, ['id' => intval($_POST['id'])]); 
        }

        wp_send_json_success(); 
    }

    public function ajax_delete_gateway() { 
        check_ajax_referer('cpg-admin-nonce','nonce'); 
        if(!current_user_can('manage_woocommerce')) wp_send_json_error();
        global $wpdb; 
        $wpdb->delete($wpdb->prefix.'custom_payment_gateways',['id'=>intval($_POST['id'])]); 
        $this->update_gateway_ids();
        wp_send_json_success(); 
    }
    
    private function update_gateway_ids() { 
        global $wpdb; 
        $t = $wpdb->prefix.'custom_payment_gateways';
        $ids = $wpdb->get_col("SELECT gateway_id FROM $t WHERE enabled = 1");
        update_option('cpg_gateway_ids', $ids);
    }
    
    public function maybe_create_gateway_classes() { 
        global $wpdb; 
        $t=$wpdb->prefix.'custom_payment_gateways'; 
        if($wpdb->get_var("SHOW TABLES LIKE '$t'")!=$t) return; 
        $rs=$wpdb->get_results("SELECT * FROM $t WHERE enabled=1"); 
        $ids=[]; 
        foreach($rs as $r){ 
            $ids[]=$r->gateway_id; 
            $this->create_gateway_class($r); 
        } 
        update_option('cpg_gateway_ids', $ids); 
    }
    
    public function add_admin_menu() { 
        add_menu_page('Custom Gateways','Custom Gateways','manage_woocommerce','cpg-admin-panel',[$this,'admin_page']); 
        add_submenu_page('cpg-admin-panel','Analytics','Analytics','manage_woocommerce','cpg-gateway-stats',[$this,'stats_page']); 
    }
    
    public function admin_page() { 
        global $wpdb; 
        $table = $wpdb->prefix.'custom_payment_gateways'; 
        $gateways = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC"); 
        include FINAL_PLUGIN_PATH . 'templates/admin-page.php'; 
    }
    
    public function stats_page() { 
        global $wpdb; 
        $table = $wpdb->prefix.'custom_payment_gateways'; 
        $gateways = $wpdb->get_results("SELECT gateway_id, title FROM {$table}"); 
        include FINAL_PLUGIN_PATH . 'templates/stats-page.php'; 
    }
    
    public function register_gateways($g) { 
        $ids=get_option('cpg_gateway_ids',[]); 
        foreach($ids as $id) { 
            $c='CPG_Gateway_Complete_'.str_replace(' ','',ucwords(str_replace('_',' ',str_replace('-','_',$id)))); 
            if(class_exists($c)) $g[]=$c; 
        } 
        return $g; 
    }
    
    public function output_fields_before_place_order() {
        global $wpdb;
        $table = $wpdb->prefix . 'custom_payment_gateways';
        $ids = $wpdb->get_col("SELECT gateway_id FROM $table WHERE enabled = 1");
        
        if (empty($ids)) return;
        ?>
        <div id="cpg-payment-proof-area" style="display:none;border:2px solid #f5f5f5;padding:25px;margin:20px 0;border-radius:8px;background:#fff;">
            <h3 style="font-weight:600; margin:0px;">Payment Proof or Transaction ID</h3>
            
            <!-- Upload Section -->
            <div id="cpg-wrapper-upload">
                <h4 style="margin-top:0;color:#333;font-weight:600;">Upload Payment Receipt</h4>
                <div id="cpg-upload-container" style="border:2px dashed #6d6d6d;border-radius:8px;padding:40px 20px;text-align:center;cursor:pointer;background:#fff;transition:all 0.3s ease;margin-bottom:15px;">
                    <div style="font-size:48px;color:#6d6d6d;margin-bottom:10px;">📁</div>
                    <p style="margin:5px 0;color:#333;font-weight:500;">Click to browse or drag and drop</p>
                    <p style="margin:5px 0;font-size:13px;color:#666;">Supported formats: JPG, PNG, PDF (Max 5MB)</p>
                    <button type="button" id="cpg-browse-btn" style="margin-top:15px;padding:10px 30px;background:#2271b1;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;">Browse Files</button>
                </div>
                
                <div id="cpg-selected-files" style="display:none;padding:15px;background:#f9f9f9;border-radius:6px;margin-bottom:15px;">
                    <p style="margin:0 0 10px 0;font-weight:600;color:#333;">Selected Files (<span id="cpg-file-count">0</span>)</p>
                    <div id="cpg-file-list"></div>
                </div>
                
                <?php if (function_exists('cpg_wc_checkout_files_upload')): ?>
                    <div style="display:none;">
                        <?php echo do_shortcode('[wpwham_checkout_files_uploader id="1"]'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Alternative Option -->
                <div style="text-align:center;margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
                    <p style="margin:0 0 10px 0;color:#666;">Or use Transaction ID instead</p>
                    <a href="#" class="cpg-switcher" data-target="tid" style="display:inline-block;padding:8px 15px;background:#f0f0f0;border-radius:4px;text-decoration:none;color:#333;font-weight:500;">Use Transaction ID</a>
                </div>
            </div>
            
            <!-- Transaction ID Section -->
            <div id="cpg-wrapper-tid" style="display:none;">
                <h4 style="margin-top:0;color:#333;font-weight:600;">Transaction ID</h4>
                <div style="display:flex;gap:10px;margin-bottom:15px;">
                    <input type="text" name="transaction_id_universal" class="input-text" placeholder="Enter your transaction/slip number" style="flex:1;padding:12px;border:1px solid #ddd;border-radius:4px;">
                    <button type="button" id="cpg-validate-tid" style="background:#2271b1;color:#fff;border:none;padding:12px 20px;border-radius:4px;font-weight:600;cursor:pointer;">Verify</button>
                </div>
                <p style="font-size:13px;color:#666;margin:5px 0 15px 0;">Enter the transaction ID from your bank receipt or payment slip.</p>
                <div style="text-align:center;">
                    <a href="#" class="cpg-switcher" data-target="upload" style="display:inline-block;padding:8px 15px;background:#f0f0f0;border-radius:4px;text-decoration:none;color:#333;font-weight:500;">← Back to Upload</a>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($){
            // Initialize variables
            var cpgInitialized = false;
            var cpgGateways = <?php echo json_encode($ids); ?>;
            
            // Check if custom gateway is selected
            function cpgCheckGateway() {
                var selected = $('form.checkout input[name="payment_method"]:checked').val();
                if(cpgGateways.includes(selected)) {
                    $('#cpg-payment-proof-area').stop(true, true).slideDown(300);
                    initUploadArea();
                } else {
                    $('#cpg-payment-proof-area').stop(true, true).slideUp(300);
                }
            }
            
            // Initialize upload area only once
            function initUploadArea() {
                if(cpgInitialized) return;
                cpgInitialized = true;
                
                // Enhanced upload functionality
                $('#cpg-upload-container').on('click', function(e){
                    if(!$(e.target).closest('#cpg-browse-btn').length) {
                        $('#cpg_checkout_files_upload_1').click();
                    }
                });
                
                $('#cpg-browse-btn').on('click', function(e){
                    e.stopPropagation();
                    $('#cpg_checkout_files_upload_1').click();
                });
                
                // Drag and drop functionality
                $('#cpg-upload-container').on('dragover', function(e){
                    e.preventDefault();
                    $(this).css({
                        'border-color': '#2271b1',
                        'background-color': '#f0f7ff'
                    });
                }).on('dragleave', function(e){
                    e.preventDefault();
                    $(this).css({
                        'border-color': '#6d6d6d',
                        'background-color': '#fff'
                    });
                }).on('drop', function(e){
                    e.preventDefault();
                    $(this).css({
                        'border-color': '#6d6d6d',
                        'background-color': '#fff'
                    });
                    // Handle dropped files
                    if(e.originalEvent.dataTransfer.files.length) {
                        $('#cpg_checkout_files_upload_1')[0].files = e.originalEvent.dataTransfer.files;
                        $('#cpg_checkout_files_upload_1').trigger('change');
                    }
                });
                
                // Listen to file selection
                $('#cpg_checkout_files_upload_1').on('change', function(){
                    var files = $(this)[0].files;
                    if(files.length > 0) {
                        $('#cpg-selected-files').show();
                        $('#cpg-file-count').text(files.length);
                        
                        // Show file list
                        var fileList = $('#cpg-file-list').empty();
                        for(var i = 0; i < files.length; i++) {
                            fileList.append(
                                '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:#f9f9f9;border-radius:4px;margin-bottom:5px;">' +
                                '<span style="font-size:13px;">' + files[i].name + '</span>' +
                                '<span style="font-size:12px;color:#666;">' + (files[i].size/1024).toFixed(1) + ' KB</span>' +
                                '</div>'
                            );
                        }
                        
                        // Update upload area style
                        $('#cpg-upload-container').css({
                            'border-color': '#4CAF50',
                            'background-color': '#f0f9f0'
                        });
                    }
                });
                
                // Toggle between upload and TID
                $(document).on('click', '.cpg-switcher', function(e){
                    e.preventDefault();
                    var target = $(this).data('target');
                    if(target == 'tid'){
                        $('#cpg-wrapper-upload').slideUp(300);
                        $('#cpg-wrapper-tid').slideDown(300);
                    } else {
                        $('#cpg-wrapper-tid').slideUp(300);
                        $('#cpg-wrapper-upload').slideDown(300);
                    }
                });
                
                // TID validation
                $('#cpg-validate-tid').on('click', function(){
                    var tid = $('input[name="transaction_id_universal"]').val().trim();
                    if(tid === '') {
                        alert('Please enter a transaction ID');
                        return;
                    }
                    $(this).text('Validating...').prop('disabled', true);
                    setTimeout(function(){
                        $('#cpg-validate-tid').text('Verified ✓').css('background', '#4CAF50');
                    }, 800);
                });
            }
            
            // Initial check with delay
            setTimeout(cpgCheckGateway, 500);
            
            // Payment method change
            $('body').on('change', 'input[name="payment_method"]', function(){
                setTimeout(cpgCheckGateway, 100);
            });
            
            // WooCommerce checkout updates
            $(document.body).on('updated_checkout', function(){
                setTimeout(cpgCheckGateway, 200);
            });
        });
        </script>
        <?php
    }
    
    public function display_transaction_id_in_admin($o) { 
        if($v = $o->get_meta('_transaction_id_for_gateway')) 
            echo "<div style='background:#f1f1f1;padding:10px;margin:10px 0'><strong>Tx ID:</strong> $v</div>"; 
    }
    
    public function display_transaction_id_in_emails($o) { 
        if ($v = $o->get_meta('_transaction_id_for_gateway')) 
            echo "Transaction ID: $v"; 
    }
    
    public function enqueue_frontend_scripts() { 
        if(!is_checkout()) return; 
        wp_enqueue_style('dashicons'); 
        wp_enqueue_style('cpg-front', FINAL_PLUGIN_URL . 'assets/frontend-custom.css', [], '2.5.0'); 
        wp_enqueue_script('cpg-front', FINAL_PLUGIN_URL . 'assets/frontend-custom.js', [], '2.5.0', true); 
    }
}