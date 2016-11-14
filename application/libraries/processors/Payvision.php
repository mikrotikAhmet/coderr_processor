<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/23/16
 * Time: 1:38 PM
 */
require(PROCESSORS . 'Payvision/test/payvision_autoload.php');

class Payvision
{
    public function __construct(){

        $this->ci =& get_instance();
        $this->response = array();

        $this->ci->load->library('currency');
        $this->ci->load->model('Currencies_model');

        $this->ci->load->library('arraytoxml');

        $this->arraytoxml = new ArrayToXML();
        $this->_api = new Api();

    }

    public function Payment($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $this->ci->db->where('active',1);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        try
        {
            if ($merchant->live_mode){
                $client = new Payvision_Client(Payvision_Client::ENV_LIVE);
            } else {
                $client = new Payvision_Client(Payvision_Client::ENV_TEST);
            }

            $authorize = new Payvision_BasicOperations_Payment;
            $authorize->setMember($merchantProcessor->memberId, $merchantProcessor->memberGuid);
            $authorize->setCountryId(Payvision_Translator::getCountryIdFromIso($params['countryId']));

            /*
             * Cardholder
             */
            if (isset($params['cardNumber']) && !empty($params['cardNumber'])){
                $cardholder = $params['cardNumber'];
            } else {
                $cardholder = NULL;
            }

            /*
             * dbaName and dbaCity
             */
            if (isset($params['dbaName']) && !empty($params['dbaName']) && isset($params['dbaCity']) && !empty($params['dbaCity'])) {
                $authorize->setDynamicDescriptor($params['dbaName'] . '|' . $params['dbaCity']);
            }

            /*
             * Check if this transaction is 3ds
             */
            if (isset($params['xid']) && !empty($params['xid'])){

                $authorize->setXid($params['xid']);
            }

            /*
             * avsAddress and avsZip
             */
            if (isset($params['avsAddress']) && !empty($params['avsAddress']) && isset($params['avsZip']) && !empty($params['avsZip'])) {
                $authorize->setAvsAddress($params['avsAddress'] , $params['avsZip']);
            }

            /* Card Info */
            $authorize->setCardNumberAndHolder($params['cardNumber'],$cardholder);
            $authorize->setCardExpiry($params['cardExpiryMonth'], $params['cardExpiryYear']);
            $authorize->setCardValidationCode($params['cardCvv']);

            $authorize->setAmountAndCurrencyId($amount, Payvision_Translator::getCurrencyIdFromIsoCode(get_client_default_currency($merchant->userid)));

            $authorize->setTrackingMemberCode($params['trackingMemberCode']);

            /*
             * merchantAccountType
             */
            if (isset($params['merchantAccountType']) && !empty($params['merchantAccountType'])) {
                $authorize->setMerchantAccountType($params['merchantAccountType']);
            }

            /*
             * additionalInfo
             */
            if (isset($params['additionalInfo']) && !empty($params['additionalInfo'])) {
                $authorize->setAdditionalInfo($params['additionalInfo']);
            }



            $client->call($authorize);

            $this->response = array(
                'ResultState'=>$authorize->getResultState(),
                'TransactionId'=>$authorize->getResultTransactionId(),
                'TransactionGuid'=>$authorize->getResultTransactionGuid(),
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5($authorize->getResultTransactionGuid(),UUID::trxid(8)),
                'Cdc'=>$authorize->getResultCdcData()
            );
        }
        catch (Payvision_Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;

    }

    public function refund($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $this->ci->db->where('active',1);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $this->ci->db->where('transactionid',$params['transactionId']);
        $this->ci->db->where('transactionguid',$params['transactionGuid']);
        $this->ci->db->where('status',0);
        $transaction = $this->ci->db->get('tbltransactions')->row();

        if (!$transaction){

            $this->_api->processApi($this->response, 2008,true);
        }

        $this->ci->db->where('transactionid',$transaction->id);
        $this->ci->db->where('type','response');
        $webhook = $this->ci->db->get('tblwebhooks')->row();

        $hookdata = (array) json_decode($webhook->hookdata);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));


