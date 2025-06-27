# Fez Delivery #
**Contributors:** biggidroid, fezdelivery  
**Donate link:** https://www.fezdelivery.co/  
**Tags:** delivery, management, fezdelivery  
**Requires at least:** 5.0  
**Tested up to:** 6.8  
**Requires PHP:** 7.4  
**Stable tag:** 1.0.3  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Fez Delivery is a WordPress delivery management system.

## Description ##

Fez Delivery is a WordPress delivery management system.

## Features ##

-   Import orders from woocommerce to fez delivery
-   Manage orders in fez delivery
-   Barcode generator for orders

## Installation ##

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Access the plugin functionality through the "Fez Delivery" section in your WordPress admin dashboard.



## Changelog ##

### 1.0.3 ###

-   Fixed the issue with the delivery state not being updated.

### 1.0.2 ###

-   Fixed the issue with the order status not being updated.

### 1.0.1 ###

-   Fixed the issue with the order status not being updated.

### 1.0.0 ###

-   Initial release with support for delivery management.

For support and feedback, please visit https://www.fezdelivery.co/.

## External Resources ##

This plugin uses the following external resources:

-   [TEC-IT Barcode Generator](https://barcode.tec-it.com/)

For more information on generating barcodes, visit [TEC-IT Barcode Generator](https://barcode.tec-it.com/).

## External Services ##

This plugin connects to several external services to provide its functionality. Below is a detailed breakdown of each service, what data is transmitted, and when:

# Fez Delivery API Services #

1. Fez Delivery API (Production and Sandbox)
   - Service URL: https://api.fezdelivery.co/
   - Purpose: Core delivery management functionality, order processing, and status updates
   - Data Transmitted:
     * Order details (recipient information, delivery address, package details)
     * Order status updates
     * Delivery tracking information
   - When Used:
     * When creating new deliveries
     * When updating order status
     * When fetching order details
     * When managing deliveries
   - Terms of Service: https://www.fezdelivery.co/terms-condition

# Third-Party Services #

1. TEC-IT Barcode Generator
   - Service URL: https://barcode.tec-it.com/
   - Purpose: Generates barcodes for order tracking
   - Data Transmitted:
     * Order numbers (to generate unique barcodes)
   - When Used:
     * When generating shipping labels
     * When printing order barcodes

## Data Privacy and Security ##

All data transmission to our services is encrypted using HTTPS. We maintain strict data protection standards and comply with relevant data protection regulations. For detailed information about how we handle your data, please refer to our terms and conditions at https://www.fezdelivery.co/terms-condition.

## Support ##

For support and feedback, please visit https://www.fezdelivery.co/ or contact us at support@fezdelivery.co.


