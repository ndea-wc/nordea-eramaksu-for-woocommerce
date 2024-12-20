<?php
/**
 * Plugin Name: Nordea Finance Erämaksu for WooCommerce
 * Plugin URI: https://github.com/ndea-wc/nordea-eramaksu-for-woocommerce
 * Author: Capgemini
 * Author URI: https://www.capgemini.com
 * Description: Nordea Finance Erämaksu Payment Gateway.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 * Tested up to: 6.7
 * Requires PHP: 7.3
 * WC requires at least: 9.0
 * WC tested up to: 9.4
 * License: MIT
 * License URL: https://opensource.org/licenses/MIT
 * Text Domain: nordea-eramaksu-for-woocommerce
 * Domain Path: /languages
 * Copyright: Nordea Finance
 * Class WC_Gateway_Nordea_Eramaksu file.
 *
 * @package NordeaEramaksu\WooCommercePaymentGateway
 */

 include plugin_dir_path(__FILE__) . 'nordea-eramaksu-configuration.php';

/**
 * Load Nordea Erämaksu gateway class
*/
add_action('plugins_loaded', 'nordea_payment_gateway_class_loader', 0);
function nordea_payment_gateway_class_loader(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 
    include_once(plugin_dir_path(__FILE__) . 'class-wc-payment-gateway-nordea.php');
}

/**
 * Add Nordea Erämaksu to payment gateways 
*/
add_filter('woocommerce_payment_gateways', 'add_nordea_eramaksu_gateway');
function add_nordea_eramaksu_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Nordea_Eramaksu';
    return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
add_action('before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        // Declare compatibility for Cart and Checkout Blocks.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );

        // Declare compatibility for Custom Order Tables.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Custom function to register a payment method type
 */
add_action( 'woocommerce_blocks_loaded', 'nordea_register_order_approval_payment_method_type' );
function nordea_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new Nordea_Eramaksu_Blocks );
        }
    );
}

/**
 * Add plugin styles
 */
function ndea_wc_css() {
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'ndea-wc-styles',  $plugin_url . "/assets/css/plugin-style.css");
}
add_action( 'wp_enqueue_scripts', 'ndea_wc_css' );


// Add custom CSS to adjust the text area width in the Payments tab for Nordea
add_action('admin_head', 'custom_nordea_gateway_payment_text_area_css');
function custom_nordea_gateway_payment_text_area_css() {
    // Only target the settings page for Nordea payment method under the Payments tab
    $screen = get_current_screen();
    if ('woocommerce_page_wc-settings' === $screen->id && isset($_GET['tab']) && $_GET['tab'] === 'checkout') {
       
        $plugin_url = plugin_dir_url( __FILE__ );
        wp_enqueue_style( 'ndea-wc-styles',  $plugin_url . "/assets/css/plugin-style.css");
        
    }
}

/**
 * Customize the checkout page payment options
 */
