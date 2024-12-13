# Nordea Finance Erämaksu for WooCommerce 

[Nordea Finance Erämaksu](https://www.nordeafinance.fi/en/personal/services/consumercredit/eramaksu.html) instalment payment service for [WooCommerce](https://www.woocommerce.com)

Nordea Finance Erämaksu for WooCommerce plugin adds an easy to apply instalment payment option to your WooCommerce shop. Nordea Finance Erämaksu is an unsecured fixed-term one-time credit intended for larger purchases of 500 to 30,000 euros. Your customer can quickly apply for the instalment payment when placing an order, which makes the purchasing experience smoother for larger purchases. 

The plugin supports WooCommerce classic and new block-based checkout.

## Requirements

PHP version: 7.3<br>
WordPress version: 6.0</br>
WooCommerce version: 9.0

An agreement with Nordea Finance is required to use this plugin.<br> 
Nordea Finance Erämaksu is only available in Finland and in EUR currency.

## Installation

The beta release of the plugin is availbe for download in [GitHub](https://github.com/ndea-wc/nordea-eramaksu-for-woocommerce).

## Configuration

Configuration settings are available after plugin activation at: → WooCommerce →  Settings → Payments →  Nordea Erämaksu for WooCommerce. 

Required authorization and authentication credentials and several other options are configurable on the settings page.  

## Dealer ID, API Client ID and API Client Secret

You will receive the required your Dealer ID, API Client ID and API Client Secret from your Nordea Finance representative upon agreement. 

## Plugin compatibility

Nordea Finance Erämaksu works with most themes that follow the normal checkout process. However, the plugin has only been tested with the [Storefront](https://woocommerce.com/products/storefront/) theme and is not guaranteed to work with other themes. Example situations when errors may occur are:
- Themes or plugins with customized template files for WooCommerce checkout page. 
- Themes or plugins that remove or modify standard checkout fields.
- Themes or plugins that modify the checkout process into multiple steps.

Nordea Finance Erämaksu can only handle product quantity that is specified as an integer. For instance you can’t send 1.5 as the product quantity to the Nordea Finance API. The plugin rounds the quanity to nearest integer value before submitting it to Nordea Finance API.

Required checkout fields are:
- Firstname
- Lastname
- Streetaddress
- City
- Postal code
- Email
- Phone number

## Order management

When an order has been made using Nordea Finance Erämaksu as payment option and the application has been approved in the process, the order itself can be managed directly in the WooCommerce order admin. This way the order status can be synchronized between the store and Nordea Finance.

The following operations cannot be undone, so some caution is required in order management:
- When completing an order which has been paid with Nordea Finance Erämaksu the plugin contacts Nordea Finance API to release the instalment payment to invoicing. 
- Order that has not been completed can be cancelled. On cancellation the plugin contacts Nordea Finance API to cancel the instalment payment.
- Order that has been completed can be refunded either partially or completely. On refund the plugin contacts Nordea Finance API to make a refund to the instalment payment. Refunds are cumulative until the total value has been refunded.

## Important notes

- Plugin supports only purchases in EUR currency
- Plugin does not support subscription type of orders
- Plugin does not necessarily support additional checkout fields or other extras
- Plugin does not support express checkout

Nordea Finance or Capgemini do not provide support for modifying the plugin or you theme to support Nordea Finance Erämaksu, but you are free to do so. You can find the [Nordea Finance API](https://developer.nordeaopenbanking.com/documentation?api=Purchase%20Finance%20API) documention to be of some help. 



