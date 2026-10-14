<?php
/**
 * The admin-facing functionality of the plugin.
 *
 * @package    UPI QR Code Payment Gateway
 * @subpackage Includes
 * @author     Dew Technolab
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 */

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit;

/**
 * UPI_WC_Payment_Gateway class.
 *
 * @class Main payment gateway class of the plugin.
 */
class UPI_WC_Payment_Gateway extends \WC_Payment_Gateway {
	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'dew-wc-upi';

	protected $instructions;
	protected $instructions_mobile;
	protected $confirm_message;
	protected $thank_you;
	protected $payment_status;
	protected $name;
	protected $vpa;
	protected $pay_button;
	protected $mc_code;
	protected $upi_address;
	protected $require_upi;
	protected $theme;
	protected $transaction_id;
	protected $transaction_image;
	protected $intent;
	protected $download_qr;
	protected $qrcode_mobile;
	protected $hide_on_mobile;
	protected $email_enabled;
	protected $email_subject;
	protected $email_heading;
	protected $additional_content;
	protected $default_status;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->icon               = apply_filters( 'dwu_gateway_icon', DWU_URL . 'images/payment.gif' );
		$this->has_fields         = true;
		$this->method_title       = __( 'UPI QR Code', 'upi-qr-code-payment-gateway' );
		$this->method_description = sprintf( '%s <span style="font-weight: 600;color: #ff0000;">%s</span>', __( 'Allows customers to use UPI mobile app like Paytm, Google Pay, BHIM, PhonePe to pay to your bank account directly using UPI.', 'upi-qr-code-payment-gateway' ), __( 'Merchant or Administrator of this site needs to manually check the payment and mark it as paid on the Order edit page as automatic payment verification is not available within this payment method.', 'upi-qr-code-payment-gateway' ) );
		$this->order_button_text  = apply_filters( 'dwu_order_button_text', __( 'Proceed to Payment', 'upi-qr-code-payment-gateway' ) );

