# Top 10 Critical Edge Cases for CebelcaBIZ Testing

Based on code analysis and potential business impact, these are the 10 most critical edge cases to test first:

## 1. VAT Calculation with Unusual Rates

**Test scenario:** Create an order with a product that has a VAT rate not in the predefined list (e.g., 15.5% when the closest defined rates are 9.5% and 17%).

**Why critical:** The `calculatePreciseSloVAT()` function returns -1 when it can't match a VAT rate within 2% of predefined levels, which prevents invoice finalization. This directly impacts your ability to generate invoices for certain products.

**Test steps:**
1. Add a product with a custom tax rate that doesn't match any in `vat_levels_list`
2. Complete an order with this product
3. Verify the error message about incorrect VAT rate appears
4. Check that no invoice is created in Cebelca BIZ

## 2. Empty Payment Methods Mapping

**Test scenario:** Configure the plugin with an empty `payment_methods_map` setting and process orders with various payment methods.

**Why critical:** The `mapPaymentMethods()` function returns error code -2 when payment methods aren't mapped, which can prevent marking invoices as paid.

**Test steps:**
1. Set `payment_methods_map` to empty in plugin settings
2. Create and complete an order
3. Try to mark the invoice as paid
4. Verify appropriate error message is shown

## 3. API Connection Failure During PDF Download

**Test scenario:** Simulate API connection issues when the plugin attempts to download invoice PDFs.

**Why critical:** PDF generation is critical for email attachments and record-keeping. Failures here directly impact customer communication.

**Test steps:**
1. Temporarily modify API endpoint or credentials to force failure
2. Complete an order that should generate and email a PDF
3. Verify system handles the failure gracefully
4. Check appropriate error messages are logged

## 4. Order with Multiple Products Having Different VAT Rates

**Test scenario:** Create an order containing multiple products with different VAT rates.

**Why critical:** The code processes each line item separately for VAT calculation, and errors with any single item can prevent the entire invoice from being created.

**Test steps:**
1. Add products with different VAT rates to cart (e.g., 0%, 9.5%, 22%)
2. Complete the order
3. Verify all line items appear correctly on the invoice
4. Check VAT calculations are correct for each item

## 5. Malformed Payment Methods Mapping

**Test scenario:** Configure the plugin with incorrectly formatted payment methods mapping.

**Why critical:** The `mapPaymentMethods()` function returns error code -1 for invalid format, which can prevent marking invoices as paid.

**Test steps:**
1. Set `payment_methods_map` to an invalid format (e.g., `PayPalPayPal` without the `->` delimiter)
2. Create and complete an order
3. Try to mark the invoice as paid
4. Verify appropriate error message is shown

## 6. Missing Customer Data

**Test scenario:** Process an order with incomplete customer information.

**Why critical:** The `assurePartner()` function requires customer data to create or update customer records in Cebelca BIZ.

**Test steps:**
1. Create an order with missing customer data (e.g., no phone number or address)
2. Complete the order
3. Verify customer is created correctly in Cebelca BIZ
4. Check invoice generation succeeds

## 7. Rapid Order Status Changes

**Test scenario:** Change an order's status rapidly between different states.

**Why critical:** The plugin hooks into status change events (`woocommerce_order_status_*`), and rapid changes could trigger multiple document creations or race conditions.

**Test steps:**
1. Create an order
2. Rapidly change status between on-hold, processing, and completed
3. Verify only appropriate documents are created
4. Check for duplicate documents or errors

## 8. Orders with Discounts

**Test scenario:** Process orders with various types of discounts applied.

**Why critical:** Discount calculation is complex, involving both the original price and discounted price, and errors here directly affect financial reporting.

**Test steps:**
1. Create an order with percentage and fixed discounts
2. Complete the order
3. Verify discount percentages are calculated correctly on the invoice
4. Check final amounts match WooCommerce totals

## 9. Insufficient Permissions for PDF Storage

**Test scenario:** Set insufficient permissions on the `/invoices` directory where PDFs are stored.

**Why critical:** File system permission issues can prevent PDF storage but might not be immediately apparent in the UI.

**Test steps:**
1. Change permissions on the `/invoices` directory to prevent writing
2. Complete an order that should generate a PDF
3. Verify appropriate error message is shown
4. Check error is logged properly

## 10. Special Characters in Product and Customer Data

**Test scenario:** Process orders with special characters in product names, descriptions, and customer information.

**Why critical:** Special characters can cause encoding issues when data is passed to the API, potentially resulting in corrupted documents.

**Test steps:**
1. Create products with special characters (e.g., ščžćđ, &, #, etc.)
2. Add customer with special characters in name and address
3. Complete an order with these products
4. Verify all text appears correctly in the generated invoice
