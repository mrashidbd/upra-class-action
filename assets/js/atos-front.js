/**
 * ATOS Frontend Statistics Display
 * Handles the display of statistics on homepage and frontend pages
 */

jQuery(document).ready(function($){
    
    // Update total shares display
    const totalSharesElement = $('p#total_share, #total_share, .total-shares');
    if (totalSharesElement.length > 0) {
        var totalShares = parseInt(ajax_front.total_share || 0);
        totalSharesElement.text(totalShares.toLocaleString());
    }
    
    // Update total people display  
    const totalPeopleElement = $('p#total_people, #total_people, .total-people');
    if (totalPeopleElement.length > 0) {
        var totalPeople = parseInt(ajax_front.total_people || 0);
        totalPeopleElement.text(totalPeople.toLocaleString());
    }

    // Update UPRA statistics displays (new format)
    updateUpraStatistics();

    // Set up auto-refresh for statistics (every 30 seconds)
    setInterval(function() {
        refreshStatistics();
    }, 30000);

    /**
     * Update UPRA statistics displays
     */
    function updateUpraStatistics() {
        // Update total shares
        $('#upra-total-shares').text(parseInt(ajax_front.total_share || 0).toLocaleString());
        
        // Update total people
        $('#upra-total-people').text(parseInt(ajax_front.total_people || 0).toLocaleString());
        
        // Update any other statistics containers
        $('.upra-stat-shares').text(parseInt(ajax_front.total_share || 0).toLocaleString());
        $('.upra-stat-people').text(parseInt(ajax_front.total_people || 0).toLocaleString());
    }

    /**
     * Refresh statistics via AJAX
     */
    function refreshStatistics() {
        $.ajax({
            url: ajax_front.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'upra_get_company_stats',
                company: 'atos'
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateStatisticsFromResponse(response.data);
                }
            },
            error: function() {
                // Silently fail - don't disrupt user experience
                console.log('Failed to refresh statistics');
            }
        });
    }

    /**
     * Update statistics from AJAX response
     */
    function updateStatisticsFromResponse(stats) {
        if (stats.total_shares !== undefined) {
            // Legacy elements
            $('p#total_share, #total_share').text(parseInt(stats.total_shares).toLocaleString());
            
            // New elements
            $('#upra-total-shares, .upra-stat-shares').text(parseInt(stats.total_shares).toLocaleString());
        }

        if (stats.shareholders_count !== undefined) {
            // Legacy elements
            $('p#total_people, #total_people').text(parseInt(stats.shareholders_count).toLocaleString());
            
            // New elements
            $('#upra-total-people, .upra-stat-people').text(parseInt(stats.shareholders_count).toLocaleString());
        }

        if (stats.total_participation !== undefined) {
            // New elements only (participation wasn't in original)
            $('#upra-total-participation, .upra-stat-participation').text(parseFloat(stats.total_participation).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        }
    }

    /**
     * Format numbers with proper localization
     */
    function formatNumber(number, decimals = 0) {
        return parseFloat(number).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    /**
     * Handle statistics animation (optional enhancement)
     */
    function animateStatistics() {
        $('.upra-stat-value').each(function() {
            var $this = $(this);
            var targetValue = parseInt($this.text().replace(/,/g, ''));
            
            if (!isNaN(targetValue)) {
                animateCounter($this, 0, targetValue, 1000);
            }
        });
    }

    /**
     * Animate counter from start to end value
     */
    function animateCounter($element, start, end, duration) {
        var startTime = null;
        
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var current = Math.floor(progress * (end - start) + start);
            
            $element.text(current.toLocaleString());
            
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }
        
        requestAnimationFrame(step);
    }

    /**
     * Initialize statistics displays
     */
    function initializeStatistics() {
        // Update all statistics on page load
        updateUpraStatistics();
        
        // Animate statistics if enabled
        if ($('.upra-stats-animated').length > 0) {
            setTimeout(animateStatistics, 500);
        }
    }

    // Initialize on page load
    initializeStatistics();

    // Handle page visibility changes (refresh when page becomes visible)
    if (typeof document.hidden !== "undefined") {
        document.addEventListener("visibilitychange", function() {
            if (!document.hidden) {
                refreshStatistics();
            }
        });
    }

});