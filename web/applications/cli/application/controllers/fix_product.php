<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 修复sku_number
 * @author: liaoxianwen@dachuwang.com
 * @version: 1.0.0
 * @since: 2015-3-25
 */
class Fix_product extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(
            array(
                'MProduct',
            )
        );
    }
    public function fix_storage() {
        $update_data = array('storage' => -1);
        $where = array('status !=' => 0);
        $this->MProduct->update_info($update_data, $where);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 修复line_id错误数据
     */
    public function fix_line_id() {
        $update_data = array('line_id' => 0);
        $where = array('line_id >' => 0);
        $this->MProduct->update_info($update_data, $where);
    }
}

/* End of file repair_sku.php */
/* Location: ./application/controllers/repair_sku.php */
