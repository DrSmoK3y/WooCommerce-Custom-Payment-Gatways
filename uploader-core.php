<?php

if ( ! defined( 'ABSPATH' ) ) exit;

$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'checkout-files-upload-woocommerce.php' === basename( __FILE__ ) ) {

	$plugin = 'checkout-files-upload-woocommerce-pro/checkout-files-upload-woocommerce-pro.php';
	if (
		in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ||
		( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION', '2.2.2' );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION', '2' );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_PATH' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_FILE' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_FILE', __FILE__ );
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

if ( ! class_exists( 'Cpg_WC_Checkout_Files_Upload' ) ) :

final class Cpg_WC_Checkout_Files_Upload {

	public $core       = null;
	public $settings   = null;

	public $version = '2.2.2';

	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	function __construct() {

		add_action( 'init', array( $this, 'load_localization' ) );

		$this->includes();

				if ( is_admin() ) {
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
					add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
				}

	}

	public function load_localization() {
		load_plugin_textdomain( 'checkout-files-upload-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
	}

	public function enqueue_scripts() {
		global $pagenow;

		if (
			$pagenow === 'post.php'
			|| ( $pagenow === 'admin.php' && isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] === 'cpg_wc_checkout_files_upload' )
		) {
			wp_enqueue_script(
				'wpwham-checkout-files-upload-admin',
				$this->plugin_url() . '/includes/js/uploader-admin.js',
				array( 'jquery' ),
				WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION,
				false
			);
			wp_localize_script(
				'wpwham-checkout-files-upload-admin',
				'wpwham_checkout_files_upload_admin',
				array(
					'i18n' => array(
						'confirmation_message' => __( 'Are you sure you want to delete this file? This cannot be undone.', 'checkout-files-upload-woocommerce' ),
					),
				)
			);
		}
	}

	public function enqueue_styles() {
		global $pagenow;

		if (
			$pagenow === 'admin.php'
			&& isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] === 'cpg_wc_checkout_files_upload'
		) {
			wp_enqueue_style(
				'wpwham-checkout-files-upload-admin',
				$this->plugin_url() . '/includes/css/admin.css',
				array(),
				WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION,
				'all'
			);
		}
	}

	function action_links( $links ) {
		$custom_links = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=cpg_wc_checkout_files_upload' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
		if ( 'checkout-files-upload-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a target="_blank" href="https://wpwham.com/products/checkout-files-upload-for-woocommerce/?utm_source=plugins_page&utm_campaign=free&utm_medium=checkout_files_upload">' .
				__( 'Unlock all', 'checkout-files-upload-woocommerce' ) . '</a>';
		}
		return array_merge( $custom_links, $links );
	}

	public static function add_settings_to_status_report() {
		$protected_settings = array( 'wpwham_checkout_files_upload_license', 'cpg_checkout_files_upload_emails_address' );
		$settings_general   = Cpg_WC_Checkout_Files_Upload_Settings_General::get_settings();
		$settings_emails    = Cpg_WC_Checkout_Files_Upload_Settings_Emails::get_settings();
		$settings_template  = Cpg_WC_Checkout_Files_Upload_Settings_Template::get_settings();
		$settings = array_merge(
			$settings_general, $settings_emails, $settings_template
		);
		$total_number = apply_filters( 'cpg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			$settings_file_inst = new Cpg_WC_Checkout_Files_Upload_Settings_File( $i );
			$settings_file = $settings_file_inst->get_settings();
			$settings = array_merge(
				$settings, $settings_file
			);
		}
		?>
		<table class="wc_status_table widefat" cellspacing="0">
			<thead>
				<tr>
					<th colspan="3" data-export-label="Checkout Files Upload Settings"><h2><?php esc_html_e( 'Checkout Files Upload Settings', 'checkout-files-upload-for-woocommerce' ); ?></h2></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $settings as $setting ): ?>
				<?php 
				if (
					in_array( $setting['type'], array( 'title', 'sectionend' ) ) ||
					! isset( $setting['id'] )
				) {
					continue;
				}
				if ( isset( $setting['title'] ) ) {
					$title = $setting['title'];
				} elseif ( isset( $setting['desc'] ) ) {
					$title = $setting['desc'];
				} else {
					$title = $setting['id'];
				}
				$value = get_option( $setting['id'] ); 
				if ( in_array( $setting['id'], $protected_settings ) ) {
					$value = $value > '' ? '(set)' : 'not set';
				}
				?>
				<tr>
					<td data-export-label="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( $title, 'checkout-files-upload-for-woocommerce' ); ?>:</td>
					<td class="help">&nbsp;</td>
					<td><?php echo is_array( $value ) ? print_r( $value, true ) : $value; ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	function includes() {

		require_once( 'includes/cpg-wc-checkout-files-upload-functions.php' );

		$this->core = require_once( 'includes/class-cpg-wc-checkout-files-upload.php' );
	}

	function version_updated() {
		foreach ( $this->settings as $section ) {
			foreach ( $section->get_settings() as $value ) {
				if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
					$autoload = isset( $value['autoload'] ) ? ( bool ) $value['autoload'] : true;
					add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}
		update_option( 'cpg_checkout_files_upload_version', $this->version );
	}

	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-wc-settings-checkout-files-upload.php' );
		return $settings;
	}

	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

}

endif;

if ( ! function_exists( 'cpg_wc_checkout_files_upload' ) ) {

	function cpg_wc_checkout_files_upload() {
		return Cpg_WC_Checkout_Files_Upload::instance();
	}
}

cpg_wc_checkout_files_upload();
?>