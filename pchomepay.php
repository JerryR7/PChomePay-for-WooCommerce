<?php
/*
 * @copyright  Copyright © 2017 PCHomePay Electronic Payment Co., Ltd.(https://www.pchomepay.com.tw)
 * @version 0.0.1
 *
 * Plugin Name: PCHomePay Payment
 * Plugin URI: https://www.pchomepay.com.tw/
 * Description: PCHomePay Integration Payment Gateway for WooCommerce
 * Version: 0.0.1
 * Author: PCHomePay Electronic Payment Co., Ltd.
 * Author URI: https://www.pchomepay.com.tw
 */

add_action('plugins_loaded', 'pchomepay_gateway_init', 0);

function pchomepay_gateway_init()
{
    # Make sure WooCommerce is setted.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Pchomepay_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'pchomepay';
            $this->icon = apply_filters('woocommerce_pchomepay_icon', plugins_url('images/pchomepay_logo.png', __FILE__));;
            $this->has_fields = false;
            $this->method_title = __('PCHomePay', 'woocommerce');
            $this->method_description = '透過 PCHomePay 付款。<br>會連結到 PCHomePay 付款頁面。';

            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->app_id = trim($this->get_option('app_id'));
            $this->secret = trim($this->get_option('secret'));
            $this->atm_expiredate = $this->get_option('atm_expiredate');
            $this->test_mode = $this->get_option('test_mode');
            $this->notify_url = add_query_arg('wc-api', 'WC_pahomepay', home_url('/')) . '&callback=return';
            $this->payment_methods = $this->get_option('payment_methods');

            // Test Mode
            if ($this->test_mode == 'yes') {
                //SandBox Mode
                $this->gateway = "https://sandbox-api-pchomepay.com.tw/v1/";
            } else {
                //Production Mode
                $this->gateway = "https://api-pchomepay.com.tw/v1/";
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('PCHomePay', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('透過 PCHomePay 付款。<br>會連結到 PCHomePay 付款頁面。', 'woocommerce'),
                ),
                'test_mode' => array(
                    'title' => __('Test Mode', 'woocommerce'),
                    'label' => __('Enable', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => __('Test order will add date as prefix.', 'woocommerce'),
                    'default' => 'no'
                ),
                'app_id' => array(
                    'title' => __('APP ID', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'ED25E956580083F635C2F2EC6C16'
                ),
                'secret' => array(
                    'title' => __('SECRET', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'rV_lOkRdWiFA3Ah_usq5z8FKlnMVlFO7lJ8q63ya'
                ),
                'payment_methods' => array(
                    'title' => __('Payment Method', 'woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('Press CTRL and the right button on the mouse to select multi payments.', 'woocommerce'),
                    'options' => array(
                        'Credit' => __('Credit'),
                        'Credit_3' => __('Credit_3'),
                        'Credit_6' => __('Credit_6'),
                        'Credit_12' => __('Credit_12'),
                        'ATM' => __('ATM'),
                        'EACH' => __('EACH'),
                        'ACCT' => __('ACCT')
                    )
                ),
                'atm_expiredate' => array(
                    'title' => __('ATM Expire Date', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("Please enter ATM expire date (1~5 days), default is 5 days", 'woocommerce'),
                    'default' => 5
                )
            );
        }

        public function admin_options()
        {
            ?>
            <h2><?php _e('PCHomePay 收款模組', 'woocommerce'); ?></h2>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        }





        ////////////////////////付款步驟////////////////////////////





        //Redirect to PCHomePay
        public function receipt_page($order_id)
        {
            # Clean the cart
            global $woocommerce;
            $woocommerce->cart->empty_cart();
            $order = new WC_Order($order_id);

            $pchomepay_args = $this->get_pchomepay_args($order);

        }

        private function get_pchomepay_args($order)
        {
            global $woocommerce;

            $app_id = $this->app_id;
            $secret = $this->secret;

            $order_id = $order->id;
            $pay_type = $this->payment_methods;
            $amount = $order->get_total();
            $return_url = $this->get_return_url($order);
            $buyer_email = $order->billing_email;

            var_dump($order->get_product_id());
            var_dump($order->get_items('line_item')['name']);
            exit();
        }


        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            // 更新訂單狀態為等待中 (等待第三方支付網站返回)
            $order->update_status('pending', __('Awaiting PCHomePay payment', 'woocommerce'));
            // 減少庫存
//            $order->reduce_order_stock();
            // 清空購物車
//            $woocommerce->cart->empty_cart();
            // 返回感謝購物頁面跳轉
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

    }

    function add_pchomepay_gateway_class($methods)
    {
        $methods[] = 'WC_Pchomepay_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_pchomepay_gateway_class');
}
