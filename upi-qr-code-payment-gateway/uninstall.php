<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the gateway settings and admin-notice data. Order data is kept on purpose: the UPI
 * Transaction IDs, payer UPI IDs and payment screenshots (uploads/dwu-payment-screenshots) are
 * payment records the store may still need. Delete orders, or use Tools > Erase Personal Data,
 * to remove them.
 *
 * @package UPI QR Code Payment Gateway
 */

// If uninstall not called from WordPress, abort!!!
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dwu_options = [
	'woocommerce_dew-wc-upi_settings',
	'dwu_plugin_installed_time',
	'dwu_plugin_dismissed_time',
	'dwu_plugin_dismissed_time_donate',
	'dwu_plugin_dismiss_rating_notice',
	'dwu_plugin_no_thanks_rating_notice',
	'dwu_plugin_dismiss_donate_notice',
	'dwu_plugin_no_thanks_donate_notice',
	'dwu_plugin_dismiss_knit_pay_upi_notice',
];

foreach ( $dwu_options as $dwu_option ) {
	delete_option( $dwu_option );
}

delete_transient( 'dwu-admin-notice-on-activation' );
delete_transient( 'dwu_plugin_knit_pay_upi_notice_random_priority' );
