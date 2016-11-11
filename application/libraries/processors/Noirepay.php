<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 11/9/16
 * Time: 5:42 PM
 */
require(PROCESSORS . 'Payvision/test/payvision_autoload.php');

class Noirepay
{
    public function __construct(){

        $this->ci =& get_instance();
        $this->response = array();

        $this->ci->load->library('currency');
        $this->ci->load->model('Currencies_model');

        $this->ci->load->library('arraytoxml');

        $this->arraytoxml = new ArrayToXML();
        $this->_api = new Api();

        $this->cc_validator = new CreditCardValidator();
    }

    public function Payment($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $amount = money_format("%!^i",$Currency->convert($params['amount'],$params['currencyId'],get_client_default_currency($merchant->userid)));

        $this->cc_validator->validate($params['cardNumber']);

        $cardInfo = $this->cc_validator->GetCardInfo();

        if ($merchant->live_mode){
            $url = "https://ctpe.net/frontend/payment.prc";
        } else {
            $url = "https://test.ctpe.net/frontend/payment.prc";
        }

        $data = "SECURITY.SENDER=".$merchantProcessor->sender .
            "&TRANSACTION.CHANNEL=". $merchantProcessor->channel .
            "&TRANSACTION.MODE=".($merchant->live_mode ? "LIVE" : "INTEGRATOR_TEST") .
            "&RECURRENCE.MODE=INITIAL".
            "&TRANSACTION.RESPONSE=SYNC".
            "&USER.LOGIN=". $merchantProcessor->login .
            "&IDENTIFICATION.TRANSACTIONID=".UUID::trxid(9).
            "&USER.PWD=". $merchantProcessor->password .
            "&PAYMENT.CODE=CC.DB" .
            "&PRESENTATION.AMOUNT=". $amount .
            "&PRESENTATION.CURRENCY=". get_client_default_currency($merchant->userid).
            "&PRESENTATION.USAGE=". $params['trackingMemberCode'] .
            "&CONTACT.IP=".$_SERVER['REMOTE_ADDR'].
            "&CUST.CNTRY.CD=".$params['countryId'].
            "&CUST.EMAIL=ahmet.gudenoglu@gmail.com".
            "&CUST.HOME.PHONE=656728972".
            "&CUST.IP.ADDR=109.203.109.19".
            "&ADDRESS.COUNTRY=GB".
            "&ACCOUNT.HOLDER=". ((isset($params['cardholder']) && !empty($params['cardholder']) ? $params['cardholder'] : NULL)).
            "&ACCOUNT.NUMBER=".$params['cardNumber'].
            "&ACCOUNT.BRAND=". strtoupper($cardInfo['type']).
            "&ACCOUNT.EXPIRY_MONTH=".$params['cardExpiryMonth'].
            "&ACCOUNT.EXPIRY_YEAR=".$params['cardExpiryYear'].
            "&ACCOUNT.VERIFICATION=".$params['cardCvv'];

        $param = array('http' => array(
            'method' => 'POST',
            'content' => $data
        ));

        $ctx = stream_context_create($param);

        $fp = @fopen($url, 'rb', false, $ctx);

        $response = @stream_get_contents($fp);

        parse_str($response,$output);

        if ($output['PROCESSING_RESULT'] == 'ACK') {
            $this->response = array(
                'ResultState' => 1,
                'TransactionId' => $output['IDENTIFICATION_SHORTID'],
                'TransactionGuid' => $output['IDENTIFICATION_UNIQUEID'],
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($output['IDENTIFICATION_UNIQUEID'], UUID::trxid(8)),
                'Cdc' => $output
            );
        } else {
            $this->response = array(
                'ResultState' => false,
                'TransactionId' => UUID::trxid(8),
                'TransactionGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'Cdc' => $output
            );
        }



        return $this->response;

    }
    public function Refund($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $this->ci->db->where('transactionid',$params['transactionId']);
        $this->ci->db->where('transactionguid',$params['transactionGuid']);
        $transaction = $this->ci->db->get('tbltransactions')->row();

        if (!$transaction){

            $this->_api->processApi($this->response, 2008,true);
        }

        $this->ci->db->where('transactionid',$transaction->id);
        $this->ci->db->where('type','response');
        $webhook = $this->ci->db->get('tblwebhooks')->row();

        $hookdata = (array) json_decode($webhook->hookdata);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        if ($merchant->live_mode){
            $url = "https://ctpe.net/frontend/payment.prc";
        } else {
            $url = "https://test.ctpe.net/frontend/payment.prc";
        }

        $data = "SECURITY.SENDER=".$merchantProcessor->sender .
            "&TRANSACTION.CHANNEL=". $merchantProcessor->channel .
            "&TRANSACTION.MODE=".($merchant->live_mode ? "LIVE" : "INTEGRATOR_TEST") .
            "&TRANSACTION.RESPONSE=SYNC".
            "&USER.LOGIN=". $merchantProcessor->login .
            "&IDENTIFICATION.TRANSACTIONID=".UUID::trxid(9).
            "&USER.PWD=". $merchantProcessor->password .
            "&PAYMENT.CODE=CC.RF" .
            "&IDENTIFICATION.REFERENCEID=".$hookdata['TransactionGuid'].
            "&PRESENTATION.AMOUNT=". $amount .
            "&PRESENTATION.CURRENCY=". get_client_default_currency($merchant->userid).
            "&PRESENTATION.USAGE=". $params['trackingMemberCode'];

        $param = array('http' => array(
            'method' => 'POST',
            'content' => $data
        ));

        $ctx = stream_context_create($param);

        $fp = @fopen($url, 'rb', false, $ctx);

        $response = @stream_get_contents($fp);

        parse_str($response,$output);

        if ($output['PROCESSING_RESULT'] == 'ACK') {
            $this->response = array(
                'ResultState' => 1,
                'TransactionId' => $output['IDENTIFICATION_SHORTID'],
                'TransactionGuid' => $output['IDENTIFICATION_UNIQUEID'],
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($output['IDENTIFICATION_UNIQUEID'], UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc' => $output
            );
        } else {
            $this->response = array(
                'ResultState' => false,
                'TransactionId' => UUID::trxid(8),
                'TransactionGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc' => $output
            );
        }



        return $this->response;

    }
    public function Authorize($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        $this->cc_validator->validate($params['cardNumber']);

        $cardInfo = $this->cc_validator->GetCardInfo();

        if ($merchant->live_mode){
            $url = "https://ctpe.net/frontend/payment.prc";
        } else {
            $url = "https://test.ctpe.net/frontend/payment.prc";
        }

        $data = "SECURITY.SENDER=".$merchantProcessor->sender .
            "&TRANSACTION.CHANNEL=". $merchantProcessor->channel .
            "&TRANSACTION.MODE=".($merchant->live_mode ? "LIVE" : "INTEGRATOR_TEST") .
            "&TRANSACTION.RESPONSE=SYNC".
            "&RECURRENCE.MODE=INITIAL".
            "&USER.LOGIN=". $merchantProcessor->login .
            "&IDENTIFICATION.TRANSACTIONID=".UUID::trxid(9).
            "&USER.PWD=". $merchantProcessor->password .
            "&PAYMENT.CODE=CC.PA" .
            "&PRESENTATION.AMOUNT=". $amount .
            "&PRESENTATION.CURRENCY=". get_client_default_currency($merchant->userid).
            "&PRESENTATION.USAGE=". $params['trackingMemberCode'] .
            "&CONTACT.IP=".$_SERVER['REMOTE_ADDR'].
            "&CUST.CNTRY.CD=".$params['countryId'].
            "&CUST.EMAIL=ahmet.gudenoglu@gmail.com".
            "&CUST.HOME.PHONE=656728972".
            "&CUST.IP.ADDR=109.203.109.19".
            "&ADDRESS.COUNTRY=GB".
            "&ACCOUNT.HOLDER=". ((isset($params['cardholder']) && !empty($params['cardholder']) ? $params['cardholder'] : NULL)).
            "&ACCOUNT.NUMBER=".$params['cardNumber'].
            "&ACCOUNT.BRAND=". strtoupper($cardInfo['type']).
            "&ACCOUNT.EXPIRY_MONTH=".$params['cardExpiryMonth'].
            "&ACCOUNT.EXPIRY_YEAR=".$params['cardExpiryYear'].
            "&ACCOUNT.VERIFICATION=".$params['cardCvv'];

        $param = array('http' => array(
            'method' => 'POST',
            'content' => $data
        ));

        $ctx = stream_context_create($param);

        $fp = @fopen($url, 'rb', false, $ctx);

        $response = @stream_get_contents($fp);

        parse_str($response,$output);

        if ($output['PROCESSING_RESULT'] == 'ACK') {
            $this->response = array(
                'ResultState' => 1,
                'TransactionId' => $output['IDENTIFICATION_SHORTID'],
                'TransactionGuid' => $output['IDENTIFICATION_UNIQUEID'],
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($output['IDENTIFICATION_UNIQUEID'], UUID::trxid(8)),
                'Cdc' => $output
            );
        } else {
            $this->response = array(
                'ResultState' => false,
                'TransactionId' => UUID::trxid(8),
                'TransactionGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'Cdc' => $output
            );
        }



        return $this->response;
    }
    public function Capture($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $this->ci->db->where('transactionid',$params['transactionId']);
        $this->ci->db->where('transactionguid',$params['transactionGuid']);
        $transaction = $this->ci->db->get('tbltransactions')->row();

        if (!$transaction){

            $this->_api->processApi($this->response, 2008,true);
        }

        $this->ci->db->where('transactionid',$transaction->id);
        $this->ci->db->where('type','response');
        $webhook = $this->ci->db->get('tblwebhooks')->row();

        $hookdata = (array) json_decode($webhook->hookdata);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        if ($merchant->live_mode){
            $url = "https://ctpe.net/frontend/payment.prc";
        } else {
            $url = "https://test.ctpe.net/frontend/payment.prc";
        }

        $data = "SECURITY.SENDER=".$merchantProcessor->sender .
            "&TRANSACTION.CHANNEL=". $merchantProcessor->channel .
            "&TRANSACTION.MODE=".($merchant->live_mode ? "LIVE" : "INTEGRATOR_TEST") .
            "&TRANSACTION.RESPONSE=SYNC".
            "&USER.LOGIN=". $merchantProcessor->login .
            "&IDENTIFICATION.TRANSACTIONID=".UUID::trxid(9).
            "&USER.PWD=". $merchantProcessor->password .
            "&PAYMENT.CODE=CC.CP" .
            "&IDENTIFICATION.REFERENCEID=".$hookdata['TransactionGuid'].
            "&PRESENTATION.AMOUNT=". $amount .
            "&PRESENTATION.CURRENCY=". get_client_default_currency($merchant->userid).
            "&PRESENTATION.USAGE=". $params['trackingMemberCode'];

        $param = array('http' => array(
            'method' => 'POST',
            'content' => $data
        ));

        $ctx = stream_context_create($param);

        $fp = @fopen($url, 'rb', false, $ctx);

        $response = @stream_get_contents($fp);

        parse_str($response,$output);

        if ($output['PROCESSING_RESULT'] == 'ACK') {
            $this->response = array(
                'ResultState' => 1,
                'TransactionId' => $output['IDENTIFICATION_SHORTID'],
                'TransactionGuid' => $output['IDENTIFICATION_UNIQUEID'],
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($output['IDENTIFICATION_UNIQUEID'], UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc' => $output
            );
        } else {
            $this->response = array(
                'ResultState' => false,
                'TransactionId' => UUID::trxid(8),
                'TransactionGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc' => $output
            );
        }



        return $this->response;

    }
    public function Void($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $this->ci->db->where('transactionid',$params['transactionId']);
        $this->ci->db->where('transactionguid',$params['transactionGuid']);
        $transaction = $this->ci->db->get('tbltransactions')->row();

        if (!$transaction){

            $this->_api->processApi($this->response, 2008,true);
        }

        $this->ci->db->where('transactionid',$transaction->id);
        $this->ci->db->where('type','response');
        $webhook = $this->ci->db->get('tblwebhooks')->row();

        $hookdata = (array) json_decode($webhook->hookdata);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        if ($merchant->live_mode){
            $url = "https://ctpe.net/frontend/payment.prc";
        } else {
            $url = "https://test.ctpe.net/frontend/payment.prc";
        }

        $data = "SECURITY.SENDER=".$merchantProcessor->sender .
            "&TRANSACTION.CHANNEL=". $merchantProcessor->channel .
            "&TRANSACTION.MODE=".($merchant->live_mode ? "LIVE" : "INTEGRATOR_TEST") .
            "&TRANSACTION.RESPONSE=SYNC".
            "&USER.LOGIN=". $merchantProcessor->login .
            "&IDENTIFICATION.TRANSACTIONID=".UUID::trxid(9).
            "&USER.PWD=". $merchantProcessor->password .
            "&PAYMENT.CODE=CC.RV" .
            "&IDENTIFICATION.REFERENCEID=".$hookdata['TransactionGuid'].
            "&PRESENTATION.AMOUNT=". $amount .
            "&PRESENTATION.CURRENCY=". get_client_default_currency($merchant->userid).
            "&PRESENTATION.USAGE=". $params['trackingMemberCode'];

        $param = array('http' => array(
            'method' => 'POST',
            'content' => $data
        ));

        $ctx = stream_context_create($param);

        $fp = @fopen($url, 'rb', false, $ctx);

        $response = @stream_get_contents($fp);

        parse_str($response,$output);

        if ($output['PROCESSING_RESULT'] == 'ACK') {
            $this->response = array(
                'ResultState' => 1,
                'TransactionId' => $output['IDENTIFICATION_SHORTID'],
                'TransactionGuid' => $output['IDENTIFICATION_UNIQUEID'],
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($output['IDENTIFICATION_UNIQUEID'], UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc' => $output
            );
        } else {
            $this->response = array(
                'ResultState' => false,
                'TransactionId' => UUID::trxid(8),
                'TransactionGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'SemiteId' => UUID::trxid(8),
                'SemiteGuid' => UUID::v5($merchantProcessor->sender, UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc' => $output
            );
        }



        return $this->response;

    }

}