<?php
/**
 * Data Collection Form Template
 * 
 * This template replaces the original atos-data-collect-template.php
 * and provides a modern, responsive form for shareholder data collection
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the current company from URL or default to ATOS
$company = isset($_GET['company']) ? sanitize_text_field($_GET['company']) : 'atos';

// Get page header
get_header(); 
?>

<div class="mh-section mh-group">
    <div id="main-content" class="mh-content" role="main" itemprop="mainContentOfPage">
        
        <?php
        // Display page content if any
        while (have_posts()) : the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
            // Comments if enabled
            if (comments_open() || get_comments_number()) {
                comments_template();
            }
        endwhile;
        ?>

        <!-- UPRA Data Collection Form -->
        <main id="primary" class="md:col-span-4 lead-card p-6">
            <div class="form-container max-w-4xl mx-auto" style="font-family: Helvetica, Arial, sans-serif !important;">
                
                <!-- Display company statistics -->
                <div class="upra-stats-display mb-8">
                    <?php echo do_shortcode("[upra_company_stats company='{$company}' format='card']"); ?>
                </div>

                <!-- Main data collection form -->
                <?php echo do_shortcode("[upra_data_collection_form company='{$company}' show_stats='false']"); ?>

                <!-- Success/Error Messages Container -->
                <div id="upra-form-messages" class="mt-6">
                    <!-- Messages will be inserted here via JavaScript -->
                </div>

            </div>
        </main>

    </div>
    
    <?php 
    // Sidebar if theme supports it
    if (function_exists('get_sidebar')) {
        get_sidebar(); 
    }
    ?>
</div>

<!-- Legacy JavaScript Support -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Legacy support for themes that might expect these elements
    if ($('#inline-share-data').length === 0) {
        $('body').append('<input type="hidden" id="inline-share-data" />');
    }
    
    // Update total shares display for legacy compatibility
    var totalShares = parseInt(upra_stats?.total_shares || 0);
    $('#inline-share-data').val(totalShares);
    
    // Legacy form submission handling (backward compatibility)
    $(document).on('submit', '#send_data', function(e) {
        e.preventDefault();
        
        // If new form handler exists, use it
        if (typeof window.upra_form_handler !== 'undefined') {
            return;
        }
        
        // Otherwise, redirect to new form
        var newForm = $('#upra-shareholder-form');
        if (newForm.length > 0) {
            // Copy data from old form to new form
            var oldFormData = $(this).serialize();
            var formData = {};
            
            // Parse old form data
            oldFormData.split('&').forEach(function(pair) {
                var keyValue = pair.split('=');
                var key = decodeURIComponent(keyValue[0]);
                var value = decodeURIComponent(keyValue[1] || '');
                formData[key] = value;
            });
            
            // Map to new form fields
            newForm.find('[name="name"]').val(formData.name || '');
            newForm.find('[name="email"]').val(formData.email || '');
            newForm.find('[name="phone"]').val(formData.phone || '');
            newForm.find('[name="stock"]').val(formData.stock || '');
            newForm.find('[name="purchase"]').val(formData.purchase || '');
            newForm.find('[name="sell"]').val(formData.sell || '');
            newForm.find('[name="loss"]').val(formData.loss || '');
            newForm.find('[name="remarks"]').val(formData.remarks || '');
            
            // Submit new form
            newForm.submit();
        }
    });
    
    // Update legacy message containers when new messages appear
    $(document).on('upra_form_success', function(e, data) {
        // Update legacy success message
        var legacySuccess = $('#success_message');
        if (legacySuccess.length > 0) {
            legacySuccess.html(data.message).removeClass('hidden').show();
        }
        
        // Clear legacy error messages
        $('#error_message, #duplicate_data, #no_values').empty().addClass('hidden').hide();
    });
    
    $(document).on('upra_form_error', function(e, data) {
        // Update legacy error messages
        if (data.message) {
            var legacyError = $('#duplicate_data');
            if (legacyError.length > 0) {
                legacyError.html(data.message).removeClass('hidden').show();
            }
        }
        
        if (data.errors && Array.isArray(data.errors)) {
            var legacyValidation = $('#no_values');
            if (legacyValidation.length > 0) {
                legacyValidation.empty();
                data.errors.forEach(function(error) {
                    legacyValidation.append('<li class="red-500">' + error + '</li>');
                });
                legacyValidation.removeClass('hidden').show();
            }
        }
        
        // Clear success message
        $('#success_message').empty().addClass('hidden').hide();
    });
});
</script>

<!-- Legacy CSS for backward compatibility -->
<style type="text/css">
/* Legacy form styling support */
.form-container {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 2rem 0;
}

.upra-stats-display {
    text-align: center;
    margin-bottom: 2rem;
}

/* Legacy message styling */
#success_message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border-radius: 0.25rem;
}

#error_message,
#duplicate_data {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border-radius: 0.25rem;
}

#no_values {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border-radius: 0.25rem;
    list-style: none;
}

#no_values li {
    margin: 0.25rem 0;
}

.hidden {
    display: none !important;
}

.red-500 {
    color: #dc3545;
}

.green-500 {
    color: #28a745;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-container {
        margin: 1rem;
        padding: 1rem;
    }
}
</style>

<?php get_footer(); ?>