
// function to update quantities based on array of SKUS and qtys

function update_product_quantity($sku_quantity_array) {
    foreach ($sku_quantity_array as $sku => $quantity) {
        $product = wc_get_product_id_by_sku($sku);
        if ($product) {
            $product->set_stock_quantity($quantity);
            $product->save();
        }
    }
}

/* Example: 

$sku_quantity_array = array(
    'SKU001' => 10,
    'SKU002' => 5,
    'SKU003' => 2
);

update_product_quantity($sku_quantity_array);

*/

add_action( 'woocommerce_api_my_endpoint', 'my_api_endpoint_callback' );


function my_api_endpoint_callback() {
    $sku = $_GET['sku'];
    $quantity = $_GET['quantity'];
    // Your code to update inventory quantity based on the provided SKU and quantity
}

/* example http call:

https://example.com/wc-api/v3/my_endpoint?sku=SKU001&quantity=10

*/
