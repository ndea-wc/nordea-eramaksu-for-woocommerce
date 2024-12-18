<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Nordea_Eramaksu_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'nordea-eramaksu';  // The name of the payment gateway

    // Initialize the payment gateway settings and instance
    public function initialize() {
        // Retrieve the payment gateway settings from WooCommerce options
        $this->settings = get_option( 'woocommerce_nordea_settings', [] );

        // Create an instance of the nordea_Gateway class
        $this->gateway = new WC_Gateway_Nordea_Eramaksu();
    }

    // Check if the payment gateway is active or available
    public function is_active() {
        // Return whether the payment gateway is available
        return $this->gateway->is_available();
    }

    // Register the JavaScript file for integrating the payment method with WooCommerce Blocks
    public function get_payment_method_script_handles() {

        // Register the script with necessary dependencies
        wp_register_script(
            'nordea-eramaksu-blocks-integration',
            plugin_dir_url(__FILE__) . '/assets/js/checkout.js',  // Path to the JS file
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        // Set script translations if available
        if( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'nordea-eramaksu-blocks-integration');
        }

        // Return the registered script handle
        return [ 'nordea-eramaksu-blocks-integration' ];
    }

    // Return the payment method data for the frontend
    public function get_payment_method_data() {
        // Return the title, description, and icon (image URL) for the payment method
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon' => plugin_dir_url( __FILE__ ) . 'assets/images/Nordea_Eramaksu.png',
        ];
    }
}

?>