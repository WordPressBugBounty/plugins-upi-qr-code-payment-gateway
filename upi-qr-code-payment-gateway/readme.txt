=== UPI QR Code Payment Gateway ===

Contributors: dewtechnolab, dew491212
Tags: woocommerce, upi payment, qrcode, gpay, paytm upi
Requires at least: 4.6
Stable tag: 1.5.0
Tested up to: 7.1
WC requires at least: 4.0
WC tested up to: 11.2
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

This Plugin enables WooCommerce shop owners to get direct and instant payments through UPI apps like GPay, PhonePe, Paytm or any banking UPI app.

== Description ==

This Plugin enables WooCommerce shopowners to get direct and instant payments through UPI apps like Google Pay, Whatsapp, Amazon Pay Paytm, BHIM, PhonePe or any banking UPI app to save payment gateway charges in India.

### UPI QR Code Payment Gateway

UPI (Unified Payments Interface) is a payment standard owned by National Payment Corporation of India, a government owned instant payment solution. UPI works 24x7 and is free subject to prevalent government guidelines.

When this plugin is installed, a customer will see UPI as a payment option. When customer chooses it, it will open a page which shows the UPI QR Code containing the payment details and in mobile it will also show a button which takes customer to the list of installed UPI mobile applications. Customer can choose an app and pay the required amount. 

