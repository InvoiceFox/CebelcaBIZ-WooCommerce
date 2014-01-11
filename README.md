##WooCommerce InvoiceFox integration

This is a first version of WooCommerce plugin that automatically adds clients and creates invoices every time your WooCommerce orders are marked "completed".

###Status

The first alpha version. 

###Install & setup

* Copy the *woocomm-invfox* folder to *./wordpress/wp-content/plugins/*.
* Go to *admin > plugins > installed plugins* and Activate *WooCommerce+InvoiceFox*.
* Press *edit* on the plugin page or open the invfox-woocomm/invfox-woocomm.php file and setup your API_KEY and API_DOMAIN. You get your API key in InvoiceFox under *Access*. There are other setting there that you can customize. You can for example create preinvoices or invoices, use partial sums to separate shipping costs from products, etc...
 
The setup interface for WP admin is the next thing on our roadmap.
 
###Where does this work

www.invoicefox.com , www.invoicefox.co.nz , www.invoicefox.com.au , www.abelie.biz , www.cebelca.biz

###TODO

* Add the options screen to WP Admin. 
* Add functionality and options to download the PDF of invoice or send it via email. 

###License

GNU GPL v2
