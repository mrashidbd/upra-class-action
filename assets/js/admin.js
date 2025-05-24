/**
 * Admin JavaScript for UPRA Class Action Plugin
 * Handles admin interface interactions and AJAX requests
 */

jQuery(document).ready(function($) {
    
    // Initialize admin functionality
    initializeAdmin();
    
    /**
     * Initialize admin features
     */
    function initializeAdmin() {
        initializeExportHandlers();
        initializeBulkEmailHandlers();
        initializeDeleteHandlers();
        initializeTableActions();
        initializeFormValidation();
        initializeStatisticsRefresh();
    }
    
    /**
     * Export data handlers
     */
    function initializeExportHandlers() {
        // Export button clicks
        $('.upra-export-btn').on('click', function(e) {
            e.preventDefault();
            
            var company = $(this).data('company');
            var format = $(this).data('format') || 'csv';
            
            if (!company) {
                alert(upra_admin.messages.error || 'Please select a company');
                return;
            }
            
            exportData(company, format);
        });
        
        // Export form submission
        $('#upra-export-form').on('submit', function(e) {
            e.preventDefault();
            
            var company = $(this).find('[name="company"]').val();
            var format = $(this).find('[name="format"]').val();
            
            if (!company) {
                alert('Please select a company');
                return;
            }
            
            exportData(company, format);
        });
    }
    
    /**
     * Export data via AJAX
     */
    function exportData(company, format) {
        showLoading('Preparing export...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_export_data',
                company: company,
                format: format,
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    // Create download link
                    var downloadUrl = upra_admin.ajax_url + 
                        '?action=upra_download_export' +
                        '&company=' + encodeURIComponent(company) +
                        '&format=' + encodeURIComponent(format) +
                        '&nonce=' + encodeURIComponent(upra_admin.nonce);
                    
                    // Trigger download
                    window.location.href = downloadUrl;
                    
                    showNotice(upra_admin.messages.export_success || 'Export completed successfully!', 'success');
                } else {
                    showNotice(response.data || 'Export failed', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Export request failed', 'error');
            }
        });
    }
    
    /**
     * Bulk email handlers
     */
    function initializeBulkEmailHandlers() {
        // Bulk email form submission
        $('#upra-bulk-email-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var company = form.find('[name="company"]').val();
            var subject = form.find('[name="subject"]').val();
            var message = form.find('[name="message"]').val();
            
            if (!company || !subject || !message) {
                alert('Please fill in all fields');
                return;
            }
            
            if (!confirm(upra_admin.messages.confirm_bulk_email || 'Are you sure you want to send this email to all shareholders?')) {
                return;
            }
            
            sendBulkEmail(company, subject, message, form);
        });
        
        // Quick bulk email buttons
        $('.upra-bulk-email-btn').on('click', function(e) {
            e.preventDefault();
            
            var company = $(this).data('company');
            if (company) {
                window.location.href = 'admin.php?page=upra-shareholders-tools&company=' + company + '&action=bulk_email';
            }
        });
    }
    
    /**
     * Send bulk email via AJAX
     */
    function sendBulkEmail(company, subject, message, form) {
        showLoading('Sending emails...');
        
        form.find('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_send_bulk_email',
                company: company,
                subject: subject,
                message: message,
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                form.find('button[type="submit"]').prop('disabled', false);
                
                if (response.success) {
                    showNotice(response.data || upra_admin.messages.email_sent, 'success');
                    form[0].reset();
                } else {
                    showNotice(response.data || 'Failed to send emails', 'error');
                }
            },
            error: function() {
                hideLoading();
                form.find('button[type="submit"]').prop('disabled', false);
                showNotice('Email request failed', 'error');
            }
        });
    }
    
    /**
     * Delete handlers
     */
    function initializeDeleteHandlers() {
        // Individual delete buttons
        $(document).on('click', '.upra-delete-shareholder', function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            var nonce = $(this).data('nonce');
            var row = $(this).closest('tr');
            
            if (!confirm(upra_admin.messages.confirm_delete || 'Are you sure you want to delete this shareholder?')) {
                return;
            }
            
            deleteShareholder(id, nonce, row);
        });
        
        // Bulk delete handling
        $(document).on('click', '.bulk-delete-btn', function(e) {
            e.preventDefault();
            
            var selected = $('.wp-list-table input[name="shareholder[]"]:checked');
            if (selected.length === 0) {
                alert('Please select shareholders to delete');
                return;
            }
            
            if (!confirm('Are you sure you want to delete ' + selected.length + ' shareholders?')) {
                return;
            }
            
            var ids = [];
            selected.each(function() {
                ids.push($(this).val());
            });
            
            bulkDeleteShareholders(ids);
        });
    }
    
    /**
     * Delete individual shareholder
     */
    function deleteShareholder(id, nonce, row) {
        showLoading('Deleting shareholder...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_delete_shareholder',
                id: id,
                nonce: nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        updateTableCounts();
                    });
                    showNotice('Shareholder deleted successfully', 'success');
                } else {
                    showNotice(response.data || 'Failed to delete shareholder', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Delete request failed', 'error');
            }
        });
    }
    
    /**
     * Table action handlers
     */
    function initializeTableActions() {
        // Edit shareholder modal/form
        $(document).on('click', '.upra-edit-shareholder', function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            // TODO: Implement edit modal or redirect to edit page
            console.log('Edit shareholder:', id);
        });
        
        // View shareholder details
        $(document).on('click', '.upra-view-shareholder', function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            // TODO: Implement view modal or redirect to view page
            console.log('View shareholder:', id);
        });
        
        // Table search enhancement
        var searchTimer;
        $('.wp-list-table .search-box input[type="search"]').on('input', function() {
            clearTimeout(searchTimer);
            var searchTerm = $(this).val();
            
            searchTimer = setTimeout(function() {
                if (searchTerm.length >= 3 || searchTerm.length === 0) {
                    // Auto-submit search after 500ms delay
                    $(this).closest('form').submit();
                }
            }.bind(this), 500);
        });
    }
    
    /**
     * Form validation
     */
    function initializeFormValidation() {
        // Export form validation
        $('#upra-export-form').on('submit', function(e) {
            var company = $(this).find('[name="company"]').val();
            if (!company) {
                e.preventDefault();
                alert('Please select a company');
                $(this).find('[name="company"]').focus();
            }
        });
        
        // Bulk email form validation
        $('#upra-bulk-email-form').on('submit', function(e) {
            var company = $(this).find('[name="company"]').val();
            var subject = $(this).find('[name="subject"]').val().trim();
            var message = $(this).find('[name="message"]').val().trim();
            
            if (!company) {
                e.preventDefault();
                alert('Please select a company');
                $(this).find('[name="company"]').focus();
                return;
            }
            
            if (!subject) {
                e.preventDefault();
                alert('Please enter an email subject');
                $(this).find('[name="subject"]').focus();
                return;
            }
            
            if (!message) {
                e.preventDefault();
                alert('Please enter an email message');
                $(this).find('[name="message"]').focus();
                return;
            }
        });
    }
    
    /**
     * Statistics refresh
     */
    function initializeStatisticsRefresh() {
        // Auto-refresh statistics every 5 minutes
        setInterval(refreshStatistics, 300000);
        
        // Manual refresh button
        $('.upra-refresh-stats').on('click', function(e) {
            e.preventDefault();
            refreshStatistics();
        });
    }
    
    /**
     * Refresh statistics via AJAX
     */
    function refreshStatistics() {
        $('.upra-stat-value, .upra-card-value, .upra-stat-number').addClass('upra-loading');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_get_all_stats',
                nonce: upra_admin.nonce
            },
            success: function(response) {
                $('.upra-stat-value, .upra-card-value, .upra-stat-number').removeClass('upra-loading');
                
                if (response.success && response.data) {
                    updateStatisticsDisplay(response.data);
                }
            },
            error: function() {
                $('.upra-stat-value, .upra-card-value, .upra-stat-number').removeClass('upra-loading');
            }
        });
    }
    
    /**
     * Update statistics display
     */
    function updateStatisticsDisplay(stats) {
        Object.keys(stats).forEach(function(company) {
            var companyStats = stats[company];
            
            // Update company-specific displays
            $('.upra-stat-' + company + '-shares').text(numberFormat(companyStats.shares || 0));
            $('.upra-stat-' + company + '-people').text(numberFormat(companyStats.shareholders || 0));
            $('.upra-stat-' + company + '-participation').text(numberFormat(companyStats.participation || 0, 2));
        });
    }
    
    /**
     * Utility functions
     */
    
    /**
     * Show loading indicator
     */
    function showLoading(message) {
        if ($('#upra-loading-modal').length === 0) {
            $('body').append(
                '<div id="upra-loading-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
                    '<div style="background: white; padding: 20px; border-radius: 4px; text-align: center;">' +
                        '<div class="upra-spinner" style="margin-bottom: 10px;"></div>' +
                        '<div id="upra-loading-message">Loading...</div>' +
                    '</div>' +
                '</div>'
            );
        }
        
        $('#upra-loading-message').text(message || 'Loading...');
        $('#upra-loading-modal').show();
    }
    
    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('#upra-loading-modal').hide();
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        var notice = $('<div class="notice notice-' + type + ' is-dismissible upra-notice upra-notice-' + type + '">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
            '</div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Number formatting
     */
    function numberFormat(number, decimals) {
        decimals = decimals || 0;
        return parseFloat(number).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    /**
     * Update table counts after operations
     */
    function updateTableCounts() {
        var visibleRows = $('.wp-list-table tbody tr:visible').length;
        $('.displaying-num').text(visibleRows + ' items');
    }
    
    /**
     * Cleanup functions
     */
    function initializeCleanup() {
        // Cleanup old data button
        $('#upra-cleanup-btn').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clean up old data? This action cannot be undone.')) {
                return;
            }
            
            cleanupOldData();
        });
        
        // Optimize database button
        $('#upra-optimize-btn').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to optimize the database?')) {
                return;
            }
            
            optimizeDatabase();
        });
    }
    
    /**
     * Cleanup old data
     */
    function cleanupOldData() {
        showLoading('Cleaning up old data...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_cleanup_data',
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice(response.data || 'Data cleanup completed', 'success');
                } else {
                    showNotice(response.data || 'Cleanup failed', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Cleanup request failed', 'error');
            }
        });
    }
    
    /**
     * Optimize database
     */
    function optimizeDatabase() {
        showLoading('Optimizing database...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_optimize_database',
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice(response.data || 'Database optimization completed', 'success');
                } else {
                    showNotice(response.data || 'Optimization failed', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Optimization request failed', 'error');
            }
        });
    }
    
    // Initialize cleanup functions
    initializeCleanup();
    
    // Initialize tooltips (if available)
    if (typeof $.fn.tooltip === 'function') {
        $('[data-tooltip]').tooltip();
    }
    
    // Initialize sortable tables (if needed)
    if (typeof $.fn.sortable === 'function') {
        $('.upra-sortable').sortable({
            axis: 'y',
            helper: 'clone',
            opacity: 0.7
        });
    }
    
});