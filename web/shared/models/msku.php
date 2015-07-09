<?php

if (! defined('BASEPATH'))
    exit('No direct script access allowed');
/**
 * 产品sku model
 * 
 * @author : liaoxianwen@ymt360.com
 * @version : 1.0.0
 * @since : 2014-12-10
 */
class MSku extends MY_Model {
    use MemAuto;
    
    private $table = 't_sku';
    
    public function __construct () {
        parent::__construct($this->table);
    }
    
    /*
     * @description :根据sku_number查找sku名称及规格 @author: wangyang@dachuwang.com
     */
    public function get_sku_info_by_sku_num ($sku_num = array(), $where = array()) {
        $this->db->select('sku_number, name, spec, category_id')->from($this->table);
        if (! (empty($sku_num))) {
            $this->db->where_in('sku_number', $sku_num);
        }
        
        if (isset($where['like'])) {
            foreach ( $where['like'] as $k => $v ) {
                if ($k == 'sku_number' || $k == 'name') {
                    $this->db->like($k, $v);
                }
            }
            unset($where['like']);
        }
        /*
         * if(isset($category_ids)) { $this->db->where_in('category_id', $category_ids); }
         */
        $query = $this->db->get();
        return $query->result_array();
    }
}

/* End of file msku.php */
/* Location: :./application/models/msku.php */
