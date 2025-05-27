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
        initializeEditHandlers();
        initializeViewHandlers();
        initializeTableActions();
        initializeFormValidation();
        initializeStatisticsRefresh();
    }
    
    /**
     * Export data handlers
     */
    function initializeExportHandlers() {
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
                    var downloadUrl = upra_admin.ajax_url + 
                        '?action=upra_download_export' +
                        '&company=' + encodeURIComponent(company) +
                        '&format=' + encodeURIComponent(format) +
                        '&nonce=' + encodeURIComponent(upra_admin.nonce);
                    
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
        
        $('.upra-bulk-email-btn').on('click', function(e) {
            e.preventDefault();
            
            var company = $(this).data('company');
            if (company) {
                window.location.href = 'admin.php?page=upra-class-action-tools&company=' + company + '&action=bulk_email';
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
        $(document).on('click', '.upra-delete-shareholder', function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            var company = $(this).data('company');
            var nonce = $(this).data('nonce');
            var row = $(this).closest('tr');
            
            if (!confirm(upra_admin.messages.confirm_delete || 'Are you sure you want to delete this shareholder?')) {
                return;
            }
            
            deleteShareholder(id, company, nonce, row);
        });
    }
    
    /**
     * Delete individual shareholder
     */
    function deleteShareholder(id, company, nonce, row) {
        showLoading('Deleting shareholder...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_delete_shareholder',
                id: id,
                company: company,
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
     * Edit handlers
     */
    function initializeEditHandlers() {
        $(document).on('click', '.upra-edit-shareholder', function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            var company = $(this).data('company');
            
            openEditModal(id, company);
        });
    }
    
    /**
     * Open edit modal
     */
    function openEditModal(id, company) {
        // Clean up any existing modals first
        $('#upra-edit-modal, #upra-view-modal').remove();
        
        showLoading('Loading shareholder data...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_get_shareholder',
                id: id,
                company: company,
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showEditModal(response.data);
                } else {
                    showNotice('Failed to load shareholder data', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Failed to load shareholder data', 'error');
            }
        });
    }
    
    /**
     * Show edit modal
     */
    function showEditModal(data) {
        var modalHtml = `
            <div id="upra-edit-modal" class="upra-modal">
                <div class="upra-modal-content">
                    <div class="upra-modal-header">
                        <h2>Edit Shareholder</h2>
                        <span class="upra-modal-close">&times;</span>
                    </div>
                    <div class="upra-modal-body">
                        <form id="upra-edit-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <input type="hidden" name="company" value="${data.company}">
                            
                            <table class="form-table">
                                <tr>
                                    <th><label for="edit-name">Name</label></th>
                                    <td><input type="text" id="edit-name" name="stockholder_name" value="${data.stockholder_name}" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-email">Email</label></th>
                                    <td><input type="email" id="edit-email" name="email" value="${data.email}" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-phone">Phone</label></th>
                                    <td><input type="text" id="edit-phone" name="phone" value="${data.phone}" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-stock">Stock</label></th>
                                    <td><input type="number" id="edit-stock" name="stock" value="${data.stock}" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-purchase">Purchase Price</label></th>
                                    <td><input type="number" id="edit-purchase" name="purchase_price" value="${data.purchase_price}" step="0.01" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-sell">Sell Price</label></th>
                                    <td><input type="number" id="edit-sell" name="sell_price" value="${data.sell_price}" step="0.01" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-loss">Loss</label></th>
                                    <td><input type="number" id="edit-loss" name="loss" value="${data.loss}" step="0.01" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="edit-remarks">Remarks</label></th>
                                    <td><textarea id="edit-remarks" name="remarks" rows="3" class="large-text">${data.remarks || ''}</textarea></td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary">Update Shareholder</button>
                                <button type="button" class="button upra-modal-close">Cancel</button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Handle modal close
        $(document).on('click', '.upra-modal-close', function() {
            $('#upra-edit-modal, #upra-view-modal').remove();
        });
        
        // Handle form submission - use document delegation
        $(document).on('submit', '#upra-edit-form', function(e) {
            e.preventDefault();
            submitEditForm($(this));
        });
    }
    
    /**
     * Submit edit form
     */
    function submitEditForm(form) {
        showLoading('Updating shareholder...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_update_shareholder',
                form_data: form.serialize(),
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    $('#upra-edit-modal').remove();
                    showNotice('Shareholder updated successfully', 'success');
                    location.reload(); // Refresh the page to show updated data
                } else {
                    showNotice(response.data || 'Failed to update shareholder', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Update request failed', 'error');
            }
        });
    }
    
    /**
     * View handlers
     */
    function initializeViewHandlers() {
        $(document).on('click', '.upra-view-shareholder', function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            var company = $(this).data('company');
            
            openViewModal(id, company);
        });
    }
    
    /**
     * Open view modal
     */
    function openViewModal(id, company) {
        // Clean up any existing modals first
        $('#upra-edit-modal, #upra-view-modal').remove();
        
        showLoading('Loading shareholder data...');
        
        $.ajax({
            url: upra_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'upra_get_shareholder',
                id: id,
                company: company,
                nonce: upra_admin.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showViewModal(response.data);
                } else {
                    showNotice('Failed to load shareholder data', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Failed to load shareholder data', 'error');
            }
        });
    }
    
    /**
     * Show view modal
     */
    function showViewModal(data) {
        var modalHtml = `
            <div id="upra-view-modal" class="upra-modal">
                <div class="upra-modal-content">
                    <div class="upra-modal-header">
                        <h2>Shareholder Details</h2>
                        <span class="upra-modal-close">&times;</span>
                    </div>
                    <div class="upra-modal-body">
                        <table class="form-table">
                            <tr><th>ID:</th><td>${data.id}</td></tr>
                            <tr><th>Name:</th><td>${data.stockholder_name}</td></tr>
                            <tr><th>Email:</th><td><a href="mailto:${data.email}">${data.email}</a></td></tr>
                            <tr><th>Phone:</th><td>${data.phone}</td></tr>
                            <tr><th>Stock:</th><td>${numberFormat(data.stock)}</td></tr>
                            <tr><th>Purchase Price:</th><td>${numberFormat(data.purchase_price, 2)}</td></tr>
                            <tr><th>Sell Price:</th><td>${numberFormat(data.sell_price, 2)}</td></tr>
                            <tr><th>Loss:</th><td>${numberFormat(data.loss, 2)}</td></tr>
                            <tr><th>IP Address:</th><td>${data.ip || 'N/A'}</td></tr>
                            <tr><th>Country:</th><td>${data.country || 'N/A'}</td></tr>
                            <tr><th>Remarks:</th><td>${data.remarks || 'N/A'}</td></tr>
                            <tr><th>Registration Date:</th><td>${formatDate(data.created_at)}</td></tr>
                        </table>
                        
                        <p class="submit">
                            <button type="button" class="button button-primary upra-modal-close">Close</button>
                            <button type="button" class="button upra-edit-shareholder" data-id="${data.id}" data-company="${data.company}">Edit</button>
                        </p>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Handle modal close
        $(document).on('click', '.upra-modal-close', function() {
            $('#upra-view-modal').remove();
        });
        
        // Handle edit button in view modal
        $('#upra-view-modal').on('click', '.upra-edit-shareholder', function() {
            var id = $(this).data('id');
            var company = $(this).data('company');
            
            // Close view modal first
            $('#upra-view-modal').remove();
            
            // Then open edit modal
            openEditModal(id, company);
        });
    }
    
    /**
     * Table action handlers
     */
    function initializeTableActions() {
        var searchTimer;
        $('.wp-list-table .search-box input[type="search"]').on('input', function() {
            clearTimeout(searchTimer);
            var searchTerm = $(this).val();
            
            searchTimer = setTimeout(function() {
                if (searchTerm.length >= 3 || searchTerm.length === 0) {
                    $(this).closest('form').submit();
                }
            }.bind(this), 500);
        });
    }
    
    /**
     * Form validation
     */
    function initializeFormValidation() {
        $('#upra-export-form').on('submit', function(e) {
            var company = $(this).find('[name="company"]').val();
            if (!company) {
                e.preventDefault();
                alert('Please select a company');
                $(this).find('[name="company"]').focus();
            }
        });
        
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
        setInterval(refreshStatistics, 300000);
        
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
            
            $('.upra-stat-' + company + '-shares').text(numberFormat(companyStats.shares || 0));
            $('.upra-stat-' + company + '-people').text(numberFormat(companyStats.shareholders || 0));
            $('.upra-stat-' + company + '-participation').text(numberFormat(companyStats.participation || 0, 2));
        });
    }
    
    /**
     * Utility functions
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
    
    function hideLoading() {
        $('#upra-loading-modal').hide();
    }
    
    function showNotice(message, type) {
        type = type || 'info';
        
        var notice = $('<div class="notice notice-' + type + ' is-dismissible upra-notice upra-notice-' + type + '">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
            '</div>');
        
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    function numberFormat(number, decimals) {
        decimals = decimals || 0;
        return parseFloat(number).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    function updateTableCounts() {
        var visibleRows = $('.wp-list-table tbody tr:visible').length;
        $('.displaying-num').text(visibleRows + ' items');
    }
    
    // Add modal CSS
    if ($('#upra-modal-styles').length === 0) {
        $('head').append(`
            <style id="upra-modal-styles">
            .upra-modal {
                display: block;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }
            .upra-modal-content {
                background-color: #fff;
                margin: 5% auto;
                padding: 0;
                border: 1px solid #ccc;
                border-radius: 4px;
                width: 80%;
                max-width: 800px;
                max-height: 90%;
                overflow-y: auto;
            }
            .upra-modal-header {
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                background: #f1f1f1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .upra-modal-header h2 {
                margin: 0;
                font-size: 18px;
            }
            .upra-modal-close {
                font-size: 24px;
                font-weight: bold;
                cursor: pointer;
                color: #999;
            }
            .upra-modal-close:hover {
                color: #000;
            }
            .upra-modal-body {
                padding: 20px;
            }
            </style>
        `);
    }
    
});