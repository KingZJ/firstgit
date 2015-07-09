<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * @author caochunhui@dachuwang.com
 * @description 订单详情表
 */

class MOrder_detail extends MY_Model {
    use MemAuto;
    private $_table = 't_order_detail';
    public function __construct() {
        parent::__construct($this->_table);
    }
    /**
     * 得到某段时间内商品sku排行的数据
     * @author zhangxiao@dachuwang.com
     * @param number $stime
     * @param number $etime
     * @param number $offset
     * @param number $pagesize
     * @return array
     */
    public function get_period_sku_rank_data($status, $stime = 0, $etime = 0, $offset = 0, $pagesize = 0, $search = array()) {
        //区分城市 wangyang@dachuwang.com
        $city_id = isset($_POST['city_id']) ? $_POST['city_id'] : C('open_cities.beijing.id');
        $where['city_id'] = $city_id;

        if($stime != 0 && $etime != 0) {
            $where['created_time >='] = $stime;
            $where['created_time <='] = $etime;
        }
        if($status == C('order.status.closed.code')) {
            $where['status !='] = C('order.status.closed.code');
            $this->db->select_sum('quantity');   
        } elseif($status == C('order.status.success.code')) {
            $where['status ='] = C('order.status.success.code');
            $this->db->select_sum('actual_quantity');
        }
        $this->db->select('sku_number');
        if(isset($where)) {
            $this->db->where($where);
        }
        $this->db->select_sum('actual_sum_price');
        $this->db->group_by('sku_number');
        $this->db->order_by('actual_sum_price','desc');
        if($pagesize >= 0) {
            $this->db->limit($offset, $pagesize);
        }
        if(!empty($search)){
            $this->db->where_in('sku_number', $search);
        }else{
            return array();
        }
        $query = $this->db->get($this->_table); 
        $res = $query->result_array();

        return $res;
    }


    /**
     * 得到某段时间内商品sku排行的order_id
     * @author wangyang@dachuwang.com
     */
    public function get_period_sku_order_id($status, $stime = 0, $etime = 0, $offset = 0, $pagesize = 0, $search = array()) {
        //区分城市 wangyang@dachuwang.com
        $city_id = isset($_POST['city_id']) ? $_POST['city_id'] : C('open_cities.beijing.id');
        $where['city_id'] = $city_id;

        if($stime != 0 && $etime != 0) {
            $where['created_time >='] = $stime;
            $where['created_time <='] = $etime;
        }
        if($status == C('order.status.closed.code')) {
            $where['status !='] = C('order.status.closed.code');
        } elseif($status == C('order.status.success.code')) {
            $where['status ='] = C('order.status.success.code');
        }
        $this->db->select('sku_number, order_id, status');
        if(isset($where)) {
            $this->db->where($where);
        }
        if($pagesize >= 0) {
            $this->db->limit($offset, $pagesize);
        }
        if(!empty($search)){
            $this->db->where_in('sku_number', $search);
        }else{
            return array();
        }
        $query = $this->db->get($this->_table); 
        $res = $query->result_array();
        return $res;
    }

    /*
     *描述：获取sku排行
     *@author：wangyang@dachuwang.com
     * */
    public function get_sku_ranks($params = array()){
        $extra ['status !='] = C('order.status.closed.code'); //无效订单
        
        if(isset($params['stime']) && !empty($params['stime'])){
            $extra['created_time >='] = $params['stime'];
        }
        if(isset($params['etime']) && !empty($params['etime'])){
            $extra['created_time <='] = $params['etime'];
        }

        $sku_info =  $this->get_sku_info($extra);
        $orders_id_sku_numbers = $this->get_orders_id($sku_info);
        $data['sku_info'] = $sku_info;
        $data['orders_id'] = $orders_id_sku_numbers['orders_id'];
        $data['sku_numbers'] = $orders_id_sku_numbers['sku_numbers'];
        return $data;
    }

    /*
     *描述：根据sku信息进行order_id提取
     * @author:wangyang@dachuwang.com
     * */ 
    public function get_orders_id($data = array()){
        if (empty($data)) return FALSE;
        $orders_id = array();
        $sku_numbers    = array();
        $return_data    = array();
        foreach($data as $key => $value){
            if(!in_array($value['order_id'], $orders_id)){
                $orders_id[] = $value['order_id'];
            }
            if(!in_array($value['sku_number'], $sku_numbers)){
                $sku_numbers[] = $value['sku_number'];
            }
        }
        $return_data['orders_id'] = $orders_id;
        $return_data['sku_numbers'] = $sku_numbers;
        return $return_data;
    }

    /*
     *描述：获取sku 信息
     *@author:wangyang@dachuwang.com
     * */
    public function get_sku_info($extra = array()){
        $this->db->select('order_id,quantity,sum_price,sku_number,status')->from($this->_table);
        if(is_array($extra) && !empty($extra)){
            $this->db->where($extra);
        }
        $query = $this->db->get();
        return  $query->result_array();
    }
}//class end

/* End of file morder_detail.php */
/* Location: :./application/models/morder_detail.php */
