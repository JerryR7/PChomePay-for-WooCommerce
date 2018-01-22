<?php
/**
 * @copyright  Copyright © 2017 PChomePay Electronic Payment Co., Ltd.(https://www.pchomepay.com.tw)
 *
 * Plugin Name: PChomePay Gateway for WooCommerce
 * Plugin URI: https://www.pchomepay.com.tw
 * Description: 讓 WooCommerce 可以使用 PChomePay支付連 進行結帳！水啦！！
 * Version: 1.0.2
 * Author: PChomePay支付連
 * Author URI: https://www.pchomepay.com.tw
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'pchomepay_gateway_init', 0);

function pchomepay_gateway_init()
{
    // Make sure WooCommerce is setted.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once 'includes/PChomePayClient.php';

    require_once 'includes/PChomePayGateway.php';

    function add_pchomepay_gateway_class($methods)
    {
        $methods[] = 'WC_Gateway_PChomePay';
        return $methods;
    }

    function add_pchomepay_settings_link($links)
    {
        $mylinks = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=pchomepay') . '">' . __('設定') . '</a>',
        );
        return array_merge($links, $mylinks);
    }

    add_filter('woocommerce_payment_gateways', 'add_pchomepay_gateway_class');
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_pchomepay_settings_link');


}

add_action('init', 'pchomepay_plugin_updater_init');

function pchomepay_plugin_updater_init()
{

    include_once 'includes/updater.php';

    define('WP_GITHUB_FORCE_UPDATE', true);

    if (is_admin()) {

        $config = array(
            'slug' => plugin_basename(__FILE__),
            'proper_folder_name' => 'PCHomePay-for-WooCommerce-master',
            'api_url' => 'https://api.github.com/repos/JerryR7/PChomePay-for-WooCommerce',
            'raw_url' => 'https://raw.github.com/JerryR7/PChomePay-for-WooCommerce/master',
            'github_url' => 'https://github.com/JerryR7/PChomePay-for-WooCommerce',
            'zip_url' => 'https://github.com/JerryR7/PChomePay-for-WooCommerce/archive/master.zip',
            'sslverify' => true,
            'requires' => '3.0',
            'tested' => '4.8',
            'readme' => 'README.md',
            'access_token' => '',
        );

        new WP_GitHub_Updater($config);

    }

}