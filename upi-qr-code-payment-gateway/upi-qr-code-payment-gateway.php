<?php
/**
 * Plugin Name: UPI QR Code Payment Gateway
 * Plugin URI: http://dewtechnolab.com/project/
 * Description: It enables a WooCommerce site to accept payments through UPI apps like Google Pay, Paytm, AmazonPay, BHIM, PhonePe or any Banking UPI app. Avoid payment gateway charges.
 * Version: 1.5.0
 * Requires at least: 4.6
 * Requires PHP: 7.4
 * Author: Dew Technolab
 * Author URI: http://dewtechnolab.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.html
 * Text Domain: upi-qr-code-payment-gateway
 * Domain Path: /languages
 * WC requires at least: 4.0
 * WC tested up to: 11.2
 * Requires Plugins: woocommerce
 *
 * UPI QR Code Payment Gateway is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * UPI QR Code Payment Gateway is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UPI QR Code Payment Gateway plugin. If not, see <http://www.gnu.org/licenses/>.
 * 
 * @category WooCommerce
 * @package  Woo UPI QR Code Payment Gateway
 * @author   Dew technolab <dewtechnolab@gmail.com>
 * @license  http://www.gnu.org/licenses/ GNU General Public License
 * @link     http://dewtechnolab.com/
**/

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit;

/**
 * DWU class.
 *
 * @class Main class of the plugin.
 */
final class DWU {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.5.0';

	/**
	 * Minimum version of WordPress required to run DWU.
	 *
	 * @var string
	 */
	private $wordpress_version = '4.6';

	/**
	 * Minimum version of PHP required to run DWU.
	 *
	 * @var string
	 */
	private $php_version = '7.4';

	/**
	 * Hold install error messages.
	 *
	 * @var bool
	 */
	private $messages = [];

	/**
	 * The single instance of the class.
	 *
	 * @var DWU
	 */
	protected static $instance = null;

