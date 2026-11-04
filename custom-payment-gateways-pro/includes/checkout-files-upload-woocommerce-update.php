<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function wpw_cfu_db_update() {
	
	global $post, $wpdb;
	
	$settings = get_option( 'wpw_cfu_settings', array() );
	
	if ( isset( $settings['db_version'] ) && $settings['db_version'] >= WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION ) {
		// all good
		return;
	}
	
	// upgrade from v1 to 2
	if ( ! isset( $settings['db_version'] ) || $settings['db_version'] < 2 ) {
		

		$field_template = get_option(
			'cpg_checkout_files_upload_form_template_field_ajax',
			'<tr><td colspan="2">%button_html% %field_html%</td></tr>'
		);
		if ( strpos( $field_template, '%button_html%' ) === false ) {
			$field_template = str_replace( '%field_html%', '%button_html% %field_html%', $field_template );
		}
		update_option( 'cpg_checkout_files_upload_form_template_field_ajax', $field_template );
		
	}
	
	// Done
	$settings['db_version'] = WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION;
	update_option( 'wpw_cfu_settings', $settings );
	
}
add_action( 'init', 'wpw_cfu_db_update' );
?>