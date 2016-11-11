<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/24/16
 * Time: 6:22 PM
 */

function addTransaction($params,$response,$merchant,$method,$processor){

    $CI = & get_instance();

    $CI->load->library('currency');
    $CI->load->model('Currencies_model');

    $Currency = new Currency();

    $cardValidator = new CreditCardValidator();

    $data = array(
        'merchantid'=>$merchant->id,
        'transactionid'=>$response['SemiteId'],
        'transactionguid'=>$response['SemiteGuid'],
        'object'=>$processor,
        'amount'=>money_format("%!^i",$params['amount']),
        'trackingcode'=>$params['trackingMemberCode'],
        'currency'=>$params['currencyId'],
        'interchange'=>money_format("%!^i",$Currency->getValue(get_client_default_currency($merchant->userid))),
        'settlement'=>money_format("%!^i",$Currency->convert(money_format("%!^i",$params['amount']),$params['currencyId'],get_client_default_currency($merchant->userid))),
        'method'=>ucfirst($method),
    );

    if ($method == 'payment'){

        $cardValidator->Validate($params['cardNumber'],(isset($params['cardholder']) && !empty($params['cardholder']) ? $params['cardholder'] : null));

        $cardInfo = $cardValidator->GetCardInfo();

        $data['card']=$cardInfo['substring'];
        $data['type']=$cardInfo['type'];
        $data['dbaname'] = (isset($params['dbaName']) && !empty($params['dbaName']) ? $params['dbaName'] : null);
        $data['dbacity'] = (isset($params['dbaCity']) && !empty($params['dbaCity']) ? $params['dbaCity'] : null);
        $data['avsaddress'] = (isset($params['avsAddress']) && !empty($params['avsAddress']) ? $params['avsAddress'] : null);
        $data['avszip'] = (isset($params['avsZip']) && !empty($params['avsZip']) ? $params['avsZip'] : null);
        $data['additionalinfo'] = (isset($params['additionalInfo'])) && !empty($params['additionalInfo'] ? json_encode($params['additionalInfo']) : null);
        $data['payment'] = 1;
    }

    if ($method == 'authorize'){

        $cardValidator->Validate($params['cardNumber'],(isset($params['cardholder']) && !empty($params['cardholder']) ? $params['cardholder'] : null));

        $cardInfo = $cardValidator->GetCardInfo();

        $data['card']=$cardInfo['substring'];
        $data['type']=$cardInfo['type'];
        $data['dbaname'] = (isset($params['dbaName']) && !empty($params['dbaName']) ? $params['dbaName'] : null);
        $data['dbacity'] = (isset($params['dbaCity']) && !empty($params['dbaCity']) ? $params['dbaCity'] : null);
        $data['avsaddress'] = (isset($params['avsAddress']) && !empty($params['avsAddress']) ? $params['avsAddress'] : null);
        $data['avszip'] = (isset($params['avsZip']) && !empty($params['avsZip']) ? $params['avsZip'] : null);
        $data['additionalinfo'] = (isset($params['additionalInfo'])) && !empty($params['additionalInfo'] ? json_encode($params['additionalInfo']) : null);
        $data['authorized'] = 1;
    }

    if ($method == 'refund'){
        $data['referenceid']=$response['ReferenceId'];
        $data['refunded'] = 1;

        $sql = "UPDATE tbltransactions SET refunded = '1' WHERE id = '".(int) $response['ReferenceId']."'";
        $CI->db->query($sql);
    }

    if ($method == 'capture'){
        $data['referenceid']=$response['ReferenceId'];
        $data['captured'] = 1;

        $sql = "UPDATE tbltransactions SET captured = '1' WHERE id = '".(int) $response['ReferenceId']."'";
        $CI->db->query($sql);
    }

    if ($method == 'void'){
        $data['referenceid']=$response['ReferenceId'];
        $data['voided'] = 1;

        $sql = "UPDATE tbltransactions SET voided = '1' WHERE id = '".(int) $response['ReferenceId']."'";
        $CI->db->query($sql);
    }

    if (isset($params['xid']) && !empty($params['xid'])){
        $data['enrolled'] = 1;
    }

    $data['date_added'] = date('Y-m-d H:i:s');
    $data['status'] = (($response['ResultState'] == 0) ? 1 : 0);

    $CI->db->insert('tbltransactions',$data);
    $transactionId = $CI->db->insert_id();

    // Add webhooks (request/response)
    addWebhook($params,$transactionId,$merchant,$response);

}

function get_client_default_currency($userid){

    $CI =& get_instance();
    $CI->db->where('userid',$userid);
    $client = $CI->db->get('tblclients')->row();

    $CI->db->where('id',$client->default_currency);
    $currencies = $CI->db->get('tblcurrencies')->row();

    return $currencies->name;

}

function addWebhook($params,$transactionId,$merchant,$response){

    $CI = & get_instance();

    // Add webhook request
    $webhook = array(
        'transactionid'=>$transactionId,
        'merchantid'=>$merchant->id,
        'type'=>'request',
        'hookdata'=>json_encode($params)
    );

    $CI->db->insert('tblwebhooks',$webhook);

    // Add webhook response
    $webhook = array(
        'transactionid'=>$transactionId,
        'merchantid'=>$merchant->id,
        'type'=>'response',
        'hookdata'=>json_encode($response)
    );

    $CI->db->insert('tblwebhooks',$webhook);
}