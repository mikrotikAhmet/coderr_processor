<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Currencies_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function refresh($new_default,$currency)
    {

// Get cURL connection done
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, 'http://download.finance.yahoo.com/d/quotes.csv?s='.$new_default. $currency . '=X&f=sl1&e=.csv');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        $content = curl_exec($curl);

        curl_close($curl);

        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {

            $currency = utf8_substr($line, 4, 3);
            $value = utf8_substr($line, 11, 6);

            if ((float)$value) {

                return $value;
            }

        }
    }

    /**
     * @return object
     * Get base currency
     */
    public function get_base_currency()
    {
        $this->db->where('isdefault', 1);
        return $this->db->get('tblcurrencies')->row();
    }

    /**
     * @param  integer ID
     * @return string
     * Get the symbol from the currency
     */
    public function get_currency_symbol($id)
    {
        if(!is_numeric($id)){
            $id = $this->get_base_currency()->id;
        }
        $this->db->select('symbol');
        $this->db->from('tblcurrencies');
        $this->db->where('id', $id);
        return $this->db->get()->row()->symbol;
    }
}
