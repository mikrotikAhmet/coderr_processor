<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/22/16
 * Time: 6:44 PM
 */
function isCurrency($number)
{
    return preg_match("/^-?[0-9]+(?:\.[0-9]{1,2})?$/", $number);
}

function shapeSpace_check_https($mode = 0) {

    if (!$mode){

        return true;
    } else if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {

        return true;
    }
    return false;
}

function token($length = 32) {
    // Create random token
    $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    $max = strlen($string) - 1;

    $token = '';

    for ($i = 0; $i < $length; $i++) {
        $token .= $string[mt_rand(0, $max)];
    }

    return $token;
}