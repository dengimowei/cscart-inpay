<?php
/*
Copyright 2017 INPAY S.A.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Tygh\KartPay\InPay;

class ApiClient {
    const INPAY_API_URL = "https://api.inpay.pl";
    const INPAY_API_URL_TEST = "https://apitest.inpay.pl";
    private $availableCurrencies = array("PLN", "USD", "EUR");
    private $apiUrl = self::INPAY_API_URL;
    private $currency = "PLN";
    private $callbackUrl;
    private $successUrl;

    public function init($options) {
        foreach((array) $options as $key => $val) {
            $this->$key = $val;
        }
    }

    public function invoiceCreate($amount, $orderCode = null) {
        $data = array(
            'apiKey' => $this->apiKey,
            'amount' => number_format(str_replace(",",".", $amount), 2),
            'currency' => $this->currency,
            'callbackUrl' => $this->callbackUrl,
            'orderCode' => $orderCode,
            'successUrl' => $this->successUrl
        );
        return $this->requestPost("/invoice/create", $data);
    }

    public function invoiceStatus($invoiceCode) {
        $data = array(
            'invoiceCode' => $invoiceCode,
        );
        return $this->requestPost("/invoice/status", $data);
    }

    public function TestMode($enable) {
        $this->apiUrl = (TRUE === $enable) ? self::INPAY_API_URL_TEST : self::INPAY_API_URL;
    }

    public function setCurrency($name) {
        if(in_array($name, $this->availableCurrencies)) {
            $this->currency = $name;
        } else {
            throw new \Exception("Currency unavailable");
        }
    }

    public function callback() {
        $receivedCallback = isset($_POST['orderCode']) &&
            isset($_POST['amount']) &&
            isset($_POST['invoiceCode']) &&
            isset($_POST['fee']) &&
            isset($_POST['status']);

        if(!$receivedCallback) {
            return FALSE;
        }

        if(!$this->checkApiHash()) {
            throw new \Exception("singature hash doesn't match!");
        }

        return array(
            'invoice_id' => $_POST['orderCode'],
            'in' => $_POST['amount'],
            'description' => 'Thank you! Transaction was successful!',
            'fee' => $_POST['fee'],
            'transaction_id' => $_POST['invoiceCode'],
            'status' => $_POST['status'],
        );
    }

    /*
     * private methods, don't use directly
     */

    private function requestPost($method, $data) {
        $url = $this->apiUrl . $method;
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ),
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result, true);
    }

    private function checkApiHash() {
        $apiHash = $_SERVER['HTTP_API_HASH'];
        $query = http_build_query($_POST);
        $hash = hash_hmac("sha512", $query, $this->apiKeySecret);
        // Timing attack safe string comparison
        return hash_equals($apiHash, $hash);
    }
}
