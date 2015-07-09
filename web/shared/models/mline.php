<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Mline extends MY_Model {
    use MemAuto;
    private $_table = 't_line';
    public function __construct() {
        parent::__construct($this->_table);
    }
}

/* End of file mline.php */
/* Location: :./application/models/mline.php */
