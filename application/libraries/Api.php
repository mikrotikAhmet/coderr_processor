<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/22/16
 * Time: 4:34 PM
 */
class Api extends REST {



    public function processApi($data, $status,$hasError = false,$response = array()) {

        $objDateTime = new DateTime('NOW');

        if (isset($data) && !empty($data) && $hasError === false) {

            if ($response['ResultState'] === false){
                $status = 1;
            }

            $processorCdc = array(
                'processorTransactionId' => $response['TransactionId'],
                'processorTransactionGuid' => $response['TransactionGuid']
            );

            $response['Cdc']['Processor'] = $processorCdc;

            $res['Result'] = $status;
            $res['Message'] = $this->getStatusMessage($status);
            $res['TrackingMemberCode'] = $data['Object']['trackingMemberCode'];
            $res['TransactionId'] = $response['SemiteId'];
            $res['TransactionGuid'] = $response['SemiteGuid'];
            $res['TransactionDateTime'] = $objDateTime->format(DateTime::ISO8601);

            if (isset($response['ReferenceId'])){
                $res['ReferenceId'] = $response['ReferenceId'];
            }

            $res['Cdc'] = $response['Cdc'];

            $this->response($this->json($res), $status);

        } else {

            $error['code'] = $status;
            $error['Message'] = $this->getStatusMessage($status);
            $error['DateTime'] = $objDateTime->format(DateTime::ISO8601);

            $this->response($this->json($error), $status);
        }
    }

    public function getStatusMessage($code) {

        $status = array(
            0=>'Transaction approved',
            1=>'Transaction declined',
            90 => 'Success',
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            2001 =>'Your connection is unsecure. Request should be originated from SSL',
            2002 =>'Authentication Failed',
            2003 =>'You have exceeded allowed per transaction limit.',
            2004 =>'You have exceeded allowed processing limit.',
            2005 =>'Your account is not allowed to process this card type.',
            2006 =>'Amount is not valid.',
            2007 =>'Card number is not valid.',
            2008 =>'Faild to complete the operation. Transaction could not be found. Please chack your information provided and try again'
        );
        return ($status[$code]) ? $status[$code] : $status[90];
    }

    private function json($data) {

        if (is_array($data)) {
            return json_encode($data);
        }
    }

}