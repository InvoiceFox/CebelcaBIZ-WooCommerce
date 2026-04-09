# CebelcaBIZ WooCommerce Plugin Edge Case Testing

This document outlines various edge cases that should be tested to ensure the CebelcaBIZ WooCommerce plugin functions correctly in all scenarios.

## VAT Calculation Edge Cases

- **Unusual VAT Rates**: Create orders with products that have VAT rates not in the predefined list (`vat_levels_list`).
  - Test with a product having a 15.5% VAT rate when the closest defined rates are 9.5% and 17%.
  - Test with an extremely high VAT rate (e.g., 40%) that exceeds all defined levels.
  - Test with a negative VAT amount (should be handled as 0%).

- **Zero Values**:
  - Test with products having zero net price but non-zero VAT.
  - Test with products having non-zero net price but zero VAT.
  - Test with both zero net price and zero VAT.

- **Rounding Issues**:
  - Test with prices that cause rounding issues (e.g., 9.999€ with 22% VAT).
  - Test with quantities that cause multiplication rounding issues (e.g., 3 items at 1.33€).

- **Mixed VAT Rates**:
  - Create an order with multiple products having different VAT rates.
  - Include products with standard VAT rates and products with custom VAT rates in the same order.

## Payment Methods Translation Edge Cases

- **Empty Mapping**:
  - Test with an empty `payment_methods_map` configuration.
  - Test with payment methods that aren't defined in the mapping.

- **Malformed Mapping**:
  - Test with a mapping string missing the `->`delimiter (e.g., `PayPalPayPal`).
  - Test with a mapping string using incorrect delimiters (e.g., `PayPal=>PayPal`).
  - Test with a mapping string missing the `;` separator (e.g., `PayPal->PayPal Gotovina->Gotovina`).

- **Special Characters**:
  - Test with payment methods containing special characters (e.g., `Credit Card (Visa/MC)`).
  - Test with payment methods containing non-Latin characters (e.g., `Plačilo po povzetju`).

- **Wildcard Handling**:
  - Test with wildcard patterns in the mapping (e.g., `*Card*->Credit Card`).
  - Test with multiple wildcard patterns that could match the same payment method.

## PDF Generation and Download Edge Cases

- **API Connection Issues**:
  - Simulate API connection timeout when requesting PDF download.
  - Simulate API returning an error response when requesting PDF download.
  - Test with invalid API credentials.

- **File System Issues**:
  - Test with insufficient permissions on the `/invoices` directory.
  - Test with a full disk that prevents writing the PDF file.
  - Test with an existing file with the same name (should overwrite).

- **PDF Content Issues**:
  - Test with extremely long product names or descriptions.
  - Test with special characters in product names, customer names, or addresses.
  - Test with a very large number of line items (e.g., 100+ products).

## Customer Data Edge Cases

- **Missing Customer Data**:
  - Test with orders missing billing email.
  - Test with orders missing billing phone.
  - Test with orders missing billing address.
  - Test with orders missing customer name.

- **Special Characters in Customer Data**:
  - Test with non-Latin characters in customer names (e.g., Chinese, Arabic).
  - Test with special characters in addresses (e.g., `#`, `&`, `'`).
  - Test with very long customer names or addresses.

- **VAT Number Handling**:
  - Test with invalid VAT number formats.
  - Test with VAT numbers from different EU countries.
  - Test with orders where VAT number is present but empty.

## Order Status Transition Edge Cases

- **Rapid Status Changes**:
  - Test changing order status rapidly from one state to another.
  - Test changing order status back and forth between states.

- **Custom Order Statuses**:
  - Test with custom order statuses added by other plugins.
  - Test transitions between custom and standard statuses.

## Product and Inventory Edge Cases

- **Product Variations**:
  - Test with complex product variations (multiple attributes).
  - Test with product variations that have different VAT rates.
  - Test with product variations that have different SKUs.

- **Inventory Management**:
  - Test with products that have zero or negative inventory.
  - Test with products that don't track inventory.
  - Test with products that exceed available inventory.

- **Product Types**:
  - Test with virtual products.
  - Test with downloadable products.
  - Test with subscription products (if WooCommerce Subscriptions is used).
  - Test with composite or bundled products.

## Shipping and Fees Edge Cases

- **Multiple Shipping Methods**:
  - Test with orders using multiple shipping methods.
  - Test with free shipping.
  - Test with shipping methods that have different VAT rates.

- **Order Fees**:
  - Test with multiple fees applied to an order.
  - Test with negative fees (discounts).
  - Test with fees that have different VAT rates than products.

## Discount and Coupon Edge Cases

- **Complex Discounts**:
  - Test with percentage discounts.
  - Test with fixed amount discounts.
  - Test with discounts that apply to specific products.
  - Test with discounts that apply to the entire order.

- **Extreme Discounts**:
  - Test with 100% discounts (free products).
  - Test with discounts that exceed the product price (should be handled correctly).

## Multi-Currency Edge Cases

- **Currency Conversion**:
  - Test with orders in different currencies (if multi-currency is supported).
  - Test with currency symbols that might cause encoding issues (e.g., ¥, £, ₽).

## Concurrent Operation Edge Cases

- **Multiple Simultaneous Orders**:
  - Test processing multiple orders simultaneously.
  - Test creating multiple documents in Cebelca BIZ simultaneously.

## Error Handling and Recovery

- **Partial Failures**:
  - Test scenarios where document creation succeeds but PDF download fails.
  - Test scenarios where customer creation succeeds but document creation fails.

- **Retry Mechanisms**:
  - Test manually retrying failed operations.
  - Test behavior when retrying already successful operations.

## Integration with Other Plugins

- **Conflict Testing**:
  - Test with other invoice/accounting plugins active.
  - Test with other plugins that modify order processing.
  - Test with other plugins that modify admin notices.

## Performance Edge Cases

- **Large Order Volume**:
  - Test with a large number of orders being processed in a short time.
  - Test with very large order history.

- **Resource Limitations**:
  - Test under low memory conditions.
  - Test under high CPU load.

## Implementation Notes

When testing these edge cases:

1. Document the expected behavior for each case.
2. Record the actual behavior observed.
3. Note any discrepancies between expected and actual behavior.
4. For failed tests, capture error messages and log entries.
5. Test both from the admin interface and through programmatic API calls where applicable.

This comprehensive testing will help ensure the plugin is robust and handles all possible scenarios correctly.
