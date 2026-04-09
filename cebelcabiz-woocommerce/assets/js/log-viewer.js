/**
 * Cebelca BIZ Log Viewer JavaScript
 */
(function($) {
    'use strict';

    // Initialize the log viewer when the document is ready
    $(document).ready(function() {
        // Clear log button
        $('#cebelcabiz-clear-log').on('click', function(e) {
            e.preventDefault(); // Prevent default button behavior
            console.log('Clear log button clicked'); // Debug log
            if (confirm(cebelcabiz_log.confirm_clear)) {
                clearLog();
            }
        });
        
        // Ensure download log button has the correct URL with nonce
        $('#cebelcabiz-download-log').attr('href', function() {
            return cebelcabiz_log.ajax_url + '?action=cebelcabiz_download_log&nonce=' + cebelcabiz_log.nonce;
        });
    });

    /**
     * Clear the log via AJAX
     */
    function clearLog() {
        console.log('Clearing log...'); // Debug log
        console.log('AJAX URL:', cebelcabiz_log.ajax_url); // Debug log
        
        // Make AJAX request
        $.ajax({
            url: cebelcabiz_log.ajax_url,
            type: 'POST',
            data: {
                action: 'cebelcabiz_clear_log',
                nonce: cebelcabiz_log.nonce
            },
            success: function(response) {
                console.log('AJAX success:', response); // Debug log
                if (response.success) {
                    alert(response.message || 'Log cleared successfully');
                } else {
                    alert(response.message || cebelcabiz_log.error_text);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error); // Debug log
                alert(cebelcabiz_log.error_text);
            }
        });
    }

})(jQuery);
