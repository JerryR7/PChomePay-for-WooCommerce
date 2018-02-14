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

//審單功能
add_action('woocommerce_order_actions', 'pchomepay_audit_order_action');

function pchomepay_audit_order_action($actions)
{
    global $theorder;

    // bail if the order has been paid for or this action has been run
    if ($theorder->get_status() != 'awaiting') {
        return $actions;
    }

    $actions['wc_order_pass'] = __('PChomePay - 訂單過單', 'woocommerce');
    $actions['wc_order_deny'] = __('PChomePay - 訂單取消', 'woocommerce');
    return $actions;
}

//過單
add_action('woocommerce_order_action_wc_order_pass', 'sv_wc_process_order_meta_box_action');

function pchomepay_audit_order_pass($order)
{

    // add the order note
    // translators: Placeholders: %s is a user's display name
    $message = sprintf(__('Order information printed by %s for packaging.', 'woocommerce'), wp_get_current_user()->display_name);
    $order->add_order_note($message);

    // add the flag
    update_post_meta($order->id, '_wc_order_marked_printed_for_packaging', 'yes');
}

//不過單
add_action('woocommerce_order_action_wc_order_deny', 'sv_wc_process_order_meta_box_action');

function pchomepay_audit_order_deny($order)
{

    // add the order note
    // translators: Placeholders: %s is a user's display name
    $message = sprintf(__('Order information printed by %s for packaging.', 'woocommerce'), wp_get_current_user()->display_name);
    $order->add_order_note($message);

    // add the flag
    update_post_meta($order->id, '_wc_order_marked_printed_for_packaging', 'yes');
}

// Add to list of WC Order statuses
add_action('init', 'register_awaiting_audit_order_status');

function register_awaiting_audit_order_status()
{
    register_post_status('wc-awaiting', array(
        'label' => '等待審單',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('等待審單 <span class="count">(%s)</span>', '等待審單 <span class="count">(%s)</span>')
    ));
}

add_filter('wc_order_statuses', 'add_awaiting_audit_order_statuses');

function add_awaiting_audit_order_statuses($order_statuses)
{
    $new_order_statuses = array();
    // add new order status after processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-awaiting'] = '等待審單';
        }
    }
    return $new_order_statuses;
}