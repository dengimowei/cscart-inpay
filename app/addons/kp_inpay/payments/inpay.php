<?php

/*
Copyright 2017 INPAY S.A.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

use Tygh\KartPay\InPay\ApiClient;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}
if (defined('PAYMENT_NOTIFICATION')) {
    define('INPAY_MAX_TIME', 60); // Time for awaiting callback

    $pp_response = array();
    $pp_response['order_status'] = 'F';
    $pp_response['reason_text'] = __('text_transaction_declined');

    $order_id = !empty($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;
    if( $order_id!=0 ) {
        $order_info = fn_get_order_info($order_id);
        if ($order_info && empty($processor_data)) {
            $processor_data = fn_get_processor_data($order_info['payment_id']);
        }
    }

    if (!empty($processor_data)) {
        if( $mode=='success') { // payment placed succesfuly. we give a little time to get callback with payment status
            $view = Tygh::$app['view'];
            $view->assign('order_action', __('placing_order'));
            $view->display('views/orders/components/placing_order.tpl');
            fn_flush();

            $_placed = false;
            $times = 0;

            while (!$_placed) {
                $data = db_get_field('SELECT data FROM ?:order_data WHERE type = ?s AND order_id = ?s', 'E', $order_id);
                if( $data ) {
                    $dataE = @unserialize($data);
                    if(is_array($dataE)) {
                        if( $dataE['status']=='confirmed' ) {   // expecting for confirmed status (maybe something else will come up???
                            $_placed=true;
                        }
                    }
                }
                if( !$_placed ) {
                    sleep(1);   // wait little more....
                }
                $times++;
                if ($times > INPAY_MAX_TIME) {
                    break;
                }
            }
            // finish order placement with what ever $pp_response we have
            if (fn_check_payment_script('inpay.php', $order_id)) {
                fn_finish_payment($order_id, $pp_response);
                fn_order_placement_routines('route', $order_id);
            }

        }
        elseif( $mode=='fail' ) {   // fail is final, we finish order place with status F
            if (fn_check_payment_script('inpay.php', $order_id)) {
                fn_finish_payment($order_id, $pp_response);
                fn_order_placement_routines('route', $order_id);
            }
        }
        elseif( $mode=='callback' ) {   // accepting payment status changes. It is suposed that inpay will hit this as status is changing new -> recieved -> confirmed
            $mode = $processor_data['processor_params']['mode'];
            $apikey = $processor_data['processor_params']['apikey'];
            $secretApiKey = $processor_data['processor_params']['secretApiKey'];
            $currency = $processor_data['processor_params']['currency'];
            $postData = [
                'apiKey' => $apikey,
                'apiKeySecret' => $secretApiKey,
                'amount' => $amount,
                'currency' => $currency,
            ];
            $api = new ApiClient();
            $api->TestMode(($mode=='test'));
            $api->init($postData);

            try {
                $resp = $api->callback();
            }
            catch(\Exception $ex) { // possibly signature doesnt match
                $resp = false;
            }
            if( $resp ) {
                // store new status to order_data table
                $data = db_get_row("SELECT * FROM ?:order_data WHERE order_id=?i and type=?s", $order_id, 'E');
                $dataE = @unserialize($data['data']);
                if( !is_array($dataE) ) {   // something is wrong!
                    $dataE = ['invoiceCode'=>'','status'=>'NA'];
                }
                $dataE['status'] = $resp['status'];
                $data['data'] = serialize($dataE);
                db_query("REPLACE INTO ?:order_data ?e", $data);
            }
            exit;   // nothing more to do.
        }
    }
} else {
    $orderId = $order_info['order_id'];
    $success_url = fn_url("payment_notification.success?payment=inpay&order_id=$orderId", AREA, 'current');
    $fail_url = fn_url("payment_notification.fail?payment=inpay&order_id=$orderId", AREA, 'current');
    $callback_url = fn_url("payment_notification.callback?payment=inpay&order_id=$orderId", AREA, 'current');
    $mode = $processor_data['processor_params']['mode'];
    $apikey = $processor_data['processor_params']['apikey'];
    $secretApiKey = $processor_data['processor_params']['secretApiKey'];
    $currency = $processor_data['processor_params']['currency'];
    $amount = number_format($order_info['total'],0, '.', '');
    $postData = [
        'apiKey' => $apikey,
        'apiKeySecret' => $secretApiKey,
        'amount' => $amount,
        'currency' => $currency,
        'orderCode' => $orderId,
        'customerEmail' => $order_info['email'],
        'callbackUrl' => $callback_url,
        'successUrl' => $success_url,
        'failUrl' => $fail_url,
    ];

    $api = new ApiClient();
    $api->TestMode(($mode=='test'));
    $api->init($postData);
    $resp1 = $api->invoiceCreate($amount,$order_info['order_id']);
    // store invoiceCode to order_date for later reference
    $data = array (
        'order_id' => $orderId,
        'type' => 'E',
        'data' => serialize(['invoiceCode'=>$resp1['invoiceCode'],'status'=>'NA']),
    );
    db_query("REPLACE INTO ?:order_data ?e", $data);

    $post_url = $resp1['redirectUrl'];
    fn_redirect($post_url, true);

    exit;
}
