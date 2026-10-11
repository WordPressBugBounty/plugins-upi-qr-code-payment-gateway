<?php
/**
 * Customer payment data kept with UPI orders: private screenshot storage and privacy tools.
 *
 * Payment screenshots show the customer's name, bank and UPI ID, so since 1.5.0 they are kept
 * out of the Media Library, in an uploads folder that denies direct web access, under random
 * file names, and are only served to users who can edit orders.
 *
 * @package    UPI QR Code Payment Gateway
 * @subpackage Includes
 * @author     Dew Technolab
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 */

// If this file is called directly, abort!!!
defined( 'ABSPATH' ) || exit;

/**
 * DWU_Order_Data class.
 *
 * @since 1.5.0
 */
final class DWU_Order_Data {

	/**
	 * Folder inside the uploads directory that holds the screenshots.
	 *
	 * @var string
	 */
	const FOLDER = 'dwu-payment-screenshots';

	/**
	 * Order meta holding the private screenshot's file name (since 1.5.0).
	 *
	 * @var string
	 */
	const META_KEY = '_dwu_order_screenshot';

	/**
	 * Order meta used by 1.4.x and earlier: the ID of a Media Library attachment.
	 *
	 * @var string
	 */
	const LEGACY_META_KEY = '_dwu_order_attachment_id';

	/**
	 * Image types accepted as screenshots.
	 *
	 * @var array
	 */
	const MIMES = [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
	];

	/**
	 * Register hooks. Runs on every request, because orders can be deleted and privacy
	 * requests handled without the payment gateway being loaded.
	 */
	public static function init() {
		add_action( 'admin_post_dwu_view_screenshot', [ __CLASS__, 'serve' ] );
		add_action( 'woocommerce_before_delete_order', [ __CLASS__, 'delete_for_order' ], 10, 2 );
		add_action( 'woocommerce_privacy_remove_order_personal_data', [ __CLASS__, 'erase_order_data' ] );
		add_filter( 'woocommerce_privacy_export_order_personal_data_props', [ __CLASS__, 'export_props' ] );
		add_filter( 'woocommerce_privacy_export_order_personal_data_prop', [ __CLASS__, 'export_prop' ], 10, 3 );
	}

