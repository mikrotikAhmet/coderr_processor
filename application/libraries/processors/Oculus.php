<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 11/9/16
 * Time: 1:00 PM
 */

require(PROCESSORS . 'Payvision/test/payvision_autoload.php');

class Oculus
{
    public function __construct()
    {

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
        $this->ci->db->where('active',1);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $amount = money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid)));

        if ($merchant->live_mode){

            $wsdl = 'https://prod.oculusgateway.ge/api/api.asmx?WSDL';
        } else {
            $wsdl = 'https://test.oculusgateway.ge/api/api.asmx?WSDL';
        }

        $trace = true;
        $exceptions = false;

        $namespace = 'https://MyCardStorage.com/';

        //Body of the Soap Header.
        $headerbody = array(
            'UserName' => $merchantProcessor->gateway_username,
            'Password' => $merchantProcessor->gateway_password
        );

        $this->cc_validator->validate($params['cardNumber']);

        $cardInfo = $this->cc_validator->GetCardInfo();

        //Create Soap Header.
        $header = new SOAPHeader($namespace, 'AuthHeader', $headerbody);

        $xml_array['creditCardSale'] = array(
            'ServiceSecurity'=>array(
                'ServiceUserName'=>$merchantProcessor->gateway_service_username,
                'ServicePassword'=>$merchantProcessor->gateway_service_password,
                'MCSAccountID'=>$merchantProcessor->gateway_account_id,
            ),
            'TokenData'=>array(
                'TokenType'=>'0',
                'CardNumber'=>$params['cardNumber'],
                'CardType'=>Payvision_Translator::getCardIdByIssuer($cardInfo['type']),
                'ExpirationMonth'=>$params['cardExpiryMonth'],
                'ExpirationYear'=>substr($params['cardExpiryYear'], -2),
                'CVV'=>$params['cardCvv'],
                'XID'=>(isset($params['xid']) && !empty($params['xid']) ? $params['xid'] : null),
                'CAVV'=>(isset($params['cavv']) && !empty($params['cavv']) ? $params['cavv'] : null),
            ),
            'TransactionData'=>array(
                'Amount'=>$amount,
                'MCSTransactionID'=>'0',
                'GatewayID'=>'3',
                'CountryCode'=>Payvision_Translator::getCountryIdFromIso($params['countryId'],true),
                'CurrencyCode'=>Payvision_Translator::getCurrencyIdFromIsoCode($params['currencyId']),
                'PurchaseCardTaxAmount'=>'0',
            )
        );

        try
        {
            $client = new SoapClient($wsdl, array('trace' => $trace, 'exceptions' => $exceptions));

            //set the Headers of Soap Client.
            $client->__setSoapHeaders($header);
            $response = $client->CreditSale_Soap($xml_array);


            /* Converty CB Response*/
            $code = $response->CreditSale_SoapResult->Result->ResultCode;

            $this->response = array(
                'ResultState'=>($code ? false : true),
                'TransactionId'=>$response->CreditSale_SoapResult->MCSTransactionID,
                'TransactionGuid'=>$response->CreditSale_SoapResult->ProcessorApprovalCode,
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5('1546058f-5a25-4334-85ae-e68f2a44bbaf',UUID::trxid(8)),
                'Cdc'=>(array) $response->CreditSale_SoapResult
            );

        }

        catch (Exception $e)
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

            $wsdl = 'https://prod.oculusgateway.ge/api/api.asmx?WSDL';
        } else {
            $wsdl = 'https://test.oculusgateway.ge/api/api.asmx?WSDL';
        }

        $trace = true;
        $exceptions = false;

        $namespace = 'https://MyCardStorage.com/';

        //Body of the Soap Header.
        $headerbody = array(
            'UserName' => $merchantProcessor->gateway_username,
            'Password' => $merchantProcessor->gateway_password
        );

        //Create Soap Header.
        $header = new SOAPHeader($namespace, 'AuthHeader', $headerbody);

        $xml_array['creditCardCredit'] = array(
            'ServiceSecurity'=>array(
                'ServiceUserName'=>$merchantProcessor->gateway_service_username,
                'ServicePassword'=>$merchantProcessor->gateway_service_password,
                'MCSAccountID'=>$merchantProcessor->gateway_account_id,
            ),
            'TransactionData'=>array(
                'Amount'=>$amount,
                'MCSTransactionID'=>$hookdata['TransactionId'],
                'GatewayID'=>'3',
                'CountryCode'=>Payvision_Translator::getCountryIdFromIso($params['countryId'],true),
                'CurrencyCode'=>Payvision_Translator::getCurrencyIdFromIsoCode($params['currencyId']),
                'PurchaseCardTaxAmount'=>'0',
            )
        );

        try
        {
            $client = new SoapClient($wsdl, array('trace' => $trace, 'exceptions' => $exceptions));

            //set the Headers of Soap Client.
            $client->__setSoapHeaders($header);

            $response = $client->CreditCredit_Soap($xml_array);

            /* Converty CB Response*/
            $code = $response->CreditCredit_SoapResult->Result->ResultCode;

            $this->response = array(
                'ResultState'=>($code ? false : true),
                'TransactionId'=>$response->CreditCredit_SoapResult->MCSTransactionID,
                'TransactionGuid'=>$response->CreditCredit_SoapResult->ProcessorApprovalCode,
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5('1546058f-5a25-4334-85ae-e68f2a44bbaf',UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc'=>(array) $response->CreditCredit_SoapResult
            );

        }

        catch (Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;
    }

    public function Authorize($params,$merchant)
    {

        $Currency = new Currency();

        $this->ci->db->where('merchantid', $merchant->id);
        $this->ci->db->where('active',1);
        $merchantProcessorData = $this->ci->db->get('tblmerchantprocessors')->row();

        $merchantProcessor = json_decode($merchantProcessorData->processor_data);

        $amount = money_format("%!^i", $Currency->convert(money_format("%!^i", $params['amount']), $params['currencyId'], get_client_default_currency($merchant->userid)));

        if ($merchant->live_mode){

            $wsdl = 'https://prod.oculusgateway.ge/api/api.asmx?WSDL';
        } else {
            $wsdl = 'https://test.oculusgateway.ge/api/api.asmx?WSDL';
        }

        $trace = true;
        $exceptions = false;

        $namespace = 'https://MyCardStorage.com/';

        //Body of the Soap Header.
        $headerbody = array(
            'UserName' => $merchantProcessor->gateway_username,
            'Password' => $merchantProcessor->gateway_password
        );

        $this->cc_validator->validate($params['cardNumber']);

        $cardInfo = $this->cc_validator->GetCardInfo();

        //Create Soap Header.
        $header = new SOAPHeader($namespace, 'AuthHeader', $headerbody);

        $xml_array['creditCardAuth'] = array(
            'ServiceSecurity'=>array(
                'ServiceUserName'=>$merchantProcessor->gateway_service_username,
                'ServicePassword'=>$merchantProcessor->gateway_service_password,
                'MCSAccountID'=>$merchantProcessor->gateway_account_id,
            ),
            'TokenData'=>array(
                'TokenType'=>'0',
                'CardNumber'=>$params['cardNumber'],
                'CardType'=>Payvision_Translator::getCardIdByIssuer($cardInfo['type']),
                'ExpirationMonth'=>$params['cardExpiryMonth'],
                'ExpirationYear'=>substr($params['cardExpiryYear'], -2),
                'CVV'=>$params['cardCvv'],
                'XID'=>(isset($params['xid']) && !empty($params['xid']) ? $params['xid'] : null),
                'CAVV'=>(isset($params['cavv']) && !empty($params['cavv']) ? $params['cavv'] : null),
            ),
            'TransactionData'=>array(
                'Amount'=>$amount,
                'MCSTransactionID'=>'0',
                'GatewayID'=>'3',
                'CountryCode'=>Payvision_Translator::getCountryIdFromIso($params['countryId'],true),
                'CurrencyCode'=>Payvision_Translator::getCurrencyIdFromIsoCode($params['currencyId']),
                'PurchaseCardTaxAmount'=>'0',
            )
        );

        try
        {
            $client = new SoapClient($wsdl, array('trace' => $trace, 'exceptions' => $exceptions));

            //set the Headers of Soap Client.
            $client->__setSoapHeaders($header);
            $response = $client->CreditAuth_Soap($xml_array);


            /* Converty CB Response*/
            $code = $response->CreditAuth_SoapResult->Result->ResultCode;

            $this->response = array(
                'ResultState'=>($code ? false : true),
                'TransactionId'=>$response->CreditAuth_SoapResult->MCSTransactionID,
                'TransactionGuid'=>$response->CreditAuth_SoapResult->ProcessorApprovalCode,
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5('1546058f-5a25-4334-85ae-e68f2a44bbaf',UUID::trxid(8)),
                'Cdc'=>(array) $response->CreditAuth_SoapResult
            );

        }

        catch (Exception $e)
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

        if ($merchant->live_mode){

            $wsdl = 'https://prod.oculusgateway.ge/api/api.asmx?WSDL';
        } else {
            $wsdl = 'https://test.oculusgateway.ge/api/api.asmx?WSDL';
        }

        $trace = true;
        $exceptions = false;

        $namespace = 'https://MyCardStorage.com/';

        //Body of the Soap Header.
        $headerbody = array(
            'UserName' => $merchantProcessor->gateway_username,
            'Password' => $merchantProcessor->gateway_password
        );

        //Create Soap Header.
        $header = new SOAPHeader($namespace, 'AuthHeader', $headerbody);

        $xml_array['creditCardCapture'] = array(
            'ServiceSecurity'=>array(
                'ServiceUserName'=>$merchantProcessor->gateway_service_username,
                'ServicePassword'=>$merchantProcessor->gateway_service_password,
                'MCSAccountID'=>$merchantProcessor->gateway_account_id,
            ),
            'TransactionData'=>array(
                'Amount'=>$amount,
                'MCSTransactionID'=>$hookdata['TransactionId'],
                'GatewayID'=>'3',
                'CountryCode'=>Payvision_Translator::getCountryIdFromIso($params['countryId'],true),
                'CurrencyCode'=>Payvision_Translator::getCurrencyIdFromIsoCode($params['currencyId']),
                'PurchaseCardTaxAmount'=>'0',
            )
        );

        try
        {
            $client = new SoapClient($wsdl, array('trace' => $trace, 'exceptions' => $exceptions));

            //set the Headers of Soap Client.
            $client->__setSoapHeaders($header);

            $response = $client->CreditCapture_Soap($xml_array);

            /* Converty CB Response*/
            $code = $response->CreditCapture_SoapResult->Result->ResultCode;

            $this->response = array(
                'ResultState'=>($code ? false : true),
                'TransactionId'=>$response->CreditCapture_SoapResult->MCSTransactionID,
                'TransactionGuid'=>$response->CreditCapture_SoapResult->ProcessorApprovalCode,
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5('1546058f-5a25-4334-85ae-e68f2a44bbaf',UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc'=>(array) $response->CreditCapture_SoapResult
            );

        }

        catch (Exception $e)
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

        if ($merchant->live_mode){

            $wsdl = 'https://prod.oculusgateway.ge/api/api.asmx?WSDL';
        } else {
            $wsdl = 'https://test.oculusgateway.ge/api/api.asmx?WSDL';
        }

        $trace = true;
        $exceptions = false;

        $namespace = 'https://MyCardStorage.com/';

        //Body of the Soap Header.
        $headerbody = array(
            'UserName' => $merchantProcessor->gateway_username,
            'Password' => $merchantProcessor->gateway_password
        );

        //Create Soap Header.
        $header = new SOAPHeader($namespace, 'AuthHeader', $headerbody);

        $xml_array['creditCardVoid'] = array(
            'ServiceSecurity'=>array(
                'ServiceUserName'=>$merchantProcessor->gateway_service_username,
                'ServicePassword'=>$merchantProcessor->gateway_service_password,
                'MCSAccountID'=>$merchantProcessor->gateway_account_id,
            ),
            'TransactionData'=>array(
                'Amount'=>$amount,
                'MCSTransactionID'=>$hookdata['TransactionId'],
                'GatewayID'=>'3',
                'CountryCode'=>Payvision_Translator::getCountryIdFromIso($params['countryId'],true),
                'CurrencyCode'=>Payvision_Translator::getCurrencyIdFromIsoCode($params['currencyId']),
                'PurchaseCardTaxAmount'=>'0',
            )
        );

        try
        {
            $client = new SoapClient($wsdl, array('trace' => $trace, 'exceptions' => $exceptions));

            //set the Headers of Soap Client.
            $client->__setSoapHeaders($header);

            $response = $client->CreditVoid_Soap($xml_array);

            /* Converty CB Response*/
            $code = $response->CreditVoid_SoapResult->Result->ResultCode;

            $this->response = array(
                'ResultState'=>($code ? false : true),
                'TransactionId'=>$response->CreditVoid_SoapResult->MCSTransactionID,
                'TransactionGuid'=>$response->CreditVoid_SoapResult->ProcessorApprovalCode,
                'SemiteId'=>UUID::trxid(8),
                'SemiteGuid'=>UUID::v5('1546058f-5a25-4334-85ae-e68f2a44bbaf',UUID::trxid(8)),
                'ReferenceId'=>$transaction->id,
                'Cdc'=>(array) $response->CreditVoid_SoapResult
            );

        }

        catch (Exception $e)
        {
            $this->response = array(
                'TransactionId'=>'0000000',
                'TransactionGuid'=>'00000000-0000-0000-000000000000'
            );
        }

        return $this->response;
    }
}