<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Suborder extends MY_Controller {

    public function __construct () {
        parent::__construct();
        $this->load->model(
            array(
                'MProduct',
                'MCustomer',
                'MOrder',
                'MOrder_detail',
                'MUser',
                'MWorkflow_log',
                'MRole',
                'MLine',
                'MPromo_event',
                'MPromo_event_rule',
                'MCategory',
                'MPick_task',
                'MDeliver_fee',
                'MLocation',
                'MSuborder',
            )
        );
        $this->load->library(
            array(
                'form_validation',
            )
        );


        //订单状态和对应中文字典
        $code_with_cn = array_values(C('order.status'));
        $codes        = array_column($code_with_cn, 'code');
        $msg          = array_column($code_with_cn, 'msg');
        $this->_status_dict = array_combine($codes, $msg);

        //deliver的code和相应文字的对应关系
        $code_with_deliver_time = array_values(C('order.deliver_time'));
        $codes                  = array_column($code_with_deliver_time, 'code');
        $msg                    = array_column($code_with_deliver_time, 'msg');
        $this->_deliver_dict    = array_combine($codes, $msg);

        //unit_id  => unit_name
        $unit_config = C('unit');
        $codes       = array_column($unit_config, 'id');
        $msg         = array_column($unit_config, 'name');
        $this->_unit_dict = array_combine($codes, $msg);
        $this->_unit_dict[0] = '无';
    }

    /**
     * @description 子订单列表
     * @author caochunhui@dachuwang.com
     */
    public function lists() {
        $page  = $this->get_page();
        $uid   = isset($_POST['user_id']) ? $_POST['user_id'] : 0;
        $where = [];

        //查看特定状态的订单,不传即查看全部
        if(isset($_POST['status']) && $_POST['status'] != -1 && $_POST['status'] != '') {
            if(is_array($_POST['status'])) {
                $where['in']['status'] = $_POST['status'];
            } else {
                $where['status'] = $_POST['status'];
            }
        }

        //查看指定用户的订单或全部
        if($uid > 0) {
            $where['user_id'] = $uid;
        }
        if(!empty($_POST['orderType'])) {
            $where['order_type'] = $_POST['orderType'];
        }

        // 根据城市筛选
        if(!empty($_POST['cityId'])) {
            $where['location_id'] = $_POST['cityId'];
        }
        //查看大厨、大果的订单
        //0 大厨 1 大果
        if(!empty($_POST['site_src'])) {
            $where['site_src'] = $_POST['site_src'];
        }

        // 配送时间筛选
        if(!empty($_POST['startTime'])) {
            $where['deliver_date >='] = $_POST['startTime'] / 1000;
        }
        if(!empty($_POST['endTime'])) {
            $where['deliver_date <='] = $_POST['endTime'] / 1000;
        }
        if(!empty($_POST['deliver_date'])) {
            $where['deliver_date'] = $_POST['deliver_date'];
        }
        if(!empty($_POST['deliver_time'])) {
            $where['deliver_time'] = $_POST['deliver_time'];
        }

        // 客户筛选，根据姓名、手机号或订单号
        if(!empty($_POST['searchValue'])) {
            // 如果输入的为大于11位的数字，按照订单号查询
            if(preg_match("/^\d{12,}$/", $_POST['searchValue'])) {
                $where['like'] = array('order_number' => $_POST['searchValue']);
            }else{
                // 如果输入关键词为数字，则匹配手机号
                if(preg_match("/^\d{11}$/", $_POST['searchValue'])){
                    $where_user['like'] = array('mobile' => $_POST['searchValue']);
                } else if (preg_match("/^\d+$/", $_POST['searchValue'])){
                    $where['id'] = $_POST['searchValue'];
                } else {
                    $where_user['like'] = array('name' => $_POST['searchValue']);
                }
                if(!empty($where_user)) {
                    $user_ids = $this->MCustomer->get_lists('id', $where_user);
                    $user_ids = array_column($user_ids, 'id');
                    if(!empty($user_ids)) {
                        $where['in']['user_id'] = $user_ids;
                    } else { // 如果没有匹配的，直接强制无结果即可
                        $where['id'] = 0;
                    }
                }
            }

        }

        // 根据线路筛选
        if(!empty($_POST['line_id'])) {
            $where['line_id'] = $_POST['line_id'];
        }
        // 根据订单ID筛选
        if(!empty($_POST['order_ids'])) {
            $where['in']['id'] = $_POST['order_ids'];
        }
        // 根据配送单号筛选
        if(!empty($_POST['dist_id'])) {
            $where['dist_id'] = $_POST['dist_id'];
        }
        if(!empty($_POST['dist_ids'])) {
            $where['in']['dist_id'] = $_POST['dist_ids'];
        }
        // 按照分拣单号筛选
        if(!empty($_POST['pick_ids'])) {
            $where['in']['pick_task_id'] = $_POST['pick_ids'];
        }
        // 线路规划只显示未分配生成配送单的订单
        if(!empty($_POST['list_type']) && $_POST['list_type'] == 'distribution') {
            $where['dist_id'] = 0;
        }

        // 排序
        if (!empty($_POST['order_by'])) {
            $order_by = $_POST['order_by'];
        } else {
            $order_by = array('created_time' => 'DESC');
        }

        // 获取订单列表
        $result = $this->MSuborder->get_lists(
            '*',
            $where, $order_by,
            array(), $page['offset'], $page['page_size']
        );

        $total_count = $this->MSuborder->count($where);

        //计算每种状态的订单数目
        //从配置文件里取道所有的code
        $status_dict = array_column(
            array_values(
                C('order.status')
            ),
            'code'
        );

        foreach($status_dict as $v) {
            if($v != -1) {
                $where['status'] = $v;
            }else{
                unset($where['status']);
            }
            $total[$v] = $this->MSuborder->count($where);
        }

        if(!empty($result)) {
            $result = $this->_format_order_list($result);
        }

        // 设置不同订单状态的颜色
        $order_status = array_values(C('order.status'));
        $status_class = array();
        foreach($order_status as $v) {
            $status_class[$v['code']] = $v['class'];
        }
        foreach($result as &$order) {
            $order['class'] = isset($status_class[$order['status']]) ? $status_class[$order['status']] : 'label-info';
        }

        $arr['status'] = C("status.req.success");
        $arr['orderlist'] = $result;
        $arr['total'] = $total;
        $arr['total_count'] = $total_count;
        $this->_return_json($arr);
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 格式化子订单列表
     * @todo 需要参考product的spec来合并属性
     */
    private function _format_order_list($suborder_list = array()) {
        if(empty($suborder_list)) {
            return $suborder_list;
        }

        //批量取出下单用户信息
        $user_ids = array_column($suborder_list, 'user_id');
        $user_ids = array_unique($user_ids);
        $users = $this->MCustomer->get_lists(
            '*',
            [
                'in' => [
                    'id' => $user_ids
                ]
            ]
        );
        $user_ids = array_column($users, 'id');
        $user_map = array_combine($user_ids, $users);
        //批量取出bd和am的信息
        $bd_ids = array_column($users, 'invite_id');
        $am_ids = array_column($users, 'am_id');
        $bd_ids = array_merge($bd_ids, $am_ids);
        $bd_ids = array_unique($bd_ids);
        $bd_ids = array_filter($bd_ids);
        $bd_users = $this->MUser->get_lists(
            'name, mobile, id',
            array(
                'in' => array(
                    'id' => $bd_ids
                )
            )
        );
        $bd_ids = array_column($bd_users, 'id');
        $bd_map = array_combine($bd_ids, $bd_users);

        // 批量取出线路信息
        $line_list = $this->MLine->get_lists('id, name, warehouse_id', array('status' => C('status.common.success')));
        $line_ids = array_column($line_list, 'id');
        $line_names = array_column($line_list, 'name');
        $line_map = array_combine($line_ids, $line_names);
        $warehouse_ids = array_column($line_list, 'warehouse_id');
        $line_to_warehouse = array_combine($line_ids, $warehouse_ids);

        // 批量取出城市信息
        $city_ids = array_column($suborder_list, 'city_id');
        $city_list = $this->MLocation->get_lists('id, name', array('in' => array('id' => $city_ids)));
        $city_dict = array_combine(array_column($city_list, 'id'), array_column($city_list, 'name'));

        // 批量取出订单分拣单号
        $pick_ids = array_column($suborder_list, 'pick_task_id');
        $pick_ids = array_unique(array_filter($pick_ids));
        if(!empty($pick_ids)) {
            $pick_list = $this->MPick_task->get_lists('*', array('in' => array('id' => $pick_ids)));
            $pick_dict = array_combine(array_column($pick_list, 'id'), $pick_list);
        } else {
            $pick_dict = array();
        }

        // 批量取出客户类型
        $customer_types = array_values(C('customer.type'));
        $customer_type_dict = array_combine(array_column($customer_types, 'value'), array_column($customer_types, 'name'));

        //批量取出母订单order_number
        $order_ids = array_column($suborder_list, 'order_id');
        $orders = $this->MOrder->get_lists(
            'id, order_number',
            array(
                'in' => array(
                    'id' => $order_ids
                )
            )
        );
        $order_ids = array_column($orders, 'id');
        $order_numbers = array_column($orders, 'order_number');
        $order_id_to_number = array_combine($order_ids, $order_numbers);

        //批量取出订单详情
        $suborder_ids = array_column($suborder_list, 'id');
        $where = [
            'in' => [ 'suborder_id' => $suborder_ids ]
        ];
        $order_details = $this->MOrder_detail->get_lists(
            '*',
            $where
        );
        $detail_map = [];
        foreach($order_details as &$item) {
            $order_id = $item['suborder_id'];
            $item['price']     /= 100;
            $item['sum_price'] /= 100;
            $item['actual_price'] /= 100;
            $item['actual_sum_price'] /= 100;
            $item['created_time'] = date('Y/m/d H:i', $item['created_time']);
            $item['updated_time'] = date('Y/m/d H:i', $item['updated_time']);
            $item['single_price'] /= 100;
            $item['unit_id'] = $this->_unit_dict[$item['unit_id']];
            $item['close_unit'] = $this->_unit_dict[$item['close_unit']];
            $spec = json_decode($item['spec'], TRUE);
            if(!empty($spec)) {
                foreach($spec as $idx => $spec_arr) {
                    if(empty($spec_arr['name']) || empty($spec_arr['val'])) {
                        unset($spec[$idx]);
                    }
                }
                $item['spec'] = $spec;
            } else {
                $item['spec'] = '';
            }
            if(isset($detail_map[$order_id])) {
                $detail_map[$order_id][] = $item;
            } else {
                $detail_map[$order_id] = [
                    $item
                ];
            }
        }
        unset($item);

        // 角色ID和名称字典
        $role_list  = $this->MRole->get_lists('id, name', array('status' => C('status.common.success')));
        $role_ids   = array_column($role_list, 'id');
        $role_names = array_column($role_list, 'name');
        $role_dict  = array_combine($role_ids, $role_names);

        foreach($suborder_list as &$item) {
            //母订单number
            $main_order_id = $item['order_id'];
            $item['main_order_number'] = $order_id_to_number[$main_order_id];
            //价格和时间
            $item['total_price']  = $item['total_price'] / 100;
            $item['deal_price']   = $item['deal_price'] / 100;
            $item['minus_amount'] = $item['minus_amount'] / 100;
            $item['deliver_fee'] = $item['deliver_fee'] / 100;
            $item['created_time'] = date('Y/m/d H:i', $item['created_time']);
            $item['final_price'] = $item['final_price'] / 100;
            //$item['pay_reduce'] = $item['pay_reduce'] / 100;
            $deliver_arr          = $this->_deliver_dict;
            $item['deliver_time_real'] = $item['deliver_time'];
            $item['deliver_time'] = isset($deliver_arr[$item['deliver_time']]) ?
                $deliver_arr[$item['deliver_time']] : '';
            $item['deliver_date'] = date('Y/m/d', $item['deliver_date']);
            $item['pick_number'] = isset($pick_dict[$item['pick_task_id']]) ? (C('barcode.prefix.picking') . $pick_dict[$item['pick_task_id']]['pick_number']) : '';
            $item['city_name'] = isset($city_dict[$item['city_id']]) ? $city_dict[$item['city_id']] : '';
            $item['site_name'] = $item['site_src'] == C('site.dachu') ? '大厨' : '大果';

            //用户相关
            $user_id                 = $item['user_id'];
            $order_user              = $user_map[$user_id];
            $item['deliver_addr']    = $order_user['address'];
            $item['mobile']          = $order_user['mobile'];
            $item['shop_name']       = $order_user['shop_name'];
            $item['realname']        = $order_user['name'];
            $item['geo']             = json_encode(['lng' => $order_user['lng'], 'lat' => $order_user['lat']]);
            $item['address']         = $order_user['address'];
            $item['line']            = isset($line_map[$item['line_id']]) ? $line_map[$item['line_id']] : '';
            $line_id = $item['line_id'];
            $item['warehouse_id']    = isset($line_to_warehouse[$line_id]) ? $line_to_warehouse[$line_id] : '';
            $item['customer_type_name'] = isset($customer_type_dict[$order_user['customer_type']]) ? $customer_type_dict[$order_user['customer_type']] : '';

            //bd信息
            $invite_id = $order_user['invite_id'];
            $bd_info = isset($bd_map[$invite_id]) ? $bd_map[$invite_id] : [];
            $bd_info['role'] = 'BD';
            $item['bd'] = $bd_info;
            $am_info = isset($bd_map[$order_user['am_id']]) ? $bd_map[$order_user['am_id']] : [];
            $am_info['role'] = 'AM';
            $item['am'] = $am_info;
            if($order_user['invite_id'] == C('customer.public_sea_code')) {
                $item['sale'] = ['role' => '公海客户', 'name' => '无对应销售'];
            } elseif ($order_user['status'] == C('customer.status.allocated.code')) {
                $item['sale'] = $am_info;
            } else {
                $item['sale'] = $bd_info;
            }


            //订单状态
            $status            = $item['status'];
            $item['status_cn'] = isset($this->_status_dict[$status]) ? $this->_status_dict[$status] : '';
            $order_id          = $item['id'];
            $item['detail']    = isset($detail_map[$order_id]) ? $detail_map[$order_id] : [];
            // 订单动态
            $log_list = $this->MWorkflow_log->get_lists('*', array('obj_id' => $item['id'], 'edit_type' => C('workflow_log.edit_type.order')), array('created_time' => 'asc'));

            foreach ($log_list as &$log) {
                $log['created_time'] = date('Y-m-d H:i:s', $log['created_time']);
                $log['operator_type_cn'] = isset($role_dict[$log['operator_type']]) ? $role_dict[$log['operator_type']] : '';
            }
            unset($log);
            $item['log_list'] = $log_list;
        }
        unset($item);
        return $suborder_list;
    }

    /**
     *
     * @description 子订单纬度的订单详情
     */
    public function info() {
        $where = [];
        if(!empty($_POST['suborder_id'])) {
            $where['id'] = intval($_POST['suborder_id']);
        }

        if(!empty($_POST['order_number'])) {
            $where['order_number'] = $_POST['order_number'];
        }

        if(empty($where)) {
            $arr = array(
                'status' => -1,
                'msg'    => '订单id和订单号中至少需要一个不为空'
            );
            $this->_return_json($arr);
        }

        $suborder = $this->MSuborder->get_one(
            '*',
            $where
        );

        if(empty($suborder)) {
            $res['msg'] = '没有相关的订单信息';
            $this->_return_json($res);
        }

        $suborder = $this->_format_order_list(
            array($suborder)
        );
        $suborder = $suborder[0];
        $arr = array(
            'status' => 0,
            'info'   => $suborder,
        );
        $this->_return_json($arr);

    }

    /*
     * @description 运营添加备注
     * 会把这个备注记录到workflow_log
     */
    public function add_comment() {
        if(empty($_POST['cur'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '用户信息不能为空'
                )
            );
        }
        if(empty($_POST['remark'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '备注不能为空'
                )
            );
        }

        if(empty($_POST['suborder_id'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '子订单id不能为空'
                )
            );
        }

        $cur = $_POST['cur'];
        $remark = $_POST['remark'];


        $result = $this->MWorkflow_log->record_order_comment($suborder_id, $cur, $remark);
        $this->_return_json(
            array(
                'status' => 0,
                'msg'    => '记录成功',
                'result' => $result
            )
        );
    }


    /**
     * @description 修改配送日期和配送时间
     */
    public function change_deliver_time() {
        $suborder_id = intval($_POST['suborder_id']);
        if(!$suborder_id) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '订单id不能为空'
                )
            );
        }

        $update_arr = [];
        if(!empty($_POST['deliver_date'])) {
            $update_arr['deliver_date'] = intval($_POST['deliver_date']);
        }
        if(!empty($_POST['deliver_time'])) {
            $update_arr['deliver_time'] = intval($_POST['deliver_time']);
        }

        $suborder_update_res = $this->MSuborder->update_info(
            $update_arr,
            array(
                'id' => $suborder_id
            )
        );

        $this->_return_json(
            array(
                'status'              => 0,
                'msg'                 => '更新配送时间成功',
                'suborder_update_res' => $suborder_update_res
            )
        );

    }

    //获取子订单的log
    private function _get_order_logs($order_id) {
        if(!$order_id) {
            return [];
        }
        $log_list = $this->MWorkflow_log->get_lists('*', array('obj_id' => $order_id, 'edit_type' => C('workflow_log.edit_type.order')), array('created_time' => 'asc'));

        foreach ($log_list as &$log) {
            $log['created_time'] = date('Y-m-d H:i:s', $log['created_time']);
            $log['operator_type_cn'] = isset($this->_role_dict[$log['operator_type']]) ? $this->_role_dict[$log['operator_type']] : '';
        }
        unset($log);

        return $log_list;
    }


    /*
     * @description 设置订单已发货
     */
    public function set_status_delivering() {
        if(!isset($_POST['suborder_id']) || intval($_POST['suborder_id']) <= 0) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供子订单id'
            );
            $this->_return_json($arr);
        }

        if(!isset($_POST['cur'])) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供操作者信息'
            );
            $this->_return_json($arr);
        }

        $suborder_id = intval($_POST['suborder_id']);
        $status = C('order.status.delivering.code');
        $cur = $_POST['cur'];
        $remark = empty($_POST['remark']) ? '' : $_POST['remark'];

        $suborder_update_res = $this->MSuborder->update_info(
            array(
                'status' => $status
            ),
            array(
                'id' => $suborder_id
            )
        );

        $this->MWorkflow_log->record_order($suborder_id, $status, $cur, $remark);

        if(!$suborder_update_res) {
            $arr = [
                'status' => -1,
                'msg'    => '订单状态更新失败'
            ];
            $this->_return_json($arr);
        }

        $arr = [
            'status' => 0,
            'msg'    => '订单更新成功'
        ];
        $this->_return_json($arr);
    }

    /*
     * @description 设置订单已签收
     */
    public function set_status_signed() {
        if(!isset($_POST['suborder_id']) || intval($_POST['suborder_id']) <= 0) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供子订单id'
            );
            $this->_return_json($arr);
        }

        if(!isset($_POST['cur'])) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供操作者信息'
            );
            $this->_return_json($arr);
        }

        $suborder_id = intval($_POST['suborder_id']);
        $status = C('order.status.wait_comment.code');
        $cur = $_POST['cur'];
        $remark = empty($_POST['remark']) ? '' : $_POST['remark'];

        // 更新订单详情
        $order_details = $this->input->post('order_details', TRUE);
        foreach ($order_details as $detail) {
            $data = array();
            $data['actual_price'] = $detail['actual_price'] * 100;
            $data['actual_quantity'] = $detail['actual_quantity'];
            $data['actual_sum_price'] = $detail['actual_sum_price'] * 100;
            $data['status'] = $status;
            $this->MOrder_detail->update_info($data, array('id' => $detail['id']));
        }
        // 更新订单
        $data = array();
        $data['deal_price'] = $this->input->post('deal_price', TRUE) * 100;
        $data['sign_msg'] = $this->input->post('sign_msg', TRUE);
        $data['status'] = $status;
        $suborder_update_res = $this->MSuborder->update_info(
            $data,
            array(
                'id' => $suborder_id
            )
        );

        $this->MWorkflow_log->record_order($suborder_id, $status, $cur, $remark);

        if(!$suborder_update_res) {
            $arr = [
                'status' => -1,
                'msg'    => '订单状态更新失败'
            ];
            $this->_return_json($arr);
        }

        $arr = [
            'status' => 0,
            'msg'    => '订单更新成功'
        ];
        $this->_return_json($arr);
    }


    /*
     * @description 设置订单已装车
     */
    public function set_status_loading() {
        if(!isset($_POST['suborder_id']) || intval($_POST['suborder_id']) <= 0) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供子订单id'
            );
            $this->_return_json($arr);
        }

        if(!isset($_POST['cur'])) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供操作者信息'
            );
            $this->_return_json($arr);
        }

        $suborder_id = intval($_POST['suborder_id']);
        $status = C('order.status.loading.code');
        $cur = empty($_POST['cur']) ? NULL : $_POST['cur'];
        $remark = empty($_POST['remark']) ? '' : $_POST['remark'];

        // 更新订单
        $data = array();
        $data['status'] = $status;

        // 更新订单详情
        $this->MOrder_detail->update_info($data, array('suborder_id' => $suborder_id));

        $suborder_update_res = $this->MSuborder->update_info(
            $data,
            array(
                'id' => $suborder_id
            )
        );

        $this->MWorkflow_log->record_order($suborder_id, $status, $cur, $remark);

        if(!$suborder_update_res) {
            $arr = [
                'status' => -1,
                'msg'    => '订单状态更新失败'
            ];
            $this->_return_json($arr);
        }

        $arr = [
            'status' => 0,
            'msg'    => '订单更新成功'
        ];
        $this->_return_json($arr);
    }

    /*
     * @description 设置订单已退货
     */
    public function set_status_rejected() {
        if(!isset($_POST['suborder_id']) || intval($_POST['suborder_id']) <= 0) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供子订单id'
            );
            $this->_return_json($arr);
        }

        if(!isset($_POST['cur'])) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供操作者信息'
            );
            $this->_return_json($arr);
        }

        $suborder_id = intval($_POST['suborder_id']);
        $status = C('order.status.sales_return.code');
        $cur = $_POST['cur'];
        $remark = empty($_POST['remark']) ? '' : $_POST['remark'];

        $current_suborder = $this->MSuborder->get_one(
            '*',
            array(
                'id' => $suborder_id
            )
        );
        if(!empty($current_suborder)) {
            $order_id = $current_suborder['order_id'];
            $this->_complete_main_order($order_id);
        }

        $suborder_update_res = $this->MSuborder->update_info(
            array(
                'status' => $status
            ),
            array(
                'id' => $suborder_id
            )
        );

        $this->MWorkflow_log->record_order($suborder_id, $status, $cur, $remark);

        if(!$suborder_update_res) {
            $arr = [
                'status' => -1,
                'msg'    => '订单状态更新失败'
            ];
            $this->_return_json($arr);
        }

        $arr = [
            'status' => 0,
            'msg'    => '订单更新成功'
        ];
        $this->_return_json($arr);
    }

    /*
     * @description 设置订单已回款
     *   注意：子订单全部完成时，需要把母订单也置为完成
     *   这是为了给bd算业绩更方便
     */
    public function set_status_success() {
        if(!isset($_POST['suborder_id']) || intval($_POST['suborder_id']) <= 0) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供子订单id'
            );
            $this->_return_json($arr);
        }

        if(!isset($_POST['cur'])) {
            $arr = array(
                'status' => -1,
                'msg'    => '需要提供操作者信息'
            );
            $this->_return_json($arr);
        }

        $suborder_id = intval($_POST['suborder_id']);
        $status = C('order.status.success.code');
        $cur = $_POST['cur'];
        $remark = empty($_POST['remark']) ? '' : $_POST['remark'];

        // 更新订单详情
        $order_details = $this->input->post('order_details', TRUE);
        if(!empty($order_details)) {
            foreach ($order_details as $detail) {
                $data = array();
                $data['actual_price'] = $detail['actual_price'] * 100;
                $data['actual_quantity'] = $detail['actual_quantity'];
                $data['actual_sum_price'] = $detail['actual_sum_price'] * 100;
                $data['status'] = $status;
                $this->MOrder_detail->update_info($data, array('id' => $detail['id']));
            }
        }
        // 更新订单
        $data = array();
        $curtime = $this->input->server("REQUEST_TIME");
        $data['deal_price'] = $this->input->post('deal_price', TRUE) * 100;
        $data['sign_msg'] = $this->input->post('sign_msg', TRUE);
        $data['status'] = $status;
        $data['payment_time'] = $curtime;
        $suborder_update_res = $this->MSuborder->update_info(
            $data,
            array(
                'id' => $suborder_id
            )
        );


        $this->MWorkflow_log->record_order($suborder_id, $status, $cur, $remark);

        //查看是否还有没完成的子订单，没有的话需要把母订单置为完成状态
        //并且把子订单的deal_price加起来写回到母单去
        $current_suborder = $this->MSuborder->get_one(
            'id, order_id',
            array(
                'id' => $suborder_id
            )
        );
        if(!empty($current_suborder)) {
            $order_id = $current_suborder['order_id'];
            // 更新母订单的最新回款时间
            $this->MOrder->update_info(
                ['payment_time' => $curtime],
                ['id' => $order_id]
            );
            $this->_complete_main_order($order_id);
        }

        if(!$suborder_update_res) {
            $arr = array(
                'status' => -1,
                'msg'    => '订单状态更新失败'
            );
            $this->_return_json($arr);
        }

        $arr = array(
            'status' => 0,
            'msg'    => '订单更新成功'
        );
        $this->_return_json($arr);
    }

    /**
     * @description 设置母订单状态
     */
    private function _complete_main_order($order_id = 0) {
        if(intval($order_id) <= 0) {
            return;
        }

        $suborders = $this->MSuborder->get_lists(
            '*',
            array(
                'order_id' => $order_id
            )
        );

        $deal_price_total = 0;
        $complete_flag = TRUE;
        foreach($suborders as $suborder) {
            $status = $suborder['status'];
            $deal_price = $suborder['deal_price'];
            if($status == C('order.status.success.code')) {
                $deal_price_total += $deal_price;
            }
            if($status != C('order.status.success.code') //回款
                && $status != C('order.status.closed.code') //关闭
                && $status != C('order.status.sales_return.code') //退货
              ) {
                  $complete_flag = FALSE;
            }
        }

        if($complete_flag) {
            $this->MOrder->update_info(
                array(
                    'status'     => C('order.status.success.code'),
                    'deal_price' => $deal_price_total
                ),
                array(
                    'order_id' => $order_id
                )
            );
        }
    }

    /**
     * 根据母单ID更新子单状态
     * @author zhangxiao@dachuwang.com
     */
    public function update_by_orderid () {
        try {
            $order_id    = $this->input->post('order_id');
            $pay_status = $this->input->post('pay_status');
            if ($order_id && $pay_status) {
                $sub_ids = $this->MSuborder->get_suborder_ids_by_orderid($order_id);
                if(is_array($sub_ids) && !empty($sub_ids)){
                    $result  = $this->MSuborder->update_batch_orders($sub_ids, $pay_status);
                    if ($result) {
                        return $this->_return_json(array(
                            'status' => 0,
                            'msg'    => '子单更新成功'
                        ));
                    } else {
                        throw new Exception('子单更新失败');
                    }
                }else {
                    throw new Exception('没有子单信息');
                }
            } else {
                throw new Exception('order_id and pay_status required');
            }
        } catch (Exception $e) {
            return $this->_return_json(array(
                'status' => -1,
                'msg'    => $e->getMessage()
            ));
        }
        
    }

}

/* End of file suborder.php */
/* Location: ./application/controllers/suborder.php */