        try
        {
            if ($merchant->live_mode){
                $client = new Payvision_Client(Payvision_Client::ENV_LIVE);
            } else {
                $client = new Payvision_Client(Payvision_Client::ENV_TEST);
            }


            $operation = new Payvision_BasicOperations_Refund();
            $operation->setMember($merchantProcessor->memberId, $merchantProcessor->memberGuid);

            $operation->setTransactionIdAndGuid($hookdata['TransactionId'], $hookdata['TransactionGuid']);
            $operation->setAmountAndCurrencyId($amount, Payvision_Translator::getCurrencyIdFromIsoCode(get_client_default_currency($merchant->userid)));

            $operation->setTrackingMemberCode($params['trackingMemberCode']);

            $client->call($operation);

            $this->response = array(
                'ResultState'=>$operation->getResultState(),
                'TransactionId'=>$operation->getResultTransactionId(),
                'TransactionGuid'=>$operation->getResultTransactionGuid(),
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5($operation->getResultTransactionGuid(),UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc'=>$operation->getResultCdcData()
            );
        }
        catch (Payvision_Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;
    }

    public function Authorize($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $this->ci->db->where('active',1);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        try
        {
            if ($merchant->live_mode){
                $client = new Payvision_Client(Payvision_Client::ENV_LIVE);
            } else {
                $client = new Payvision_Client(Payvision_Client::ENV_TEST);
            }

            $authorize = new Payvision_BasicOperations_Authorize();
            $authorize->setMember($merchantProcessor->memberId, $merchantProcessor->memberGuid);
            $authorize->setCountryId(Payvision_Translator::getCountryIdFromIso($params['countryId']));

            /*
             * Cardholder
             */
            if (isset($params['cardNumber']) && !empty($params['cardNumber'])){
                $cardholder = $params['cardNumber'];
            } else {
                $cardholder = NULL;
            }

            /*
             * dbaName and dbaCity
             */
            if (isset($params['dbaName']) && !empty($params['dbaName']) && isset($params['dbaCity']) && !empty($params['dbaCity'])) {
                $authorize->setDynamicDescriptor($params['dbaName'] . '|' . $params['dbaCity']);
            }

            /*
             * Check if this transaction is 3ds
             */
            if (isset($params['xid']) && !empty($params['xid'])){

                $authorize->setXid($params['xid']);
            }

            /*
             * avsAddress and avsZip
             */
            if (isset($params['avsAddress']) && !empty($params['avsAddress']) && isset($params['avsZip']) && !empty($params['avsZip'])) {
                $authorize->setAvsAddress($params['avsAddress'] , $params['avsZip']);
            }

            /* Card Info */
            $authorize->setCardNumberAndHolder($params['cardNumber'],$cardholder);
            $authorize->setCardExpiry($params['cardExpiryMonth'], $params['cardExpiryYear']);
            $authorize->setCardValidationCode($params['cardCvv']);

            $authorize->setAmountAndCurrencyId($amount, Payvision_Translator::getCurrencyIdFromIsoCode(get_client_default_currency($merchant->userid)));

            $authorize->setTrackingMemberCode($params['trackingMemberCode']);

            /*
             * merchantAccountType
             */
            if (isset($params['merchantAccountType']) && !empty($params['merchantAccountType'])) {
                $authorize->setMerchantAccountType($params['merchantAccountType']);
            }

            /*
             * additionalInfo
             */
            if (isset($params['additionalInfo']) && !empty($params['additionalInfo'])) {
                $authorize->setAdditionalInfo($params['additionalInfo']);
            }



            $client->call($authorize);

            $this->response = array(
                'ResultState'=>$authorize->getResultState(),
                'TransactionId'=>$authorize->getResultTransactionId(),
                'TransactionGuid'=>$authorize->getResultTransactionGuid(),
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5($authorize->getResultTransactionGuid(),UUID::trxid(8)),
                'Cdc'=>$authorize->getResultCdcData()
            );
        }
        catch (Payvision_Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;

    }

    public function capture($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $this->ci->db->where('active',1);
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


        try
        {
            if ($merchant->live_mode){
                $client = new Payvision_Client(Payvision_Client::ENV_LIVE);
            } else {
                $client = new Payvision_Client(Payvision_Client::ENV_TEST);
            }


            $operation = new Payvision_BasicOperations_Capture();
            $operation->setMember($merchantProcessor->memberId, $merchantProcessor->memberGuid);
            $operation->setAmountAndCurrencyId($amount, Payvision_Translator::getCurrencyIdFromIsoCode(get_client_default_currency($merchant->userid)));

            $operation->setTransactionIdAndGuid($hookdata['TransactionId'], $hookdata['TransactionGuid']);

            $operation->setTrackingMemberCode($params['trackingMemberCode']);

            $client->call($operation);

            $this->response = array(
                'ResultState'=>$operation->getResultState(),
                'TransactionId'=>$operation->getResultTransactionId(),
                'TransactionGuid'=>$operation->getResultTransactionGuid(),
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5($operation->getResultTransactionGuid(),UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc'=>$operation->getResultCdcData()
            );
        }
        catch (Payvision_Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;
    }

    public function void($params,$merchant){

        $Currency = new Currency();

        $this->ci->db->where('merchantid',$merchant->id);
        $this->ci->db->where('active',1);
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


        try
        {
            if ($merchant->live_mode){
                $client = new Payvision_Client(Payvision_Client::ENV_LIVE);
            } else {
                $client = new Payvision_Client(Payvision_Client::ENV_TEST);
            }


            $operation = new Payvision_BasicOperations_Void();
            $operation->setMember($merchantProcessor->memberId, $merchantProcessor->memberGuid);

            $operation->setTransactionIdAndGuid($hookdata['TransactionId'], $hookdata['TransactionGuid']);
            $operation->setAmountAndCurrencyId($amount, Payvision_Translator::getCurrencyIdFromIsoCode(get_client_default_currency($merchant->userid)));

            $operation->setTrackingMemberCode($params['trackingMemberCode']);

            $client->call($operation);

            $this->response = array(
                'ResultState'=>$operation->getResultState(),
                'TransactionId'=>$operation->getResultTransactionId(),
                'TransactionGuid'=>$operation->getResultTransactionGuid(),
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5($operation->getResultTransactionGuid(),UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc'=>$operation->getResultCdcData()
            );
        }
        catch (Payvision_Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;
    }
}