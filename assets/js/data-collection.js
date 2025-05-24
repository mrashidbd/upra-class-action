/**
 * Data Collection Form Handler
 * Handles form submission with proper nonce verification
 */

jQuery(document).ready(function($) {
    
    // Form submission handler
    $('#upra-shareholder-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var submitText = submitBtn.find('.upra-submit-text');
        var submitLoading = submitBtn.find('.upra-submit-loading');
        
        // Clear previous messages
        clearMessages();
        
        // Show loading state
        submitBtn.prop('disabled', true);
        submitText.addClass('hidden');
        submitLoading.removeClass('hidden');
        
        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'upra_add_shareholder');
        formData.append('nonce', upra_ajax.nonce);
        formData.append('shareholder_data', form.serialize());
        
        // Submit via AJAX
        $.ajax({
            url: upra_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                handleFormResponse(response, form);
            },
            error: function(xhr, status, error) {
                handleFormError(error);
            },
            complete: function() {
                // Reset loading state
                submitBtn.prop('disabled', false);
                submitText.removeClass('hidden');
                submitLoading.addClass('hidden');
            }
        });
    });
    
    /**
     * Handle successful form response
     */
    function handleFormResponse(response, form) {
        if (response.success) {
            // Show success message
            showMessage(response.data.message || upra_ajax.messages.success, 'success');
            
            // Reset form
            form[0].reset();
            
            // Update statistics if available
            if (response.data.stats) {
                updateStatistics(response.data.stats);
            }
            
            // Trigger custom event for backward compatibility
            $(document).trigger('upra_form_success', [response.data]);
            
            // Scroll to success message
            scrollToMessage();
            
        } else {
            // Handle validation errors
            if (response.data && Array.isArray(response.data)) {
                showValidationErrors(response.data);
                $(document).trigger('upra_form_error', [{errors: response.data}]);
            } else if (response.data && response.data.message) {
                showMessage(response.data.message, 'error');
                $(document).trigger('upra_form_error', [{message: response.data.message}]);
            } else {
                showMessage(upra_ajax.messages.error, 'error');
                $(document).trigger('upra_form_error', [{message: upra_ajax.messages.error}]);
            }
        }
    }
    
    /**
     * Handle form error
     */
    function handleFormError(error) {
        showMessage(upra_ajax.messages.error, 'error');
        console.error('Form submission error:', error);
    }
    
    /**
     * Clear all messages
     */
    function clearMessages() {
        $('#upra-success-message').addClass('hidden').empty();
        $('#upra-error-message').addClass('hidden').empty();
        $('#upra-validation-errors').addClass('hidden').find('ul').empty();
    }
    
    /**
     * Show success or error message
     */
    function showMessage(message, type) {
        var messageContainer = $('#upra-' + type + '-message');
        if (messageContainer.length > 0) {
            messageContainer.html(message).removeClass('hidden');
        }
    }
    
    /**
     * Show validation errors
     */
    function showValidationErrors(errors) {
        var container = $('#upra-validation-errors');
        var list = container.find('ul');
        
        // Clear existing errors
        list.empty();
        
        // Add each error
        errors.forEach(function(error) {
            list.append('<li>' + error + '</li>');
        });
        
        // Show validation message
        container.removeClass('hidden');
        
        // Show general validation header
        showMessage(upra_ajax.messages.validation_error, 'error');
    }
    
    /**
     * Update statistics display
     */
    function updateStatistics(stats) {
        if (stats.total_shares !== undefined) {
            $('#upra-total-shares').text(numberWithCommas(stats.total_shares));
        }
        if (stats.shareholders_count !== undefined) {
            $('#upra-total-people').text(numberWithCommas(stats.shareholders_count));
        }
        if (stats.total_participation !== undefined) {
            $('#upra-total-participation').text(numberWithCommas(stats.total_participation.toFixed(2)));
        }
    }
    
    /**
     * Scroll to message area
     */
    function scrollToMessage() {
        var messageArea = $('.upra-form-messages');
        if (messageArea.length > 0) {
            $('html, body').animate({
                scrollTop: messageArea.offset().top - 100
            }, 500);
        }
    }
    
    /**
     * Format number with commas
     */
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    /**
     * Legacy form support
     * Handle old form submissions and redirect to new system
     */
    $(document).on('submit', '#send_data', function(e) {
        e.preventDefault();
        
        var oldForm = $(this);
        var newForm = $('#upra-shareholder-form');
        
        if (newForm.length > 0) {
            // Map old form data to new form
            mapLegacyFormData(oldForm, newForm);
            
            // Submit new form
            newForm.submit();
        } else {
            // Fallback to legacy AJAX if new form not available
            handleLegacySubmission(oldForm);
        }
    });
    
    /**
     * Map legacy form data to new form
     */
    function mapLegacyFormData(oldForm, newForm) {
        var mapping = {
            'name': 'name',
            'email': 'email', 
            'phone': 'phone',
            'stock': 'stock',
            'purchase': 'purchase',
            'sell': 'sell',
            'loss': 'loss',
            'remarks': 'remarks'
        };
        
        Object.keys(mapping).forEach(function(oldField) {
            var newField = mapping[oldField];
            var value = oldForm.find('[name="' + oldField + '"]').val();
            newForm.find('[name="' + newField + '"]').val(value || '');
        });
    }
    
    /**
     * Handle legacy form submission
     */
    function handleLegacySubmission(form) {
        var formData = new FormData();
        formData.append('action', 'kdb_add_member'); // Legacy action
        formData.append('add_member', form.serialize());
        
        $.ajax({
            url: upra_ajax.ajax_url || ajax.url, // Try new then old
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showLegacyMessage(response.data, 'success');
                    form[0].reset();
                } else {
                    if (response.data.message) {
                        showLegacyMessage(response.data.message, 'error');
                    } else if (Array.isArray(response.data)) {
                        showLegacyValidationErrors(response.data);
                    }
                }
            },
            error: function() {
                showLegacyMessage('An error occurred. Please try again.', 'error');
            }
        });
    }
    
    /**
     * Show legacy messages
     */
    function showLegacyMessage(message, type) {
        clearLegacyMessages();
        
        var containerId = type === 'success' ? 'success_message' : 'duplicate_data';
        var container = $('#' + containerId);
        
        if (container.length > 0) {
            container.html(message).removeClass('hidden').show();
        }
    }
    
    /**
     * Show legacy validation errors
     */
    function showLegacyValidationErrors(errors) {
        clearLegacyMessages();
        
        var container = $('#no_values');
        var errorTitle = $('#error_title');
        
        if (errorTitle.length > 0) {
            errorTitle.html('Please fill out the following fields:').removeClass('hidden').show();
        }
        
        if (container.length > 0) {
            container.empty();
            errors.forEach(function(error) {
                container.append('<li class="red-500">' + error + '</li>');
            });
            container.removeClass('hidden').show();
        }
    }
    
    /**
     * Clear legacy messages
     */
    function clearLegacyMessages() {
        $('#success_message, #duplicate_data, #error_title, #no_values').empty().addClass('hidden').hide();
    }
    
    // Auto-update statistics on page load for legacy support
    if (typeof upra_stats !== 'undefined') {
        updateStatistics(upra_stats);
    }
    
    // Legacy variable support
    if (typeof ajax_front !== 'undefined') {
        $('#total_share').text(numberWithCommas(ajax_front.total_share));
        $('#total_people').text(numberWithCommas(ajax_front.total_people));
    }
    
});