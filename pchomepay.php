<?php
/**
 * @copyright  Copyright © 2017 PChomePay Electronic Payment Co., Ltd.(https://www.pchomepay.com.tw)
 *
 * Plugin Name: PChomePay Gateway for WooCommerce
 * Plugin URI: https://www.pchomepay.com.tw
 * Description: 讓 WooCommerce 可以使用 PChomePay支付連 進行結帳！水啦！！
 * Version: 0.0.1
 * Author: PChomePay支付連
 * Author URI: https://www.pchomepay.com.tw
 */

add_action('plugins_loaded', 'pchomepay_gateway_init', 0);

function pchomepay_gateway_init()
{
    // Make sure WooCommerce is setted.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once(dirname(__FILE__) . '/PChomePayClient.php');

    class WC_Gateway_PChomepay extends WC_Payment_Gateway
    {

        public function __construct()
        {
            // Validate ATM ExpireDate
            if (isset($_POST['woocommerce_pchomepay_atm_expiredate']) && (!preg_match('/^\d*$/', $_POST['woocommerce_pchomepay_atm_expiredate']) || $_POST['woocommerce_pchomepay_atm_expiredate'] < 1 || $_POST['woocommerce_pchomepay_atm_expiredate'] > 5)) {
                $_POST['woocommerce_pchomepay_atm_expiredate'] = 5;
            }

            $this->id = 'pchomepay';
            $this->icon = apply_filters('woocommerce_pchomepay_icon', plugins_url('images/pchomepay_logo.png', __FILE__));;
            $this->has_fields = false;
            $this->method_title = __('PChomePay', 'woocommerce');
            $this->method_description = '透過 PChomePay支付連 付款。<br>會連結到 PChomePay支付連 付款頁面。';
            $this->supports = array('products', 'refunds');

            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->app_id = trim($this->get_option('app_id'));
            $this->secret = trim($this->get_option('secret'));
            $this->atm_expiredate = $this->get_option('atm_expiredate');
            $this->test_mode = $this->get_option('test_mode');
            $this->notify_url = WC()->api_request_url(get_class($this));
            $this->payment_methods = $this->get_option('payment_methods');
            $this->card_installment = $this->get_option('card_installment');
            $this->card_rate = $this->get_option('card_rate');
            $this->cover_transfee = $this->get_option('cover_transfee');

            // Test Mode
            $this->test_mode = ($this->get_option('test_mode') === 'yes') ? true : false;

            if (empty($this->app_id) || empty($this->secret)) {
                $this->enabled = false;
            } else {
                $this->client = new PChomePayClient($this->app_id, $this->secret, $this->test_mode);
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'receive_response'));
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
                    'default' => __('PChomePay', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('透過 PChomePay支付連 付款。<br>會連結到 PChomePay支付連 付款頁面。', 'woocommerce'),
                ),
                'test_mode' => array(
                    'title' => __('測試模式', 'woocommerce'),
                    'label' => __('Enable', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => __('使用支付連 SandBox 測試環境', 'woocommerce'),
                    'default' => 'no'
                ),
                'app_id' => array(
                    'title' => __('APP ID', 'woocommerce'),
                    'type' => 'text',
                    'default' => ''
                ),
                'secret' => array(
                    'title' => __('SECRET', 'woocommerce'),
                    'type' => 'text',
                    'default' => ''
                ),
                'payment_methods' => array(
                    'title' => __('付款方式', 'woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('按下 CTRL 與 滑鼠右鍵 以選擇多種付款方式。', 'woocommerce'),
                    'options' => array(
                        'CARD' => __('信用卡'),
                        'ATM' => __('ATM'),
                        'EACH' => __('銀行支付'),
                        'ACCT' => __('支付連餘額支付')
                    )
                ),
                'card_installment' => array(
                    'title' => __('信用卡分期', 'woocommerce'),
                    'type' => 'multiselect',
                    'description' => __('按下 CTRL 與 滑鼠右鍵 以選擇多種付款方式。<br><br>信用卡分期不適用於金額低於30元之訂單<br>低於30元之訂單於支付連信用卡付款頁面僅會顯示一次付清選項', 'woocommerce'),
                    'options' => array(
                        'CRD_0' => __('一次付清', 'woocommerce'),
                        'CRD_3' => __('3 期', 'woocommerce'),
                        'CRD_6' => __('6 期', 'woocommerce'),
                        'CRD_12' => __('12 期', 'woocommerce'),
                    )
                ),
                'card_rate' => array(
                    'title' => __('信用卡分期利率', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('信用卡分期利率設定', 'woocommerce'),
                    'options' => array(
                        '0' => __('0 利率', 'woocommerce'),
                        '1' => __('一般信用卡分期利率', 'woocommerce')
                    )
                ),
                'atm_expiredate' => array(
                    'title' => __('ATM 虛擬帳號繳費期限', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("請輸入 ATM 虛擬帳號繳費期限 (1~5 天)，預設 5 天。", 'woocommerce'),
                    'default' => 5
                ),
                'cover_transfee' => array(
                    'title' => __('退款的跨行轉帳手續費，<br>值須為 Y 或 N。', 'woocommerce'),
                    'type' => 'select',
                    'description' => __("Y : 由串接廠商自行吸收跨行轉帳手續費。<br>N : 由使用者自行負擔跨行轉帳手續費。", 'woocommerce'),
                    'options' => array(
                        'Y' => __('Y', 'woocommerce'),
                        'N' => __('N', 'woocommerce')
                    )
                )
            );
        }

        public function admin_options()
        {
            ?>
            <h2><?php _e('PChomePay 收款模組', 'woocommerce'); ?></h2>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        }

        private function get_pchomepay_payment_data($order)
        {
            global $woocommerce;

            $order_id = date('Ymd') . $order->get_order_number();
            $pay_type = $this->payment_methods;
            $amount = ceil($order->get_total());
            $return_url = $this->get_return_url($order);
            $notify_url = $this->notify_url;
            $buyer_email = $order->get_billing_email();

            if (isset($this->atm_expiredate) && (!preg_match('/^\d*$/', $this->atm_expiredate) || $this->atm_expiredate < 1 || $this->atm_expiredate > 5)) {
                $this->atm_expiredate = 5;
            }

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
            ];

            if ($card_info) $pchomepay_args['card_info'] = $card_info;

            $pchomepay_args = apply_filters('woocommerce_pchomepay_args', $pchomepay_args);

            return $pchomepay_args;
        }

        public function process_payment($order_id)
        {
            try {
                global $woocommerce;

                $order = new WC_Order($order_id);

                // 更新訂單狀態為等待中 (等待第三方支付網站返回)
                $order->update_status('pending', __('Awaiting PChomePay payment', 'woocommerce'));

                $pchomepay_args = json_encode($this->get_pchomepay_payment_data($order));

                if (!class_exists('PChomePayClient')) {
                    if (!require(dirname(__FILE__) . 'PChomePayClient.php')) {
                        throw new Exception(__('PChomePayClient Class missed.', 'woocommerce'));
                    }
                }

                // 建立訂單
                $result = $this->client->postPayment($pchomepay_args);
                // 減少庫存
                wc_reduce_stock_levels($order_id);
                // 清空購物車
                $woocommerce->cart->empty_cart();
                add_post_meta($order_id, 'pchomepay_orderid', $result->order_id);
                // 返回感謝購物頁面跳轉
                return array(
                    'result' => 'success',
//                'redirect' => $order->get_checkout_payment_url(true)
                    'redirect' => $result->payment_url
                );

            } catch (Exception $e) {
                wc_add_notice(__($e->getMessage(), 'woocommerce'), 'error');
            }
        }

        public function receive_response()
        {
            $notify_type = $_REQUEST['notify_type'];
            $notify_message = $_REQUEST['notify_message'];

            if (!$notify_type || !$notify_message) {
                http_response_code(404);
                exit;
            }

            $order_data = json_decode(str_replace('\"', '"', $notify_message));

            $order = new WC_Order(substr($order_data->order_id, 8));

            if ($notify_type == 'order_expired') {
                $order->update_status(
                    'failed',
                    sprintf(
                        __('Error return code: %1$s', 'woocommerce'),
                        $order_data->status_code
                    )
                );
            } elseif ($notify_type == 'order_confirm') {
                $order->payment_complete();
            }

            echo 'success';
            exit();
        }

        private function get_pchomepay_refund_data($orderID, $amount, $refundID = null)
        {
            try {
                global $woocommerce;

                $order = $this->client->getPayment($orderID);

                $order_id = $order->order_id;

                if ($refundID) {
                    $number = (int)trim(substr($refundID, -3), 0) + 1;
                    $refund_id = 'RF' . $order_id . str_pad($number, 3, 0, STR_PAD_LEFT);
                } else {
                    $refund_id = 'RF' . $order_id . '000';
                }
                $trade_amount = (int)$amount;
                (in_array($order->pay_type, ['ATM', 'EACH'])) ? $cover_transfee = $this->cover_transfee : $cover_transfee = null;
                $pchomepay_args = [
                    'order_id' => $order_id,
                    'refund_id' => $refund_id,
                    'trade_amount' => $trade_amount,
                    'cover_transfee' => $cover_transfee,
                ];

                $pchomepay_args = apply_filters('woocommerce_pchomepay_args', $pchomepay_args);

                return $pchomepay_args;
            } catch (Exception $e) {
                throw $e;
            }
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            try {
                $orderID = get_post_meta($order_id, 'pchomepay_orderid', true);
                $refundID = get_post_meta($order_id, 'pchomepay_refundid', true);

                $pchomepay_args = json_encode($this->get_pchomepay_refund_data($orderID, $amount, $refundID));

                if (!class_exists('PChomePayClient')) {
                    if (!require(dirname(__FILE__) . 'PChomePayClient.php')) {
                        throw new Exception(__('PChomePayClient Class missed.', 'woocommerce'));
                    }
                }

                // 退款
                $response_data = $this->client->postRefund($pchomepay_args);

                if (!$response_data) return false;

                // 更新 meta
                ($refundID) ? update_post_meta($order_id, 'pchomepay_refundid', $response_data->refund_id) : add_post_meta($order_id, 'pchomepay_refundid', $response_data->refund_id);

                if (isset($response_data->redirect_url)) {
                    (get_post_meta($order_id, 'pchomepay_refund_url', true)) ? update_post_meta($order_id, 'pchomepay_refund_url', $response_data->redirect_url) : add_post_meta($order_id, 'pchomepay_refund_url', $response_data->redirect_url);
                }

                return true;
            } catch (Exception $e) {
                throw $e;
            }
        }
    }

    function add_pchomepay_gateway_class($methods)
    {
        $methods[] = 'WC_Gateway_PChomepay';
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

    include_once 'updater.php';

    define('WP_GITHUB_FORCE_UPDATE', true);

    if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin

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