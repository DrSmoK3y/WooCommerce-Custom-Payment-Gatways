<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'cpg_current_filter_priority' ) ) {
	function cpg_current_filter_priority() {
		global $wp_filter;
		$current_filter_data = $wp_filter[ current_filter() ];
		if ( class_exists( 'WP_Hook' ) ) {
			// since WordPress v4.7
			return $current_filter_data->current_priority();
		} else {
			// before WordPress v4.7
			return key( $current_filter_data );
		}
	}
}

if ( ! function_exists( 'cpg_get_cpg_uploads_dir' ) ) {
	function cpg_get_cpg_uploads_dir( $subdir = '' ) {
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];
		$upload_dir = $upload_dir . '/woocommerce_uploads/cpg_uploads';
		if ( '' != $subdir ) {
			$upload_dir = $upload_dir . '/' . $subdir;
		}
		return $upload_dir;
	}
}

if ( ! function_exists( 'cpg_is_user_role' ) ) {
	function cpg_is_user_role( $user_role, $user_id = 0 ) {
		// Always return true, all roles allowed
		return true;
	}
}
?>