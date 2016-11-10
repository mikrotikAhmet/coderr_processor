<?php
/**
 * Created by PhpStorm.
 * User: mbicanin
 * Date: 7/16/16
 * Time: 6:04 PM
 */

class Currency {
    private $currencies = array();
    private $_instance;

    public function __construct() {

        $this->_instance = &get_instance();

        $query = $this->_instance->db->query("SELECT * FROM tblcurrencies");

        foreach ($query->result_array() as $result) {
            $this->currencies[$result['name']] = array(
                'id'   => $result['id'],
                'name'         => $result['name'],
                'symbol'   => $result['symbol'],
                'decimal_place' => 2,
                'value'         => $result['value']
            );
        }
    }

    public function format($number, $currency, $value = '', $format = true) {
        $symbol = $this->currencies[$currency]['symbol'];
        $decimal_place = 2;

        if (!$value) {
            $value = $this->currencies[$currency]['value'];
        }

        $amount = $value ? (float)$number * $value : (float)$number;

        $amount = round($amount, (int)$decimal_place);

        if (!$format) {
            return $amount;
        }

        $string = '';

        $string .= $symbol;

        $string .= number_format($amount, (int)$decimal_place, '.', ',');

        return $string;
    }

    public function convert($value, $from, $to) {
        if (isset($this->currencies[$from])) {
            $from = $this->currencies[$from]['value'];
        } else {
            $from = 1;
        }

        if (isset($this->currencies[$to])) {
            $to = $this->currencies[$to]['value'];
        } else {
            $to = 1;
        }

        return $value * ($to / $from);
    }

    public function getSymbol($currency){
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['symbol'];
        } else {
            return null;
        }
    }

    public function getValue($currency) {
        if (isset($this->currencies[$currency])) {
            return $this->currencies[$currency]['value'];
        } else {
            return 0;
        }
    }

    public function getNameById($id) {

        $query = $this->_instance->db->query("SELECT * FROM tblcurrencies WHERE id = '".(int)($id)."'");

        return $query->row()->name;
    }

    public function has($currency) {
        return isset($this->currencies[$currency]);
    }
}