		// Method with all the options fields
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title               = $this->get_option( 'title' );
		$this->description         = $this->get_option( 'description' );
		$this->instructions        = $this->get_option( 'instructions', $this->description );
		$this->instructions_mobile = $this->get_option( 'instructions_mobile', $this->description );
		$this->confirm_message     = $this->get_option( 'confirm_message' );
		$this->thank_you           = $this->get_option( 'thank_you' );
		$this->payment_status      = $this->get_option( 'payment_status', 'on-hold' );
		$this->name                = $this->get_option( 'name' );
		$this->vpa                 = $this->get_option( 'vpa' );
		$this->pay_button          = $this->get_option( 'pay_button' );
		$this->mc_code             = $this->get_option( 'mc_code' );
		$this->upi_address         = $this->get_option( 'upi_address', 'show_handle' );
		$this->require_upi         = $this->get_option( 'require_upi', 'yes' );
		$this->theme               = $this->get_option( 'theme', 'light' );
		$this->transaction_id      = $this->get_option( 'transaction_id', 'show_require' );
		$this->transaction_image   = $this->get_option( 'transaction_image', 'show_require' );
		/**
		 * UPI app buttons (intent links) are off: UPI apps no longer accept them from websites, so the setting was
		 * removed (as in the original UPI QR Code plugin). Developers can still turn them back on.
		 *
		 * @since 1.5.0
		 *
		 * @param bool $enabled Whether to show the Google Pay / PhonePe / Paytm buttons on mobile. Default false.
		 */
		$this->intent              = apply_filters( 'dwu_enable_payment_buttons', false ) ? 'yes' : 'no';
		$this->download_qr         = $this->get_option( 'download_qr', 'no' );
		$this->qrcode_mobile       = $this->get_option( 'qrcode_mobile', 'yes' );
		$this->hide_on_mobile      = $this->get_option( 'hide_on_mobile', 'no' );
		$this->email_enabled       = $this->get_option( 'email_enabled' );
		$this->email_subject       = $this->get_option( 'email_subject' );
		$this->email_heading       = $this->get_option( 'email_heading' );
		$this->additional_content  = $this->get_option( 'additional_content' );
		$this->default_status      = apply_filters( 'dwu_process_payment_order_status', 'pending' );
		
		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// We need custom JavaScript to obtain the transaction number
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		// Thank you page output
		add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'generate_qr_code' ], 4, 1 );

		// Verify payment from redirection
		add_action( 'woocommerce_api_dwu-payment', [ $this, 'capture_payment' ] );

		// Customize on hold email template subject
		add_filter( 'woocommerce_email_subject_customer_on_hold_order', [ $this, 'email_subject_pending_order' ], 10, 3 );

		// Customize on hold email template heading
		add_filter( 'woocommerce_email_heading_customer_on_hold_order', [ $this, 'email_heading_pending_order' ], 10, 3 );

		// Customize on hold email template additional content
		add_filter( 'woocommerce_email_additional_content_customer_on_hold_order', [ $this, 'email_additional_content_pending_order' ], 10, 3 );

		// Customer Emails
		add_action( 'woocommerce_email_after_order_table', [ $this, 'email_instructions' ], 10, 4 );

		// Add support for payment for on hold orders
		add_action( 'woocommerce_valid_order_statuses_for_payment', [ $this, 'on_hold_payment' ], 99, 2 );

		// Change wc payment link if exists payment method is QR Code
		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'custom_checkout_url' ], 99, 2 );
		
		// Add custom text on thankyou page
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'order_received_text' ], 99, 2 );

		// Disable upi payment gateway
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'disable_gateway' ], 999 );

		// Add order column data ( HPOS compatibility )
		add_filter( 'woocommerce_shop_order_list_table_columns', [ $this, 'column_item' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_column' ], 10, 2 );

		// Add order column data ( old post columns )
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'column_item' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_column' ], 10, 2 );

		// Check plugin availability
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {
		if ( in_array( get_woocommerce_currency(), apply_filters( 'dwu_supported_currencies', [ 'INR' ] ) ) ) {
			return true;
		}

		return false;
	}
	
	/**
	 * Admin Panel Options.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error">
				<p>
					<strong><?php esc_html_e( 'Gateway disabled', 'upi-qr-code-payment-gateway' ); ?></strong>: <?php esc_html_e( 'This plugin does not support your store currency. UPI Payment only supports Indian Currency. Contact developer for support.', 'upi-qr-code-payment-gateway' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'             => [
				'title'       => __( 'Enable / Disable:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable UPI QR Code Payment Method', 'upi-qr-code-payment-gateway' ),
				'description' => __( 'Enable this if you want to collect payment via UPI QR Codes.', 'upi-qr-code-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => false,
			],
			'title'               => [
				'title'       => __( 'Gateway Title:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Pay with UPI QR Code', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'description'         => [
				'title'       => __( 'Description:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'It uses UPI apps like BHIM, Paytm, Google Pay, PhonePe or any Banking UPI app to make payment.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'upi_address'         => [
				'title'       => __( 'Payee UPI Address (VPA):', 'upi-qr-code-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'If you want to collect UPI Address from customers on checkout page, set it here. You can verify the payment against this UPI ID.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
				'default'     => 'show_handle',
				'options'     => [
					'hide'        => __( 'Hide Field', 'upi-qr-code-payment-gateway' ),
					'show'        => __( 'Show Input Field', 'upi-qr-code-payment-gateway' ),
					'show_handle' => __( 'Show Input Field & Handle', 'upi-qr-code-payment-gateway' ),
				],
			],
			'require_upi'         => [
				'title'       => __( 'Require UPI ID:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'If you want to make UPI Address field required on checkout page, set it here.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
				'default'     => 'yes',
				'options'     => [
					'yes' => __( 'Require Field', 'upi-qr-code-payment-gateway' ),
					'no'  => __( 'Don\'t Require Field', 'upi-qr-code-payment-gateway' ),
				],
			],
			'payment_status'      => [
				'title'       => __( 'Payment Complete Status:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'select',
				'description' => sprintf( '%s <span style="font-weight: 600;color: #ff0000;">%s</span>', __( 'Order status set when the customer submits their payment details. Recommended: On Hold.', 'upi-qr-code-payment-gateway' ), __( 'Submitted Transaction IDs are not verified automatically. Processing or Completed releases downloads and starts fulfilment for payments nobody has checked.', 'upi-qr-code-payment-gateway' ) ),
				'desc_tip'    => false,
				'default'     => 'on-hold',
				'options'     => apply_filters(
					'dwu_settings_order_statuses',
					[
						'pending'    => __( 'Pending Payment', 'upi-qr-code-payment-gateway' ),
						'on-hold'    => __( 'On Hold', 'upi-qr-code-payment-gateway' ),
						'processing' => __( 'Processing', 'upi-qr-code-payment-gateway' ),
						'completed'  => __( 'Completed', 'upi-qr-code-payment-gateway' ),
					]
				),
			],
			'unpaid_timeout'      => [
				'title'             => __( 'Unpaid Order Timeout (hours):', 'upi-qr-code-payment-gateway' ),
				'type'              => 'number',
				'description'       => __( 'How long an unpaid UPI order is kept before WooCommerce cancels it, so customers who pay and come back later can still submit their Transaction ID. Orders whose payment details were submitted are never cancelled automatically. Set 0 to use the WooCommerce "Hold stock" time.', 'upi-qr-code-payment-gateway' ),
				'default'           => 24,
				'custom_attributes' => [
					'min'  => 0,
					'step' => 1,
				],
				'desc_tip'          => false,
			],
			'thank_you'           => [
				'title'       => __( 'Thank You Message:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This displays a message to customer after a successful payment is made.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Thank you for your order. We have received your payment details and will confirm your order as soon as we have verified the payment. Please check your email inbox for details.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'hide_on_mobile'      => [
				'title'       => __( 'Mobile Visibility:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Disable QR Code Payment Gateway on Mobile Devices', 'upi-qr-code-payment-gateway' ),
				'description' => __( 'Enable this if you want to disable QR Code Payment Gateway on Mobile Devices.', 'upi-qr-code-payment-gateway' ),
				'default'     => 'no',
				'desc_tip'    => false,
			],
			'payment_page'        => [
				'title'       => __( 'Payment Popup Settings', 'upi-qr-code-payment-gateway' ),
				'type'        => 'title',
				'description' => __( 'Customize various settings of the Payment Popup here.', 'upi-qr-code-payment-gateway' ),
			],
			'name'                => [
				'title'       => __( 'Your Store or Shop Name:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Enter Your Store or Shop name. If you are a person, you can enter your name.', 'upi-qr-code-payment-gateway' ),
				'default'     => get_bloginfo( 'name' ),
				'desc_tip'    => false,
			],
			'vpa'                 => [
				'title'       => __( 'Merchant UPI VPA ID:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'text',
				'description' => sprintf( '%s <span style="color: #ff0000;font-weight: 600;">%s</span>', __( 'Enter Your Merchant UPI VPA (e.g. 12345678@icici) at which you want to collect payments.', 'upi-qr-code-payment-gateway' ), __( 'Use only Merchant UPI ID. General/Normal User UPI VPA will not work.', 'upi-qr-code-payment-gateway' ) ),
				'default'     => '',
				'desc_tip'    => false,
			],
			'pay_button'          => [
				'title'       => __( 'Pay Now Button Text:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Enter the text to show as the payment button.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Scan & Pay Now', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'mc_code'             => [
				'title'       => __( 'Merchant Category Code:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'number',
				'description' => sprintf( '%s <a href="https://www.citibank.com/tts/solutions/commercial-cards/assets/docs/govt/Merchant-Category-Codes.pdf" target="_blank">%s</a> or <a href="https://docs.checkout.com/resources/codes/merchant-category-codes" target="_blank">%s</a> %s', __( 'You can refer to these links to find out your MCC.', 'upi-qr-code-payment-gateway' ), 'Citi Bank', 'Checkout.com', __( 'If you do not have one, use 0000.', 'upi-qr-code-payment-gateway' ) ),
				'default'     => '0000',
				'desc_tip'    => false,
			],
			'theme'               => [
				'title'       => __( 'Popup Theme:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select the QR Code Popup theme here.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
				'default'     => 'light',
				'options'     => apply_filters(
					'dwu_popup_themes',
					[
						'light' => __( 'Light Theme', 'upi-qr-code-payment-gateway' ),
						'dark'  => __( 'Dark Theme', 'upi-qr-code-payment-gateway' ),
					]
				),
			],
			'transaction_id'      => [
				'title'       => __( 'UPI Transaction ID:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'If you want to collect UPI Transaction ID from customers on payment page, set it here. Requiring it makes payments easier to match in your bank or UPI app, but the ID is not verified automatically: always check it before completing the order.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
				'default'     => 'show_require',
				'options'     => [
					'hide'         => __( 'Hide Field', 'upi-qr-code-payment-gateway' ),
					'show'         => __( 'Show Input Field', 'upi-qr-code-payment-gateway' ),
					'show_require' => __( 'Show & Require Input Field', 'upi-qr-code-payment-gateway' ),
				],
			],
			'transaction_image'   => [
				'title'       => __( 'UPI Screenshot / Image:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'If you want to collect transaction screenshot from customers on payment page, set it here.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
				'default'     => 'show_require',
				'options'     => [
					'hide'         => __( 'Hide Field', 'upi-qr-code-payment-gateway' ),
					'show'         => __( 'Show Upload Field', 'upi-qr-code-payment-gateway' ),
					'show_require' => __( 'Show & Require Input Field', 'upi-qr-code-payment-gateway' ),
				],
			],
			// "Payment Buttons" (intent) setting removed in 1.5.0, see the dwu_enable_payment_buttons filter.
			'download_qr'         => [
				'title'       => __( 'Download Button:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show / Hide download QR Code Button', 'upi-qr-code-payment-gateway' ),
				'description' => __( 'Enable this if you want to show download QR Code Button. Buyers can pay using this QR Code by uploading it from gallery to any UPI supported apps.', 'upi-qr-code-payment-gateway' ),
				'default'     => 'no',
				'desc_tip'    => false,
			],
			'qrcode_mobile'       => [
				'title'       => __( 'Mobile QR Code:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show / Hide QR Code on Mobile Devices', 'upi-qr-code-payment-gateway' ),
				'description' => __( 'Enable this if you want to show UPI QR Code on mobile devices.', 'upi-qr-code-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => false,
			],
			'payment_content'     => [
				'title'       => __( 'Payment Popup Content', 'upi-qr-code-payment-gateway' ),
				'type'        => 'title',
				'description' => __( 'Customize various texts of the Payment Popup here.', 'upi-qr-code-payment-gateway' ),
			],
			'instructions'        => [
				'title'       => __( 'Instructions:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the order pay popup on desktop devices.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Please scan the QR code with any UPI app to pay for your order. After payment, enter the UPI Reference ID or Transaction Number (e.g. 401422121258) on the next screen. We\'ll manually verify your payment using the provided information.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'instructions_mobile' => [
				'title'       => __( 'Mobile Instructions:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the order pay popup on mobile devices.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Please scan the QR code with any UPI app to pay for your order. After payment, enter the UPI Reference ID or Transaction Number (e.g. 401422121258) on the next screen. We\'ll manually verify your payment using the provided information.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'confirm_message'     => [
				'title'       => __( 'Confirm Message:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This displays a message to customer as payment processing text.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Please ensure that the amount has been deducted from your account before clicking "Confirm". We will manually verify your transaction once submitted.', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
			'email'               => [
				'title'       => __( 'Configure Email', 'upi-qr-code-payment-gateway' ),
				'type'        => 'title',
				'description' => __( 'Configure the Payment Pending email settings here.', 'upi-qr-code-payment-gateway' ),
			],
			'email_enabled'       => [
				'title'       => __( 'Enable / Disable:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Email Notification', 'upi-qr-code-payment-gateway' ),
				'description' => __( 'Enable this option if you want to send payment link to the customer via email after placing the successful order.', 'upi-qr-code-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => false,
			],
			'email_subject'       => [
				'title'       => __( 'Email Subject:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'text',
				'desc_tip'    => false,
				/* translators: %s: List of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'upi-qr-code-payment-gateway' ), '<code>' . esc_html( '{site_title}, {site_address}, {order_date}, {order_number}' ) . '</code>' ),
				'default'     => __( '[{site_title}]: Payment pending #{order_number}', 'upi-qr-code-payment-gateway' ),
			],
			'email_heading'       => [
				'title'       => __( 'Email Heading:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'text',
				'desc_tip'    => false,
				/* translators: %s: List of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'upi-qr-code-payment-gateway' ), '<code>' . esc_html( '{site_title}, {site_address}, {order_date}, {order_number}' ) . '</code>' ),
				'default'     => __( 'Thank you for your order', 'upi-qr-code-payment-gateway' ),
			],
			'additional_content'  => [
				'title'       => __( 'Email Body Text:', 'upi-qr-code-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This text will be attached to the On Hold email template sent to customer. Use {upi_pay_link} to add the link of payment page.', 'upi-qr-code-payment-gateway' ),
				'default'     => __( 'Please complete the payment via UPI by going to this link: {upi_pay_link} (ignore if already done).', 'upi-qr-code-payment-gateway' ),
				'desc_tip'    => false,
			],
		];
	}

	/**
	 * Display the UPi Id field
	 */
	public function payment_fields() {
		$order_id = WC()->session ? absint( WC()->session->get( 'order_awaiting_payment' ) ) : 0;

		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( is_a( $order, 'WC_Order' ) ) {
				$payment_upi_id = $order->get_meta( '_transaction_upi_id', true );
			}
		}
		
		$upi_address = $upi_handle = '';
		if ( isset( $payment_upi_id ) && ! empty( $payment_upi_id ) ) {
			$payment_upi_id = explode( '@', $payment_upi_id );
			if ( is_array( $payment_upi_id ) && count( $payment_upi_id ) == 2 ) {
				$upi_address = $payment_upi_id[0];
				$upi_handle  = $payment_upi_id[1];
			}
		}

		// display description before the payment form
		if ( ! empty( $this->description ) ) {
			// display the description with <p> tags
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
		
		$handles = array_unique( apply_filters( 'dwu_upi_handle_list', [ 'airtel', 'airtelpaymentsbank', 'apb', 'apl', 'allbank', 'albk', 'allahabadbank', 'andb', 'axisgo', 'axis', 'axisbank', 'axisb', 'okaxis', 'abfspay', 'axl', 'barodampay', 'barodapay', 'boi', 'cnrb', 'csbpay', 'csbcash', 'centralbank', 'cbin', 'cboi', 'cub', 'dbs', 'dcb', 'dcbbank', 'denabank', 'equitas', 'federal', 'fbl', 'finobank', 'hdfcbank', 'payzapp', 'okhdfcbank', 'rajgovhdfcbank', 'hsbc', 'imobile', 'pockets', 'ezeepay', 'eazypay', 'idbi', 'idbibank', 'idfc', 'idfcbank', 'idfcnetc', 'cmsidfc', 'indianbank', 'indbank', 'indianbk', 'iob', 'indus', 'indusind', 'icici', 'myicici', 'okicici', 'ikwik', 'ibl', 'jkb', 'jsbp', 'kbl', 'karb', 'kbl052', 'kvb', 'karurvysyabank', 'kvbank', 'kotak', 'kaypay', 'kmb', 'kmbl', 'okbizaxis', 'obc', 'paytm', 'pingpay', 'psb', 'pnb', 'sib', 'srcb', 'sc', 'scmobile', 'scb', 'scbl', 'sbi', 'oksbi', 'syndicate', 'syndbank', 'synd', 'lvb', 'lvbank', 'rbl', 'tjsb', 'uco', 'unionbankofindia', 'unionbank', 'uboi', 'ubi', 'united', 'utbi', 'upi', 'vjb', 'vijb', 'vijayabank', 'ubi', 'yesbank', 'ybl', 'yesbankltd' ] ) );
		sort( $handles );

		$required    = '';
		$upi_address = ( isset( $_POST['customer_dwu_address'] ) ) ? sanitize_text_field( wp_unslash( $_POST['customer_dwu_address'] ) ) : $upi_address; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only used to refill the field, WooCommerce verifies the checkout nonce.
		$placeholder = ( $this->upi_address === 'show_handle' ) ? 'mobilenumber' : 'mobilenumber@oksbi';
		$placeholder = apply_filters( 'dwu_upi_address_placeholder', $placeholder );

		if ( $this->require_upi === 'yes' ) {
			$required = ' <span class="required">*</span>';
		}

		// Only allow known handles to be pre-selected.
		if ( ! in_array( $upi_handle, $handles, true ) ) {
			$upi_handle = '';
		}

		if ( in_array( $this->upi_address, [ 'show', 'show_handle' ] ) ) {
			?>
			<fieldset id="dwu-checkout-payment-form" class="dwu-checkout-payment-form wc-payment-form">
				<?php do_action( 'woocommerce_upi_form_start', $this->id ); ?>
				<div class="dwu-input">
					<label><?php echo esc_html__( 'UPI Address', 'upi-qr-code-payment-gateway' ) . wp_kses( $required, [ 'span' => [ 'class' => [] ] ] ); ?></label>
					<div class="dwu-input-field">
						<input id="dwu-address" class="dwu-address <?php echo esc_attr( str_replace( '_', '-', $this->upi_address ) ); ?>" name="customer_dwu_address" type="text" autocomplete="off" placeholder="e.g. <?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( $upi_address ); ?>">
						<?php if ( $this->upi_address === 'show_handle' ) { ?>
							<select id="dwu-handle" name="customer_dwu_handle" style="width: 100%;"><option selected disabled hidden value=""><?php esc_html_e( '-- Select --', 'upi-qr-code-payment-gateway' ); ?></option>
								<?php
								foreach ( $handles as $handle ) {
									echo '<option value="' . esc_attr( $handle ) . '" ' . selected( $upi_handle, $handle, false ) . '>' . esc_html( $handle ) . '</option>';
								}
								?>
							</select>
						<?php } ?>
					</div>
				</div>
				<?php do_action( 'woocommerce_upi_form_end', $this->id ); ?>
				</fieldset>
				<script type="text/javascript">
					( function( $ ) {
						if ( $( '#dwu-handle' ).length ) {
							var dwuSelect = $( "#dwu-handle" ).selectize( {
								create: <?php echo filter_var( apply_filters( 'dwu_create_upi_handle', 'false' ), FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false'; ?>,
							} );
							<?php if ( ! empty( $upi_handle ) ) { ?>
								var dwuSelectize = dwuSelect[0].selectize;
								dwuSelectize.setValue( <?php echo wp_json_encode( $upi_handle ); ?> );
							<?php } ?>
						}
					} )( jQuery );
				</script>
			<?php
		}
	}

	/**
	 * Validate UPI ID field
	 */
	public function validate_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before calling this.
		if ( ! in_array( $this->upi_address, [ 'show', 'show_handle' ], true ) ) {
			return true;
		}

		$upi_address = isset( $_POST['customer_dwu_address'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_dwu_address'] ) ) : '';
		$upi_handle  = isset( $_POST['customer_dwu_handle'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_dwu_handle'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$is_required = $this->require_upi === 'yes';

		if ( '' === $upi_address && $is_required ) {
			wc_add_notice( __( '<strong>UPI Address</strong> is a required field.', 'upi-qr-code-payment-gateway' ), 'error' );
			return false;
		}

		if ( $this->upi_address === 'show_handle' ) {
			if ( '' === $upi_handle && $is_required ) {
				wc_add_notice( __( '<strong>UPI Handle</strong> is a required field.', 'upi-qr-code-payment-gateway' ), 'error' );
				return false;
			}

			if ( '' !== $upi_handle && ! preg_match( '/^[a-z0-9.-]+$/i', $upi_handle ) ) {
				wc_add_notice( __( 'Please select a <strong>valid UPI Handle</strong>!', 'upi-qr-code-payment-gateway' ), 'error' );
				return false;
			}
		}

		$regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*$/i';
		if ( $this->upi_address === 'show_handle' ) {
			$regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*$/i';
		}

		if ( '' !== $upi_address && ! preg_match( $regex, $upi_address ) ) {
			wc_add_notice( __( 'Please enter a <strong>valid UPI Address</strong>!', 'upi-qr-code-payment-gateway' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Custom CSS and JS
	 */
	public function payment_scripts() {
		if ( 'no' === $this->enabled ) {
			return;
		}

		// Selectize powers the handle dropdown of the classic checkout form, which is also used on the
		// "pay for order" page. The Checkout block ships its own dropdown.
		// Before WordPress 5.0 there are no blocks, so the checkout is always the classic form.
		$classic_form = is_wc_endpoint_url( 'order-pay' ) || ! function_exists( 'has_block' ) || ! has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) );

		if ( is_checkout() && $classic_form && $this->upi_address !== 'hide' ) {
			wp_enqueue_style( 'dwu-selectize', DWU_URL . 'css/selectize.min.css', [], '0.15.2' );
			wp_enqueue_style( 'dwu-checkout', DWU_URL . 'css/checkout.min.css', [ 'dwu-selectize' ], DWU_VERSION );

			wp_enqueue_script( 'dwu-selectize', DWU_URL . 'js/selectize.min.js', [ 'jquery' ], '0.15.2', false );
		}
	
		$order_id = absint( get_query_var( 'order-pay' ) );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) || $this->id !== $order->get_payment_method() ) {
			return;
		}

		wp_register_style( 'dwu-jquery-confirm', DWU_URL . 'css/jquery-confirm.min.css', [], '3.3.4' );
		wp_register_style( 'dwu-payment', DWU_URL . 'css/payment.min.css', [ 'dwu-jquery-confirm' ], DWU_VERSION );
		
		wp_register_script( 'dwu-qr-code', DWU_URL . 'js/easy.qrcode.min.js', [ 'jquery' ], '3.8.3', true );
		wp_register_script( 'dwu-jquery-confirm', DWU_URL . 'js/jquery-confirm.min.js', [ 'jquery' ], '3.3.4', true );
		wp_register_script( 'dwu-payment', DWU_URL . 'js/payment.min.js', [ 'jquery', 'dwu-qr-code', 'dwu-jquery-confirm' ], DWU_VERSION, true );
	
		$total     = apply_filters( 'dwu_order_total_amount', $order->get_total(), $order );
		$payee_vpa = $this->get_vpa( $order );

		wp_localize_script(
			'dwu-payment',
			'dwuData',
			[
				'order_id'          => $order->get_id(),
				// UPI expects the amount with two decimals, e.g. 499.00.
				'order_amount'      => wc_format_decimal( $total, 2 ),
				'order_key'         => $order->get_order_key(),
				// Only used in the UPI link's note: drop characters such as # & ? = that would break the link.
				'order_number'      => preg_replace( '/[^\p{L}\p{N}\-_.\/]/u', '', $order->get_order_number() ),
				'confirm_message'   => $this->confirm_message,
				'callback_url'      => add_query_arg( [ 'wc-api' => 'dwu-payment' ], trailingslashit( get_home_url() ) ),
				'payment_url'       => $order->get_checkout_payment_url(),
				'cancel_url'        => apply_filters( 'dwu_payment_cancel_url', wc_get_checkout_url(), $this->get_return_url( $order ), $order ),
				'transaction_id'    => $this->transaction_id,
				'transaction_image' => $this->transaction_image,
				'mc_code'           => $this->get_mcc(),
				'btn_timer'         => apply_filters( 'dwu_enable_button_timer', true ),
				'btn_show_interval' => apply_filters( 'dwu_button_show_interval', 30000 ),
				'theme'             => $this->theme ? $this->theme : 'light',
				'payer_vpa'         => htmlentities( strtolower( $order->get_meta( '_transaction_upi_id', true ) ) ),
				'payee_vpa'         => $payee_vpa,
				'payee_name'        => preg_replace( '/[^\p{L}\p{N}\s]/u', '', $this->name ),
				'is_mobile'         => ( wp_is_mobile() ) ? 'yes' : 'no',
				'nonce'             => wp_create_nonce( 'dwu_payment_' . $order->get_id() ),
				'app_version'       => DWU_VERSION,
			]
		);
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order       = wc_get_order( $order_id );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before calling this.
		$upi_address = ! empty( $_POST['customer_dwu_address'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_dwu_address'] ) ) : '';
		$upi_address = ! empty( $_POST['customer_dwu_handle'] ) ? $upi_address . '@' . sanitize_text_field( wp_unslash( $_POST['customer_dwu_handle'] ) ) : $upi_address;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$message     = __( 'Awaiting UPI Payment!', 'upi-qr-code-payment-gateway' );

		// The unpaid-order window counts from now, not from when the order was created: the order may be
		// older (paid later from the "Pay for order" page, or a failed payment retried). Saved with the status.
		$order->update_meta_data( '_dwu_payment_started', time() );

		// Mark as pending (we're awaiting the payment)
		$order->update_status( $this->default_status );

		// update meta
		$order->update_meta_data( '_dwu_order_paid', 'no' );

		if ( ! empty( $upi_address ) ) {
			$order->update_meta_data( '_transaction_upi_id', preg_replace( '/\s+/', '', $upi_address ) );
			/* translators: %s: Customer UPI ID */
			$message .= '<br />' . esc_html( sprintf( __( 'UPI ID: %s', 'upi-qr-code-payment-gateway' ), preg_replace( '/\s+/', '', $upi_address ) ) );
		}

		// add some order notes
		$order->add_order_note( apply_filters( 'dwu_process_payment_note', $message, $order ), false );
		$order->save();

		if ( apply_filters( 'dwu_payment_empty_cart', false ) ) {
			// Empty cart
			WC()->cart->empty_cart();
		}

		do_action( 'dwu_after_payment_init', $order_id, $order );

		// check plugin settings
		if ( 'yes' === $this->enabled && 'yes' === $this->email_enabled && $order->has_status( 'pending' ) ) {
			// Get an instance of the WC_Email_Customer_On_Hold_Order object
			$wc_email = WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order'];
			
			// Send "New Email" notification
			$wc_email->trigger( $order_id );
		}

		// Return redirect
		return [
			'result'   => 'success',
			'redirect' => apply_filters( 'dwu_process_payment_redirect', $order->get_checkout_payment_url( true ), $order ),
		];
	}
	
	/**
	 * Show UPI details as html output
	 *
	 * @param WC_Order $order_id Order id.
	 * @return string
	 */
	public function generate_qr_code( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$payee_vpa  = $this->get_vpa( $order );
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$is_iphone  = stripos( $user_agent, 'iPhone' ) !== false;

		// enqueue required css files
		wp_enqueue_style( 'dwu-jquery-confirm' );
		wp_enqueue_style( 'dwu-payment' );

		// enqueue required js files
		wp_enqueue_script( 'dwu-qr-code' );
		wp_enqueue_script( 'dwu-jquery-confirm' );
		wp_enqueue_script( 'dwu-payment' );

		$hide_mobile_qr   = ( wp_is_mobile() && $this->qrcode_mobile === 'no' );
		$show_intent_btn  = ( wp_is_mobile() && $this->intent === 'yes' );
		$show_qr_download = ( wp_is_mobile() && $this->download_qr === 'yes' );
		
		$qr_code_class = ( $hide_mobile_qr ) ? 'dwu-hide' : 'dwu-show';
		$form_class    = ( $this->transaction_id !== 'hide' || $this->transaction_image !== 'hide' ) ? 'dwu-payment-confirm-form-container' : 'dwu-payment-confirm-form-container dwu-hidden';

		// add html output on payment endpoint
		if ( 'yes' === $this->enabled && $order->needs_payment() === true && $order->has_status( $this->default_status ) && ! empty( $payee_vpa ) ) {
			?>
			<section class="dwu-section">
				<div class="dwu-info">
					<h6 class="dwu-waiting-text"><?php esc_html_e( 'Please wait and don\'t press back or refresh this page while we are processing your payment.', 'upi-qr-code-payment-gateway' ); ?></h6>
					<?php do_action( 'dwu_after_before_title', $order ); ?>
					<div class="dwu-buttons">
						<button id="dwu-processing" class="btn button" disabled="disabled"><?php esc_html_e( 'Waiting for payment...', 'upi-qr-code-payment-gateway' ); ?></button>
						<button id="dwu-confirm-payment" class="btn button" style="display: none;"><?php echo esc_html( apply_filters( 'dwu_payment_button_text', $this->pay_button ) ); ?></button>
						<?php if ( apply_filters( 'dwu_show_cancel_button', true ) ) { ?>
							<button id="dwu-cancel-payment" class="btn button" style="display: none;"><?php esc_html_e( 'Cancel', 'upi-qr-code-payment-gateway' ); ?></button>
						<?php } ?>
					</div>
					<?php if ( apply_filters( 'dwu_show_choose_payment_method', true ) ) { ?>
						<div class="dwu-return-link" style="margin-top: 5px;"><?php esc_html_e( 'Choose another payment method', 'upi-qr-code-payment-gateway' ); ?></div>
					<?php } ?>
					<?php do_action( 'dwu_after_payment_buttons', $order ); ?>
					<div id="dwu-payment-success-container" style="display: none;"></div>
				</div>
				<div class="dwu-modal-header">
					<div class="dwu-payment-header">
						<div class="dwu-payment-merchant-name"><?php echo esc_html( preg_replace( '/[^\p{L}\p{N}\s]/u', '', $this->name ) ); ?></div>
						<div class="dwu-payment-order-info">
							<span class="dwu-payment-prefix"><?php esc_html_e( 'Order ID: ', 'upi-qr-code-payment-gateway' ); ?></span> 
							<span class="dwu-payment-order-id">#<?php echo esc_html( $order->get_order_number() ); ?></span>
						</div>
					</div>
				</div>
				<div class="dwu-modal-content">
					<div class="dwu-payment-content">
						<div id="dwu-payment-qr-code" class="dwu-payment-qr-code <?php echo esc_attr( $qr_code_class ); ?>"></div>
						<div class="dwu-payment-actions">
							<div class="dwu-payment-upi-id" title="<?php esc_attr_e( 'Click to Copy', 'upi-qr-code-payment-gateway' ); ?>"><?php echo esc_html( html_entity_decode( $payee_vpa, ENT_QUOTES, 'UTF-8' ) ); ?></div>
							<?php if ( $show_qr_download ) { ?>
								<button type="button" id="upi-download" class="btn dwu-payment-button"><?php echo esc_html( apply_filters( 'dwu_donwload_button_text', __( 'Download QR Code', 'upi-qr-code-payment-gateway' ) ) ); ?></button>
							<?php } ?>
						</div>
						<?php if ( $show_intent_btn ) { ?>
							<div class="dwu-payment-container">
								<?php if ( $is_iphone ) { ?>
									<div class="dwu-payment-hint"><?php esc_html_e( 'Pay with installed app', 'upi-qr-code-payment-gateway' ); ?></div>
								<?php } else { ?>
									<div class="dwu-payment-hint"><?php esc_html_e( 'Pay with installed app, or use others', 'upi-qr-code-payment-gateway' ); ?></div>
								<?php } ?>
								<div class="dwu-payment-btn-container">
									<div class="dwu-payment-btn" data-type="gpay">
										<div class="app-logo">
											<img src="<?php echo esc_url( DWU_URL . 'images/googlepay.svg' ); ?>" alt="google-pay-app-logo" class="logo">
										</div>
										<div class="app-title"><?php esc_html_e( 'Google Pay', 'upi-qr-code-payment-gateway' ); ?></div>
									</div>
									<div class="dwu-payment-btn" data-type="phonepe">
										<div class="app-logo">
											<img src="<?php echo esc_url( DWU_URL . 'images/phonepe.svg' ); ?>" alt="phonepe-app-logo" class="logo">
										</div>
										<div class="app-title"><?php esc_html_e( 'PhonePe', 'upi-qr-code-payment-gateway' ); ?></div>
									</div>
									<div class="dwu-payment-btn" data-type="paytm">
										<div class="app-logo">
											<img src="<?php echo esc_url( DWU_URL . 'images/paytm.svg' ); ?>" alt="paytm-app-logo" class="logo">
										</div>
										<div class="app-title"><?php esc_html_e( 'Paytm', 'upi-qr-code-payment-gateway' ); ?></div>
									</div>
									<?php if ( ! $is_iphone ) { ?>
										<div class="dwu-payment-btn" data-type="upi">
											<div class="app-logo">
												<img src="<?php echo esc_url( DWU_URL . 'images/bhim.svg' ); ?>" alt="bhim-app-logo" class="logo">
											</div>
											<div class="app-title"><?php esc_html_e( 'Others', 'upi-qr-code-payment-gateway' ); ?></div>
										</div>
									<?php } ?>
								</div>
								<div class="dwu-payment-intent-error" style="display: none;"></div>
							</div>
						<?php } ?>
						<div class="dwu-payment-info">
							<div class="dwu-payment-info-text">
								<?php
								if ( wp_is_mobile() ) {
									echo wp_kses_post( wptexturize( $this->instructions_mobile ) );
								} else {
									echo wp_kses_post( wptexturize( $this->instructions ) );
								}
								?>
							</div>
							<?php if ( ! $show_intent_btn ) { ?>
								<div class="dwu-payment-info-logo">
									<img src="<?php echo esc_url( DWU_URL . 'images/googlepay.svg' ); ?>" alt="google-pay-app-logo" class="logo">
									<img src="<?php echo esc_url( DWU_URL . 'images/phonepe.svg' ); ?>" alt="phonepe-app-logo" class="logo">
									<img src="<?php echo esc_url( DWU_URL . 'images/paytm.svg' ); ?>" alt="paytm-app-logo" class="logo">
									<img src="<?php echo esc_url( DWU_URL . 'images/bhim.svg' ); ?>" alt="bhim-app-logo" class="logo">
								</div>
							<?php } ?>
						</div>
						<div class="dwu-payment-confirm" style="display: none;">
							<div class="<?php echo esc_attr( $form_class ); ?>">
								<form id="dwu-payment-confirm-form" class="dwu-payment-confirm-form">
									<?php if ( $this->transaction_id !== 'hide' ) { ?>
										<div class="dwu-form-row">
											<label for="dwu-payment-transaction-number">
												<strong><?php esc_html_e( 'Enter 12-digit Transaction / UTR / Reference ID:', 'upi-qr-code-payment-gateway' ); ?></strong> 
												<?php if ( $this->transaction_id === 'show_require' ) { ?>
													<span class="field-required">*</span>
												<?php } ?>
											</label>
											<input type="text" id="dwu-payment-transaction-number" name="dwu_transaction_id" maxlength="12" onkeypress="return dwuIsNumber(event)" />
										</div>
									<?php } ?>
									<?php if ( $this->transaction_image !== 'hide' ) { ?>
										<div class="dwu-form-row">
											<label for="dwu-payment-file">
												<strong><?php esc_html_e( 'Upload Screenshot:', 'upi-qr-code-payment-gateway' ); ?></strong>
												<?php if ( $this->transaction_image === 'show_require' ) { ?>
													<span class="field-required">*</span>
												<?php } ?>
											</label>
											<input type="file" id="dwu-payment-file" name="dwu_file" accept=".jpg, .jpeg, .png," />
										</div>
									<?php } ?>
								</form>
								<div class="dwu-payment-error" style="display: none;"></div>
							</div>
							<div class="dwu-payment-confirm-text"><?php echo wp_kses_post( $this->confirm_message ); ?></div>
						</div>
					</div>
				</div>
			</section>
			<?php
		}
	}

	/**
	 * Process payment verification.
	 */
	public function capture_payment() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );

		// Find the order, then verify the order key and the nonce (bound to that order) below.
		$order_id  = isset( $_POST['dwu_order_id'] ) ? absint( $_POST['dwu_order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below, once the order is known.
		$order_key = isset( $_POST['dwu_order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dwu_order_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below, once the order is known.
		$nonce     = isset( $_POST['dwu_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dwu_nonce'] ) ) : '';

		$order = $order_id ? wc_get_order( $order_id ) : false;

		// As in the original plugin, fall back to the order key when the ID doesn't match. The key is still required.
		if ( '' !== $order_key && ( ! is_a( $order, 'WC_Order' ) || ! hash_equals( $order->get_order_key(), $order_key ) ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( $order_key ) );
		}

		// The order key is a secret only known to the customer who placed the order.
		if ( ! is_a( $order, 'WC_Order' ) || '' === $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			wp_die( esc_html__( 'Order can\'t be found against this Order ID. If the money debited from your account, please Contact with Site Administrator for further action.', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 404 ] );
		}

		// The nonce was created for this order on its payment page.
		if ( ! wp_verify_nonce( $nonce, 'dwu_payment_' . $order->get_id() ) ) {
			wp_die( esc_html__( 'Security check failed!', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 403 ] );
		}

		if ( $this->id !== $order->get_payment_method() ) {
			wp_die( esc_html__( 'This order was not placed using UPI payment method.', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 400 ] );
		}

		// Payment already submitted (e.g. double submission), just take the customer to the thank you page.
		if ( 'yes' === $order->get_meta( '_dwu_order_paid', true ) ) {
			wp_safe_redirect( apply_filters( 'dwu_payment_redirect_url', $this->get_return_url( $order ), $order ) );
			exit;
		}

		if ( ! $order->needs_payment() || ! $order->has_status( $this->default_status ) ) {
			if ( $order->has_status( 'cancelled' ) ) {
				// Usually cancelled as unpaid while the customer was paying: they may already have been debited.
				/* translators: %s: Order number */
				wp_die( esc_html( sprintf( __( 'This order was cancelled because the payment details were not submitted in time. If money was debited from your account, please contact us with your UPI Transaction ID (UTR) and order number #%s.', 'upi-qr-code-payment-gateway' ), $order->get_order_number() ) ), esc_html( $site_name ), [ 'response' => 400 ] );
			}
			wp_die( esc_html__( 'This order does not need a payment.', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 400 ] );
		}

		// Validate transaction id.
		$transaction_id = isset( $_POST['dwu_transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['dwu_transaction_id'] ) ) : '';

		if ( 'hide' === $this->transaction_id ) {
			$transaction_id = '';
		} elseif ( '' === $transaction_id && 'show_require' === $this->transaction_id ) {
			wp_die( esc_html__( 'Transaction ID is required!', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 400, 'back_link' => true ] );
		} elseif ( '' !== $transaction_id && ! preg_match( '/^[0-9]{12}$/', $transaction_id ) ) {
			wp_die( esc_html__( 'Transaction ID should be of 12 digits!', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 400, 'back_link' => true ] );
		}

		if ( '' !== $transaction_id ) {
			$old_orders = wc_get_orders(
				[
					'transaction_id' => $transaction_id,
					'limit'          => 2,
					'return'         => 'ids',
				]
			);
			$old_orders = array_diff( array_map( 'absint', $old_orders ), [ $order->get_id() ] );

			if ( ! empty( $old_orders ) ) {
				wp_die( esc_html__( 'Transaction ID was already used in the previous order.', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 400, 'back_link' => true ] );
			}
		}

		// Validate and upload screenshot.
		$has_file   = 'hide' !== $this->transaction_image && ! empty( $_FILES['dwu_file']['name'] );
		$screenshot = '';

		if ( ! $has_file && 'show_require' === $this->transaction_image ) {
			wp_die( esc_html__( 'Payment screenshot is required!', 'upi-qr-code-payment-gateway' ), esc_html( $site_name ), [ 'response' => 400, 'back_link' => true ] );
		}

		if ( $has_file ) {
			$max_size = apply_filters( 'dwu_screenshot_max_size', 5 * MB_IN_BYTES );
			$size     = isset( $_FILES['dwu_file']['size'] ) ? absint( $_FILES['dwu_file']['size'] ) : 0;

			if ( ! $size || $size > $max_size ) {
				/* translators: %s: Maximum file size */
				wp_die( esc_html( sprintf( __( 'Screenshot must be an image smaller than %s.', 'upi-qr-code-payment-gateway' ), size_format( $max_size ) ) ), esc_html( $site_name ), [ 'response' => 400, 'back_link' => true ] );
			}

			// Kept private (not in the Media Library): screenshots show the customer's bank details.
			// Only real JPEG / PNG images are accepted; WordPress checks the actual file content.
			$screenshot = DWU_Order_Data::store_upload( $_FILES['dwu_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by wp_handle_upload().

			if ( is_wp_error( $screenshot ) ) {
				wp_die( esc_html( $screenshot->get_error_message() ), esc_html( $site_name ), [ 'response' => 400, 'back_link' => true ] );
			}
		}

		// All checks passed, save the payment details.
		if ( '' !== $transaction_id ) {
			$order->set_transaction_id( $transaction_id );
		}

		if ( $screenshot ) {
			$order->update_meta_data( DWU_Order_Data::META_KEY, $screenshot );
			$order->add_order_note( sprintf( '%s <a href="%s" target="_blank">%s</a>', esc_html__( 'Screenshot uploaded successfully.', 'upi-qr-code-payment-gateway' ), esc_url( DWU_Order_Data::view_url( $order ) ), esc_html__( 'View', 'upi-qr-code-payment-gateway' ) ), false );
		}

		// Mark as paid before changing status, so emails triggered by the status change can see it.
		$order->update_meta_data( '_dwu_order_paid', 'yes' );
		$order->save();

		$status_to_update = apply_filters( 'dwu_capture_payment_order_status', $this->payment_status, $order );
		$order->update_status( $status_to_update );

		// Reduce stock once. For on-hold/processing/completed WooCommerce has already done this on the
		// status change; the "maybe" variant also flags the order so stock is restored if it is cancelled.
		wc_maybe_reduce_stock_levels( $order->get_id() );

		// check order if it actually needs payment
		if ( in_array( $status_to_update, apply_filters( 'dwu_valid_order_status_for_note', [ 'pending', 'on-hold' ] ), true ) ) {
			// set order note
			$order->add_order_note( __( 'Payment primarily completed. Needs shop owner\'s verification.', 'upi-qr-code-payment-gateway' ), false );
		}

		// add custom actions
		do_action( 'dwu_after_payment_verify', $order->get_id(), $order );

		// create redirect
		wp_safe_redirect( apply_filters( 'dwu_payment_redirect_url', $this->get_return_url( $order ), $order ) );
		exit;
	}

	/**
	 * Customize the WC emails template.
	 *
	 * @access public
	 * @param string   $formated_subject
	 * @param WC_Order $order
	 * @param object   $object
	 */
	public function email_subject_pending_order( $formated_subject, $order, $object ) {
		// We exit for 'order-accepted' custom order status
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && 'yes' === $this->enabled && $order->has_status( 'pending' ) ) {
			return $object->format_string( $this->email_subject );
		}

		return $formated_subject;
	}

	/**
	 * Customize the WC emails template.
	 *
	 * @access public
	 * @param string   $formated_subject
	 * @param WC_Order $order
	 * @param object   $object
	 */
	public function email_heading_pending_order( $formated_heading, $order, $object ) {
		// We exit for 'order-accepted' custom order status
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && 'yes' === $this->enabled && $order->has_status( 'pending' ) ) {
			return $object->format_string( $this->email_heading );
		}

		return $formated_heading;
	}

	/**
	 * Customize the WC emails template.
	 *
	 * @access public
	 * @param string   $formated_subject
	 * @param WC_Order $order
	 * @param object   $object
	 */
	public function email_additional_content_pending_order( $formated_additional_content, $order, $object ) {
		// We exit for 'order-accepted' custom order status
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && 'yes' === $this->enabled && $order->has_status( 'pending' ) ) {
			return $object->format_string( str_replace( '{upi_pay_link}', $order->get_checkout_payment_url( true ), $this->additional_content ) );
		}

		return $formated_additional_content;
	}

	/**
	 * Custom order received text.
	 *
	 * @param string   $text Default text.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function order_received_text( $text, $order ) {
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && ! empty( $this->thank_you ) ) {
			return esc_html( $this->thank_you );
		}

		return $text;
	}

	/**
	 * Custom checkout URL.
	 *
	 * @param string   $url Default URL.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function custom_checkout_url( $url, $order ) {
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && ( ( $order->has_status( 'on-hold' ) && $this->default_status === 'on-hold' ) || ( $order->has_status( 'pending' ) && apply_filters( 'dwu_custom_checkout_url', false ) ) ) ) {
			return esc_url_raw( remove_query_arg( 'pay_for_order', $url ) );
		}

		return $url;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 * @param object   $email
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text, $email ) {
		// check upi gateway name
		if ( is_a( $order, 'WC_Order' ) && 'yes' === $this->enabled && 'yes' === $this->email_enabled && ! empty( $this->additional_content ) && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) && 'yes' !== $order->get_meta( '_dwu_order_paid', true ) ) {
			$content = str_replace( '{upi_pay_link}', esc_url( $order->get_checkout_payment_url( true ) ), $this->additional_content );

			if ( $plain_text ) {
				echo esc_html( wp_strip_all_tags( $content ) ) . PHP_EOL;
			} else {
				echo wp_kses_post( wpautop( wptexturize( $content ) ) ) . PHP_EOL;
			}
		}
	}

	/**
	 * Allows payment for orders with on-hold status.
	 *
	 * @param array    $statuses  Default status.
	 * @param WC_Order $order     Order data.
	 * @return string
	 */
	public function on_hold_payment( $statuses, $order ) {
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) && $order->get_meta( '_dwu_order_paid', true ) !== 'yes' && $this->default_status === 'on-hold' ) {
			$statuses[] = 'on-hold';
		}
	
		return $statuses;
	}

	/**
	 * Disable UPI from available payment gateways.
	 *
	 * @param array $available_gateways  Available payment gateways.
	 * @return array
	 */
	public function disable_gateway( $available_gateways ) {
		if ( empty( $this->vpa ) || ( wp_is_mobile() && $this->hide_on_mobile === 'yes' ) ) {
			unset( $available_gateways['dew-wc-upi'] );
		}

		return $available_gateways;
	}

	/**
	 * Add item to column.
	 *
	 * @param array $columns  Columns.
	 * @return array
	 */
	public function column_item( $columns ) {
		$columns['wc_upi'] = __( 'UPI Payment', 'upi-qr-code-payment-gateway' );

		return $columns;
	}

	/**
	 * Render column content.
	 *
	 * @param string       $column_name                Column ID to render.
	 * @param WC_Order|int $order_object_or_post_id    Order object or Post ID.
	 */
	public function render_column( $column_name, $order_object_or_post_id ) {
		if ( ! $order_object_or_post_id || 'wc_upi' !== $column_name ) {
			return;
		}

		$order = wc_get_order( $order_object_or_post_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			echo '—';
			return;
		}

		$payment_method = $order->get_payment_method();
		$content        = '';

		// check payment method
		if ( $this->id === $payment_method ) {
			$payment_id     = $order->get_transaction_id();
			$payment_upi_id = $order->get_meta( '_transaction_upi_id', true );
			$is_paid        = $order->get_meta( '_dwu_order_paid', true );
			
			// fix for old orders.
			if ( strpos( $payment_id, '@' ) !== false ) {
				$payment_id = '';
			}

			if ( 'yes' === $is_paid ) {
				// "_dwu_order_paid" means the customer submitted payment details, not that anyone verified them.
				$paid_label = $order->is_paid() ? __( 'Paid via:', 'upi-qr-code-payment-gateway' ) : __( 'From:', 'upi-qr-code-payment-gateway' );

				if ( ! $order->is_paid() ) {
					$content .= sprintf( '<p><mark class="order-status status-on-hold"><span>%s</span></mark></p>', esc_html__( 'Submitted, verify payment', 'upi-qr-code-payment-gateway' ) );
				}
				if ( ! empty( $payment_id ) ) {
					$content .= sprintf( '<p><strong>%1$s</strong> %2$s</p>', esc_html__( 'UTR:', 'upi-qr-code-payment-gateway' ), esc_html( $payment_id ) );
				}
				if ( ! empty( $payment_upi_id ) ) {
					if ( empty( $payment_id ) ) {
						$content .= sprintf( '<p><strong>%1$s</strong> %2$s</p>', esc_html( $paid_label ), esc_html( $payment_upi_id ) );
					} else {
						$content .= sprintf( '<span style="font-size: 12px;">%1$s %2$s</span>', esc_html( $paid_label ), esc_html( $payment_upi_id ) );
					}
				}
				$screenshot_url = DWU_Order_Data::view_url( $order );
				if ( $screenshot_url ) {
					$content .= sprintf( '<p><a href="%1$s" target="_blank">%2$s</a></p>', esc_url( $screenshot_url ), esc_html__( 'View screenshot', 'upi-qr-code-payment-gateway' ) );
				}
			} else {
				if ( ! empty( $payment_upi_id ) ) {
					$content .= sprintf( '<p>%1$s %2$s</p>', esc_html__( 'Initiated:', 'upi-qr-code-payment-gateway' ), esc_html( $payment_upi_id ) );
				}
			}
		}

		echo ( ! empty( $content ) ) ? wp_kses_post( $content ) : '—';
	}

	/**
	 * Merchant Category Code for the UPI link: the saved digits, or "0000" when none is set.
	 * Before 1.5.0 an empty or zero code was sent as 8931, which UPI apps can reject.
	 *
	 * @return string
	 */
	private function get_mcc() {
		$mcc = preg_replace( '/\D/', '', (string) $this->mc_code );

		if ( '' === $mcc || 0 === (int) $mcc ) {
			return '0000';
		}

		return str_pad( $mcc, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Get UPI ID
	 *
	 * @param WC_Order $order     Order data.
	 * @return string
	 */
	private function get_vpa( $order ) {
		$payee_vpa = apply_filters( 'dwu_payee_vpa', $this->vpa, $order );

		return trim( htmlentities( strtolower( $payee_vpa ) ) );
	}
}