Like UPI QR Code Payment Gateway plugin? Consider leaving a [review](https://wordpress.org/support/plugin/upi-qr-code-payment-gateway/reviews/#new-post).

#### Benefits

* Simple & Easy to Setup.
* Avoid Payment Gateway Fees.
* Instant Money Settlement.
* Direct Payment.
* 100% Success Rate.
* Send QR Code link to Customer.
* 24x7 Availability.
* Multisite Network Supported.
* No KYC, No GST number Required.
* No Hidden or Additional Charges.

#### Detailed Steps

* Customer will see UPI as a payment option in WooCommerce Checkout page.
* When customer chooses it, it will open a page which shows the UPI QR Code containing the payment details and in mobile it will also show a button which takes customer to the list of installed UPI mobile applications.
* Customer can scan the QR Code using any UPI app or choose an app from mobile to pay the required order amount.
* After successful payment, a 12-digits Transaction/UTR ID will appear in the Customer's UPI app from which he/she made the payment.
* After that, customer needs to enter that 12 digit transaction number to the "Enter the Transaction ID" textbox and click submit.
* After successful submission of the ID, the order will be marked as on hold (customizable).
* Now, Merchant gets a notification on the mobile on his/her UPI app (Google Pay/PhonePe/BHIM/Paytm etc.)
* Merchant opens notification, sees a payment made. Sees the "Order ID".
* Merchant opens the WooCommerce Dashboard, checks the "pending orders" for this Order ID.
* Checks the order details and processes it (shipping etc) and makes the orders as "processing" or "completed".

#### Compatibility

* This plugin is fully compatible with WordPress Version 4.6 and beyond and also compatible with any WordPress theme.

#### Support
* Community support via the [support forums](https://wordpress.org/support/plugin/upi-qr-code-payment-gateway/) at WordPress.org.

== Installation ==

1. Visit 'Plugins > Add New'.
1. Search for 'UPI QR Code Payment Gateway' and install it.
1. Or you can upload the `upi-qr-code-payment-gateway` folder to the `/wp-content/plugins/` directory manually.
1. Activate UPI QR Code Payment Gateway from your Plugins page.
1. After activation go to 'WooCommerce > Settings > Payments > UPI QR Code'.
1. Enable options and save changes.

== Frequently Asked Questions ==

= Is there any admin interface for this plugin? =

Yes. You can access this from 'WooCommerce > Settings > Payments > UPI QR Code'.

= How to use this plugin? =

Go to 'WooCommerce > Settings > Payments > UPI QR Code', enable/disable options as per your need and save your changes.

= Is this plugin compatible with any themes? =

Yes, this plugin is compatible with any theme. Also, compatible with Divi, Astra themes.

= I want auto verification after payment is done. Is is possible? =

Unfortunately no, automatic payment verification is not possible as NPCI does not allow to use their API and verify the transaction of any website. Keep "Payment Complete Status" on "On Hold" and confirm each payment in your bank or UPI app before completing the order.

= What Merchant Category Code should I use? =

Use the 4-digit code from your bank or payment provider if your UPI ID is a merchant account. If you don't have one, use 0000. Since 1.5.0 an empty code is sent as 0000; earlier versions saved 8931 as the default, so check this setting after updating.

= Where are payment screenshots stored? =

Since version 1.5.0, screenshots are stored privately in `wp-content/uploads/dwu-payment-screenshots/` under random file names, not in the Media Library, because they show the customer's bank details. Only users who can edit orders can open them, from the order notes or the "UPI Payment" column. On Nginx, also deny web access to that folder in your server configuration. Screenshots uploaded before 1.5.0 stay in the Media Library.

= Why are unpaid UPI orders not cancelled after the WooCommerce "Hold stock" time? =

Customers often pay in their UPI app and come back later to enter the Transaction ID, so UPI orders are kept for 24 hours by default before WooCommerce may cancel them (setting: "Unpaid Order Timeout"). Orders whose payment details were submitted are never cancelled automatically.

= The plugin isn't working or have a bug? =

Post detailed information about the issue in the [support forum](https://wordpress.org/support/plugin/upi-qr-code-payment-gateway/) and we will work to fix it.

== Screenshots ==

1. Admin Dashboard
2. Checkout page
3. QR Code Page
4. Qr Code Verification Message
5. Order Received/thank you page.
6. Order Details

== Changelog ==

If you like UPI QR Code Payment Gateway, please take a moment to [leave a review](https://wordpress.org/support/plugin/upi-qr-code-payment-gateway/reviews/#new-post). It helps to keep development and support going strong. Thank you!

= 1.5.0 =
Release Date: October 11, 2026

* Fixed: On the block-based checkout, the plugin's settings were not loaded, so the UPI handle list was missing and checkout failed with "UPI Handle is a required field" on default settings. The configured title, description and "Hide Field" option now work there too.
* Security: Payment screenshots are now stored privately (not in the Media Library) under random file names and can only be opened by users who can edit orders.
* Security: Clear warnings when "Payment Complete Status" is Processing or Completed, as submitted Transaction IDs are not verified automatically.
* Added: "Unpaid Order Timeout" setting (24 hours by default). Unpaid UPI orders are kept longer before WooCommerce cancels them, and orders whose payment details were submitted are never cancelled automatically.
* Added: The customer's UPI ID and payment screenshot are included in WordPress personal data export and erasure.
* Fixed: A customer submitting payment details for a cancelled order now sees what to do if money was debited.
* Fixed: Stock is restored when an order submitted with "Payment Complete Status" set to Pending is cancelled.
* Fixed: The UPI payment link now always uses a two-decimal amount, and special characters in order numbers no longer break it.
* Fixed: When no Merchant Category Code is set, the UPI payment link now uses 0000 instead of 8931, which UPI apps can reject. New installs default to 0000. Existing settings are not changed: if yours shows 8931 and you have no merchant category code, change it to 0000.
* Changed: The "Payment Buttons" option (Google Pay / PhonePe / Paytm buttons on mobile) is removed, as UPI apps no longer support these links from websites. Developers can turn the buttons back on with the `dwu_enable_payment_buttons` filter.
* Added: Translation template (languages/upi-qr-code-payment-gateway.pot), and translations saved in the plugin's languages folder (for example with Loco Translate) are now loaded.
* Tweak: Payment confirmation also finds the order by its order key if the order ID does not match (the key and security check are still required).
* Tweak: The review request appears after one week, and the Knit Pay - UPI automatic verification suggestion covers more merchant UPI IDs.
* Fixed: The "UPI Payment" column was missing from the Orders screen because the gateway was not loaded there.
* Tweak: The orders list marks submitted payments as "Submitted, verify payment" and links to the screenshot.
* Tweak: The screenshot is removed when its order is permanently deleted.
* Tweak: Checkout scripts for the classic form are no longer loaded on the block-based checkout.
* Tweak: Removed the unused log folder and added uninstall cleanup of the gateway settings (order data is kept).
* Tweak: Declared compatibility with the WooCommerce Cart and Checkout blocks.
* Tested with WordPress v7.1 and WooCommerce v11.2.

= 1.4.4 =
Release Date: September 27, 2026

* Security: Fixed an issue where the payment confirmation request could update an order without the order key. The request is now verified with an order-bound nonce, the order key, the payment method and the order status.
* Security: Screenshot uploads are now limited to real JPEG/PNG images with a size limit.
* Security: Improved sanitization and escaping of all input and output.
* Fixed: "Complete the payment" text was added to emails after the payment was submitted.
* Fixed: Server-side validation of Transaction ID and screenshot.
* Fixed: Admin notices and text domain.
* Fixed: Required UPI field indicator and default settings on block checkout.
* Tested with WordPress v7.1.

= 1.4.3 =
Release Date: Jun 07, 2026

* Added compatibility with WordPress version 7.0.0.

= 1.4.2 =
Release Date: January 04, 2025

* Fixed: Setting Page.

= 1.4.1 =
Release Date: December 28, 2024

* Tested with WordPress v6.7.1 and WooCommerce v9.5.1

= 1.4.0 =
Release Date: March 7, 2024

* Tweak: PHP 8.3 Support.
* Added: Support for WooCommerce Block-based checkout.
* Added: Security check on submission.
* Added: Screenshot upload field.

= 1.3.0 =
Release Date: May 31, 2022

* Added: Merchant Category Code input option.
* Tested with WordPress v6.0 and WooCommerce v6.8.2.

= 1.2.0 =
Release Date: March 18, 2021

* Added: Option to add Merchant Codes according to the latest UPI Specification. Please use a valid Merchant UPI VPA ID (not user UPI ID), otherwise all payments will be failed.
* Added: A button to download the QR Codes on Mobile Devices easily.
* Tweak: Hide Copy UPI ID button by default. It can enabled via this filter: `add_filter( 'dwu_show_upi_id_copy_button', '__return_true' );`.

= 1.0.0 =
Release Date: March 14, 2020

* Initial release.

== Upgrade Notice ==

= 1.5.0 =
Fixes UPI payments on the block-based checkout, stores payment screenshots privately and keeps unpaid UPI orders longer before they are cancelled. Recommended for all stores.

= 1.4.4 =
Important security update. Please update immediately.
