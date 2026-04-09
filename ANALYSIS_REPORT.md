# Cebelca BIZ WooCommerce Plugin - Bug Analysis Report

## Overview
Analyzed the Cebelca BIZ WooCommerce plugin v0.8.9 for bugs and issues, particularly around PDF downloads and payment method handling.

## Critical Issues Found

### 1. **Payment Method Mapping Issues**

#### Problem:
The payment method mapping system has several limitations and potential failure points:

**Current Implementation:**
- Uses both `get_payment_method_title()` (human-readable name) and `get_payment_method()` (gateway ID)
- Falls back to ID if title mapping fails
- Mapping format: `"Title->CebelcaMethod;Title2->Method2"`

**Issues:**
1. **Payment method titles can be localized/customized** - A Stripe payment might show as "Credit Card", "Kreditna kartica", or custom text depending on language/settings
2. **No clear guidance for users** on how to find payment method IDs
3. **Case sensitivity** in mapping (though the code handles exact matches only)
4. **Limited error messaging** when mapping fails

**Location in code:**
```php
// cebelcabiz.php lines 474-485
$currentPM = sanitize_text_field($order->get_payment_method_title());
$currentPM_id = sanitize_text_field($order->get_payment_method());
$payment_method = $this->mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
if ( $payment_method == -2 && ! empty( $currentPM_id ) ) {
    $payment_method = $this->mapPaymentMethods($currentPM_id, $this->conf['payment_methods_map']);
}
```

### 2. **PDF Download Vulnerabilities and Issues**

#### Problem 1: Directory Security
**Location:** `cebelcabiz.php` lines 1180-1200
```php
if (!is_dir($upload_path)) {
    $dir_created = wp_mkdir_p($upload_path);
    // Sets 0755 permissions but no .htaccess protection
}
```

**Issues:**
- **No .htaccess protection** for the `/wp-content/uploads/invoices/` directory
- PDFs are directly accessible via URL if someone guesses the filename
- **Privacy/GDPR issue**: Customer invoice data exposed

#### Problem 2: PDF Download Error Handling
**Location:** `lib/invfoxapi.php` lines 225-400

**Issues:**
1. **Insufficient error checking** for API responses
2. **No retry mechanism** on failed downloads
3. **Inadequate WAF/ModSecurity detection** (only checks basic response format)
4. **Missing file cleanup** on partial failures

#### Problem 3: PDF Validation Issues
```php
// Only checks for %PDF- signature but not PDF validity
if (strpos($trimmedStart, '%PDF-') !== 0) {
    // Error handling
}
```

**Issues:**
- Doesn't validate if PDF is actually readable/valid
- Could save corrupted PDF files

### 3. **Payment Method ID Discovery Issues**

#### Problem:
Users have no clear way to discover payment method IDs for mapping.

**For Stripe specifically:**
- Payment method ID is typically `stripe` 
- But could be `stripe_cc`, `stripe_sepa`, etc. depending on setup
- Title varies: "Credit Card", "Stripe", "Credit card (Stripe)", etc.

#### Current User Guidance:
The plugin only provides this description:
> "Tu v obliki "Način plačila woocommerce->Način plačila Čebelca;...." vnesete kako se naj načini plačila v vašem Woocommerce pretvorijo v načina plačila na Čebelci"

**Missing:**
- How to find payment method IDs
- Examples for common gateways
- Debugging tools to see what values are being used

## Minor Issues

### 4. **Inconsistent Function Calls**
**Location:** `cebelcabiz.php` line 928, 931
```php
$payment_method = mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
// Should be:
$payment_method = $this->mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
```

This calls global function instead of class method, though both exist.

### 5. **Debug Logging Inconsistency**
Some API calls disable debug hook:
```php
$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
// $api->setDebugHook( "woocomm_invfox__trace" ); // Commented out
```

### 6. **VAT Calculation Edge Cases**
The `calculatePreciseSloVAT()` function may fail with certain VAT configurations, leading to document creation failures.

## Security Issues

### 7. **PDF Directory Access**
- PDFs stored in `/wp-content/uploads/invoices/` without access protection
- Filenames are predictable: `{prefix}_{id}_{extid}_{rand}.pdf`
- No authentication required to access PDFs

### 8. **Input Validation**
- Payment method mapping accepts unsanitized input in admin settings
- Some API parameters lack proper sanitization

## Recommendations

### 1. **Improve Payment Method Mapping**

**Add debug functionality:**
```php
// Add to admin interface
public function show_payment_methods_debug() {
    $available_gateways = WC()->payment_gateways->payment_gateways();
    echo '<h3>Available Payment Methods:</h3>';
    foreach($available_gateways as $id => $gateway) {
        echo "ID: {$id} | Title: {$gateway->title}<br>";
    }
}
```

**Better user guidance:**
- Add examples for common payment methods (Stripe, PayPal, etc.)
- Show current order payment methods in debug log
- Add validation for mapping format

### 2. **Secure PDF Storage**
```php
// Add .htaccess to invoices directory
private function secure_invoices_directory($path) {
    $htaccess_content = "Order Deny,Allow\nDeny from all\n";
    file_put_contents($path . '/.htaccess', $htaccess_content);
}
```

**Alternative:** Store PDFs outside web root or implement download authentication.

### 3. **Enhanced PDF Validation**
```php
// Add PDF integrity check
private function validate_pdf_file($filepath) {
    if (!file_exists($filepath)) return false;
    
    $fileContent = file_get_contents($filepath, false, null, 0, 1024);
    if (strpos($fileContent, '%PDF-') !== 0) return false;
    
    // Check for PDF trailer
    $endContent = file_get_contents($filepath, false, null, -1024);
    if (strpos($endContent, '%%EOF') === false) return false;
    
    return true;
}
```

### 4. **Payment Method ID Discovery**

**Add to admin settings:**
```php
// Show payment methods for easier mapping
private function render_payment_methods_helper() {
    echo '<div class="payment-methods-helper">';
    echo '<h4>Discovered Payment Methods:</h4>';
    echo '<p><strong>Recent orders payment methods:</strong></p>';
    
    $orders = wc_get_orders(array('limit' => 20));
    $methods_found = array();
    
    foreach($orders as $order) {
        $method_id = $order->get_payment_method();
        $method_title = $order->get_payment_method_title();
        if (!isset($methods_found[$method_id])) {
            $methods_found[$method_id] = $method_title;
        }
    }
    
    foreach($methods_found as $id => $title) {
        echo "<code>{$id}</code> → {$title}<br>";
    }
    echo '</div>';
}
```

### 5. **Common Payment Method Examples**
Add to plugin documentation:
```
Common Payment Method Mappings:
- Stripe: "stripe->Kreditna kartica" or "Credit Card->Kreditna kartica"  
- PayPal: "paypal->PayPal" or "PayPal->PayPal"
- Bank Transfer: "bacs->Bančno nakazilo"
- Cash on Delivery: "cod->Gotovina ob dostavi"
```

## Conclusion

The plugin has several important issues that should be addressed:

1. **High Priority**: Secure PDF storage and implement proper access controls
2. **High Priority**: Better payment method mapping guidance and debugging tools  
3. **Medium Priority**: Fix function call inconsistencies
4. **Medium Priority**: Improve PDF validation and error handling
5. **Low Priority**: Enhanced debug logging consistency

The payment method mapping system works but needs better user experience and documentation. The PDF download functionality has security and reliability issues that should be addressed promptly.