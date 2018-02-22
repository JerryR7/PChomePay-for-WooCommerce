<?php
/**
 * Created by PhpStorm.
 * User: Jerry
 * Date: 2018/1/19
 * Time: 上午10:26
 */

if (!defined('ABSPATH')) exit;

class WC_Gateway_PChomePay extends WC_Payment_Gateway
{
    /** @var bool Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    public function __construct()
    {
        // Validate ATM ExpireDate
        if (isset($_POST['woocommerce_pchomepay_atm_expiredate']) && (!preg_match('/^\d*$/', $_POST['woocommerce_pchomepay_atm_expiredate']) || $_POST['woocommerce_pchomepay_atm_expiredate'] < 1 || $_POST['woocommerce_pchomepay_atm_expiredate'] > 5)) {
            $_POST['woocommerce_pchomepay_atm_expiredate'] = 5;
        }

        $this->id = 'pchomepay';
        $this->icon = apply_filters('woocommerce_pchomepay_icon', plugins_url('images/pchomepay_logo.png', dirname(__FILE__)));
        $this->has_fields = false;
        $this->method_title = __('PChomePay支付連', 'woocommerce');
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
        $this->sandbox_secret = trim($this->get_option('sandbox_secret'));
        $this->atm_expiredate = $this->get_option('atm_expiredate');
        // Test Mode
        $this->test_mode = ($this->get_option('test_mode') === 'yes') ? true : false;
        $this->debug = ($this->get_option('debug') === 'yes') ? true : false;
        $this->notify_url = WC()->api_request_url(get_class($this));
        $this->payment_methods = $this->get_option('payment_methods');
        $this->card_installment = $this->get_option('card_installment');
        $this->card_last_number = ($this->get_option('card_last_number') === 'yes') ? true : false;

        self::$log_enabled = $this->debug;

        if (empty($this->app_id) || empty($this->secret)) {
            $this->enabled = false;
        } else {
            $this->client = new PChomePayClient($this->app_id, $this->secret, $this->sandbox_secret, $this->test_mode, self::$log_enabled);
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'receive_response'));
    }

    public function init_form_fields()
    {
        $this->form_fields = include('settings.php');
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

        $order_id = 'AW' . date('Ymd') . $order->get_order_number();
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

        foreach ($this->card_installment as $items) {
            switch ($items) {
                case 'CRD_3' :
                    $card_installment['installment'] = 3;
                    break;
                case 'CRD_6' :
                    $card_installment['installment'] = 6;
                    break;
                case 'CRD_12' :
                    $card_installment['installment'] = 12;
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

            $pchomepay_args = json_encode($this->get_pchomepay_payment_data($order));

            if (!class_exists('PChomePayClient')) {
                if (!require(dirname(__FILE__) . '/PChomePayClient.php')) {
                    throw new Exception(__('PChomePayClient Class missed.', 'woocommerce'));
                }
            }

            // 建立訂單
            $result = $this->client->postPayment($pchomepay_args);

            if (!$result) {
                self::log("交易失敗：伺服器端未知錯誤，請聯絡 PChomePay支付連。");
                throw new Exception("嘗試使用付款閘道 API 建立訂單時發生錯誤，請聯絡網站管理員。");
            }

            // 減少庫存
            wc_reduce_stock_levels($order_id);
            // 清空購物車
            $woocommerce->cart->empty_cart();

            // 更新訂單狀態為等待中 (等待第三方支付網站返回)
            add_post_meta($order_id, 'pchomepay_orderid', $result->order_id);
            $order->update_status('pending', __('Awaiting PChomePay payment', 'woocommerce'));
            $order->add_order_note('訂單編號: ' . $result->order_id, true);
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

        $order = new WC_Order(substr($order_data->order_id, 10));

        # 紀錄訂單付款方式
        switch ($order_data->pay_type) {
            case 'ATM':
                $pay_type_note = 'ATM 付款';
                break;
            case 'CARD':
                if ($order_data->payment_info->installment == 1) {
                    $pay_type_note = '信用卡 付款 (一次付清)';
                } else {
                    $pay_type_note = '信用卡 分期付款 (' . $order_data->payment_info->installment . '期)';
                }

                if ($this->card_last_number) $pay_type_note .= '<br>末四碼: ' . $order_data->payment_info->card_last_number;

                break;
            case 'ACCT':
                $pay_type_note = '支付連餘額 付款';
                break;
            case 'EACH':
                $pay_type_note = '銀行支付 付款';
                break;
            default:
                $pay_type_note = $order_data->pay_type . '付款';
        }

        if ($notify_type == 'order_expired') {
            $order->add_order_note($pay_type_note, true);
            if ($order_data->status_code) {
                $order->update_status('failed');
                $order->add_order_note(sprintf(__('訂單已失敗。<br>error code: %1$s<br>message: %2$s', 'woocommerce'), $order_data->status_code, OrderStatusCodeEnum::getErrMsg($order_data->status_code)), true);
            } else {
                $order->update_status('failed');
                $order->add_order_note('訂單已失敗。', true);
            }
        } elseif ($notify_type == 'order_confirm') {
            $order->add_order_note($pay_type_note, true);
            $order->update_status('pending');
            $order->payment_complete();
        } elseif ($notify_type == 'order_audit') {
            if ($order_data->status_code === 'WA') {
                $order->update_status('awaiting');
            }
            $order->add_order_note(sprintf(__('訂單交易等待中。<br>status code: %1$s<br>message: %2$s', 'woocommerce'), $order_data->status_code, OrderStatusCodeEnum::getErrMsg($order_data->status_code)), true);
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

            if ($amount === $order->amount) {
                $refund_id = 'RF' . $order_id;
            } else {
                if ($refundID) {
                    $number = (int)substr($refundID, strpos($refundID, '-') + 1) + 1;
                    $refund_id = 'RF' . $order_id . '-' . $number;
                } else {
                    $refund_id = 'RF' . $order_id . '-1';
                }
            }

            $trade_amount = (int)$amount;
            $pchomepay_args = [
                'order_id' => $order_id,
                'refund_id' => $refund_id,
                'trade_amount' => $trade_amount,
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
            $refundIDs = get_post_meta($order_id, 'pchomepay_refundid', true);

            if ($refundIDs) {
                $refundID = trim(strrchr($refundIDs, ','), ', ') ? trim(strrchr($refundIDs, ','), ', ') : $refundIDs;
            } else {
                $refundID = $refundIDs;
            }

            $wcOrder = new WC_Order($order_id);
            $orderNote = $this->get_order_notes($order_id);

            $pchomepay_args = json_encode($this->get_pchomepay_refund_data($orderID, $amount, $refundID));

            if (!class_exists('PChomePayClient')) {
                if (!require(dirname(__FILE__) . '/PChomePayClient.php')) {
                    throw new Exception(__('PChomePayClient Class missed.', 'woocommerce'));
                }
            }

            // 退款
            $response_data = $this->client->postRefund($pchomepay_args);

            if (!$response_data) {
                self::log("退款失敗：伺服器端未知錯誤，請聯絡 PChomePay支付連。");
                return false;
            }

            // 更新 meta
            ($refundID) ? update_post_meta($order_id, 'pchomepay_refundid', $refundIDs . ", " . $response_data->refund_id) : add_post_meta($order_id, 'pchomepay_refundid', $response_data->refund_id);

            if (isset($response_data->redirect_url)) {
                (get_post_meta($order_id, 'pchomepay_refund_url', true)) ? update_post_meta($order_id, 'pchomepay_refund_url', $response_data->refund_id . ' : ' . $response_data->redirect_url) : add_post_meta($order_id, 'pchomepay_refund_url', $response_data->refund_id . ' : ' . $response_data->redirect_url);
            }

            $wcOrder->add_order_note('退款編號：' . $response_data->refund_id, true);

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function get_pchomepay_audit_data($wcOrder, $status)
    {
        $order_id = 'AW' . date_format($wcOrder->get_date_created(), 'Ymd') . $wcOrder->get_id();
        $order = $this->client->getPayment($order_id);

        $pchomepay_args = [
            'order_id' => $order->order_id,
            'status' => $status
        ];

        $pchomepay_args = apply_filters('woocommerce_pchomepay_args', $pchomepay_args);

        return $pchomepay_args;
    }

    public function process_audit($wc_orderid, $status)
    {
        try {
            $wcOrder = new WC_Order($wc_orderid);
            $pchomepay_args = json_encode($this->get_pchomepay_audit_data($wcOrder, $status));

            if (!class_exists('PChomePayClient')) {
                if (!require(dirname(__FILE__) . '/PChomePayClient.php')) {
                    throw new Exception(__('PChomePayClient Class missed.', 'woocommerce'));
                }
            }

            $response_data = $this->client->postPaymentAudit($pchomepay_args);

            self::log(json_encode($response_data));

            if (!$response_data) throw new Exception(__('狀態錯誤', 'woocommerce'));

            if ($response_data->status === 'SUCC') {

                switch ($status) {
                    case 'PASS':
                        $wcOrder->add_order_note('訂單編號：' . $response_data->order_id . '已過單', true);
                        break;
                    case 'DENY':
                        $wcOrder->add_order_note('訂單編號：' . $response_data->order_id . '已過單', true);
                        break;
                    default:
                        throw new Exception(__('審單狀態錯誤', 'woocommerce'));
                }
            }

            return true;
        } catch (Exception $e) {
            if ($e->getCode()) {
                $wcOrder->add_order_note('審單失敗，錯誤代碼：' . $e->getCode());
            } else {
                $wcOrder->add_order_note('審單失敗，未知的錯誤原因');
            }
            return false;
        }
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'pchomepay'));
        }
    }

    /**
     * @param $order_id
     * @return array
     */
    function get_order_notes($order_id)
    {
        global $wpdb;

        $table_perfixed = $wpdb->prefix . 'comments';
        $results = $wpdb->get_results("
        SELECT *
        FROM $table_perfixed
        WHERE  `comment_post_ID` = $order_id
        AND  `comment_type` LIKE  'order_note'
    ");

        foreach ($results as $note) {
            $order_note[] = array(
                'note_id' => $note->comment_ID,
                'note_date' => $note->comment_date,
                'note_author' => $note->comment_author,
                'note_content' => $note->comment_content,
            );
        }
        return $order_note;
    }
}