	/**
	 * Absolute path of the private folder, created and protected on first use.
	 *
	 * @return string|false Folder path, or false when it cannot be created.
	 */
	public static function get_dir() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::FOLDER;
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// Deny direct web access on Apache / LiteSpeed. The random file names protect
		// servers that ignore .htaccess, such as Nginx.
		$guards = [
			'.htaccess' => "# UPI payment screenshots are private.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
			'index.php' => "<?php\n// Silence is golden.\n",
		];
		foreach ( $guards as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- small static file; WP_Filesystem may need FTP credentials during checkout.
			}
		}

		return $dir;
	}

	/**
	 * Move an uploaded screenshot into the private folder.
	 *
	 * @param array $file One entry of $_FILES.
	 * @return string|WP_Error Stored file name, or an error the customer can read.
	 */
	public static function store_upload( $file ) {
		if ( ! self::get_dir() ) {
			return new WP_Error( 'dwu_screenshot_dir', __( 'The screenshot could not be saved. Please contact us to confirm your payment.', 'upi-qr-code-payment-gateway' ) );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$to_private_dir = static function ( $uploads ) {
			$uploads['subdir'] = '/' . self::FOLDER;
			$uploads['path']   = $uploads['basedir'] . $uploads['subdir'];
			$uploads['url']    = $uploads['baseurl'] . $uploads['subdir'];
			return $uploads;
		};

		$overrides = [
			'test_form'                => false,
			// Only real JPEG / PNG images: WordPress checks the file content, not just the name.
			'mimes'                    => self::MIMES,
			// A random, unguessable name; the customer's own file name is not kept.
			'unique_filename_callback' => static function ( $dir, $name, $ext ) {
				return wp_generate_password( 32, false ) . strtolower( $ext );
			},
		];

		add_filter( 'upload_dir', $to_private_dir );
		$result = wp_handle_upload( $file, $overrides );
		remove_filter( 'upload_dir', $to_private_dir );

		if ( ! empty( $result['error'] ) ) {
			return new WP_Error( 'dwu_screenshot_upload', $result['error'] );
		}

		return wp_basename( $result['file'] );
	}

	/**
	 * Absolute path of an order's private screenshot.
	 *
	 * @param WC_Order $order Order.
	 * @return string Path, or '' when the order has no private screenshot on disk.
	 */
	public static function get_file_path( $order ) {
		$name = (string) $order->get_meta( self::META_KEY, true );

		// Stored names are always 32 letters/digits plus an image extension; anything else is ignored.
		if ( ! preg_match( '/^[A-Za-z0-9]{32}\.(jpg|jpeg|jpe|png)$/', $name ) ) {
			return '';
		}

		$uploads = wp_upload_dir( null, false );
		$path    = trailingslashit( $uploads['basedir'] ) . self::FOLDER . '/' . $name;

		return is_file( $path ) ? $path : '';
	}

	/**
	 * Admin link that shows an order's screenshot, private (1.5.0+) or legacy Media Library file.
	 *
	 * @param WC_Order $order Order.
	 * @return string URL, or '' when the order has no screenshot.
	 */
	public static function view_url( $order ) {
		if ( '' !== (string) $order->get_meta( self::META_KEY, true ) ) {
			return add_query_arg(
				[
					'action'   => 'dwu_view_screenshot',
					'order_id' => $order->get_id(),
				],
				admin_url( 'admin-post.php' )
			);
		}

		$legacy = absint( $order->get_meta( self::LEGACY_META_KEY, true ) );
		$url    = $legacy ? wp_get_attachment_url( $legacy ) : '';

		return $url ? $url : '';
	}

	/**
	 * Send an order's screenshot to a user who can edit orders
	 * (admin-post.php?action=dwu_view_screenshot&order_id=…).
	 */
	public static function serve() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to view payment screenshots.', 'upi-qr-code-payment-gateway' ), '', [ 'response' => 403 ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view, gated by the capability check above.
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		$path     = $order ? self::get_file_path( $order ) : '';

		if ( ! $path ) {
			wp_die( esc_html__( 'Screenshot not found.', 'upi-qr-code-payment-gateway' ), '', [ 'response' => 404 ] );
		}

		$type = wp_check_filetype( $path, self::MIMES );

		nocache_headers();
		header( 'Content-Type: ' . $type['type'] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="order-' . $order->get_id() . '-screenshot.' . $type['ext'] . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a private image.
		exit;
	}

	/**
	 * Remove the private screenshot when an order is permanently deleted.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order    Order.
	 */
	public static function delete_for_order( $order_id, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( $order instanceof WC_Order ) {
			self::delete_file( $order );
		}
	}

	/**
	 * Tools > Erase Personal Data (with WooCommerce's "Remove personal data from orders" enabled):
	 * remove the customer's UPI ID and payment screenshot. The UTR stays, as core keeps payment references.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function erase_order_data( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::delete_file( $order );

		$legacy = absint( $order->get_meta( self::LEGACY_META_KEY, true ) );
		if ( $legacy && 'attachment' === get_post_type( $legacy ) ) {
			wp_delete_attachment( $legacy, true );
		}

		$order->delete_meta_data( self::META_KEY );
		$order->delete_meta_data( self::LEGACY_META_KEY );
		$order->delete_meta_data( '_transaction_upi_id' );
		$order->save();
	}

	/**
	 * Tools > Export Personal Data: add the customer's UPI ID to the order data.
	 *
	 * @param array $props Order properties to export.
	 * @return array
	 */
	public static function export_props( $props ) {
		$props['dwu_upi_id'] = __( 'UPI ID', 'upi-qr-code-payment-gateway' );

		return $props;
	}

	/**
	 * Value of the exported UPI ID.
	 *
	 * @param mixed    $value Value.
	 * @param string   $prop  Property.
	 * @param WC_Order $order Order.
	 * @return mixed
	 */
	public static function export_prop( $value, $prop, $order ) {
		if ( 'dwu_upi_id' === $prop ) {
			$value = $order->get_meta( '_transaction_upi_id', true );
		}

		return $value;
	}

	/**
	 * Delete an order's private screenshot file.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function delete_file( $order ) {
		$path = self::get_file_path( $order );

		if ( $path ) {
			wp_delete_file( $path );
		}
	}
}
