<?php
/**
 * Created by PhpStorm.
 * User: smt2016
 * Date: 25.9.16.
 * Time: 13.48
 */

require_once APPPATH."libraries/mpi/library/Endeavour_client.php";
require_once APPPATH."helpers/semite_translator_helper.php";

class Endeavour {

    private $_mid = 'SemiteTest';
    private $_url = 'https://www.3dsecurempi.com/TDS/MPIVerifyEnrollmentHTML?';
    private $_parseurl = 'https://www.3dsecureMPI.com/TDS/MPIDecodeParesHTML?';

    public function MPILookup($data){

        /********************************************************************************/
        /*                                                                              */
        /*Using the local variables and constants, build the Endeavour message using the*/
        /*Endeavour Thin Client.                                                        */
        /*                                                                              */
        /********************************************************************************/

        $endeavourClient = new EndeavourClient();

        $amount = $data['amount'] * 100;
        $expiry = substr($data['cardExpiryYear'], -2).$data['cardExpiryMonth'];

        $endeavourClient->add('mid',$this->getMID());
        $endeavourClient->add('name',(isset($data['cardholder'])) ? $data['cardholder'] : null);
        $endeavourClient->add('pan',$data['cardNumber']);
        $endeavourClient->add('expiry',$expiry);
        $endeavourClient->add('currency',Translator::getCurrencyIdFromIsoCode($data['currencyId']));
        $endeavourClient->add('amount',$amount);
        $endeavourClient->add('desc',$data['trackingMemberCode']);
        $endeavourClient->add('useragent','Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US)');
        $endeavourClient->add('accept','en-us');
        $endeavourClient->add('trackid',date('YmdHsi'));

        /**********************************************************************************/
        /*                                                                                */
        /*Send the XML Msg to the MAPS Server, the Response is the CentinelResponse Object*/
        /*                                                                                */
        /**********************************************************************************/

        $response = $endeavourClient->sendHttp($this->_url);

        parse_str($response, $get_array);

        if ($get_array['avr'] == 'Y') {
            return (str_replace(' ', '+', $get_array));
        } else {
            return $get_array;
        }

    }

    public function setMID($mid){

        $this->_mid = $mid;
    }

    public function getMID(){

        return $this->_mid;
    }

    public function MPIAuthenticate($data){

        $endeavourClient = new EndeavourClient();

        $endeavourClient->add('pares',$data['PaRes']);

        $response = $endeavourClient->sendHttp($this->_parseurl);

        parse_str($response, $get_array);

        return ($get_array);
    }

    public function getRedirectHtmlForm ($action,$pareq,$return_url, $merchant_data = NULL)
    {
        $html =
            '<html>' . PHP_EOL .
            '	<head>' . PHP_EOL .
            '		<title>Semite Payment System 3D-Secure MPI</title>' . PHP_EOL .
            '	</head>' . PHP_EOL .
            '	<script type="text/javascript">' . PHP_EOL .
            '		function OnLoadEvent()' . PHP_EOL .
            '		{' . PHP_EOL .
            '			// Make the form post as soon as it has been loaded.' . PHP_EOL .
            '			document.forms[0].submit();' . PHP_EOL .
            '		}' . PHP_EOL .
            '	</script>' . PHP_EOL .
            '	<body onload="OnLoadEvent();">' . PHP_EOL .
            '		<p>' . PHP_EOL .
            '			If your browser does not start loading the page, press the button below.' . PHP_EOL .
            '			You will be sent back to this site after you authorize the transaction.' . PHP_EOL .
            '		</p>' . PHP_EOL .
            '		<form name="Payer" id="Payer" method="post" action="' . $action . '">' . PHP_EOL .
            '		<input type="hidden" name="PaReq" value="' . $pareq . '" />' . PHP_EOL .
            '		<input type="hidden" name="TermUrl" value="' . $return_url . '" />' . PHP_EOL .
            '		<input type="hidden" name="MD" value="' . $merchant_data . '" />' . PHP_EOL .
            '		</form>' . PHP_EOL .
            '	</body>' . PHP_EOL .
            '</html>';

        return $html;
    }


} 