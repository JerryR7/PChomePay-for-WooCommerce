<?php
/*
 * @copyright  Copyright © 2017 PCHomePay Electronic Payment Co., Ltd.(https://www.pchomepay.com.tw)
 * @version 0.0.1
 *
 * Plugin Name: PCPay Payment
 * Plugin URI: https://www.pchomepay.com.tw/
 * Description: PCPay Integration Payment Gateway for WooCommerce
 * Version: 0.0.1
 * Author: PCHomePay Electronic Payment Co., Ltd.
 * Author URI: https://www.pchomepay.com.tw
 */

add_action('plugins_loaded', 'pchomepay_gateway_init', 0);

function pchomepay_gateway_init() {
    # Make sure WooCommerce is setted.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Your_Gateway extends WC_Payment_Gateway {








    }
}