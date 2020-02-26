<?php

# Example filter that attaches PDF invoices to emails
# Should be part of your theme's functions.php  


function my_theme_enqueue_styles() { 
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );

function attach_file_woocommerce_email($attachments, $id, $object)
{           
    if($id == 'customer_completed_order')
    {   
        
    try {
        $str = "invoicefox_attached_pdf";
        $updated_order = wc_get_order( $object->get_id());
        $upload = $updated_order->get_meta($str, True);
        error_log("Emailing invoice: ");
        error_log($upload); 
        $attachments[] = $upload;  

     }
    
    catch (Exception $e) {
        error_log($e->getMessage());
     }
    }

    return $attachments;
}
add_filter('woocommerce_email_attachments', 'attach_file_woocommerce_email', 10, 3);