function nordea_checkout_script() {
    if (is_checkout() && !is_wc_endpoint_url()) {
        // PHP variables
        $options = get_option('woocommerce_nordea-eramaksu_settings');
        $cart_total = WC()->cart->get_total('raw'); // Full total, including shipping and fees
        // Retrieve the Start Range value from the settings
        ?>
        <script type="text/javascript">
            jQuery(function($) {
                console.log('Nordea checkout script loaded');

                // Pass PHP variables to JS
                var cartAmountText = '<?php echo esc_js($options['custom_text']); ?>';
                var continueShoppingUrl = '<?php echo esc_url($options['continue_shopping_url']); ?>';
                var continueShoppingText = '<?php echo esc_js($options['continue_shopping_text']); ?>';
                var fallbackCartTotal = parseFloat('<?php echo $cart_total; ?>');
                var startRange = <?php echo esc_js($options['start_range']); ?>;
                var endRange = <?php echo esc_js($options['end_range']); ?>;  
        
                function getCartTotal() {
                    var cartTotalText = $('.order-total .amount').text(); // Try fetching from DOM
                    console.log('Raw cart total text:', cartTotalText);

                    if (!cartTotalText) {
                        console.log('Using fallback cart total from PHP');
                        return fallbackCartTotal; // Use PHP fallback if DOM fails
                    }

                    // Remove currency symbols, commas, and sanitize the value
                    var sanitizedTotal = parseFloat(cartTotalText.replace(/[^\d,.-]/g, '').replace(',', '.'));

                    console.log('Sanitized cart total:', sanitizedTotal);
                    return sanitizedTotal || fallbackCartTotal; // Use fallback if parse fails
                }

                function checkCartTotalAndDisablePayment() {
                    // Clear previous messages and links
                    $('#nordea_payment_message').remove();
                    $('.continue-shopping').remove();

                    var cartTotal = getCartTotal();
                    console.log('Checking cart total:', cartTotal);

                    var nordeaRadioButtonBlock = $('input[name="radio-control-wc-payment-method-options"][value="nordea-eramaksu"]');
                    var nordeaBlockContainer = nordeaRadioButtonBlock.closest('.wc-block-components-radio-control-accordion-option');

                    var nordeaRadioButtonClassic = $('#payment_method_nordea-eramaksu');
                    var nordeaClassicContainer = nordeaRadioButtonClassic.closest('li');

                    if (cartTotal < startRange || cartTotal > endRange) {

                        // Disable the payment method
                        if (nordeaRadioButtonBlock.length > 0) {
                            nordeaRadioButtonBlock.prop('disabled', true).prop('checked', false);
                            nordeaBlockContainer.append('<div id="nordea_payment_message" class="custom-notice">' 
                                + cartAmountText + '<br>' 
                                + '<a href="' + continueShoppingUrl + '" class="continue-shopping-link" style="color: red;">' + continueShoppingText + '</a></div>');
                        }

                        if (nordeaRadioButtonClassic.length > 0) {
                            nordeaRadioButtonClassic.prop('disabled', true).prop('checked', false);
                            nordeaClassicContainer.append('<div id="nordea_payment_message" class="custom-notice">' 
                                + cartAmountText + '<br>' 
                                + '<a href="' + continueShoppingUrl + '" class="continue-shopping-link" style="color: red;">' + continueShoppingText + '</a></div>');
                        }
                    } else {
                        console.log('Enabling Nordea payment method');
                        // Enable the payment method
                        nordeaRadioButtonBlock.prop('disabled', false);
                        nordeaRadioButtonClassic.prop('disabled', false);
                    }
                }

                $(document).ready(function() {
                    checkCartTotalAndDisablePayment();

                    // Re-check on update
                    $(document.body).on('updated_checkout updated_cart_totals', function() {
                        checkCartTotalAndDisablePayment();
                    });
                });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'nordea_checkout_script');

function nordea_enqueue_checkout_scripts() {
    if (is_checkout() && !is_wc_endpoint_url()) {
        // Register your script
        wp_register_script(
            'nordea-eramaksu-checkout-script',
            plugin_dir_url(__FILE__) . 'assets/js/checkout.js', // Path to your JS file
            array('jquery', 'wc-blocks-checkout'), // Dependencies
            '1.0',
            true // Load in footer
        );

        // Localize script with PHP data
        $options = get_option('woocommerce_nordea-eramaksu_settings');
        wp_localize_script('nordea-eramaksu-checkout-script', 'nordeaPaymentData', array(
            'cartAmountText' => esc_html($options['custom_text']),
            'continueShoppingUrl' => esc_url($options['continue_shopping_url']),
            'continueShoppingText' => esc_html($options['continue_shopping_text']),
            'startRange' => intval($options['start_range']), // Ensure it's an integer
            'endRange' => intval($options['end_range']),    // Ensure it's an integer
            'paymentCancelledMessage' => __( 'Payment error: Payment cancelled', 'nordea-eramaksu-for-woocommerce' ),
            'paymentFailedMessage' => __( 'Payment error: Payment failed', 'nordea-eramaksu-for-woocommerce' ),
        ));

        // Enqueue the script
        wp_enqueue_script('nordea-eramaksu-checkout-script');
    }
}
add_action('wp_enqueue_scripts', 'nordea_enqueue_checkout_scripts');

/**
 * Load plugin textdomain
 */
function nordea_eramaksu_load_plugin_textdomain() {
    load_plugin_textdomain( 'nordea-eramaksu-for-woocommerce', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action('plugins_loaded', 'nordea_eramaksu_load_plugin_textdomain');

/**
 * Add failure and cancel notice when customer is redirected nack to checkout after failed or cancelled finance application
 */
function nordea_failure_and_cancel_notice() {
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
    if ($status) {
        if ($status == 'cancel') {
            wc_add_notice( __( 'Payment error: Payment cancelled', 'nordea-eramaksu-for-woocommerce' ), 'error' );
        } else {
            wc_add_notice( __( 'Payment error: Payment failed', 'nordea-eramaksu-for-woocommerce' ), 'error' );
        }
    }
}
add_action( 'woocommerce_before_checkout_form', 'nordea_failure_and_cancel_notice', 10 );

/**
 * Add notification_url rest endpoint for Nordea
 */
function nordea_notification_url_rest_route(){
	register_rest_route(
		'nordea-eramaksu/v1',
		'/payment-notification',
		array(
			'methods' => 'PATCH',
			'callback' => 'nordea_notification_handler',
		)
	);
}
add_action( 'rest_api_init', 'nordea_notification_url_rest_route' );

/**
 * Handle rest endpoint notification requestes
 */
function nordea_notification_handler($request){
    $parameters = $request->get_json_params();
     // Add logger to capture the request
     if (class_exists('WC_Logger')) {
        $logger = new WC_Logger();
    } else {
        $logger = null; // Fallback if WC_Logger is unavailable
    }

    $order_key = $request->get_param('key') !== null ? sanitize_text_field($request->get_param('key')) : null;
    //$order_id = isset($_GET['order-received']) ? sanitize_text_field($_GET['order-received']) : null;

    if (empty($order_key )) {
        return new WP_Error( 'invalid_key', 'Invalid order key', array( 'status' => 404 ) );
    }

    $order_id = wc_get_order_id_by_order_key( $order_key );
    if ($order_id != wc_get_order_id_by_order_key( $order_key )) {
        return new WP_Error( 'invalid_key', 'Invalid order key or id', array( 'status' => 404 ) );
    }

    if ( empty( $parameters['status']) ) {
        return new WP_Error( 'invalid_status', 'Invalid status', array( 'status' => 404 ) );
    }
    if ( empty( $parameters['application_reference_id'])) {
        return new WP_Error( 'invalid_application_reference_id', 'Invalid application reference id', array( 'status' => 404 ) );
    }

    $order = wc_get_order( $order_id );
    if ( $parameters['application_reference_id'] != $order->get_meta('_nordea_eramaksu_application_reference_id') ) {
        return new WP_Error( 'invalid_application_reference_id', 'Invalid application reference id', array( 'status' => 404 ) );
    }

    $old_status = $order->get_status();

    if ($order->get_payment_method() != 'nordea-eramaksu') {
        $logger->error('Invalid payment method for order.', ['source' => 'plugin-nordea-eramaksu']);
        return new WP_Error( 'invalid_payment_method', 'Payment method changed', array( 'status' => 404 ) );
    }

    switch ($parameters['status']) {
        case 'approved':
            if ($old_status == 'pending' || $old_status == 'on-hold' || $old_status == 'failed') {
                $order->update_status('processing', __('Payment application completed successfully. Order status updated to processing by REST API notification.', 'nordea-eramaksu-for-woocommerce'));
                $order->payment_complete();
                $logger->info('Order ' . $order_id .': Status updated to processing by REST API notification.', ['source' => 'plugin-nordea-eramaksu']);
                break;
            }
        case 'application_cancelled':
        case 'decision_pending':
            if ($old_status != 'completed' && $old_status != 'refunded') {
                $order->update_status('pending', __('Nordea Finance notification. Payment application decision is pending. ', 'nordea-eramaksu-for-woocommerce'));
                $logger->info('Order ' . $order_id .': Status updated to pending by REST API notification.', ['source' => 'plugin-nordea-eramaksu']);
                break;
            }
        case 'rejected':
            if ($old_status != 'completed' && $old_status != 'refunded') {
                $order->update_status('failed', __('Nordea Finance notification. Payment application was rejected by Nordea. ', 'nordea-eramaksu-for-woocommerce'));
                $logger->info('Order ' . $order_id .': Status updated to failed by REST API notification.', ['source' => 'plugin-nordea-eramaksu']);
                break;
            }
        case 'technical_failure':
            if ($old_status != 'completed' && $old_status != 'refunded') {
                $order->update_status('failed', __('Nordea Finance notification. Payment application failed due to a technical issue with Nordea. ', 'nordea-eramaksu-for-woocommerce'));
                $logger->info('Order ' . $order_id .': Status updated to failed due to technical failure by REST API notification.', ['source' => 'plugin-nordea-eramaksu']);
                break;
            }
        default:
            $order->add_order_note(__('Nordea Finance notification. Payment application status changed to ', 'nordea-eramaksu-for-woocommerce').$parameters['status'].'. ');
            $logger->info('Order ' . $order_id .': Application status update to ' . $parameters['status'] . ' by REST API notification.', ['source' => 'plugin-nordea-eramaksu']);
            break;
    }
    $response = new WP_REST_Response();
    $response->set_status( 200 );
    return $response;
}

/**
 *  Hide Refund button from pending and processing orders. Refunding cannot be done if it's not invoiced or paid yet.
 */
add_action('admin_head', 'nordea_disable_refund_for_processing_orders');
function nordea_disable_refund_for_processing_orders() {
    if (isset($_GET['page']) && $_GET['page'] == 'wc-orders' && isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $order_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : null;
        if ($order = wc_get_order( $order_id )) {
            $payment_method = $order->get_payment_method();
            $status = $order->get_status();
            if ($payment_method == 'nordea-eramaksu' && ($status == 'processing' || $status == 'pending')) {
                echo '<style>
                  .button.refund-items {
                    display: none !important;
                  }
                </style>';
            }
        }
    }
}

/**
 * Resize amdin steiings text field
 */
function custom_admin_js_for_nordea_legal_text() {
    ?>
    <script type="text/javascript">
        jQuery(function($) {
            // Ensure the textarea is resized properly
            $('textarea[name="woocommerce_nordea-eramaksu_legal_text"]').attr('rows', '8').css({
                
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'custom_admin_js_for_nordea_legal_text');


?>