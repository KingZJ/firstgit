<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CRM数据统计
 * @author liudeen@dachuwang.com
 * @date 2015-03-24
 */
class Performance extends MY_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(
            array(
                'MCustomer',
                'MOrder',
                'MCustomer_potential',
                'MUser'
            )
        );
    }
    private $_bd_list = [12,13];
    private $_am_list = [14,15];

    private function _bd_or_am() {
        $role_id = $_POST['role_id'];
        if(!$role_id) {
            return FALSE;
        }
        if(in_array($role_id, $this->_bd_list)) {
            return 'bd';
        } else if(in_array($role_id, $this->_am_list)) {
            return 'am';
        } else {
            return 'none';
        }
    }

    private function _assemble_res($status, $msg, $res) {
        $arr = array(
            'status' => $status,
            'msg'    => $msg,
            'res'    => $res
        );
        $this->_return_json($arr);
    }

    private function _assemble_err($status, $msg) {
        $arr = array(
            'status' => $status,
            'msg'    => $msg,
        );
        $this->_return_json($arr);
    }

    //传入当前日期,格式为date('Y-m-d',xxx)，返回本月第一天的时间戳
    private function _get_month_firstday($date) {
        return strtotime(date('Y-m-01',strtotime($date)));
    }

    private function _get_start_and_end() {
        $res = [];
        $time_type = isset($_POST['time_type']) ? $_POST['time_type'] : '';
        switch($time_type) {
        case 'by_day':
            $res['start'] = strtotime('today');
            $res['end']   = strtotime('now');
            break;
        case 'by_week':
            $res['start'] = strtotime('this Monday')>strtotime('now') ? strtotime('last Monday'):strtotime('this Monday');
            $res['end'] = strtotime('now');
            break;
        case 'by_month':
            $res['start'] = $this->_get_month_firstday(date('Y-m-d',time()));
            $res['end'] = strtotime('now');
            break;
        case 'all':
            $res['start'] = strtotime('2015-05-06');
            $res['end'] = strtotime('now');
            break;
        case 'optional':
            $res['start'] = isset($_POST['begin_time']) ? $_POST['begin_time'] : NULL;
            $res['end'] = isset($_POST['end_time']) ? $_POST['end_time'] + 86400 : NULL;
            if($res['start'] === NULL || $res['end'] === NULL) {
                return FALSE;
            }
            break;
        default:
            return FALSE;
            break;
        }
        //统计的最早时间从5月6号开始
        $latest = strtotime('2015-05-06');
        $res['start'] = $res['start']<$latest ? $latest:$res['start'];
        return $res;
    }

    private function _parse_bd_id() {
        $res = [];
        if(!empty($_POST['bd_ids'])) {
            if(is_array($_POST['bd_ids'])) {
                foreach($_POST['bd_ids'] as $item) {
                    $res[] = intval($item);
                }
            } else {
                $res = array(
                    intval($_POST['bd_ids'])
                );
            }
        }
        return $res;
    }

    private function _get_customer_ids_by_bd($bd_ids = array()) {
        $res = [];
        if(!empty($bd_ids)) {
            $where = [
                'status !=' => C('customer.status.invalid.code'),
                'in' => array(
                    'invite_id' => $bd_ids
                )
            ];
            $invited_customers = $this->MCustomer->get_lists(
                'id',
                $where
            );
            if(!empty($invited_customers)) {
                $res = array_column($invited_customers, 'id');
            }
        }
        return $res;
    }

    //用户数统计
    public function customer_num() {
        $answer = $this->_private_customer_num();
        if(is_string($answer)) {
            $this->_assemble_err(-1, $answer);
        } else {
            $this->_assemble_res(0, 'success', $answer);
        }
    }

    private function _private_customer_num() {
        $where = [
            'status !=' => C('customer.status.invalid.code')
        ];
        $time_arr = $this->_get_start_and_end();
        if($time_arr!== FALSE) {
            $where['created_time >='] = $time_arr['start'];
            $where['created_time <']  = $time_arr['end'];
        } else {
            return 'error time param';
        }

        $bd_ids = $this->_parse_bd_id();
        if(!empty($bd_ids)) {
            $role = $this->_bd_or_am();
            $key = NULL;
            if($role === 'bd') {
                $key = 'invite_id';
            } else if($role === 'am'){
                $key = 'am_id';
            }
            if($key === NULL) {
                return  'lack of role';
            }
            $where['in'] = array(
                $key => $bd_ids
            );
        } else {
            return 'lack of bd_ids';
        }
        $cnt = $this->MCustomer->count($where);
        return intval($cnt);
    }

    //下单用户数 针对AM，不能查BD
    public function order_customer_num() {
        $answer = $this->_private_order_customer_num();
        if(is_string($answer)) {
            $this->_assemble_err(-1, $answer);
        } else {
            $this->_assemble_res(0, 'success', $answer);
        }
    }

    private function _private_order_customer_num() {
        //查询am时bd_ids代表am集合
        $bd_ids = $this->_parse_bd_id();
        if(!is_array($bd_ids) || count($bd_ids)<=0) {
            return 'lack of bd_ids';
        }
        //先查找在某时间段中下过单的客户
        $time_arr = $this->_get_start_and_end();
        if($time_arr!==FALSE) {
            $where['created_time >='] = $time_arr['start'];
            $where['created_time <']  = $time_arr['end'];
        } else {
            return 'error time param';
        }
        $user_ids = $this->_parse_user_ids($this->MOrder->get_lists(['user_id'], $where,[],['user_id']));
        if(!is_array($user_ids) || count($user_ids)<=0) {
            return 0;
        }
        //在客户表中找出上述客户中am_id属于查询时提交的bd_ids集合
        $where = ['in'=>[]];
        $where['in']['id'] = $user_ids;
        $where['in']['am_id'] = $bd_ids;
        $cnt = $this->MCustomer->count($where);
        return $cnt;
    }

    //从数组中提取出user_id
    private function _parse_user_ids($temp, $key = 'user_id') {
        $user_ids = [];
        foreach($temp as $v) {
            if($v[$key]) {
                $user_ids[] = $v[$key];
            }
        }
        return $user_ids;
    }

    //总客户数与时间无关
    private function _private_all_customer_num() {
        $bd_ids = $this->_parse_bd_id();
        if(count($bd_ids)<=0) {
            return 'lack of bdids';
        }
        $where = ['in'=>[]];
        $role = $this->_bd_or_am();
        if($role === 'bd') {
            $where['in']['invite_id'] = $bd_ids;
        } else if($role === 'am') {
            $where['in']['am_id'] = $bd_ids;
        } else {
            return 'lack of role';
        }
        $cnt = $this->MCustomer->count($where);
        return $cnt;
    }

    //未下单客户数
    public function not_order_customer_num() {
        //获取下单客户数
        $order_customer_num = $this->_private_order_customer_num();
        if(is_string($order_customer_num)) {
            $this->_assemble_err(-1, $order_customer_num);
        }
        //获取总客户数
        $customer_num = $this->_private_all_customer_num();
        if(is_string($customer_num)) {
            $this->_assemble_err(-1, $customer_num);
        }
        $this->_assemble_res(0, 'success', $customer_num - $order_customer_num);
    }

    public function finish_order_num() {
        $answer = $this->_private_finish_order_num();
        if(is_string($answer)) {
            $this->_assemble_err(-1, $answer);
        } else {
            $this->_assemble_res(0, 'success', $answer);
        }
    }

    private function _private_finish_order_num() {
        $bd_ids = $this->_parse_bd_id();
        if(count($bd_ids)<=0) {
            return 'lack of bdids';
        }
        //找出属于bd_ids的客户
        $where = ['in'=>[]];
        $where['in']['am_id'] = $bd_ids;
        $user_ids = $this->_parse_user_ids($this->MCustomer->get_lists(['id'], $where), 'id');
        if(!is_array($user_ids) || count($user_ids)<=0) {
            return 0;
        }
        $time_arr = $this->_get_start_and_end();
        if($time_arr === FALSE) {
            return 'time param error';
        }
        //找出客户下的状态为完成的订单数
        $where = [];
        $where['status'] = C('order.status.success.code');
        $where['updated_time >='] = $time_arr['start'];
        $where['updated_time <'] = $time_arr['end'];
        $where['in'] = ['user_id' => $user_ids];
        $cnt = $this->MOrder->count($where);
        return $cnt;
    }

    /*
     * 已完成首单数
     * 订单是完成状态，完成时间最早的一单算首单
     */
    public function first_finish_order_num() {
        $answer = $this->_private_first_finish_order_num();
        if(is_string($answer)) {
            $this->_assemble_err(-1, $answer);
        } else {
            $this->_assemble_res(0, 'success', $answer);
        }
    }

    /*
     * 全部首单数
     * 订单是有效状态（不一定完成了）
     */
    public function first_order_num() {
        $answer = $this->_private_first_order_num();
        if(is_string($answer)) {
            $this->_assemble_err(-1, $answer);
        } else {
            $this->_assemble_res(0, 'success', $answer);
        }
    }

    private function _private_first_finish_order_num() {
        $bd_ids = $_POST['bd_ids'];
        if(!is_array($bd_ids)) {
            return 'lack of bdids';
        }
        //找出首单发生在某一时间段内的客户
        $time_arr = $this->_get_start_and_end();
        if(empty($time_arr)) {
            return 'lack of time param';
        }
        $where = [
            'status' => C('order.status.success.code'),
            'created_time >=' => strtotime('2015-05-06'),
        ];
        //所有用户的首单
        $results = $this->MOrder->get_lists(['user_id','min(updated_time) as min_time'], $where, [], ['user_id']);
        if(!is_array($results) || count($results)<=0) {
            return 0;
        }
        //去除首单时间不在查询时间段的用户
        $len = count($results);
        for($i=0; $i<$len; $i++) {
            if(intval($results[$i]['min_time'])<intval($time_arr['start']) || intval($results[$i]['min_time'])>intval($time_arr['end'])) {
                unset($results[$i]);
            }
        }
        //获取user_ids
        $user_ids = $this->_parse_user_ids($results);
        if(count($user_ids)<=0) {
            return 0;
        }
        //属于某些am或者bd的客户
        $role = $this->_bd_or_am();
        $where = [
            'in' => ['id' => $user_ids]
        ];
        if($role === 'bd') {
            $where['in']['invite_id'] = $bd_ids;
        } else if($role === 'am') {
            $where['in']['am_id'] = $bd_ids;
        } else {
            return 'lack of role';
        }
        $cnt = $this->MCustomer->count($where);
        return $cnt;
    }


    private function _private_first_order_num() {
        $bd_ids = $_POST['bd_ids'];
        if(!is_array($bd_ids)) {
            return 'lack of bdids';
        }
        //找出首单发生在某一时间段内的客户
        $time_arr = $this->_get_start_and_end();
        if(empty($time_arr)) {
            return 'lack of time param';
        }
        $where = array(
            'status !=' => C('order.status.closed.code'),
            'in' => array(
                'sale_id' => $bd_ids,
            ),
            'created_time >=' => strtotime('2015-05-06'),
        );
        //所有用户的首单
        $results = $this->MOrder->get_lists(['user_id','min(created_time) as min_time'], $where, [], ['user_id']);
        if(!is_array($results) || count($results)<=0) {
            return 0;
        }
        //去除首单时间不在查询时间段的用户
        $len = count($results);
        for($i=0; $i<$len; $i++) {
            if(intval($results[$i]['min_time'])<intval($time_arr['start']) || intval($results[$i]['min_time'])>intval($time_arr['end'])) {
                unset($results[$i]);
            }
        }
        //获取user_ids
        $user_ids = $this->_parse_user_ids($results);
        if(count($user_ids)<=0) {
            return 0;
        }
        //属于某些am或者bd的客户
        $role = $this->_bd_or_am();
        $where = [
            'in' => ['id' => $user_ids]
        ];
        if($role === 'bd') {
            $where['in']['invite_id'] = $bd_ids;
        } else if($role === 'am') {
            $where['in']['am_id'] = $bd_ids;
        } else {
            return 'lack of role';
        }
        $cnt = $this->MCustomer->count($where);
        return $cnt;
    }

    public function order_num() {
        $where = [
            'status !=' => C('order.status.closed.code')
        ];

        //根据bd得到用户ids
        $bd_ids = $this->_parse_bd_id();
        if(!empty($bd_ids)) {
            $customer_ids = $this->_get_customer_ids_by_bd($bd_ids);
            if(empty($customer_ids)) {
                return $this->_assemble_res(0, 'success', 0);
            }
            //指定用户的订单
            $where['in'] = array(
                'user_id' => $customer_ids
            );
        }

        $time_arr = $this->_get_start_and_end();
        if(!empty($time_arr)) {
            $where['created_time >='] = $time_arr['start'];
            $where['created_time <'] = $time_arr['end'];
        }


        $cnt = $this->MOrder->get_one(
            array(
                'count(distinct(concat(`user_id`, "_", `deliver_date`, "_", `deliver_time`))) cnt'
            ),
            $where
        );

        $cnt = empty($cnt) ? 0 : $cnt['cnt'];
        return $this->_assemble_res(0, 'success', $cnt);
    }

    private function _get_private_potential_customer_num($bd_id) {
        if(!$bd_id) {
            return 0;
        }
        $where = ['invite_id' => $bd_id, 'status !=' => C('customer.status.invalid.code')];
        return $this->MCustomer_potential->count($where);
    }

    private function _get_private_new_register_customer_num($bd_id) {
        if(!$bd_id) {
            return 0;
        }
        $where = ['invite_id' => $bd_id, 'status' => C('customer.status.new.code')];
        return $this->MCustomer->count($where);
    }

    private function _get_private_potential_capability($bd_id) {
        if(!$bd_id) {
            return 0;
        }
        $where = ['id' => $bd_id];
        $capability = $this->MUser->get_one(['max_potential_customer'], $where);
        return intval($capability['max_potential_customer']);
    }

    private function _get_private_new_register_capability($bd_id) {
        if(!$bd_id) {
            return 0;
        }
        $where = ['id' => $bd_id];
        $capability = $this->MUser->get_one(['max_customer'], $where);
        return intval($capability['max_customer']);
    }

    public function get_capability() {
        if(!isset($_POST['bd_id'])) {
            $this->_assemble_err(C('status.req.failed'), 'lack of bdid');
        }
        $bd_id = $_POST['bd_id'];
        $arr = [
            ['key' => '私海潜在客户数量', 'val' => ['current' => $this->_get_private_potential_customer_num($bd_id), 'upper' => $this->_get_private_potential_capability($bd_id)]],
            ['key' => '私海新注册客户数量', 'val' => ['current' => $this->_get_private_new_register_customer_num($bd_id), 'upper' => $this->_get_private_new_register_capability($bd_id)]],
        ];
        $this->_assemble_res(C('status.req.success'), '请求成功', $arr);
    }
}
