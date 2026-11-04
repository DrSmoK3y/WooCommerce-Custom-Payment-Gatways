// File: assets/admin.js (FINAL COMPLETE VERSION - V3.0 Discount Support)

jQuery(document).ready(function($) {
    
    // --- UTILS ---
    function showNote(msg, type='success') {
        var c = type==='success' ? '#d4edda' : '#f8d7da';
        var t = type==='success' ? '#155724' : '#721c24';
        var html = '<div style="background:'+c+';color:'+t+';padding:10px;margin-bottom:15px;border-radius:5px;border:1px solid '+t+'">'+msg+'</div>';
        $('.gateway-form-container').prepend(html);
        $('html, body').animate({ scrollTop: 0 }, 500);
        setTimeout(function(){ $('.gateway-form-container div').first().fadeOut(); }, 3000);
    }

    // --- QR CHECKBOX TOGGLE ---
    $('#qr_enabled_check').on('change', function() {
        if($(this).is(':checked')) {
            $('#qr_enabled').val('1');
            $('#qr-upload-row').slideDown();
        } else {
            $('#qr_enabled').val('0');
            $('#qr-upload-row').slideUp();
        }
    });
    
    // --- DISCOUNT CHECKBOX TOGGLE ---
    $('#discount_enabled_check').on('change', function() {
        if($(this).is(':checked')) {
            $('#discount_enabled').val('1');
            $('#discount-settings-row').slideDown();
        } else {
            $('#discount_enabled').val('0');
            $('#discount-settings-row').slideUp();
        }
    });

    // --- FORM SUBMIT (Add/Update) ---
    $('#gateway-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#submit-gateway');
        var originalText = $btn.text();
        
        if(!$.trim($('#gateway_id').val()) || !$.trim($('#title').val())) {
            alert('ID and Title are required'); return;
        }

        // --- NEW: DISCOUNT VALIDATION ---
        var isDiscount = $('#discount_enabled_check').is(':checked');
        if(isDiscount) {
            var discType = $('#discount_type').val();
            var discVal = parseFloat($('#discount_value').val());
            
            if(isNaN(discVal) || discVal < 0) {
                alert('Please enter a valid positive number for discount.'); return;
            }
            // Constraint: Percentage > 100 Validation
            if(discType === 'percent' && discVal > 100) {
                alert('Percentage Discount cannot be greater than 100.'); return;
            }
        }

        $btn.prop('disabled', true).text('Saving...');
        
        var isUpdate = $('#gateway_db_id').val() !== '';
        var action = isUpdate ? 'cpg_update_gateway' : 'cpg_add_gateway';
        var extra = '&action=' + action + '&nonce=' + cpg_admin_ajax.nonce;
        if(isUpdate) extra += '&id=' + $('#gateway_db_id').val();

        // Ensure Hidden inputs match checkboxes
        var qrVal = $('#qr_enabled_check').is(':checked') ? '1' : '0';
        $('#qr_enabled').val(qrVal);
        
        var discValNum = $('#discount_enabled_check').is(':checked') ? '1' : '0';
        $('#discount_enabled').val(discValNum);

        $.post(cpg_admin_ajax.ajax_url, $(this).serialize() + extra, function(res) {
            $btn.prop('disabled', false).text(originalText);
            if(res.success) {
                showNote('Gateway Saved Successfully!', 'success');
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                showNote('Error: ' + (res.data || 'Unknown'), 'error');
            }
        }).fail(function() {
            showNote('Server Error', 'error');
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // --- MEDIA UPLOADER ---
    var frame;
    $('.upload-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this);
        var inputId = btn.data('target'); 
        var previewId = btn.data('preview');
        
        frame = wp.media({ title: 'Select Image', button:{text:'Use'}, multiple:false });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            $(inputId).val(att.url);
            $(previewId).html('<img src="'+att.url+'" style="max-height:80px; margin-top:5px; border:1px solid #ccc; padding:2px;">');
        });
        frame.open();
    });

    // --- EDIT BUTTON ---
    $('.edit-gateway').on('click', function(){
        var d = $(this).data();
        $('#gateway_db_id').val(d.id);
        $('#gateway_id').val(d.gateway_id).prop('disabled', true);
        $('#title').val(d.title);
        $('#account_number').val(d.account_number);
        $('#account_title').val(d.account_title);
        $('#instructions').val(d.instructions);
        
        // Handle Icons
        $('#icon_url').val(d.icon_url);
        if(d.icon_url) $('#icon-preview').html('<img src="'+d.icon_url+'" style="max-height:50px;">');
        
        // Handle QR
        var hasQr = (d.qr_enabled == 1);
        $('#qr_enabled_check').prop('checked', hasQr).trigger('change');
        $('#qr_code_url').val(d.qr_code_url);
        if(d.qr_code_url) $('#qr-preview').html('<img src="'+d.qr_code_url+'" style="max-height:50px;">');
        
        // --- HANDLE DISCOUNT EDIT ---
        var hasDisc = (d.discount_enabled == 1);
        $('#discount_enabled_check').prop('checked', hasDisc).trigger('change');
        $('#discount_type').val(d.discount_type || 'fixed');
        $('#discount_value').val(d.discount_value || '');

        // Handle Custom Fields (JSON parsing)
        $('#custom-fields-container').empty();
        if(d.custom_fields){
            try {
                var fields = (typeof d.custom_fields === 'string') ? JSON.parse(d.custom_fields) : d.custom_fields;
                if(Array.isArray(fields)) {
                    fields.forEach(function(f){
                        addCustomField(f.title, f.value);
                    });
                }
            } catch(err) { console.log('JSON parse error', err); }
        }

        $('#form-title').text('Edit Gateway');
        $('#submit-gateway').text('Update Gateway');
        $('#cancel-edit').show();
        $('html, body').animate({ scrollTop: $('.gateway-form-container').offset().top }, 500);
    });

    // --- CANCEL EDIT ---
    $('#cancel-edit').click(function(e){
        e.preventDefault();
        $('#gateway-form')[0].reset();
        $('#gateway_id').prop('disabled', false);
        $('#gateway_db_id').val('');
        $('#custom-fields-container').empty();
        $('#icon-preview, #qr-preview').empty();
        
        $('#qr_enabled_check').prop('checked', false).trigger('change');
        $('#discount_enabled_check').prop('checked', false).trigger('change');
        
        $(this).hide();
        $('#form-title').text('Add New Gateway');
        $('#submit-gateway').text('Save Gateway');
    });
    
    // --- CUSTOM FIELDS REPEATER ---
    $('#add-custom-field').click(function(){ addCustomField(); });
    $(document).on('click', '.remove-field', function(){ $(this).parent().remove(); });
    
    function addCustomField(t='', v='') {
        var html = '<div class="custom-field-row" style="margin-bottom:10px; display:flex; gap:10px;">'+
        '<input type="text" name="custom_field_title[]" placeholder="Label (e.g. Branch)" value="'+t+'" style="flex:1;">'+
        '<input type="text" name="custom_field_value[]" placeholder="Value" value="'+v+'" style="flex:1;">'+
        '<button type="button" class="button remove-field">X</button>'+
        '</div>';
        $('#custom-fields-container').append(html);
    }
    
    // --- DELETE / TOGGLE ---
    $('.delete-gateway').click(function(){ 
        if(confirm('Delete?')) $.post(cpg_admin_ajax.ajax_url, {action:'cpg_delete_gateway', id:$(this).data('id'), nonce:cpg_admin_ajax.nonce}, function(){ location.reload(); });
    });
    
    $('.toggle-enabled').change(function(){
        $.post(cpg_admin_ajax.ajax_url, {action:'cpg_update_gateway', id:$(this).data('id'), enabled: $(this).is(':checked')?1:0, nonce:cpg_admin_ajax.nonce});
    });
});