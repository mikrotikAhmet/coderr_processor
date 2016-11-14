<?php

class EndeavourClient {

    var $request ;
    var $response ;
    var $parser;

    /////////////////////////////////////////////////////////////////////////////////////////////
    // Function Add(name, value)
    //
    // Add name/value pairs to the Endeavour request collection.
    /////////////////////////////////////////////////////////////////////////////////////////////


    function add($name, $value) {
        $this->request[$name] = $this->escapeXML($value);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    // Function getValue(name)
    //
    // Retrieve a specific value for the give name within the Centinel response collection.
    /////////////////////////////////////////////////////////////////////////////////////////////


    function getValue($name) {
        return $this->response[$name];
    }


    /////////////////////////////////////////////////////////////////////////////////////////////
    // Function getRequestXml(name)
    //
    // Serialize all elements of the request collection into a XML message, and format the required
    // form payload according to the Endeavour XML Message APIs. The form payload is returned from
    // the function.
    /////////////////////////////////////////////////////////////////////////////////////////////


    function printRequestXml(){
        foreach ($this->request as $name => $value) {
            $queryString[$name] = ($value) ;
        }
        echo http_build_query($queryString);
    }

    function getRequestXml($url){
        foreach ($this->request as $name => $value) {
            $queryString[$name] = ($value) ;
        }
        return http_build_query($queryString);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    // Function sendHttp(url, "", $timeout)
    //
    // HTTP POST the form payload to the url using cURL.
    // form payload according to the Endeavour XML Message APIs. The form payload is returned from
    // the function.
    /////////////////////////////////////////////////////////////////////////////////////////////

    function sendHttp($url, $connectTimeout="", $timeout = 60) {

            //Construct the payload to POST to the url.

            $data = $this->getRequestXml($url, $timeout);

            // create a new cURL resource

            $ch = curl_init($url);

            // set URL and other appropriate options

            curl_setopt($ch, CURLOPT_POST,1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);  // CURLOPT_TIMEOUT_MS can also be used

            // Execute the request.

            $result = curl_exec($ch);
            $succeeded  = curl_errno($ch) == 0 ? true : false;

            // close cURL resource, and free up system resources
            curl_close($ch);



        return($result);

    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    // Function escapeXML(value)
    //
    // Escaped string converting all '&' to '&amp;' and all '<' to '&lt'. Return the escaped value.
    /////////////////////////////////////////////////////////////////////////////////////////////

    function escapeXML($elementValue){

        $escapedValue = str_replace("&", "&amp;", $elementValue);
        $escapedValue = str_replace("<", "&lt;", $escapedValue);

        return $escapedValue;

    }
}