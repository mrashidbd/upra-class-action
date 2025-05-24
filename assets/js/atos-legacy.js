/**
 * ATOS Legacy JavaScript
 * Maintains backward compatibility with original ATOS form implementation
 */

jQuery(document).ready(function($){
    
    // Set total shares value for legacy support
    const total = $('input#inline-share-data');
    if (total.length > 0) {
        total.val(parseInt(ajax.total_share || 0));
    }

    // Legacy form references
    const sendForm = $('form#send_data');
    const ajaxUrl = ajax.url;

    // IP detection (simplified - original used external service)
    function getIP(){
        // Simplified IP detection - in production you might want to use a service
        return {
            ipAddress: 'Unknown',
            countryName: 'Unknown'
        };
    }

    // Clear dynamic feedback messages
    function clearDynFeeds(){
        $('p#success_message').empty().addClass('hidden');
        $('h4#duplicate_data').empty().addClass('hidden');
        $('ul#no_values').empty().addClass('hidden');
        $('#error_title').empty().addClass('hidden');
    }

    // Legacy form submission handler
    sendForm.on('submit', function(e) {
        e.preventDefault();

        // Check if new form handler exists
        if ($('#upra-shareholder-form').length > 0) {
            // Redirect to new form handler
            var newForm = $('#upra-shareholder-form');
            
            // Map legacy fields to new fields
            mapLegacyToNewForm(sendForm, newForm);
            
            // Submit new form
            newForm.submit();
            return;
        }

        // Legacy form submission
        handleLegacyFormSubmission();
    });

    /**
     * Map legacy form fields to new form
     */
    function mapLegacyToNewForm(legacyForm, newForm) {
        var fieldMapping = {
            'name': 'name',
            'email': 'email',
            'phone': 'phone', 
            'stock': 'stock',
            'purchase': 'purchase',
            'sell': 'sell',
            'loss': 'loss',
            'remarks': 'remarks'
        };

        Object.keys(fieldMapping).forEach(function(legacyField) {
            var newField = fieldMapping[legacyField];
            var value = legacyForm.find('[name="' + legacyField + '"]').val();
            newForm.find('[name="' + newField + '"]').val(value || '');
        });
    }

    /**
     * Handle legacy form submission
     */
    function handleLegacyFormSubmission() {
        // Get IP info (simplified)
        const ipCall = getIP();
        
        // Prepare form data
        const form = sendForm.serialize() + 
                    '&ip=' + encodeURIComponent(ipCall.ipAddress) + 
                    '&country=' + encodeURIComponent(ipCall.countryName);
        
        const formData = new FormData();
        formData.append('action', 'kdb_add_member');
        formData.append('add_member', form);

        // Submit via AJAX
        $.ajax({ 
            url: ajaxUrl,
            data: formData,
            type: 'post', 
            dataType: 'json',
            processData: false,
            contentType: false,
            beforeSend: function() {
                // Add loading state
                var submitBtn = sendForm.find('button[type="submit"]');
                submitBtn.prop('disabled', true).text('Submitting...');
            },
            success: function(result) {
                handleLegacyResponse(result);
            },
            error: function(xhr, status, error) {
                clearDynFeeds();
                showLegacyError('An error occurred. Please try again.');
            },
            complete: function() {
                // Remove loading state
                var submitBtn = sendForm.find('button[type="submit"]');
                submitBtn.prop('disabled', false).text('Submit');
            }
        });
    }

    /**
     * Handle legacy AJAX response
     */
    function handleLegacyResponse(result) {
        if (result.success === true) {
            clearDynFeeds();

            // Show success message
            var successContainer = $('p#success_message');
            if (successContainer.hasClass('hidden')) {
                successContainer.html(result.data).removeClass('hidden').show();
            } else {
                successContainer.html(result.data).show();
            }

            // Reset form
            sendForm[0].reset();

            // Update statistics if available
            if (typeof result.stats !== 'undefined') {
                updateLegacyStats(result.stats);
            }

        } else if (result.data && result.data.message) {
            clearDynFeeds();

            // Show duplicate/error message
            var duplicateContainer = $('h4#duplicate_data');
            if (duplicateContainer.hasClass('hidden')) {
                duplicateContainer.html(result.data.message).removeClass('hidden').show();
            } else {
                duplicateContainer.html(result.data.message).show();
            }

        } else {
            clearDynFeeds();

            // Show validation errors
            var errorTitle = $('h4#error_title');
            var errorContainer = $('ul#no_values');
            
            if (errorTitle.hasClass('hidden')) {
                errorTitle.html('Please fill out the following fields:').removeClass('hidden').show();
            } else {
                errorTitle.html('Please fill out the following fields:').show();
            }

            if (Array.isArray(result.data)) {
                $.each(result.data, function(key, value) {
                    errorContainer.append('<li class="red-500">' + value + '</li>');
                });
            }
            
            errorContainer.removeClass('hidden').show();
        }
    }

    /**
     * Show legacy error message
     */
    function showLegacyError(message) {
        var errorContainer = $('h4#duplicate_data');
        if (errorContainer.hasClass('hidden')) {
            errorContainer.html(message).removeClass('hidden').show();
        } else {
            errorContainer.html(message).show();
        }
    }

    /**
     * Update legacy statistics display
     */
    function updateLegacyStats(stats) {
        if (stats.total_shares !== undefined) {
            // Update any total share displays
            $('.total-shares-display').text(parseInt(stats.total_shares).toLocaleString());
        }
    }

    // Initialize legacy support
    initializeLegacySupport();

    /**
     * Initialize legacy support features
     */
    function initializeLegacySupport() {
        // Ensure legacy message containers exist
        ensureLegacyContainers();
        
        // Set up legacy event listeners
        setupLegacyEventListeners();
        
        // Initialize legacy styling
        initializeLegacyStyling();
    }

    /**
     * Ensure legacy message containers exist
     */
    function ensureLegacyContainers() {
        var containers = [
            {id: 'success_message', classes: 'bg-green-100 green-500 px-4 py-2 hidden'},
            {id: 'duplicate_data', classes: 'bg-red-100 red-500 px-4 py-2 hidden'},
            {id: 'error_title', classes: 'bg-red-100 red-500 px-4 py-2 hidden'},
            {id: 'no_values', classes: 'bg-red-100 hidden'},
            {id: 'error_message', classes: 'bg-red-100 hidden'}
        ];

        containers.forEach(function(container) {
            if ($('#' + container.id).length === 0) {
                $('<div>', {
                    id: container.id,
                    class: container.classes
                }).appendTo('.form-container, .upra-form-container');
            }
        });
    }

    /**
     * Set up legacy event listeners
     */
    function setupLegacyEventListeners() {
        // Listen for new form events and update legacy displays
        $(document).on('upra_form_success', function(e, data) {
            clearDynFeeds();
            var successContainer = $('p#success_message');
            successContainer.html(data.message || 'Success!').removeClass('hidden').show();
        });

        $(document).on('upra_form_error', function(e, data) {
            clearDynFeeds();
            if (data.message) {
                showLegacyError(data.message);
            }
            if (data.errors && Array.isArray(data.errors)) {
                var errorContainer = $('ul#no_values');
                data.errors.forEach(function(error) {
                    errorContainer.append('<li class="red-500">' + error + '</li>');
                });
                errorContainer.removeClass('hidden').show();
            }
        });
    }

    /**
     * Initialize legacy styling
     */
    function initializeLegacyStyling() {
        // Add legacy CSS classes if they don't exist
        if ($('style#upra-legacy-styles').length === 0) {
            var legacyStyles = `
                <style id="upra-legacy-styles">
                .bg-red-100 { background-color: #fee; }
                .bg-green-100 { background-color: #efe; }
                .red-500 { color: #dc3545; }
                .green-500 { color: #28a745; }
                .hidden { display: none !important; }
                .px-4 { padding-left: 1rem; padding-right: 1rem; }
                .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
                </style>
            `;
            $('head').append(legacyStyles);
        }
    }

});