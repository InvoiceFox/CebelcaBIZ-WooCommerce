<?php
/**
 * Cebelca BIZ Log Viewer.
 *
 * @package  WC_Cebelcabiz_Log_Viewer
 * @category Admin
 * @author   Janko M.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Log Viewer class for Cebelca BIZ
 */
class WC_Cebelcabiz_Log_Viewer {
    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Maximum number of lines to display
     *
     * @var int
     */
    private $max_lines = 1000;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_file = WOOCOMM_INVFOX_LOG_FILE;
        
        // Add AJAX handlers
        add_action('wp_ajax_cebelcabiz_view_log', array($this, 'ajax_view_log'));
        add_action('wp_ajax_cebelcabiz_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_cebelcabiz_download_log', array($this, 'ajax_download_log'));
        
        // Add assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts and styles for the log viewer
     */
    public function enqueue_scripts($hook) {
        // Only load on the WooCommerce settings page
        if (strpos($hook, 'wc-settings') === false) {
            return;
        }
        
        // Check if we're on the Cebelca BIZ integration tab
        if (!isset($_GET['section']) || $_GET['section'] !== 'cebelcabiz') {
            return;
        }
        
        wp_enqueue_style(
            'cebelcabiz-log-viewer',
            plugins_url('/assets/css/log-viewer.css', dirname(__FILE__)),
            array(),
            WOOCOMM_INVFOX_VERSION
        );
        
        wp_enqueue_script(
            'cebelcabiz-log-viewer',
            plugins_url('/assets/js/log-viewer.js', dirname(__FILE__)),
            array('jquery'),
            WOOCOMM_INVFOX_VERSION,
            true
        );
        
        wp_localize_script('cebelcabiz-log-viewer', 'cebelcabiz_log', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cebelcabiz-log-viewer'),
            'loading_text' => __('Loading log...', 'woocommerce-cebelcabiz'),
            'error_text' => __('Error loading log', 'woocommerce-cebelcabiz'),
            'empty_text' => __('Log is empty', 'woocommerce-cebelcabiz'),
            'confirm_clear' => __('Are you sure you want to clear the log?', 'woocommerce-cebelcabiz')
        ));
    }

    /**
     * AJAX handler for viewing the log
     */
    public function ajax_view_log() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woocommerce-cebelcabiz'));
        }
        
        // Verify nonce
        check_ajax_referer('cebelcabiz-log-viewer', 'nonce');
        
        $response = array(
            'success' => false,
            'data' => ''
        );
        
        if (file_exists($this->log_file)) {
            $log_content = $this->get_log_content();
            
            if ($log_content) {
                $response['success'] = true;
                $response['data'] = $log_content;
            } else {
                $response['data'] = __('Log file is empty', 'woocommerce-cebelcabiz');
            }
        } else {
            $response['data'] = __('Log file does not exist', 'woocommerce-cebelcabiz');
        }
        
        wp_send_json($response);
    }

    /**
     * AJAX handler for clearing the log
     */
    public function ajax_clear_log() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woocommerce-cebelcabiz'));
        }
        
        // Verify nonce
        check_ajax_referer('cebelcabiz-log-viewer', 'nonce');
        
        $response = array(
            'success' => false,
            'message' => ''
        );
        
        if (file_exists($this->log_file)) {
            // Get current user info
            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            
            // Create a timestamp and clear message
            $timestamp = date('[Y-m-d H:i:s]');
            $clear_message = $timestamp . ' WC_Cebelcabiz: [LOG_CLEARED] Log was cleared by user: ' . $username . PHP_EOL;
            
            // Write the clear message to the log file (replacing all content)
            $result = file_put_contents($this->log_file, $clear_message);
            
            if ($result !== false) {
                $response['success'] = true;
                $response['message'] = __('Log cleared successfully', 'woocommerce-cebelcabiz');
            } else {
                $response['message'] = __('Failed to clear log file', 'woocommerce-cebelcabiz');
            }
        } else {
            $response['message'] = __('Log file does not exist', 'woocommerce-cebelcabiz');
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX handler for downloading the log
     */
    public function ajax_download_log() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woocommerce-cebelcabiz'));
        }
        
        // Verify nonce
        check_ajax_referer('cebelcabiz-log-viewer', 'nonce');
        
        if (file_exists($this->log_file)) {
            // Set headers for file download
            header('Content-Description: File Transfer');
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="cebelcabiz-debug-' . date('Y-m-d-H-i-s') . '.log"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($this->log_file));
            
            // Clear output buffer
            ob_clean();
            flush();
            
            // Read the file and output it
            readfile($this->log_file);
            exit;
        } else {
            wp_die(__('Log file does not exist', 'woocommerce-cebelcabiz'));
        }
    }

    /**
     * Get the log content
     *
     * @return string Formatted log content
     */
    private function get_log_content() {
        if (!file_exists($this->log_file)) {
            return '';
        }
        
        $content = '';
        
        // Get the last X lines of the log file
        $lines = $this->tail($this->log_file, $this->max_lines);
        
        if (!empty($lines)) {
            // Format the log entries
            foreach ($lines as $line) {
                // Escape HTML
                $line = esc_html($line);
                
                // Highlight timestamps
                $line = preg_replace('/(\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/', '<span class="log-timestamp">$1</span>', $line);
                
                // Highlight error messages
                $line = preg_replace('/(NAPAKA|ERROR|Warning|Exception)/', '<span class="log-error">$1</span>', $line);
                
                // Highlight context labels
                $line = preg_replace('/\[([^\]]+)\]/', '<span class="log-context">[$1]</span>', $line);
                
                $content .= $line . "\n";
            }
        }
        
        return $content;
    }

    /**
     * Get the last X lines of a file
     *
     * @param string $file File path
     * @param int $lines Number of lines to get
     * @return array Array of lines
     */
    private function tail($file, $lines = 10) {
        $result = array();
        
        // Open the file
        $f = @fopen($file, "r");
        if (!$f) {
            return $result;
        }
        
        // Jump to the end of the file
        fseek($f, 0, SEEK_END);
        
        // Get file size
        $filesize = ftell($f);
        
        // If the file is empty, return empty array
        if ($filesize == 0) {
            fclose($f);
            return $result;
        }
        
        // Start from the end of the file
        $pos = $filesize - 1;
        $count = 0;
        
        // Read the file backwards
        while ($pos >= 0 && $count < $lines) {
            fseek($f, $pos);
            $char = fgetc($f);
            
            // If we found a newline, we found a complete line
            if ($char == "\n" && $pos != $filesize - 1) {
                $count++;
            }
            
            $pos--;
        }
        
        // Read the rest of the file
        $result = array();
        while (!feof($f)) {
            $line = fgets($f);
            if ($line !== false) {
                $result[] = rtrim($line);
            }
        }
        
        fclose($f);
        
        return $result;
    }

    /**
     * Render the log viewer
     */
    public function render() {
        ?>
        <div class="cebelcabiz-log-viewer">
            <h3><?php _e('Debug Log', 'woocommerce-cebelcabiz'); ?></h3>
            
            <div class="cebelcabiz-log-actions">
                <button type="button" id="cebelcabiz-clear-log" class="button"><?php _e('Clear Log', 'woocommerce-cebelcabiz'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=cebelcabiz_download_log&nonce=' . wp_create_nonce('cebelcabiz-log-viewer'))); ?>" id="cebelcabiz-download-log" class="button"><?php _e('Download Log', 'woocommerce-cebelcabiz'); ?></a>
            </div>
            
            <p class="description">
                <?php printf(__('Log file location: %s', 'woocommerce-cebelcabiz'), $this->log_file); ?>
            </p>
        </div>
        <?php
    }
}
