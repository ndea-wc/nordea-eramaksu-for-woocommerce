<?php
/**
 * Nordea Erämaksu for Woocommerce payment gateway class
 */
final class WC_Gateway_Nordea_Eramaksu extends WC_Payment_Gateway {

	/**
	 * WooCommerce logger
	 *
	 * @var \WC_Logger
	 */
	protected $logger;

	/**
	 * Supported features.
	 *
	 * @var array
	 */
	public $supports = [
		'products',
		'refunds',
	];

    /**
     * Gateway constructor
     */
    public function __construct() {
        $this->id                 = 'nordea-eramaksu';
        $this->icon               = apply_filters('woocommerce_nordea_icon', plugins_url('/assets/images/Nordea_Eramaksu.png', __FILE__));
        $this->method_title       = __('Nordea Finance Erämaksu', 'nordea-eramaksu-for-woocommerce');
        $this->method_description = __('Enable payments via Nordea Erämaksu', 'nordea-eramaksu-for-woocommerce');

        // Gateway setting initialization
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title                       = 'Nordea Finance Erämaksu';
        $this->description                 = $this->get_option('description') . '</br>' . $this->get_option('legal_text') ;
        $this->client_id                   = $this->get_option('client_id');
        $this->client_secret               = $this->get_option('client_secret');
        $this->dealer_id                   = $this->get_option('dealer_id');
        $this->order_id_prefix             = $this->get_option('order_id_prefix');
        $this->custom_text                 = $this->get_option('custom_text');
        $this->continue_shopping_url       = $this->get_option('continue_shopping_url');
        $this->continue_shopping_text      = $this->get_option('continue_shopping_text');
        $this->start_range                 = $this->get_option('start_range');
        $this->end_range                   = $this->get_option('end_range');


        // Add actions and filters.
        $this->add_actions();
        // Initialize the logger
        if (class_exists('WC_Logger')) {
            $this->logger = new WC_Logger();
        } else {
            $this->logger = null; // Ensure logger is null if WC_Logger doesn't exist
        }
    }

