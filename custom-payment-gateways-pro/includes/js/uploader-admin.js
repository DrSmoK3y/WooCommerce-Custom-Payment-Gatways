(function( $ ){
	
	$( document ).ready( function(){
		

		$( '.wpwham-checkout-files-upload-file-delete-button' ).on( 'click', function(){
			return confirm( wpwham_checkout_files_upload_admin.i18n.confirmation_message );
		});
		

		var toggleImageWidthHeight = function() {
			var fileUploader = false;
			var show = false;
			$( '.cpg_checkout_files_upload_file_validate_image_dimensions' ).each( function(){
				fileUploader = $( this ).data( 'file-uploader' );
				if ( $( this ).val() > '' ) {
					show = true;
					return false;
				}
			});
			if ( show ) {
				$( '#cpg_checkout_files_upload_file_validate_image_dimensions_w_' + fileUploader ).closest( 'tr' ).show();
				$( '#cpg_checkout_files_upload_file_validate_image_dimensions_h_' + fileUploader ).closest( 'tr' ).show();
			} else {
				$( '#cpg_checkout_files_upload_file_validate_image_dimensions_w_' + fileUploader ).closest( 'tr' ).hide();
				$( '#cpg_checkout_files_upload_file_validate_image_dimensions_h_' + fileUploader ).closest( 'tr' ).hide();
			}
		}
		$( '.cpg_checkout_files_upload_file_validate_image_dimensions' ).on( 'change', toggleImageWidthHeight );
		toggleImageWidthHeight();
		
	});
	
})( jQuery );