	/**
	 * Retrieve main DWU instance.
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @see dwu()
	 * @return DWU
	 */
	public static function get() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof DWU ) ) {
			self::$instance = new DWU();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Instantiate the plugin.
	 */
	private function setup() {
		// Define plugin constants.
		$this->define_constants();

		if ( ! $this->is_requirements_meet() ) {
			return;
		}

		// Instantiate services.
		$this->instantiate();

		// Loaded action.
		do_action( 'dwu_loaded' );
	}

	/**
	 * Check that the WordPress and PHP setup meets the plugin requirements.
	 *
	 * @return bool
	 */
	private function is_requirements_meet() {
		// Check WordPress version.
		if ( version_compare( get_bloginfo( 'version' ), $this->wordpress_version, '<' ) ) {
			/* translators: WordPress Version */
			$this->messages[] = sprintf( esc_html__( 'You are using the outdated WordPress, please update it to version %s or higher.', 'upi-qr-code-payment-gateway' ), $this->wordpress_version );
		}

		// Check PHP version.
		if ( version_compare( phpversion(), $this->php_version, '<' ) ) {
			/* translators: PHP Version */
			$this->messages[] = sprintf( esc_html__( 'UPI QR Code Payment Gateway requires PHP version %s or above. Please update PHP to run this plugin.', 'upi-qr-code-payment-gateway' ), $this->php_version );
		}

		if ( empty( $this->messages ) ) {
			return true;
		}

		// Auto-deactivate plugin.
		add_action( 'admin_init', [ $this, 'auto_deactivate' ] );
		add_action( 'admin_notices', [ $this, 'activation_error' ] );

		return false;
	}

	/**
	 * Auto-deactivate plugin if requirements are not met, and display a notice.
	 */
	public function auto_deactivate() {
		deactivate_plugins( DWU_BASENAME );
        if ( isset( $_GET['activate'] ) ) { // phpcs:ignore
            unset( $_GET['activate'] ); // phpcs:ignore
		}
	}

	/**
	 * Error notice on plugin activation.
	 */
	public function activation_error() {
		?>
		<div class="notice dwu-notice notice-error">
			<p>
				<?php echo wp_kses( join( '<br>', $this->messages ), [ 'br' => [] ] ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Define the plugin constants.
	 */
	private function define_constants() {
		define( 'DWU_VERSION', $this->version );
		define( 'DWU_FILE', __FILE__ );
		define( 'DWU_PATH', dirname( DWU_FILE ) . '/' );
		define( 'DWU_URL', plugins_url( '', DWU_FILE ) . '/' );
		define( 'DWU_BASENAME', plugin_basename( DWU_FILE ) );
	}

	/**
	 * Instantiate services.
	 */
	private function instantiate() {
		// Activation hook.
		register_activation_hook(
			DWU_FILE,
			function () {
				set_transient( 'dwu-admin-notice-on-activation', true, 5 );
			}
		);

		// Deactivation hook.
		register_deactivation_hook(
			DWU_FILE,
			function () {
				delete_option( 'dwu_plugin_dismiss_rating_notice' );
				delete_option( 'dwu_plugin_no_thanks_rating_notice' );
				delete_option( 'dwu_plugin_installed_time' );
				delete_option( 'dwu_plugin_dismiss_donate_notice' );
				delete_option( 'dwu_plugin_no_thanks_donate_notice' );
				delete_option( 'dwu_plugin_dismissed_time' );
				delete_option( 'dwu_plugin_dismissed_time_donate' );
				delete_option( 'dwu_plugin_dismiss_knit_pay_upi_notice' );
			}
		);

		// Private payment screenshots and privacy tools (needed even when the gateway isn't loaded).
		require_once DWU_PATH . 'core/classes/class.order-data.php';
		DWU_Order_Data::init();

		// Initialize the action and filter hooks.
		$this->init_actions();
	}

	/**
	 * Initialize WordPress action and filter hooks.
	 */
	private function init_actions() {
		// Load translations (on init, as WordPress 6.7+ requires).
		add_action( 'init', [ $this, 'localization_setup' ] );

		// Add plugin action links.
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_filter( 'plugin_action_links_' . DWU_BASENAME, [ $this, 'action_links' ] );

		// Declaring HPOS compatibility.
		add_action( 'before_woocommerce_init', [ $this, 'declare_compatibility' ] );

		// Register payment gateway.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ] );

		// Load payment gateway.
		add_action( 'plugins_loaded', [ $this, 'load_gateway' ] );
		add_action( 'woocommerce_blocks_loaded', [ $this, 'block_support' ] );

		// Give UPI orders longer before WooCommerce cancels them as unpaid (runs in cron, where gateways aren't loaded).
		add_filter( 'woocommerce_cancel_unpaid_order', [ $this, 'maybe_keep_unpaid_order' ], 10, 2 );

		// The gateway registers the "UPI Payment" orders column, but WooCommerce doesn't load gateways on the order screens.
		add_action( 'current_screen', [ $this, 'load_gateway_on_order_screens' ] );

		// Load admin notices.
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'admin_notices', [ $this, 'payment_status_notice' ] );
		add_action( 'admin_init', [ $this, 'dismiss_notice' ] );
	}

	/**
	 * Load the payment gateways on the order list screens (HPOS and legacy), before the list table is built,
	 * so the gateway's "UPI Payment" column hooks are registered.
	 *
	 * @param WP_Screen $screen Current admin screen.
	 */
	public function load_gateway_on_order_screens( $screen ) {
		if ( $screen && in_array( $screen->id, [ 'woocommerce_page_wc-orders', 'edit-shop_order' ], true ) && function_exists( 'WC' ) ) {
			WC()->payment_gateways();
		}
	}

	/**
	 * Decide whether WooCommerce may auto-cancel an unpaid UPI order.
	 *
	 * Customers often pay in their UPI app and come back later to enter the Transaction ID, so
	 * UPI orders get their own, longer window than WooCommerce's "Hold stock" time. Orders whose
	 * payment details were already submitted are never cancelled automatically: the merchant
	 * checks them.
	 *
	 * @param bool     $should_cancel Whether WooCommerce would cancel the order.
	 * @param WC_Order $order         The unpaid (pending) order.
	 * @return bool
	 */
	public function maybe_keep_unpaid_order( $should_cancel, $order ) {
		if ( ! $should_cancel || ! is_a( $order, 'WC_Order' ) || 'dew-wc-upi' !== $order->get_payment_method() ) {
			return $should_cancel;
		}

		if ( 'yes' === $order->get_meta( '_dwu_order_paid', true ) ) {
			return false;
		}

		$settings = get_option( 'woocommerce_dew-wc-upi_settings', [] );
		$hours    = isset( $settings['unpaid_timeout'] ) && '' !== $settings['unpaid_timeout'] ? (float) $settings['unpaid_timeout'] : 24;

		/**
		 * Filters how many hours an unpaid UPI order is kept before WooCommerce may cancel it.
		 * 0 falls back to WooCommerce's "Hold stock" time.
		 *
		 * @since 1.5.0
		 *
		 * @param float    $hours Hours to keep the order. Default from the gateway settings (24).
		 * @param WC_Order $order The unpaid order.
		 */
		$hours   = (float) apply_filters( 'dwu_unpaid_order_timeout_hours', $hours, $order );
		$created = $order->get_date_created();

		if ( $hours > 0 && $created && $created->getTimestamp() > time() - (int) ( $hours * HOUR_IN_SECONDS ) ) {
			// Too early: WooCommerce checks again on its next run.
			return false;
		}

		return $should_cancel;
	}

	/**
	 * Warn on WooCommerce screens when submitted, unverified payment details move orders to
	 * Processing or Completed (which releases downloads and starts fulfilment).
	 */
	public function payment_status_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'plugins', 'woocommerce_page_wc-settings', 'woocommerce_page_wc-orders', 'edit-shop_order' ], true ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_dew-wc-upi_settings', [] );
		$status   = isset( $settings['payment_status'] ) ? $settings['payment_status'] : 'on-hold';
		$enabled  = ! isset( $settings['enabled'] ) || 'yes' === $settings['enabled'];

		if ( ! $enabled || ! in_array( $status, [ 'processing', 'completed' ], true ) ) {
			return;
		}

		$statuses = wc_get_order_statuses();
		$label    = isset( $statuses[ 'wc-' . $status ] ) ? $statuses[ 'wc-' . $status ] : $status;
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'UPI QR Code Payment Gateway:', 'upi-qr-code-payment-gateway' ); ?></strong>
				<?php
				/* translators: %s: order status name, e.g. Processing */
				echo esc_html( sprintf( __( 'orders are set to %s as soon as a customer submits a UPI Transaction ID. These IDs are not checked automatically, so anyone can enter any 12-digit number and receive downloads, or have the order fulfilled, without paying. We recommend "On Hold" and confirming each payment in your bank or UPI app.', 'upi-qr-code-payment-gateway' ), $label ) );
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=dew-wc-upi' ) ); ?>"><?php esc_html_e( 'Change setting', 'upi-qr-code-payment-gateway' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Load translations from the plugin's languages folder, e.g. files made from languages/upi-qr-code-payment-gateway.pot
	 * with Loco Translate or Poedit. WordPress.org language packs keep working as before.
	 */
	public function localization_setup() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- also loads translations shipped or saved in /languages, not only WordPress.org language packs.
		load_plugin_textdomain( 'upi-qr-code-payment-gateway', false, dirname( DWU_BASENAME ) . '/languages' );
	}

	/**
	 * Add extra links as row meta on the plugin screen.
	 *
	 * @param  mixed $links Plugin Row Meta.
	 * @param  mixed $file  Plugin Base file.
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( DWU_BASENAME !== $file ) {
			return $links;
		}

		$more = [
			'<a href="https://wordpress.org/support/plugin/upi-qr-code-payment-gateway/" target="_blank">' . __( 'Support', 'upi-qr-code-payment-gateway' ) . '</a>',
			'<a href="https://wordpress.org/plugins/upi-qr-code-payment-gateway/#faq" target="_blank">' . __( 'FAQ', 'upi-qr-code-payment-gateway' ) . '</a>',
			// '<a href="https://www.divyeshghediya.in/donate" target="_blank">' . __( 'Donate', 'upi-qr-code-payment-gateway' ) . '</a>',
		];

		return array_merge( $links, $more );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param  mixed $links Plugin Action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=dew-wc-upi' ) . '">' . __( 'Settings', 'upi-qr-code-payment-gateway' ) . '</a>';

		return $links;
	}

	/**
	 * Declaring HPOS compatibility
	 */
	public function declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DWU_FILE, true );
			// The gateway ships its own Checkout block integration (core/blocks/).
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DWU_FILE, true );
		}
	}

	/**
	 * Register WooCommerce gateway.
	 *
	 * @param  mixed $links Plugin Action links.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'UPI_WC_Payment_Gateway'; // class name

		return $gateways;
	}

	/**
	 * Load Payment Gateway.
	 */
	public function load_gateway() {
		if ( class_exists( '\WC_Payment_Gateway' ) ) {
			require_once DWU_PATH . 'core/classes/class.payment.php';
		}
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 */
	public function block_support() {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once DWU_PATH . 'core/blocks/class-blocks-support.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new UPI_WC_Payment_Gateway_Blocks_Support() );
				}
			);
		}
	}

	/**
	 * Show internal admin notices.
	 */
	public function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check transient, if available display notice
		if ( get_transient( 'dwu-admin-notice-on-activation' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong>
				<?php
				printf(
					wp_kses(
						/* translators: 1: Plugin name, 2: Plugin version, 3: Settings page URL */
						__( 'Thanks for installing %1$s v%2$s plugin. Click <a href="%3$s">here</a> to configure plugin settings.', 'upi-qr-code-payment-gateway' ),
						[ 'a' => [ 'href' => [] ] ]
					),
					'UPI QR Code Payment Gateway',
					esc_html( DWU_VERSION ),
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=dew-wc-upi' ) )
				);
				?>
				</strong></p>
			</div>
			<?php
			delete_transient( 'dwu-admin-notice-on-activation' );
		}

		$show_rating = true;
		if ( $this->calculate_time() > strtotime( '-7 days' )
			|| '1' === get_option( 'dwu_plugin_dismiss_rating_notice' )
			|| apply_filters( 'dwu_plugin_hide_sticky_notice', false ) ) {
			$show_rating = false;
		}

		if ( $show_rating ) {
			$dismiss   = wp_nonce_url( add_query_arg( 'dwu_notice_action', 'dismiss_rating' ), 'dwu_notice_nonce' );
			$no_thanks = wp_nonce_url( add_query_arg( 'dwu_notice_action', 'no_thanks_rating' ), 'dwu_notice_nonce' );
			?>
			<div class="notice notice-success">
				<p><?php echo wp_kses( __( 'Hey, I noticed you&rsquo;ve been using UPI QR Code Payment Gateway for more than a week &ndash; that&rsquo;s awesome! Could you please do me a BIG favor and leave a <strong>review</strong> on WordPress.org? Just to help me spread the word and boost my motivation.', 'upi-qr-code-payment-gateway' ), [ 'strong' => [] ] ); ?></p>
				<p><a href="https://wordpress.org/support/plugin/upi-qr-code-payment-gateway/reviews/#new-post" target="_blank" class="button button-secondary"><?php esc_html_e( 'Ok, you deserve it', 'upi-qr-code-payment-gateway' ); ?></a>&nbsp;
				<a href="<?php echo esc_url( $dismiss ); ?>" class="already-did"><strong><?php esc_html_e( 'I already did', 'upi-qr-code-payment-gateway' ); ?></strong></a>&nbsp;<strong>|</strong>
				<a href="<?php echo esc_url( $no_thanks ); ?>" class="later"><strong><?php esc_html_e( 'Nope, maybe later', 'upi-qr-code-payment-gateway' ); ?></strong></a></p>
			</div>
			<?php
		}

		/*
		* Knit Pay UPI Notice.
		*/
		$show_knit_pay_upi_notice            = false;
		$knit_pay_upi_notice_random_priority = get_transient( 'dwu_plugin_knit_pay_upi_notice_random_priority' );

		// Set random priority if not already set
		if ( empty( $knit_pay_upi_notice_random_priority ) ) {
			$knit_pay_upi_notice_random_priority = strval( wp_rand( 1, 75 ) );
			set_transient( 'dwu_plugin_knit_pay_upi_notice_random_priority', $knit_pay_upi_notice_random_priority, WEEK_IN_SECONDS );
		}

		// Only proceed with VPA check if user was randomly selected
		if ( '1' === $knit_pay_upi_notice_random_priority ) {
			$dwu_settings = get_option( 'woocommerce_dew-wc-upi_settings', [] );
			$vpa            = isset( $dwu_settings['vpa'] ) ? sanitize_text_field( $dwu_settings['vpa'] ) : '';

			// Check for specific VPA patterns
			if ( ! empty( $vpa ) && preg_match( '/^(q.+@ybl|paytm.+@[a-z]+|bharatpe.+@[a-z]+|.+@hdfcbank|.+@freecharge)$/i', $vpa ) ) {
				$show_knit_pay_upi_notice = true;
			}
		}

		// Check if notice was previously dismissed
		if ( '1' === get_option( 'dwu_plugin_dismiss_knit_pay_upi_notice' ) ) {
			$show_knit_pay_upi_notice = false;
		}

		if ( $show_knit_pay_upi_notice ) {
			$dismiss   = wp_nonce_url(
				add_query_arg( 'dwu_notice_action', 'dismiss_knit_pay_upi' ),
				'dwu_notice_nonce'
			);
			$no_thanks = wp_nonce_url(
				add_query_arg( 'dwu_notice_action', 'no_thanks_knit_pay_upi' ),
				'dwu_notice_nonce'
			);
			?>

			<div class="notice notice-success">
				<p><strong>UPI QR Code Payment Gateway for WooCommerce</strong> - <?php esc_html_e( 'Exciting News! You no longer have to manually check the payment status of your UPI/QR payments. Knit Pay - UPI plugin can check the payment status automatically for you.', 'upi-qr-code-payment-gateway' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=Knit%2520Pay%2520UPI%2520QR%2520code%2520RapidAPI&tab=search&type=term' ) ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Get it Now', 'upi-qr-code-payment-gateway' ); ?></a>&nbsp;
					<a href="<?php echo esc_url( $no_thanks ); ?>" class="later"><strong><?php esc_html_e( 'Remind Later', 'upi-qr-code-payment-gateway' ); ?></strong></a>&nbsp;|
					<a href="<?php echo esc_url( $dismiss ); ?>" class="already-did"><strong><?php esc_html_e( 'Not interested', 'upi-qr-code-payment-gateway' ); ?></strong></a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Dismiss admin notices.
	 */
	public function dismiss_notice() {
		// Check for Rating Notice
		if ( get_option( 'dwu_plugin_no_thanks_rating_notice' ) === '1'
			&& get_option( 'dwu_plugin_dismissed_time' ) <= strtotime( '-10 days' ) ) {
			delete_option( 'dwu_plugin_dismiss_rating_notice' );
			delete_option( 'dwu_plugin_no_thanks_rating_notice' );
		}

		// Check for Donate Notice
		if ( get_option( 'dwu_plugin_no_thanks_donate_notice' ) === '1'
			&& get_option( 'dwu_plugin_dismissed_time_donate' ) <= strtotime( '-14 days' ) ) {
			delete_option( 'dwu_plugin_dismiss_donate_notice' );
			delete_option( 'dwu_plugin_no_thanks_donate_notice' );
		}

		if ( empty( $_REQUEST['dwu_notice_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'dwu_notice_nonce' );

		$notice      = sanitize_key( wp_unslash( $_REQUEST['dwu_notice_action'] ) );
		$notice      = explode( '_', $notice );
		$notice_type = end( $notice );
		array_pop( $notice );
		$notice_action = join( '_', $notice );

		if ( ! in_array( $notice_type, [ 'rating', 'donate', 'upi' ], true ) ) {
			return;
		}

		if ( 'dismiss_knit_pay' === $notice_action ) {
			// Knit Pay UPI Notice Dismiss.
			update_option( 'dwu_plugin_dismiss_knit_pay_upi_notice', '1' );
		} elseif ( 'no_thanks_knit_pay' === $notice_action ) {
			// Knit Pay UPI Notice skip atleast for 1 week.
			set_transient( 'dwu_plugin_knit_pay_upi_notice_random_priority', '0', WEEK_IN_SECONDS );
		}

		if ( 'dismiss' === $notice_action ) {
			update_option( 'dwu_plugin_dismiss_' . $notice_type . '_notice', '1' );
		}
	
		if ( 'no_thanks' === $notice_action ) {
			update_option( 'dwu_plugin_no_thanks_' . $notice_type . '_notice', '1' );
			update_option( 'dwu_plugin_dismiss_' . $notice_type . '_notice', '1' );
			if ( 'donate' === $notice_type ) {
				update_option( 'dwu_plugin_dismissed_time_donate', time() );
			} else {
				update_option( 'dwu_plugin_dismissed_time', time() );
			}
		}
	
		wp_safe_redirect( remove_query_arg( [ 'dwu_notice_action', '_wpnonce' ] ) );
		exit;
	}

	/**
	 * Calculate install time.
	 */
	private function calculate_time() {
		$installed_time = get_option( 'dwu_plugin_installed_time' );
		
		if ( ! $installed_time ) {
			$installed_time = time();
			update_option( 'dwu_plugin_installed_time', $installed_time );
		}
		
		return $installed_time;
	}
}

/**
 * Returns the main instance of DWU to prevent the need to use globals.
 *
 * @return DWU
 */
function dwu() {
	return DWU::get();
}

// Start it.
dwu();