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
            $this->card_installment = $this->get_option('card_installment');
            $this->card_rate = $this->get_option('card_rate');

            // Test Mode
            if ($this->test_mode == 'yes') {
                //SandBox Mode
                $this->gateway = "https://sandbox-api.pchomepay.com.tw/v1/";
            } else {
                //Production Mode
                $this->gateway = "https://api.pchomepay.com.tw/v1/";
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'receive_response'));

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
                        'CARD' => __('CARD'),
                        'ATM' => __('ATM'),
                        'EACH' => __('EACH'),
                        'ACCT' => __('ACCT')
                    )
                ),
                'card_installment' => array(
                    'title' => __('Card Installment', 'woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('Card Installment Setting<br>Press CTRL and the right button on the mouse to select multi payments.', 'woocommerce'),
                    'options' => array(
                        'CRD_0' => __('Credit', 'woocommerce'),
                        'CRD_3' => __('Credit_3', 'woocommerce'),
                        'CRD_6' => __('Credit_6', 'woocommerce'),
                        'CRD_12' => __('Credit_12', 'woocommerce'),
                    )
                ),
                'card_rate' => array(
                    'title' => __('Card Installment', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Card Rate Setting', 'woocommerce'),
                    'options' => array(
                        '0' => __('Zero-percent Interest Rate', 'woocommerce'),
                        '1' => __('General Interest Rate', 'woocommerce')
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

            $pchomepay_args = (object)$this->get_pchomepay_args($order);

            $userAuth = "{$this->app_id}:{$this->secret}";

            $token_gateway = $this->gateway . "/token";
            $payment_gateway = $this->gateway . "/payment";

            if (!class_exists('CurlTool')) {
                if (!require(plugin_dir_path(__FILE__) . '/src/CurlTool.php')) {
                    throw new Exception(__('CurlTool module missed.', 'woocommerce'));
                }
            }

            $curl = CurlTool::getInstance();
            $tokenJson = $curl->postToken($userAuth, $token_gateway);
//            $this->handleResult($tokenJson);
            $token = json_decode($tokenJson)->token;

            $result = $curl->postAPI($token, $payment_gateway, json_encode($pchomepay_args));
            $this->handleResult($result);

            $payment_url = "'" . json_decode($result)->payment_url . "'";

            echo '<p><span id="timer">3</span> 秒後會自動跳轉到 PCHomePay 付款頁面，或者按下方按鈕直接前往<br></p>
                  <input type="submit" class="button-alt" id="submit_pchomepay" value="前往 PCHomePay 付款頁面" onclick="location.href=' . $payment_url . '" />' .
                  "<script>
                      function countDown()
                      {
                          var x = document.getElementById(\"timer\");
                          x.innerHTML = x.innerHTML - 1;

                          if (x.innerHTML == 0){
                              window.location = $payment_url;
                          } else {
                          setTimeout(\"countDown()\", 1000);
                          }
                      }
                      setTimeout(\"countDown()\", 1000);
                  </script>";
            exit();
        }

        private function get_pchomepay_args($order)
        {
            global $woocommerce;

            $order_id = (string)$order->get_order_number();
            $pay_type = $this->payment_methods;
            $amount = ceil($order->get_total());
            $return_url = $this->get_return_url($order);
            $notify_url = $this->notify_url;
            $buyer_email = $order->get_billing_email();
            $atm_info = (object)['expire_days' => (int)$this->atm_expiredate];

            $card_info = [];
            $card_rate = $this->card_rate == 1 ? null : 0;

            foreach ($this->card_installment as $items) {
                switch ($items) {
                    case 'CRD_3' :
                        $card_installment['installment'] = 3;
                        $card_installment['rate'] = $card_rate;
                        break;
                    case 'CRD_6' :
                        $card_installment['installment'] = 6;
                        $card_installment['rate'] = $card_rate;
                        break;
                    case 'CRD_12' :
                        $card_installment['installment'] = 12;
                        $card_installment['rate'] = $card_rate;
                        break;
                    default :
                        unset($card_installment);
                        break;
                }
                if (isset($card_installment)) {
                    $card_info[] = (object)$card_installment;
                }
            }

            $items = [];

            $order_items = $order->get_items();
            foreach ($order_items as $item) {
                $product = [];
                $order_item = new WC_Order_Item_Product($item);
                $product_id = ($order_item->get_product_id());
                $product['name'] = $order_item->get_name();
                $product['url'] = get_permalink($product_id);

                $items[] = (object)$product;
            }

            $pchomepay_args = [
                'order_id' => $order_id,
                'pay_type' => $pay_type,
                'amount' => $amount,
                'return_url' => $return_url,
                'notify_url' => $notify_url,
                'items' => $items,
                'buyer_email' => $buyer_email,
                'atm_info' => $atm_info,
                'card_info' => $card_info
            ];

            $pchomepay_args = apply_filters('woocommerce_spgateway_args', $pchomepay_args);

            return $pchomepay_args;
        }

        private function handleResult($result)
        {
            $jsonErrMap = [
                JSON_ERROR_NONE => 'No error has occurred',
                JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
                JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
                JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
                JSON_ERROR_SYNTAX => 'Syntax error',
                JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded	PHP 5.3.3',
                JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded	PHP 5.5.0',
                JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded	PHP 5.5.0',
                JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given	PHP 5.5.0'
            ];

            $obj = json_decode($result);

            $err = json_last_error();
            var_dump($result);
            var_dump($obj);
            var_dump($err);
            echo('114.44.115.208');
            exit();

            if ($err) {
                $errStr = "($err)" . $jsonErrMap[$err];
                if (empty($errStr)) {
                    $errStr = " - unknow error, error code ({$err})";
                }
                throw new Exception("server result error($err) {$errStr}:$result");
            }

            return $obj;
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            // 更新訂單狀態為等待中 (等待第三方支付網站返回)
            $order->update_status('pending', __('Awaiting PCHomePay payment', 'woocommerce'));
            // 減少庫存
            $order->reduce_order_stock();
            // 清空購物車
            $woocommerce->cart->empty_cart();
            // 返回感謝購物頁面跳轉
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

    }

    function receive_response()
    {
        $result = $_REQUEST;
        var_dump($result);
        exit();
    }

    function add_pchomepay_gateway_class($methods)
    {
        $methods[] = 'WC_Pchomepay_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_pchomepay_gateway_class');
}