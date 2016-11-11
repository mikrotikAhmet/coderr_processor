<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/22/16
 * Time: 3:59 PM
 */
defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions

class Service extends CI_Controller {

    protected $_api;

    private $_isOk = false;
    private $_response = array();

    function __construct()
    {
        // Construct the parent class
        parent::__construct();

        $this->load->library('arraytoxml');

        $this->arraytoxml = new ArrayToXML();
        $this->_api = new Api();
        $this->cardValidator = new CreditCardValidator();
    }

    public function index(){

        // grab the request
        $request = trim(file_get_contents('php://input'));

        // find out if the request is valid XML
        $xml = @simplexml_load_string($request);


        // if it is not valid XML...
        if (!$xml) {

            $this->_api->processApi(array(), 400,true);

        } else {

            // Make an array out of the XML
            $params = $this->arraytoxml->toArray($xml);

        }

        // Authenticate merchant
        $sql = "SELECT * FROM tblmerchants WHERE api_id = '".$params['memberId']."' AND secret_key = '".$params['memberGuid']."'";

        $merchant = $this->db->query($sql)->row();

        if (!$merchant){

            $this->_api->processApi($this->_response, 2002,true);
        }

        // Get merchant processor object
        $sql = "SELECT * FROM tblmerchantprocessors mp LEFT JOIN tblprocessors p ON(mp.processorid = p.id) WHERE merchantid = '".(int) $merchant->id."'";

        $merchantProcessor = $this->db->query($sql)->row();

        if (!$merchantProcessor->active){

            $this->_api->processApi($this->_response, 2002,true);
        }

        if ($this->input->method(TRUE) === "POST") {

            $this->_isOk = true;


            $this->load->library('processors/'.$merchantProcessor->object,$merchantProcessor->object);

            unset($params['memberId']);
            unset($params['memberGuid']);

            // Check if method is exist
            if (!method_exists($this,$params['method'])){

                $this->_api->processApi(array(), 406,true);
            }

            $this->{$params['method']}($params,$merchant,$merchantProcessor->object);

        } else {

            $this->_api->processApi(array(), 400,true);
        }

    }

    public function payment($params,$merchant,$processor){


        $this->_response['Object'] = $params;

        if ($this->_isOk === true){

            $this->validatePayment($params,$merchant);

        if (!shapeSpace_check_https(0)){

            $this->_api->processApi($this->_response, 2001,true);
        }

            $response = $this->$processor->payment($params,$merchant);

            addTransaction($params,$response,$merchant,'payment',$processor);

            $this->_api->processApi($this->_response, 0,false,$response);
        }

        $this->_api->processApi($this->_response, 500,true);
    }

    public function refund($params,$merchant,$processor){


        $this->_response['Object'] = $params;

        if ($this->_isOk === true){

            if (!shapeSpace_check_https(0)){

                $this->_api->processApi($this->_response, 2001,true);
            }

            $response = $this->$processor->refund($params,$merchant);

            addTransaction($params,$response,$merchant,'refund',$processor);

            $this->_api->processApi($this->_response, 0,false,$response);
        }

        $this->_api->processApi($this->_response, 500,true);
    }

    public function authorize($params,$merchant,$processor){


        $this->_response['Object'] = $params;

        if ($this->_isOk === true){

            $this->validatePayment($params,$merchant);

            if (!shapeSpace_check_https(0)){

                $this->_api->processApi($this->_response, 2001,true);
            }

            $response = $this->$processor->authorize($params,$merchant);

            addTransaction($params,$response,$merchant,'authorize',$processor);

            $this->_api->processApi($this->_response, 0,false,$response);
        }

        $this->_api->processApi($this->_response, 500,true);
    }


    public function capture($params,$merchant,$processor){


        $this->_response['Object'] = $params;

        if ($this->_isOk === true){

            if (!shapeSpace_check_https(0)){

                $this->_api->processApi($this->_response, 2001,true);
            }

            $response = $this->$processor->capture($params,$merchant);

            addTransaction($params,$response,$merchant,'capture',$processor);

            $this->_api->processApi($this->_response, 0,false,$response);
        }

        $this->_api->processApi($this->_response, 500,true);
    }

    public function void($params,$merchant,$processor){


        $this->_response['Object'] = $params;

        if ($this->_isOk === true){

            if (!shapeSpace_check_https(0)){

                $this->_api->processApi($this->_response, 2001,true);
            }

            $response = $this->$processor->void($params,$merchant);

            addTransaction($params,$response,$merchant,'void',$processor);

            $this->_api->processApi($this->_response, 0,false,$response);
        }

        $this->_api->processApi($this->_response, 500,true);
    }



    protected function validatePayment($params,$merchant){

        $this->db->where('merchantid',$merchant->id);
        $processorData = $this->db->get('tblmerchantprocessors')->row();

        $merchantProcessorData = json_decode($processorData->processor_data);

        // Check allowed transaction limit
        if ($params['amount'] > $processorData->transactionLimit){

            $this->_api->processApi(array(), 2003,true);

        }

        // Check allowed total processing limit
        $sql = "SELECT DATE_FORMAT(date_added, '%m-%Y') AS month,SUM(settlement) AS total FROM tbltransactions WHERE merchantid = '".(int) $merchant->id."' GROUP BY DATE_FORMAT(date_added, '%m-%Y') ";
        $totalSettlement = $this->db->query($sql)->row();

        if ($totalSettlement) {
            $totalSettlementAmount = $totalSettlement->total + $params['amount'];

            if ($totalSettlementAmount > $processorData->processingLimit) {
                $this->_api->processApi(array(), 2004, true);
            }
        }

        if ( strval($params['countryId']) == strval(intval($params['countryId'])) ) {
            $this->_api->processApi(array(), 2010, true);
        }

        if ( strval($params['currencyId']) == strval(intval($params['currencyId'])) ) {
            $this->_api->processApi(array(), 2009, true);
        }

        // Check allowed card types
        $this->cardValidator->Validate($params['cardNumber']);
        $cardInfo = $this->cardValidator->GetCardInfo();


        if (!array_key_exists($cardInfo['type'],(array) $merchantProcessorData)){

            $this->_api->processApi(array(), 2005,true);
        }

        // Check if amount is valid
        if (!preg_match('/^[0-9]+(?:\.[0-9]{0,2})?$/', $params['amount'])){

            $this->_api->processApi(array(), 2006,true);
        }

        // Check if card number is valid
        if ($cardInfo['status'] !="valid"){
            $this->_api->processApi(array(), 2007,true);
        }
    }

}