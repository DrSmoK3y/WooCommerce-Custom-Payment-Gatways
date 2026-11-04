<?php
/*
Plugin Name: Custom Payment Gateways - Pro
Plugin URI:  https://lmwebdesigners.com/plugin/woocommerce-custom-gateway/
Description: Ultimate solution for custom payment gateways with QR Codes, Analytics, and Discounts.
Version:     2.0.0
Author:      LM Designers
Author URI:  https://lmwebdesigners.com/
Text Domain: wc-custom-payment
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ) {
    return;
}

define('FINAL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FINAL_PLUGIN_URL', plugin_dir_url(__FILE__));

class Final_Gateway_Uploader_Main {
    private static $instance;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
        
        add_action('plugins_loaded', [$this, 'load_dependencies']);
        add_action('admin_init', [$this, 'check_db_updates']);
    }

    public function load_dependencies() {
        require_once FINAL_PLUGIN_PATH . 'uploader-core.php';
        require_once FINAL_PLUGIN_PATH . 'gateway-core.php';
        if (function_exists('cpg_wc_checkout_files_upload')) { cpg_wc_checkout_files_upload(); }
        if (class_exists('CPG_Modified_Main')) { CPG_Modified_Main::instance(); }
    }
    
    // Automatic DB Fixer - Version Bumped to 2.0.0 for Discount Fields
    public function check_db_updates() {
        if (get_option('cpg_db_ver') !== '2.0.0') {
            $this->activate_plugin();
            update_option('cpg_db_ver', '2.0.0');
        }
    }

    public function activate_plugin() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_payment_gateways';
        $charset_collate = $wpdb->get_charset_collate();

        // SQL Updated with discount columns
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gateway_id varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            account_number varchar(255) NOT NULL,
            account_title varchar(255) NOT NULL,
            instructions text,
            icon_url varchar(500),
            qr_enabled tinyint(1) DEFAULT 0,
            qr_code_url varchar(500) DEFAULT '',
            discount_enabled tinyint(1) DEFAULT 0,
            discount_type varchar(20) DEFAULT 'fixed',
            discount_value decimal(10,2) DEFAULT 0.00,
            shipping_method varchar(100),
            enabled tinyint(1) DEFAULT 1,
            custom_fields text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY gateway_id (gateway_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
Final_Gateway_Uploader_Main::instance();