// File: assets/frontend-custom.js (FINAL COMPLETE VERSION - V2.0)

jQuery(document).ready(function($) {
    'use strict';

    // 1. Feature: Copy to Clipboard
    $(document.body).on('click', '.cpg-copy-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        // Find the value within the same row (sibling with class .val)
        var textToCopy = $btn.siblings('.val').text().trim();
        
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val(textToCopy).select();
        document.execCommand("copy");
        $temp.remove();

        var originalHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-yes" style="color:green;"></span>');
        setTimeout(function(){ $btn.html(originalHtml); }, 1500);
    });

    // 2. Feature: Toggle QR Code
    $(document.body).on('click', '.cpg-toggle-qr-view', function(e) {
        e.preventDefault();
        // Find parent container
        var $container = $(this).closest('.cpg-gateway-container');
        var $details = $container.find('.cpg-view-details');
        var $qr = $container.find('.cpg-view-qr');
        
        if($details.is(':visible')){
            // Switch to QR
            $details.slideUp(200);
            $qr.slideDown(200);
            $(this).html('<span class="dashicons dashicons-text" style="margin-top:2px;"></span> Show Details');
        } else {
            // Switch to Details
            $qr.slideUp(200);
            $details.slideDown(200);
            $(this).html('<span class="dashicons dashicons-qr-code" style="margin-top:2px;"></span> Show QR');
        }
    });
});