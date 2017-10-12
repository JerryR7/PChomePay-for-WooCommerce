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

add_action('plugins_loaded', 'pcpay_plugin_init', 0);

function pcpay_plugin_init()
{
    # Make sure WooCommerce is setted.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Pcpay extends WC_Payment_Gateway {
        var $test_mode;
        var $merchant_id;
        var $app_id;
        var $secret;
        var $choose_payment;
        var $payment_methods;
        var $domain;


        public function __construct() {

            // Check ExpireDate is validate or not
            if(isset($_POST['woocommerce_pcpay_atm_expiredate']) && (!preg_match('/^\d*$/', $_POST['woocommerce_pcpay_atm_expiredate']) || $_POST['woocommerce_pcpay_atm_expiredate'] < 1 || $_POST['woocommerce_pcpay_atm_expiredate'] > 180)){
                $_POST['woocommerce_pcpay_atm_expiredate'] = 5;
            }

            # Load the translation
            $this->domain = 'pcpay';
//            load_plugin_textdomain($this->pcpay_domain, false, '/pcpay/translation');

            # Initialize construct properties
            $this->id = 'pcpay';

            # Title of the payment method shown on the admin page
            $this->method_title = $this->tran('PCPay');

            # If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image
            $this->icon = apply_filters('woocommerce_pcpay_icon', plugins_url('images/pchomepay_logo.png', __FILE__));

            # Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
            $this->has_fields = true;

            # Load the form fields
            $this->init_form_fields();

            # Load the administrator settings
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->test_mode = $this->get_option('test_mode');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->app_id = $this->get_option('app_id');
            $this->secret = $this->get_option('secret');
            $this->payment_methods = $this->get_option('payment_methods');
            $this->atm_expiredate = $this->get_option('atm_expiredate');

            # Register a action to save administrator settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            # Register a action to redirect to PCPay payment center
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            # Register a action to process the callback
            add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'receive_response'));
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields () {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => $this->tran('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => $this->tran('Enable'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => $this->tran('Title'),
                    'type' => 'text',
                    'description' => $this->tran('This controls the title which the user sees during checkout.'),
                    'default' => $this->tran('PCPay')
                ),
                'description' => array(
                    'title' => $this->tran('Description'),
                    'type' => 'textarea',
                    'description' => $this->tran('This controls the description which the user sees during checkout.'),
                    'default' => $this->tran('透過 PCHomePay 付款。<br>會連結到 PCHomePay 付款頁面。'),
                ),
                'test_mode' => array(
                    'title' => $this->tran('Test Mode'),
                    'label' => $this->tran('Enable'),
                    'type' => 'checkbox',
                    'description' => $this->tran('Test order will add date as prefix.'),
                    'default' => 'no'
                ),
                'app_id' => array(
                    'title' => $this->tran('APP ID'),
                    'type' => 'text',
                    'default' => 'ED25E956580083F635C2F2EC6C16'
                ),
                'secret' => array(
                    'title' => $this->tran('SECRET'),
                    'type' => 'text',
                    'default' => 'rV_lOkRdWiFA3Ah_usq5z8FKlnMVlFO7lJ8q63ya'
                ),
                'payment_methods' => array(
                    'title' => $this->tran('Payment Method'),
                    'type' => 'multiselect',
                    'description' => $this->tran('Press CTRL and the right button on the mouse to select multi payments.'),
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
                    'title' => $this->tran('ATM Expire Date'),
                    'type' => 'text',
                    'description' => $this->tran("Please enter ATM expire date (1~5 days), default is 5 days"),
                    'default' => 5
                )
            );
        }

        /**
         * Set the admin title and description
         */
        public function admin_options() {
            echo $this->add_next_line('  <h3>' . $this->tran('PCPay Integration Payments') . '</h3>');
            echo $this->add_next_line('  <p>' . $this->tran('PCPay is the most popular payment gateway for online shopping in Taiwan') . '</p>');
            echo $this->add_next_line('  <table class="form-table">');

            # Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo $this->add_next_line('  </table>');
        }

        /**
         * Display the form when chooses PCPay payment
         */
        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        /**
         * Get pcpay Args for passing to pcpay
         *
         * @access public
         * @param mixed $order
         * @return array
         *
         * MPG參數格式
         */
        function get_pcpay_args($order) {

            global $woocommerce;

            $respondtype = "String"; //回傳格式
            $timestamp = time(); //時間戳記
            $version = "1.1"; //串接版本
            $order_id = $order->id;
            $amt = $order->get_total(); //訂單總金額
            $logintype = "0"; //0:不需登入會員，1:須登入會員
            //商品資訊
            $item_name = $order->get_items();
            $item_cnt = 1;
            $itemdesc = "";
            foreach ($item_name as $item_value) {
                if ($item_cnt != count($item_name)) {
                    $itemdesc .= $item_value['name'] . " × " . $item_value['qty'] . "，";
                } elseif ($item_cnt == count($item_name)) {
                    $itemdesc .= $item_value['name'] . " × " . $item_value['qty'];
                }

                //支付寶、財富通參數
                $pcpay_args_1["Count"] = $item_cnt;
                $pcpay_args_1["Pid$item_cnt"] = $item_value['product_id'];
                $pcpay_args_1["Title$item_cnt"] = $item_value['name'];
                $pcpay_args_1["Desc$item_cnt"] = $item_value['name'];
                $pcpay_args_1["Price$item_cnt"] = $item_value['line_subtotal'] / $item_value['qty'];
                $pcpay_args_1["Qty$item_cnt"] = $item_value['qty'];

                var_dump($pcpay_args_1);
                exit();

                $item_cnt++;
            }

            //CheckValue 串接
            $check_arr = array('MerchantID' => $merchantid, 'TimeStamp' => $timestamp, 'MerchantOrderNo' => $order_id, 'Version' => $version, 'Amt' => $amt);
            //按陣列的key做升幕排序
            ksort($check_arr);
            //排序後排列組合成網址列格式
            $check_merstr = http_build_query($check_arr, '', '&');
            $checkvalue_str = "HashKey=" . $this->HashKey . "&" . $check_merstr . "&HashIV=" . $this->HashIV;
            $CheckValue = strtoupper(hash("sha256", $checkvalue_str));

            $buyer_name = $order->billing_last_name . $order->billing_first_name;
            $total_fee = $order->order_total;
            $tel = $order->billing_phone;
            $pcpay_args_2 = array(
                "MerchantID" => $merchantid,
                "RespondType" => $respondtype,
                "CheckValue" => $CheckValue,
                "TimeStamp" => $timestamp,
                "Version" => $version,
                "MerchantOrderNo" => $order_id,
                "Amt" => $amt,
                "ItemDesc" => $itemdesc,
                "ExpireDate" => date('Ymd', time()+intval($this->ExpireDate)*24*60*60),
                "Email" => $order->billing_email,
                "LoginType" => $logintype,
                "NotifyURL" => $this->notify_url, //幕後
                "ReturnURL" => $this->get_return_url($order), //幕前(線上)
                "ClientBackURL" => $this->get_return_url($order), //取消交易
                "CustomerURL" => $this->get_return_url($order), //幕前(線下)
                "Receiver" => $buyer_name, //支付寶、財富通參數
                "Tel1" => $tel, //支付寶、財富通參數
                "Tel2" => $tel, //支付寶、財富通參數
                "LangType" => $this->LangType
            );

            $pcpay_args = array_merge($pcpay_args_1, $pcpay_args_2);
            $pcpay_args = apply_filters('woocommerce_pcpay_args', $pcpay_args);
            return $pcpay_args;
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function receipt_page($order) {
            echo '<p>' . __('3秒後會自動跳轉到PCHomePay支付頁面，或者按下方按鈕直接前往<br>', 'PCHomePay') . '</p>';
            echo $this->generate_pcpay_form($order);
        }

        function generate_pcpay_form($orderid) {
            global $woocommerce;
            $order = new WC_Order($orderid);
            $pcpay_args = $this->get_pcpay_args($order);

            $pcpay_gateway = $this->gateway;
            $pcpay_args_array = array();
            foreach ($pcpay_args as $key => $value) {
                $pcpay_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }

            return '<form id="pcpay" name="pcpay" action=" ' . $pcpay_gateway . ' " method="post" target="_top">' . implode('', $pcpay_args_array) . '
				<input type="submit" class="button-alt" id="submit_pcpay_payment_form" value="' . $this->tran('前往 PCHomePay 支付頁面') . '" />
				</form>' . "<script>setTimeout(\"document.forms['pcpay'].submit();\",\"3000\")</script>";
        }

        /**
         * Process the payment
         */
        public function process_payment($order_id) {
            # Update order status
            $order = new WC_Order($order_id);
            // Empty awaiting payment session
            unset($_SESSION['order_awaiting_payment']);
            //$this->receipt_page($order_id);

            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Process the callback
         */
        public function receive_response() {
            $result_msg = '1|OK';
            $order = null;
            try {
                # Retrieve the check out result
                $this->invoke_allpay_module();
                $aio = new AllInOne();
                $aio->HashKey = $this->allpay_hash_key;
                $aio->HashIV = $this->allpay_hash_iv;
                $aio->MerchantID = $this->allpay_merchant_id;
                $allpay_feedback = $aio->CheckOutFeedback();
                unset($aio);
                if(count($allpay_feedback) < 1) {
                    throw new Exception('Get allPay feedback failed.');
                } else {
                    # Get the cart order id
                    $cart_order_id = $allpay_feedback['MerchantTradeNo'];
                    if ($this->allpay_test_mode == 'yes') {
                        $cart_order_id = substr($allpay_feedback['MerchantTradeNo'], 14);
                    }

                    # Get the cart order amount
                    $order = new WC_Order($cart_order_id);
                    $cart_amount = $order->get_total();

                    # Check the amounts
                    $allpay_amount = $allpay_feedback['TradeAmt'];
                    if (round($cart_amount) != $allpay_amount) {
                        throw new Exception('Order ' . $cart_order_id . ' amount are not identical.');
                    }
                    else
                    {
                        # Set the common comments
                        $comments = sprintf(
                            $this->tran('Payment Method : %s<br />Trade Time : %s<br />'),
                            $allpay_feedback['PaymentType'],
                            $allpay_feedback['TradeDate']
                        );

                        # Set the getting code comments
                        $return_code = $allpay_feedback['RtnCode'];
                        $return_message = $allpay_feedback['RtnMsg'];
                        $get_code_result_comments = sprintf(
                            $this->tran('Getting Code Result : (%s)%s'),
                            $return_code,
                            $return_message
                        );

                        # Set the payment result comments
                        $payment_result_comments = sprintf(
                            $this->tran('Payment Result : (%s)%s'),
                            $return_code,
                            $return_message
                        );

                        # Set the fail message
                        $fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);

                        # Get allPay payment method
                        $allpay_payment_method = $this->get_payment_method($allpay_feedback['PaymentType']);

                        # Set the order comments
                        switch($allpay_payment_method) {
                            case PaymentMethod::Credit:
                            case PaymentMethod::WebATM:
                            case PaymentMethod::Alipay:
                            case PaymentMethod::Tenpay:
                            case PaymentMethod::TopUpUsed:
                                if ($return_code != 1 and $return_code != 800) {
                                    throw new Exception($fail_msg);
                                } else {
                                    if (!$this->is_order_complete($order)) {
                                        $this->confirm_order($order, $payment_result_comments);
                                    } else {
                                        # The order already paid or not in the standard procedure, do nothing
                                    }
                                }
                                break;
                            case PaymentMethod::ATM:
                                if ($return_code != 1 and $return_code != 2 and $return_code != 800) {
                                    throw new Exception($fail_msg);
                                } else {
                                    if ($return_code == 2) {
                                        # Set the getting code result
                                        $comments .= $this->get_order_comments($allpay_feedback);
                                        $comments .= $get_code_result_comments;
                                        $order->add_order_note($comments);
                                    } else {
                                        if (!$this->is_order_complete($order)) {
                                            $this->confirm_order($order, $payment_result_comments);
                                        } else {
                                            # The order already paid or not in the standard procedure, do nothing
                                        }
                                    }
                                }
                                break;
                            case PaymentMethod::CVS:
                                if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
                                    throw new Exception($fail_msg);
                                } else {
                                    if ($return_code == 10100073) {
                                        # Set the getting code result
                                        $comments .= $this->get_order_comments($allpay_feedback);
                                        $comments .= $get_code_result_comments;
                                        $order->add_order_note($comments);
                                    } else {
                                        if (!$this->is_order_complete($order)) {
                                            $this->confirm_order($order, $payment_result_comments);
                                        } else {
                                            # The order already paid or not in the standard procedure, do nothing
                                        }
                                    }
                                }
                                break;
                            default:
                                throw new Exception('Invalid payment method of the order ' . $cart_order_id . '.');
                                break;
                        }
                    }
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
                if (!empty($order)) {
                    $comments .= sprintf($this->tran('Faild To Pay<br />Error : %s<br />'), $error);
                    $order->update_status('failed', $comments);
                }

                # Set the failure result
                $result_msg = '0|' . $error;
            }
            echo $result_msg;
            exit;
        }


        # Custom function

        /**
         * Translate the content
         * @param  string   translate target
         * @return string   translate result
         */
        private function tran($content) {
            return __($content, $this->pcpay_domain);
//            return $content;
        }

        /**
         * Get the payment method description
         * @param  string   payment name
         * @return string   payment method description
         */
        private function get_payment_desc($payment_name) {
            $payment_desc = array(
                'Credit' => $this->tran('Credit'),
                'Credit_3' => $this->tran('Credit(3 Installments)'),
                'Credit_6' => $this->tran('Credit(6 Installments)'),
                'Credit_12' => $this->tran('Credit(12 Installments)'),
                'Credit_18' => $this->tran('Credit(18 Installments)'),
                'Credit_24' => $this->tran('Credit(24 Installments)'),
                'WebATM' => $this->tran('WEB-ATM'),
                'ATM' => $this->tran('ATM'),
                'EACH' => $this->tran('EACH'),
                'ACCT' => $this->tran('ACCT'),
            );

            return $payment_desc[$payment_name];
        }

        /**
         * Add a next line character
         * @param  string   content
         * @return string   content with next line character
         */
        private function add_next_line($content) {
            return $content . "\n";
        }

        /**
         * Invoke allPay module
         */
        private function invoke_allpay_module() {
            if (!class_exists('AllInOne')) {
                if (!require(plugin_dir_path(__FILE__) . '/lib/AllPay.Payment.Integration.php')) {
                    throw new Exception($this->tran('allPay module missed.'));
                }
            }
        }

        /**
         * Format the version description
         * @param  string   version string
         * @return string   version description
         */
        private function format_version_desc($version) {
            return str_replace('.', '_', $version);
        }

        /**
         * Add a WooCommerce error message
         * @param  string   error message
         */
        private function allPay_add_error($error_message) {
            wc_add_notice($error_message, 'error');
        }

        /**
         * Check if the order status is complete
         * @param  object   order
         * @return boolean  is the order complete
         */
        private function is_order_complete($order) {
            $status = '';
            $status = (method_exists($Order,'get_status') == true )? $order->get_status(): $order->status;

            if ($status == 'pending') {
                return false;
            } else {
                return true;
            }
        }

        /**
         * Get the payment method from the payment_type
         * @param  string   payment type
         * @return string   payment method
         */
        private function get_payment_method($payment_type) {
            $info_pieces = explode('_', $payment_type);

            return $info_pieces[0];
        }

        /**
         * Get the order comments
         * @param  array    allPay feedback
         * @return string   order comments
         */
        function get_order_comments($allpay_feedback)
        {
            $comments = array(
                'ATM' =>
                    sprintf(
                        $this->tran('Bank Code : %s<br />Virtual Account : %s<br />Payment Deadline : %s<br />'),
                        $allpay_feedback['BankCode'],
                        $allpay_feedback['vAccount'],
                        $allpay_feedback['ExpireDate']
                    ),
                'CVS' =>
                    sprintf(
                        $this->tran('Trade Code : %s<br />Payment Deadline : %s<br />'),
                        $allpay_feedback['PaymentNo'],
                        $allpay_feedback['ExpireDate']
                    )
            );
            $payment_method = $this->get_payment_method($allpay_feedback['PaymentType']);

            return $comments[$payment_method];
        }

        /**
         * Complete the order and add the comments
         * @param  object   order
         */
        function confirm_order($order, $comments) {
            $order->add_order_note($comments, true);
            $order->payment_complete();

            // call invoice model
            $invoice_active_ecpay = 0 ;
            $invoice_active_allpay = 0 ;

            $active_plugins = (array) get_option( 'active_plugins', array() );

            $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

            foreach($active_plugins as $key => $value)
            {
                if ( (strpos($value,'/woocommerce-ecpayinvoice.php') !== false))
                {
                    $invoice_active_ecpay = 1;
                }

                if ( (strpos($value,'/woocommerce-allpayinvoice.php') !== false))
                {
                    $invoice_active_allpay = 1;
                }
            }

            if($invoice_active_ecpay == 0 && $invoice_active_allpay == 1) // allpay
            {
                if( is_file( get_home_path().'/wp-content/plugins/allpay_invoice/woocommerce-allpayinvoice.php') )
                {
                    $aConfig_Invoice = get_option('wc_allpayinvoice_active_model') ;

                    if(isset($aConfig_Invoice) && $aConfig_Invoice['wc_allpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_allpay_invoice_auto'] == 'auto' )
                    {
                        do_action('allpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
                    }
                }
            }
            elseif($invoice_active_ecpay == 1 && $invoice_active_allpay == 0) //ecpay
            {
                if( is_file( get_home_path().'/wp-content/plugins/ecpay_invoice/woocommerce-ecpayinvoice.php') )
                {
                    $aConfig_Invoice = get_option('wc_ecpayinvoice_active_model') ;

                    if(isset($aConfig_Invoice) && $aConfig_Invoice['wc_ecpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_ecpay_invoice_auto'] == 'auto' )
                    {
                        do_action('ecpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
                    }
                }
            }
        }
    }



    /**
     * Add the Gateway Plugin to WooCommerce
     * */
    function woocommerce_add_pcpay_plugin($methods) {
        $methods[] = 'WC_Gateway_Pcpay';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_pcpay_plugin');
}
