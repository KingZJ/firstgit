<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class MStock extends MY_Model {

    private $_table = 't_stock';
    public function __construct() {
        parent::__construct($this->_table);
    }

}

/* End of file mstock.php */
/* Location: :./application/models/mstock.php */