    /**
	 * Add gateway actions and filters.
	 *
	 * @return void
	 */
	protected function add_actions() {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'nordea_thankyou_page']);
        add_action('woocommerce_order_status_changed', [$this, 'nordea_order_status_change_handler'], 10, 3);
	}

    /**
	 * Create admin form fields.
	 *
	 * @return void
	 */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'nordea-eramaksu-for-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable Nordea Erämaksu', 'nordea-eramaksu-for-woocommerce'),
                'default' => 'yes',
                'description' => '<div style="display: flex; align-items: center; margin-top: 10px;">' .
                                    '<img src="' . plugins_url('/assets/images/Nordea_Eramaksu.png', __FILE__) . '" alt="Nordea Erämaksu" style="vertical-align: middle; max-width: 150px; height: auto; margin-left: 10px;">' .
                                    '</div>',
            ),
            'description' => array(
                'title'       => __('Description', 'nordea-eramaksu-for-woocommerce'),
                'type'        => 'textarea',
                'default'     => DESCRIPTION,
                'desc_tip'    => true,
            ),
            'legal_text' => array(
                'title'       => __('Legal Information', 'nordea-eramaksu-for-woocommerce'),
                'type'        => 'textarea',
                'default'     => LEGAL_INFORMATION, 
                'desc_tip'    => true,
                'custom_attributes' => array('readonly' => 'readonly'),
            ),
            'client_id' => array(
                'title'       => __('API Client ID', 'nordea-eramaksu-for-woocommerce'),
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'client_secret' => array(
                'title'       => __('API Client Secret', 'nordea-eramaksu-for-woocommerce'),
                'type'        => 'password',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'dealer_id' => array(
                'title'       => __('Dealer ID', 'nordea-eramaksu-for-woocommerce'),
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'order_id_prefix' => array(
                'title'       => __('Order ID Prefix', 'nordea-eramaksu-for-woocommerce'),
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'custom_text' => array(
                'title'             => __('Nordea Custom Text', 'nordea-eramaksu-for-woocommerce'),
                'type'              => 'textarea',
                'default'           => CUSTOM_TEXT,
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'rows' => 5,
                    'cols' => 50,
                ),
            ),
            'continue_shopping_url' => array(
                'title'       => __('Nordea Continue Shopping URL', 'nordea-eramaksu-for-woocommerce'),
                'id'          => 'woocommerce_nordea_continue_shopping_url',
                'type'        => 'text',
                'default'     => home_url(),
                'desc_tip'    => true,
            ),
            'continue_shopping_text' => array(
                'title'       => __('Continue Shopping Text', 'nordea-eramaksu-for-woocommerce'),
                'id'          => 'woocommerce_nordea_continue_shopping_text',
                'type'        => 'text',
                'default'     => CONTINUE_SHOPPING_TEXT,
                'desc_tip'    => true,
            ),
            'start_range' => array(
                'title'       => __('Start Range', 'nordea-eramaksu-for-woocommerce'),
                'desc'        => __('The starting value of the range.', 'nordea-eramaksu-for-woocommerce'),
                'id'          => 'woocommerce_nordea_start_range',
                'type'        => 'number',
                'default'     => '500',
                'custom_attributes' => array('min' => '500', 'max' => '30000'),
                'desc_tip'    => true,
            ),
            'end_range' => array(
                'title'       => __('End Range', 'nordea-eramaksu-for-woocommerce'),
                'desc'        => __('The ending value of the range.', 'nordea-eramaksu-for-woocommerce'),
                'id'          => 'woocommerce_nordea_end_range',
                'type'        => 'number',
                'default'     => '30000',
                'custom_attributes' => array('min' => '500', 'max' => '30000'),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Function validate_fields is run before the process_payment is executed. Works for both the classic and block checkout.
     *
     * @return boolean
     */
    public function validate_fields() {
        // Check if the Client ID, Client Sercret or Dealer Id are empty.
        if (empty($this->get_option('client_id')) || empty($this->get_option('client_secret')) || empty($this->get_option('dealer_id'))) {
            $this->logger->error("Payment error. Missing required gateway credentials.", ['source' => 'plugin-nordea-eramaksu']);
            wc_add_notice(__('Payment error: Missing required gateway credentials.', 'nordea-eramaksu-for-woocommerce'), 'error');
            return false;
        }
        // Validate the cart total range dynamically using the values from plugin settings
        $cart_total = floatval(WC()->cart->get_total('raw'));
        if ($cart_total < $this->get_option('start_range') || $cart_total > $this->get_option('end_range')) {
            wc_add_notice(
                sprintf(__('Payment error: Total value for Nordea Erämaksu must be between %1$s€ and %2$s€.', 'nordea-eramaksu-for-woocommerce'), $this->get_option('start_range'), $this->get_option('end_range')),
                'error'
            );
            return false;  // Prevent checkout
        }
        return true; // Validation passed.
    }
  
    /**
    * Send payment information into Nordea Finance API
    *
    * @param  $order Order Object
    * @return array
    */
    private function send_payment_request_to_nordea($order) {
        // Retrieve order ID for logging    
        $order_id = $order->get_id(); 
        // API URL
        $api_url = str_replace('{dealer_id}', $this->dealer_id, 'https://api.nordeaopenbanking.com/retail-finance/purchase-finance/v1/{dealer_id}/application');
        $purchase_items = []; // Initialize the items array
        // Loop through the order items and populate the purchase_items array
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product(); // Get the product object
            $categories = $product ? wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']) : [];
            $product_category = !empty($categories) ? $categories[0] : ''; // Use the first category name or an empty string
        
            $purchase_items[] = [
                'item_amount' => [
                    'amount' => $item->get_total(),
                    'currency_code' => $order->get_currency(),
                ],
                'item_quantity' => round($item->get_quantity()), // Ensure quantity is rounded to the nearest integer
                'item_reference_id' => $item_id,
                'product_category' => $product_category, // Use the actual product category
                'product_id' => $item->get_product_id(),
                'product_name' => $item->get_name(),
                'purchase_estimated_delivery_date' => date('Y-m-d H:i:sO', strtotime('+7 days')), // Default delivery date
            ];
        }
        //$this->logger->debug('Order ' . $order_id . ': Purchase items prepared.', ['source' => 'plugin-nordea-eramaksu', 'items' => $purchase_items]);
        // Prepare JSON payload
        $payload = [
            'credit_application' => [
                'applicant_details' => [
                    'applicant_personal_details' => [
                        'address'       => $order->get_billing_address_1(),
                        'city'          => $order->get_billing_city(),
                        'email'         => $order->get_billing_email(),
                        'first_name'    => $order->get_billing_first_name(),
                        'language_code' => 'FI',
                        'phone_number'  => $order->get_billing_phone(),
                        'postal_code'   => $order->get_billing_postcode(),
                        'surname'       => $order->get_billing_last_name(),
                    ]
                ],
                'channel' => 'webshop',
                'purchase_details' => [
                    'campaign_id' => '',
                    'financing_product' => '501',
                    'order_reference_id' => $this->order_id_prefix . $order->get_id(),
                    'purchase_items' => [ // Use the populated purchase_items array here
                        'purchase_item' => $purchase_items, 
                    ],
                    'total_purchase_amount' => [
                        'amount' => $order->get_total(),
                        'currency_code' => $order->get_currency(),
                    ],
                ],
                'refund' => [
                    'amount' => 0, // Set to 0 initially, but can be dynamically updated if necessary
                    'currency_code' => $order->get_currency(), // Match the refund currency
                ]
            ],
            'webshop_urls' => [
                'cancel_url' => add_query_arg('status', 'cancel', wc_get_checkout_url()),
                'failure_url' => add_query_arg('status', 'failure', wc_get_checkout_url()),
                'notification_url' => add_query_arg('key', $order->get_order_key(), get_rest_url(null, '/nordea-eramaksu/v1/payment-notification', 'https')),
                'success_url' => $this->get_return_url($order),
            ]
        ];
        
        //$this->logger->debug('Order ' . $order_id . ': Nordea application creation payload prepared.', ['source' => 'plugin-nordea-eramaksu', 'json' => $payload]);
        // Send urls in email to customer for testing only
        //$this->nordea_test_send_pending_order_email($order); 

        // Send API request
        $response = wp_remote_post($api_url, [
            'method' => 'POST',
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IBM-Client-Id' => $this->client_id,
                'X-IBM-Client-Secret' => $this->client_secret,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Order ' . $order_id . ': Failed to connect to Nordea API.', ['source' => 'plugin-nordea-eramaksu', 'error' => $response->get_error_message()]);
            return [
                'status' => 'error',
                'message' => __('Payment error: Unable to connect to Nordea. Please check your credentials and try again.', 'nordea-eramaksu-for-woocommerce'),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        //$this->logger->debug('Order ' . $order_id . ': API response received.', ['source' => 'plugin-nordea-eramaksu', 'status_code' => $status_code, 'response' => $response_data]);

        // Handle 201 - Success
        if ($status_code == 201) {
            if (isset($response_data['credit_application']['application_reference_id']) && isset($response_data['application_completion_url'])) {
                $this->logger->info('Order ' . $order_id . ': Payment application ' . $response_data['credit_application']['application_reference_id'] . ' created successfully. Redirecting cutomer to Nordea Finance.', ['source' => 'plugin-nordea-eramaksu']);
                $order->update_meta_data( '_nordea_eramaksu_application_reference_id', $response_data['credit_application']['application_reference_id'] );
                $order->update_meta_data( '_nordea_eramaksu_order_reference_id', $this->order_id_prefix.$order->get_id());
                return [
                    'status' => 'success',
                    'message' => 'Payment request successful, no further action needed.',
                    'redirect' => $response_data['application_completion_url'],
                ];
            } else {
                $this->logger->error('Order ' . $order_id . ': Missing application reference ID or redirect URL.', ['source' => 'plugin-nordea-eramaksu', 'status_code' => $status_code, 'response' => $response_data]);
                return [
                    'status' => 'error',
                    'message' => __('Payment error: Application reference id or redirect url not returned.', 'nordea-eramaksu-for-woocommerce'),
                ];
            }
        }

        // Handle 400+ - Error
        if ($status_code >= 400) {
            $this->logger->error('Order ' . $order_id . ': Error response from Nordea API.', ['source' => 'plugin-nordea-eramaksu', 'status_code' => $status_code, 'response' => $response_data]);
            switch ($status_code) {
                case '400':
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Bad or invalid request', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '401':
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Invalid authentication headers', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '403':
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Unauthorized or invalid dealer id', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '500':
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Internal Server Error', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '501':
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Payment method unavailable', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '503':
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Service unavailable', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;            
                default:
                    return [
                        'status' => 'error',
                        'message' => __('Payment error: Unknown error', 'nordea-eramaksu-for-woocommerce')
                    ];
                    break;
            }
        }   
    }

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
    public function process_payment( $order_id ) {

        $order = wc_get_order( $order_id );
        // Verify order currency
        if ($order->get_currency() != 'EUR') {
            $this->logger->error("Order $order_id: Payment error. Invalid currency.", ['source' => 'plugin-nordea-eramaksu']);
            wc_add_notice(__('Payment error: Nordea Erämaksu cannot be used for non EUR purchases.', 'nordea-eramaksu-for-woocommerce'), 'error');
            return array(
                'result'   => 'error'
            );
        }
        
        // Call the Nordea API to create the payment transaction
        $response = $this->send_payment_request_to_nordea( $order );
        
        // Check if there was an error
        if ( is_wp_error( $response ) || $response['status'] == 'error' ) {
            // Add the error message to the WooCommerce checkout page
            $order->add_order_note($response['message']);
            wc_add_notice( $response['message'], 'error' );
            return;
        }
        // Set the order status to "pending" for awaiting payment
        $order->update_status( 'pending', __( 'Awaiting payment confirmation from Nordea.', 'nordea-eramaksu-for-woocommerce' ) );
        $order->save();

        // Redirect to Nordea payment portal if successful
        if (isset($response['redirect'])) {
            return [
                'result'   => 'success',
                'redirect' => $response['redirect'],
            ];
        } else {
            wc_add_notice(__( 'Payment error: Please retry.', 'nordea-eramaksu-for-woocommerce' ), 'error' );
            return;
        }
    }

    /**
	 * Handle return from Nordea Finance portal and verify payment status from Nordea Finance API
	 *
     * @param  int $order_id Order ID.
	 * @return void
	 */
    public function nordea_thankyou_page($order_id) {
        // Get the order object
        $order = wc_get_order($order_id);
        // Get apllication id for order meta
        $application_reference_id = $order->get_meta('_nordea_eramaksu_application_reference_id');
        // Verify the payment status from Nordea Finance API
        $payment = $this->verify_payment_with_nordea($application_reference_id);
        //$this->logger->debug("Order $order_id: Payment verification response received." . print_r($payment, true), ['source' => 'plugin-nordea-eramaksu', 'response' => $payment]);

        if ($payment['status'] == 'error') {
            $this->logger->error("Order ID $order_id: Payment verification failed: $error_message", ['source' => 'plugin-nordea-eramaksu']);
            $order->add_order_note($payment['message']);
        } else {
            switch ($payment['status']) {
                case 'approved':
                    $this->logger->info("Order $order_id: Payment approved. " . $payment['message'], ['source' => 'plugin-nordea-eramaksu']);
                    $order->update_status('processing', __('Payment application completed successfully. ', 'nordea-eramaksu-for-woocommerce').$payment['message']);
                    $order->payment_complete();
                    break;
                case 'decision_pending':
                    $this->logger->info("Order $order_id: Payment decision pending. " . $payment['message'], ['source' => 'plugin-nordea-eramaksu']);
                    $order->update_status('pending', __('Payment application decision is pending. ', 'nordea-eramaksu-for-woocommerce').$payment['message']);
                    break;
                case 'rejected':
                    $this->logger->info("Order $order_id: Payment rejected. " . $payment['message'], ['source' => 'plugin-nordea-eramaksu']);
                    $order->update_status('failed', __('Payment application was rejected by Nordea. ', 'nordea-eramaksu-for-woocommerce').$payment['message']);
                    break;
                case 'technical_failure':
                    $this->logger->error("Order $order_id: Payment failed due to a technical issue. " . $payment['message'], ['source' => 'plugin-nordea-eramaksu']);
                    $order->update_status('failed', __('Payment application failed due to a technical issue with Nordea. ', 'nordea-eramaksu-for-woocommerce').$payment['message']);
                    break;
                default:
                    $this->logger->warning("Order $order_id: Unexpected payment status. " . $payment['status'], ['source' => 'plugin-nordea-eramaksu']);
                    $order->add_order_note(__('Payment application status ', 'nordea-eramaksu-for-woocommerce').$payment['status'].'. '.$payment['message']); 
                    break;
            }
        }
    }   

    /**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
    private function verify_payment_with_nordea($application_reference_id) {

        // Assuming the endpoint to verify payment status is like this:
        $api_url =  str_replace('{dealer_id}', $this->dealer_id, 'https://api.nordeaopenbanking.com/retail-finance/purchase-finance/v1/{dealer_id}/'). $application_reference_id;
    
        // Make API request
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IBM-Client-Id' => $this->client_id,
                'X-IBM-Client-Secret' => $this->client_secret,
            ]
        ]);
         
        if (is_wp_error($response)) {
            $this->logger->error('Payment application ' . $application_reference_id. ' verification error: Failed to connect to Nordea API.', ['source' => 'plugin-nordea-eramaksu', 'error' => $response->get_error_message()]);
            return [
                'status' => 'error',
                'message' => __('Payment verification error: Unable to verify payment', 'nordea-eramaksu-for-woocommerce')
            ];
        }
    
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
    
        //$this->logger->debug("Received response for payment application $application_reference_id verification, status: $status_code.", ['source' => 'plugin-nordea-eramaksu', 'response' => $response_data]);

        // Check if the payment status is returned in the response
        if ($status_code == 200 && isset($response_data['application_status']['status'])) {
            return [
                        'status' => $response_data['application_status']['status'], // Return the payment status (e.g., 'approved', 'rejected', etc.)
                        'message' => $response_data['application_status']['remarks'],
            ];
        } else {
            switch ($status_code) {
                case '400':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 400. Bad or invalid request.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Bad or invalid request', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '401':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 401. Invalid authentication headers.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Invalid authentication headers', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '403':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 403. Unauthorized or invalid dealer id.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Unauthorized or invalid dealer id', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '404':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 404. Finance application not found.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Finance application not found', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '500':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 500. Internal Server Error.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Internal Server Error', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '501':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 501. Method not implemented.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Method not implemented', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;
                case '503':
                    $this->logger->error("Payment verification error for application $application_reference_id. Status Code: 503. Service unavailable.", ['source' => 'plugin-nordea-eramaksu']);
                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Service unavailable', 'nordea-eramaksu-for-woocommerce'),
                    ];
                    break;            
                default:
                  $this->logger->error("Payment verification error for application $application_reference_id. Status Code: $status_code. Unknown error.", ['source' => 'plugin-nordea-eramaksu']);

                    return [
                        'status' => 'error',
                        'message' => __('Payment verification error: Unknown error', 'nordea-eramaksu-for-woocommerce')
                    ];
                    break;
            }
        }
        // Fallback for unexpected cases
        if ($this->logger) {
            $this->logger->error("Payment verification failed for application $application_reference_id. Unexpected error occurred.", ['source' => 'plugin-nordea-eramaksu']);
        }
        return [
            'status' => 'error',
            'message' => __('Payment verification error: Unexpeted error', 'nordea-eramaksu-for-woocommerce')
        ];
    }
    
    /**
	 * Process the payment and return the result.
	 *
	 * @param  int $application_reference_id    Nordea application ID
     * @param  string $payload                  Request body in json
	 * @return array
	 */
    private function send_patch_request_to_nordea($application_reference_id, $payload) {
        // API URL
        $api_url = str_replace('{dealer_id}', $this->dealer_id, 'https://api.nordeaopenbanking.com/retail-finance/purchase-finance/v1/{dealer_id}/').$application_reference_id;
        // Send API request
        $response = wp_remote_post($api_url, [
            'method' => 'PATCH',
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IBM-Client-Id' => $this->client_id,
                'X-IBM-Client-Secret' => $this->client_secret,
            ],
        ]);
        //$this->logger->debug("Nordea API PATCH request payload prepared: ", ['source' => 'plugin-nordea-eramaksu', 'json' => $payload]);

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        //$this->logger->debug("Nordea API PATCH request response status: $status_code, response: ", ['source' => 'plugin-nordea-eramaksu', 'response' => $response_data]);

        if ($status_code == 200) {
            return [
                'result' => 'success',
                'message' => 'Patch request successful, no further action needed.',
            ];
        } else {
            $message = 'Invalid request';
            if (isset($response_data['error_description'])) {
                $message = $response_data['error_description'];
            }
            //$this->logger->error("PATCH request failed for application reference ID $application_reference_id. Status Code: $status_code. Error: $message", ['source' => 'plugin-nordea-eramaksu']);
            return [
                'result' => 'error',
                'message' => $message,
            ];
        }
    }

    /**
	 * Handle admin order status change actions
	 *
	 * @param  int $order_id        Order ID.
     * @param  string $old_status   Current staus before change
     * @param  string $new_status   New status after change
	 * @return array
	 */
    public function nordea_order_status_change_handler($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if ($order->get_payment_method() != 'nordea-eramaksu') {
            return;
        }
        $application_reference_id = $order->get_meta('_nordea_eramaksu_application_reference_id');
        $order_reference_id = $order->get_meta('_nordea_eramaksu_order_reference_id');
        switch ($new_status) {
            case 'completed':
                $payload = [
                    'application_status' => [
                        'status' => 'order_fulfilled',
                        'remarks' => 'Order completed in WooCommerce Orders'
                    ],
                ];
                $response = $this->send_patch_request_to_nordea($application_reference_id, $payload);
                if ($response['result'] == 'success') {
                    $order->add_order_note(__('Succesfully moved Nordea Erämaksu payment to invoicing.', 'nordea-eramaksu-for-woocommerce'));
                    $this->logger->info("Order $order_id: Successfully moved Nordea Erämaksu payment $application_reference_id to invoicing.", ['source' => 'plugin-nordea-eramaksu']);
                } else {
                    $order->add_order_note(__('Failed to move Nordea Erämaksu payment to invoicing. Nordea API error: ', 'nordea-eramaksu-for-woocommerce')).$response['message'];
                    $this->logger->error("Order $order_id: Failed to move Nordea Erämaksu payment $application_reference_id to invoicing.", ['source' => 'plugin-nordea-eramaksu', 'error' => $response['message']]);
                }
                break;
            case 'cancelled':
                $payload = [
                    'application_status' => [
                        'status' => 'order_cancelled',
                        'remarks' => 'Order cancelled in WooCommerce Orders'
                    ],
                ];
                $response = $this->send_patch_request_to_nordea($application_reference_id, $payload);
                if ($response['result'] == 'success') {
                    $order->add_order_note(__('Cancelled Nordea Erämaksu installment payment.', 'nordea-eramaksu-for-woocommerce'));
                    $this->logger->info("Order $order_id: Successfully cancelled Nordea Erämaksu installment payment $application_reference_id.", ['source' => 'plugin-nordea-eramaksu']);
                } else {
                    $order->add_order_note(__('Failed to cancel Nordea Erämaksu installment payment. Nordea API error: ', 'nordea-eramaksu-for-woocommerce').$response['message']);
                    $this->logger->error("Order $order_id: Failed to cancel Nordea Erämaksu installment payment $application_reference_id.", ['source' => 'plugin-nordea-eramaksu', 'error' => $response['message']]);
                }
                break;
            default:
                //noop
                break;
        }
    
    }

	/**
	 * Process a refund  // TBD API GIVES INTERNAL SERVER ERROR
	 *
	 * @param  int $order_id    Order ID.
     * @param  float $amount    Refuded total amount.
     * @param  string $reason   Refund comment. 
	 * @return array
	 */
    public function process_refund( $order_id, $amount = null, $reason = '') {

        // return 400 error if amount is less than 1€ // TBD discussing with client is there some limitation to refunds
        if ($amount < 1) {
            return new \WP_Error('400',__('The refund amount must be larger than 1 EUR.', 'nordea-eramaksu-for-woocommerce'));
        }

        $order = wc_get_order($order_id);
        $application_reference_id = $order->get_meta('_nordea_eramaksu_application_reference_id');
        $this->logger->info('Processing Refund', [
            'order_id' => $order_id,
            'amount' => $amount,
            'reason' => $reason,
            'application_reference_id' => $application_reference_id,
        ]);
        //error_log(print_r('### REFUND ###', true));
        //error_log(print_r($amount, true));
        //error_log(print_r($reason, true));

        $payload = [
            'refund' => [
                'amount' => $amount,
                'currency_code' => 'EUR'
            ]
        ];
        $response = $this->send_patch_request_to_nordea($application_reference_id, $payload);
        if ($response['result'] == 'success') {
            $order->add_order_note(__('Refunded Nordea Erämaksu installment payment. Refunded amount: ', 'nordea-eramaksu-for-woocommerce').$amount.' EUR');
            $this->logger->info('Order ' .$order_id. ': Refund processed successfully. Refunded amount: '.$amount, ['source' => 'plugin-nordea-eramaksu']);
        } else {
            $order->add_order_note(__('Nordea API error in refund: ', 'nordea-eramaksu-for-woocommerce').$response['message']);
            $this->logger->error('Order ' .$order_id. ': Refund request failed. ', ['source' => 'plugin-nordea-eramaksu', 'error' => $response['message']]);
        }
        return true;
    }

    // TBD this function might be obsolete
    //public function action_woocommerce_order_refunded( $order_id, $refund_id ) 
    //{ 
    // Code
    //}

    // For testing without Nordea portal
    /*
    function nordea_test_content_specific_email( $order, $sent_to_admin, $plain_text, $email )  { 
        if ( $email->id == 'customer_invoice' && 'nordea-eramaksu' == $order->get_payment_method()) {
            echo '<p>cancel_url => '. add_query_arg('status', 'cancel', wc_get_checkout_url()).'</p>';
            echo '<p>failure_url => '. add_query_arg('status', 'failure', wc_get_checkout_url()).'</p>';
            echo '<p>success_url => '. $this->get_return_url($order).'</p>';
            echo '<p>notification_url => '. add_query_arg('key', $order->get_order_key(), get_rest_url(null, '/nordea-eramaksu/v1/payment-notification', 'https')).'</p>';
        }
    }
    private function nordea_test_send_pending_order_email($order) {
        add_action('woocommerce_email_before_order_table', [$this, 'nordea_test_content_specific_email'], 10, 4);
        WC()->mailer()->customer_invoice( $order );
    }
        */

}
?>