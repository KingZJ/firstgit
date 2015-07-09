<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Stock extends MY_Controller {

    public function __construct () {
        parent::__construct();
    }

    public function update() {
        $return = $this->format_query('/stock/update', $_POST);
        $this->_return_json($return);
    }

    public function lists() {
        $post = $this->post;
        if(isset($post['searchType'])) {
            switch($post['searchType']) {
            case 'sku_number':
                if(!empty($_POST['searchVal'])) {
                    $_POST['sku_number'] = $_POST['searchVal'];
                }
                break;
            case 'warehouse_id':
                if(!empty($_POST['searchVal'])) {
                    $_POST['warehouse_id'] = $_POST['searchVal'];
                }
                break;
            default:
                break;
            }
        }
        $return = $this->format_query('/stock/lists', $_POST);
        $this->_return_json($return);
    }
}

/* End of file stock.php */
/* Location: ./application/controllers/stock.php */
