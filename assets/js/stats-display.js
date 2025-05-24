/**
 * Statistics Display JavaScript
 * Handles the display and animation of statistics on frontend pages
 */

jQuery(document).ready(function($) {
    
    // Initialize statistics display
    initializeStatsDisplay();
    
    /**
     * Initialize statistics display functionality
     */
    function initializeStatsDisplay() {
        updateStatisticsFromData();
        initializeCounterAnimations();
        initializeAutoRefresh();
        initializeVisibilityHandling();
    }
    
    /**
     * Update statistics from localized data
     */
    function updateStatisticsFromData() {
        if (typeof upra_stats !== 'undefined') {
            updateStatElements(upra_stats);
        }
        
        // Legacy support
        if (typeof ajax_front !== 'undefined') {
            updateLegacyElements(ajax_front);
        }
    }
    
    /**
     * Update statistic elements
     */
    function updateStatElements(stats) {
        // Update total shares
        if (stats.total_shares !== undefined) {
            updateElement('#upra-total-shares', stats.total_shares);
            updateElement('.upra-stat-shares', stats.total_shares);
            updateElement('.total-shares', stats.total_shares);
        }
        
        // Update total people
        if (stats.total_people !== undefined) {
            updateElement('#upra-total-people', stats.total_people);
            updateElement('.upra-stat-people', stats.total_people);
            updateElement('.total-people', stats.total_people);
        }
        
        // Update total participation
        if (stats.total_participation !== undefined) {
            updateElement('#upra-total-participation', stats.total_participation, 2);
            updateElement('.upra-stat-participation', stats.total_participation, 2);
            updateElement('.total-participation', stats.total_participation, 2);
        }
    }
    
    /**
     * Update legacy elements
     */
    function updateLegacyElements(legacyStats) {
        if (legacyStats.total_share !== undefined) {
            updateElement('#total_share', legacyStats.total_share);
            updateElement('p#total_share', legacyStats.total_share);
        }
        
        if (legacyStats.total_people !== undefined) {
            updateElement('#total_people', legacyStats.total_people);
            updateElement('p#total_people', legacyStats.total_people);
        }
    }
    
    /**
     * Update individual element
     */
    function updateElement(selector, value, decimals) {
        var elements = $(selector);
        if (elements.length > 0) {
            var formattedValue = formatNumber(value, decimals || 0);
            elements.text(formattedValue);
        }
    }
    
    /**
     * Initialize counter animations
     */
    function initializeCounterAnimations() {
        // Only animate if not already animated
        if ($('.upra-stat-value:not(.animated)').length > 0) {
            animateCounters();
        }
        
        // Animate on scroll for elements below the fold
        $(window).on('scroll', function() {
            animateVisibleCounters();
        });
    }
    
    /**
     * Animate all counters
     */
    function animateCounters() {
        $('.upra-stat-value:not(.animated)').each(function() {
            var $element = $(this);
            var targetValue = parseValue($element.text());
            
            if (!isNaN(targetValue) && targetValue > 0) {
                animateCounter($element, 0, targetValue, 2000);
                $element.addClass('animated');
            }
        });
    }
    
    /**
     * Animate counters that are currently visible
     */
    function animateVisibleCounters() {
        $('.upra-stat-value:not(.animated)').each(function() {
            var $element = $(this);
            
            if (isElementInViewport($element[0])) {
                var targetValue = parseValue($element.text());
                
                if (!isNaN(targetValue) && targetValue > 0) {
                    animateCounter($element, 0, targetValue, 2000);
                    $element.addClass('animated');
                }
            }
        });
    }
    
    /**
     * Animate counter from start to end value
     */
    function animateCounter($element, start, end, duration) {
        var startTime = null;
        var decimals = $element.hasClass('decimal') ? 2 : 0;
        
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            
            // Use easing function for smoother animation
            var easedProgress = easeOutQuart(progress);
            var current = Math.floor(easedProgress * (end - start) + start);
            
            $element.text(formatNumber(current, decimals));
            
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                // Ensure final value is exact
                $element.text(formatNumber(end, decimals));
            }
        }
        
        requestAnimationFrame(step);
    }
    
    /**
     * Easing function for animations
     */
    function easeOutQuart(t) {
        return 1 - (--t) * t * t * t;
    }
    
    /**
     * Check if element is in viewport
     */
    function isElementInViewport(el) {
        var rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    /**
     * Parse numeric value from text
     */
    function parseValue(text) {
        return parseInt(text.replace(/[^\d]/g, ''), 10);
    }
    
    /**
     * Format number with proper localization
     */
    function formatNumber(number, decimals) {
        decimals = decimals || 0;
        return parseFloat(number).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    /**
     * Initialize auto-refresh functionality
     */
    function initializeAutoRefresh() {
        // Refresh every 30 seconds
        setInterval(function() {
            refreshStatistics();
        }, 30000);
    }
    
    /**
     * Refresh statistics via AJAX
     */
    function refreshStatistics() {
        // Don't refresh if page is not visible
        if (document.hidden) {
            return;
        }
        
        var ajaxUrl = (typeof upra_stats !== 'undefined' && upra_stats.ajax_url) || 
                      (typeof ajaxurl !== 'undefined' && ajaxurl) || 
                      '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'upra_get_company_stats',
                company: getCurrentCompany()
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
        updateStatElements(stats);
        
        // Trigger custom event
        $(document).trigger('upra_stats_updated', [stats]);
    }
    
    /**
     * Get current company context
     */
    function getCurrentCompany() {
        // Try to get company from data attributes
        var company = $('.upra-stats-container').data('company');
        if (company) return company;
        
        // Try to get from URL
        var urlParams = new URLSearchParams(window.location.search);
        company = urlParams.get('company');
        if (company) return company;
        
        // Default to ATOS
        return 'atos';
    }
    
    /**
     * Initialize page visibility handling
     */
    function initializeVisibilityHandling() {
        // Refresh stats when page becomes visible
        if (typeof document.hidden !== "undefined") {
            document.addEventListener("visibilitychange", function() {
                if (!document.hidden) {
                    setTimeout(refreshStatistics, 1000);
                }
            });
        }
        
        // Refresh stats when window gains focus
        $(window).on('focus', function() {
            setTimeout(refreshStatistics, 1000);
        });
    }
    
    /**
     * Statistics card hover effects
     */
    function initializeHoverEffects() {
        $('.upra-stat-item, .upra-stat-card').hover(
            function() {
                $(this).addClass('hover-effect');
            },
            function() {
                $(this).removeClass('hover-effect');
            }
        );
    }
    
    /**
     * Initialize responsive behavior
     */
    function initializeResponsiveBehavior() {
        function handleResize() {
            var width = $(window).width();
            
            // Adjust grid layout for mobile
            if (width < 768) {
                $('.upra-stats-grid').addClass('mobile-layout');
            } else {
                $('.upra-stats-grid').removeClass('mobile-layout');
            }
            
            // Adjust font sizes for very small screens
            if (width < 480) {
                $('.upra-stat-value').addClass('small-screen');
            } else {
                $('.upra-stat-value').removeClass('small-screen');
            }
        }
        
        // Initial check
        handleResize();
        
        // Handle window resize
        $(window).on('resize', debounce(handleResize, 250));
    }
    
    /**
     * Debounce function
     */
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Initialize accessibility features
     */
    function initializeAccessibility() {
        // Add ARIA labels to statistics
        $('.upra-stat-value').each(function() {
            var $this = $(this);
            var $label = $this.siblings('.upra-stat-label').first();
            
            if ($label.length > 0) {
                var labelText = $label.text();
                var value = $this.text();
                $this.attr('aria-label', labelText + ': ' + value);
            }
        });
        
        // Add live region for dynamic updates
        if ($('#upra-stats-live-region').length === 0) {
            $('body').append('<div id="upra-stats-live-region" aria-live="polite" aria-atomic="true" class="screen-reader-text"></div>');
        }
        
        // Announce updates to screen readers
        $(document).on('upra_stats_updated', function(e, stats) {
            var message = 'Statistics updated. ';
            if (stats.total_shares) {
                message += 'Total shares: ' + formatNumber(stats.total_shares) + '. ';
            }
            if (stats.shareholders_count) {
                message += 'Total shareholders: ' + formatNumber(stats.shareholders_count) + '.';
            }
            
            $('#upra-stats-live-region').text(message);
        });
    }
    
    // Initialize all features
    initializeHoverEffects();
    initializeResponsiveBehavior();
    initializeAccessibility();
    
    // Custom events support
    $(document).on('upra_refresh_stats', function() {
        refreshStatistics();
    });
    
    $(document).on('upra_animate_stats', function() {
        $('.upra-stat-value').removeClass('animated');
        animateCounters();
    });
    
});