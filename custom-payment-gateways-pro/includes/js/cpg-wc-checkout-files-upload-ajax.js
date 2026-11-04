(function( $ ) {
	"use strict";

	var $container;

	var queue = [];
	var activeQueue = [];
	var maxWorkers = 4;
	var suppressedErrors = {
		fail_max_files: false
	}

	var processQueue = function() {

		var key;

		for ( var i = 0; i < queue.length; i++ ) {
			if ( activeQueue.length < maxWorkers ) {
				activeQueue.push( queue.shift() );
				key = activeQueue.length - 1;
				activeQueue[key].upload.doUpload( activeQueue[key].fileUploader, key );
			}
		}

		if ( queue.length ) {
			setTimeout( processQueue, 100 );
		} else {
			suppressedErrors.fail_max_files = false;
		}

	}

	var blockCheckout = function() {
		$container.block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
	};

	var unblockCheckout = function() {
		$container.unblock();
	};

	var Upload = function ( file ) {
		this.file = file;
	};
	Upload.prototype.getType = function() {
		return this.file.type;
	};
	Upload.prototype.getSize = function() {
		return this.file.size;
	};
	Upload.prototype.getName = function() {
		return this.file.name;
	};
	Upload.prototype.doUpload = function ( fileUploader, queuePosition ) {

		blockCheckout();

		var formData = new FormData();
		formData.append( 'file', this.file, this.getName() );
		formData.append( 'action', 'cpg_ajax_file_upload' );
		formData.append( 'file_uploader', fileUploader );
		formData.append( 'order_id', $( '#cpg_checkout_files_upload_order_id_' + fileUploader ).val() );
		formData.append( 'key', $( '#cpg_checkout_files_upload_order_key_' + fileUploader ).val() );
		formData.append( 'nonce', $( '#wpwham-checkout-files-upload-nonce-' + fileUploader ).val() );

		if ( cpg_wc_checkout_files_upload.progress_bar_enabled ) {
			var progressBarId = 'wpw_cfu_' + Date.now();

			var $progressBarTemplate = $( '#cpg-wc-checkout-files-upload-progress-wrapper-' + fileUploader );
			var $progressBar = $progressBarTemplate.clone();
			$progressBar.first().attr( 'id', progressBarId );
			$( '#cpg_checkout_files_upload_form_' + fileUploader +
				' .cpg_checkout_files_upload_result_' + fileUploader + ', ' +
				'#cpg_checkout_files_upload_form_' + fileUploader +
				' .cpg-wc-checkout-files-upload-progress-wrapper'
			).last().after( $progressBar.show() );
		}

		$.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			xhr: function () {
				var xhr = $.ajaxSettings.xhr();
				if ( cpg_wc_checkout_files_upload.progress_bar_enabled && xhr.upload ) {
					xhr.upload.fileUploader = fileUploader;
					xhr.upload.addEventListener( 'progress', function( event ) {
						if ( event.lengthComputable ) {
							var percentComplete = ( event.loaded / event.total ) * 100;
							percentComplete = percentComplete.toFixed( 0 );
							$( '#' + progressBarId + ' .cpg-wc-checkout-files-upload-progress-bar' )
								.css( 'width', percentComplete + '%' );
							$( '#' + progressBarId + ' .cpg-wc-checkout-files-upload-progress-status' )
								.text( percentComplete + '%' );
						}
					}, false );
				}
				return xhr;
			},
			success: function (data) {
				var $template;
				var $result;
				var data_decoded = $.parseJSON(data);
				if ( data_decoded['result'] != 0 ) {

					$template = $( '#cpg_checkout_files_upload_result_' + fileUploader );
					$result = $template.clone();
					$result.first().removeAttr( 'id' );
					$result.find( '.cpg_checkout_files_upload_result_file_name' )
						.html( data_decoded['data'] );
					if ( typeof data_decoded['data_img'] !== 'undefined' && data_decoded['data_img'] > '') {
						$result.find( '.cpg_checkout_files_upload_result_image' )
							.html( data_decoded['data_img'] );
					}
					if ( cpg_wc_checkout_files_upload.progress_bar_enabled ) {
						$result.data( 'progress-bar-id', progressBarId );
						$( '#' + progressBarId ).before( $result.show() );
					} else {
						$( '.cpg_checkout_files_upload_result_' + fileUploader ).last()
							.after( $result.show() );
					}

					if ( ! $( '#cpg_checkout_files_upload_' + fileUploader ).attr( 'multiple' ) ) {
						$( '#cpg_checkout_files_upload_button_' + fileUploader ).hide();
					}
				} else {
					if ( cpg_wc_checkout_files_upload.progress_bar_enabled ) {
						$( '#' + progressBarId ).remove();
					}
				}
				if ( data_decoded['message'] != '' ) {
					if (
						typeof data_decoded['error_code'] !== "undefined"
						&& data_decoded['error_code'] === 'fail_max_files'
					) {
						if ( ! suppressedErrors.fail_max_files ) {
							alert( data_decoded['message'] );
						}
						suppressedErrors.fail_max_files = true;
						queue = [];
					} else {
						alert( data_decoded['message'] );
					}
				}
				activeQueue.splice( queuePosition, 1);
				unblockCheckout();
			},
			error: function (error) {
				activeQueue.splice( queuePosition, 1);
				unblockCheckout();
			},
			async: true,
			data: formData,
			cache: false,
			contentType: false,
			processData: false,
			timeout: 60000
		});
	};

	$(document).ready(function() {

		$container = jQuery( 'form.woocommerce-checkout' ).closest( 'div' );

		$( document ).on( 'click', '.cpg_checkout_files_upload_button', function() {
			var fileUploader = $( this ).data( 'file-uploader' );
			$( '#cpg_checkout_files_upload_' + fileUploader ).click();
		});

		$( document ).on( 'change', '.cpg_checkout_files_upload_file_input', function() {
			var files = $( this )[0].files;
			var fileUploader = $( this ).data( 'file-uploader' );
			if ( ! files.length ) {
				return;
			}
			for ( var i = 0; i < files.length; i++ ) {
				var file = files[i];
				var upload = new Upload( file );
				var max_file_size = parseInt( cpg_wc_checkout_files_upload.max_file_size );
				if ( max_file_size > 0 && upload.getSize() > max_file_size ) {
					alert( cpg_wc_checkout_files_upload.max_file_size_exceeded_message );
				} else {
					queue.push({ 'fileUploader': fileUploader, 'upload': upload });
				}
			}
			processQueue();
			$( this ).val('');
		});

		$( document ).on( 'click', '.cpg_checkout_files_upload_result_delete', function( event ) {
			event.preventDefault();
			blockCheckout();
			var $fileContainer = $( this ).closest( 'div' );
			var fileUploader = $fileContainer.data( 'file-uploader' );
			var fileKey = $fileContainer.find( '.cpg_checkout_files_upload_result_file_name a' )
				.first().data( 'file-key' );
			var formData = new FormData();
			formData.append( 'action', 'cpg_ajax_file_delete' );
			formData.append( 'file_uploader', fileUploader );
			formData.append( 'file_key', fileKey );
			formData.append( 'order_id', $( '#cpg_checkout_files_upload_order_id_' + fileUploader ).val() );
			formData.append( 'nonce', $( '#wpwham-checkout-files-upload-nonce-' + fileUploader ).val() );
			$.ajax({
				type: "POST",
				url: ajax_object.ajax_url,
				success: function ( data ) {
					var data_decoded = $.parseJSON( data );
					if ( data_decoded['result'] != 0 ) {
						if ( cpg_wc_checkout_files_upload.progress_bar_enabled ) {
							$( '#' + $fileContainer.data( 'progress-bar-id' ) ).remove();
						}
						$fileContainer.remove();
						$( '#cpg_checkout_files_upload_button_' + fileUploader ).show();
					}
					if ( data_decoded['message'] != '' ) {
						alert( data_decoded['message'] );
					}
					unblockCheckout();
				},
				error: function (error) {
					unblockCheckout();
				},
				async: true,
				data: formData,
				cache: false,
				contentType: false,
				processData: false,
				timeout: 60000
			});
		});
	});

})( jQuery );