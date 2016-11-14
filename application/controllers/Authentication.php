<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Authentication extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->library('mpi/endeavour');
    }

    public function index(){

        parse_str($_POST['MD'],$MD);

        $this->db->where('id', $MD['merchant_id']);
        $merchant = $this->db->get('tblmerchants')->row();

        // Get merchant processor object
        $sql = "SELECT * FROM tblmerchantprocessors mp LEFT JOIN tblprocessors p ON(mp.processorid = p.id) WHERE mp.merchantid = '".(int) $merchant->id."' AND mp.active = '1'";

        $merchantProcessor = $this->db->query($sql)->row();

        $processorData = json_decode($merchantProcessor->processor_data);


        $Endeavour = new Endeavour();

        if ($merchant->live_mode && $processorData->secure && !empty($processorData->secure_id)) {

            $Endeavour->setMID($processorData->secure_id);
        }

        $authResponse = $Endeavour->MPIAuthenticate($_POST);


        if ($authResponse['status'] == 'Y'){

            if ($merchant->live_mode) {
                $url = _LIVE_URL;
            } else {
                $url = _TEST_URL;
            }

            $post_string = '<?xml version="1.0" encoding="UTF-8"?>
<request>
  <memberId>'.$merchant->api_id.'</memberId>
  <memberGuid>'.$merchant->secret_key.'</memberGuid>
  <method>'.$MD['method'].'</method>
  <countryId>'.$MD['countryId'].'</countryId>
  <amount>'.$MD['amount'].'</amount>
  <currencyId>'.$MD['currencyId'].'</currencyId>
  <trackingMemberCode>'.$MD['trackingMemberCode'].'</trackingMemberCode>
  <cardNumber>'.$MD['creditCard']['cardNumber'].'</cardNumber>
  <cardholder>'.(isset($MD['creditCard']['cardholder']) ? $MD['creditCard']['cardholder'] : null).'</cardholder>
  <cardExpiryMonth>'.$MD['creditCard']['cardExpiryMonth'].'</cardExpiryMonth>
  <cardExpiryYear>'.$MD['creditCard']['cardExpiryYear'].'</cardExpiryYear>
  <cardCvv>'.$MD['creditCard']['cardCvv'].'</cardCvv>
  <merchantAccountType>1</merchantAccountType>
  <xid>'.$authResponse['xid'].'</xid>
  <dbaName></dbaName>
  <dbaCity></dbaCity>
  <avsAddress></avsAddress>
  <avsZip></avsZip>
  <additionalInfo>'.json_encode($MD['additionalInfo']).'</additionalInfo>
</request>';

            $postfields = $post_string;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);


            $res = curl_exec($ch);

            if(curl_errno($ch))
            {
                curl_error($ch);
            }
            else
            {
                curl_close($ch);
                $response = json_decode($res);
            }

            redirect('http://crm.macropay.io/terminal/result/'.$response->TransactionId.'/1');
        } else {

            redirect('http://crm.macropay.io/terminal/failedauthentication/1');
        }
    }
}