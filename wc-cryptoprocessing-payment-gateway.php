<?php
/*
 * Plugin Name: WooCommerce Cryptoprocessing Payment
 * Description: Cryptoprocessing payment gateway for WooCommerce
 * Version: 1.0
 * Author: Arman H
 * Author URI: https://airarm.wordpress.com
 * Text Domain: wc_cp_payment
 */

add_filter('woocommerce_payment_gateways', 'wc_cp_payment_gateways');
function wc_cp_payment_gateways($gateways)
{
    $gateways[] = 'WC_Cryptoprocessing_Gateway';
    return $gateways;
}


add_action('plugins_loaded', 'wc_cp_payment_gateway_init');
function wc_cp_payment_gateway_init()
{
    load_plugin_textdomain('wc_cp_payment', false, dirname(plugin_basename( __FILE__ )).'/languages/');

    require_once plugin_dir_path(__FILE__) . 'WC_Cryptoprocessing_Gateway.php';
}

function get_wc_cp_payment_settings()
{
    return get_option('woocommerce_wc_cp_payment_settings');
}