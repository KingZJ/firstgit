<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @author caochunhui@dachuwang.com
 * @description 订单service
 */
class Order extends MY_Controller {

    private $_status_dict  = [];
    private $_deliver_dict = [];

    public function __construct () {
        parent::__construct();
        $this->load->model(
            array(
                'MProduct',
                'MCustomer',
                'MOrder',
                'MOrder_detail',
                'MOrder_detail_weight',
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
                'MService_fee',
            )
        );
        $this->load->library(
            array(
                'form_validation',
                'dachu_request',
                'skip32',
                'http',
                'Order_split'
            )
        );
        // 均摊减免的函数 /data/www/dachuwang/web/applications/s/applications/helpers
        $this->load->helper(array('devide_minus'));

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

        //order_type_cn
        $order_type_config = $this->order_split->get_config();
        $codes = array_column($order_type_config, 'id');
        $msgs = array_column($order_type_config, 'type_name');
        $this->_order_type_config = array_combine($codes, $msgs);

        //pay_type_dict
        $pay_type_arr = array_values(C('payment.type'));
        $codes = array_column($pay_type_arr, 'code');
        $msgs = array_column($pay_type_arr, 'msg');
        $this->_pay_type_dict = array_combine($codes, $msgs);

        //pay_status_dict
        $pay_status_arr = array_values(C('payment.status'));
        $codes = array_column($pay_status_arr, 'code');
        $msgs = array_column($pay_status_arr, 'msg');
        $this->_pay_status_dict = array_combine($codes, $msgs);

    }

    /**
     * @description 根据订单详情id,设置货品的重量
     * @author wangshuang@dachuwang.com
     */
    public function weight_sku() {
        $weight_id = 0;
        if(!empty($_POST['data'])) {
           $data = $_POST['data'];
           $row['created_time'] = $this->input->server("REQUEST_TIME");
           $row['updated_time'] = $this->input->server("REQUEST_TIME");
           foreach($data as $val) {
               $row['order_id'] = $val['order_id'];
               $row['sub_order_id'] = $val['order_id'];//冗余子母单字段，为以后更新做准备
               $row['order_detail_id'] = $val['detail_id'];
               $weights = explode(',', $val['weights']);
               foreach($weights as $val) {
                   $row['weight'] = $val;
                   $rows[] = $row;
               }
           }

            $weight_id = $this->MOrder_detail_weight->create_batch(
                  $rows
            );
        }

        if(!$weight_id) {
            $this->_return_json(
                ['status' => -1, 'msg' => '提交失败', 'res' => "{$weight_id}"]
            );
        }
        else {
            $this->_return_json(
                ['status' => 0, 'msg' => '提交成功', 'res' => "{$weight_id}"]
            );
        }
    }

    /**
     * @description 根据波次id和sku_number获取订单详情
     * @author wangshuang@dachuwang.com
     */
    public function get_details_by_wave_and_sku() {
        if(empty($_POST['wave_id']) || empty($_POST['sku_number'])) {
            $this->_return_json(array('status'=>'-1', 'msg'=>'参数有误', 'data'=>''));
        }
        //查看特定状态的订单
       //$where['in']['status'] =array(C('order.status.confirmed.code'),C('order.status.wave_executed.code'),C('order.status.picking.code'), C('order.status.picked.code'));//待生产、波次中、待分拣、已分拣
        // 根据波次筛选
        if(!empty($_POST['wave_id'])) {
            $where['wave_id'] = $_POST['wave_id'];
        }
        $orders = $this->MOrder->get_lists(
            'id, user_id, line_id, remarks',
            $where
        );
        if(empty($orders)) {
            $this->_return_json(array('status'=>'-1', 'msg'=>'未找到该波次的待生产、波次中、待分拣或已分拣订单', 'data'=>''));
        }
        foreach($orders as $val) {
            $order_list[$val['id']] = $val;
        }

        $order_ids = array();
        foreach ($order_list as $val) {
            $order_ids[] = $val['id'];
            $order_remarks[$val['id']] = $val['remarks'];
        }
        $map['in']['order_id'] = $order_ids;
        if(!empty($_POST['sku_number'])) {
            $map['sku_number'] = $_POST['sku_number'];
        }
        $order_details = $this->MOrder_detail->get_lists(
            '*', $map
        );

        if(empty($order_details)) {
            $this->_return_json(array('status'=>'-1', 'msg'=>'此货品不属于该波次订单', 'data'=>''));
        }
        $order_ids = array();
        foreach($order_details as &$item) {
            $order_ids[] = $item['order_id'];
            $order_id = $item['order_id'];
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
        }
        foreach($order_list as $key => $val) {
            if(!in_array($key,$order_ids)) {
               unset($order_list[$key]);
            }
        }
        $user_ids = array_column($order_list, 'user_id');
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
        // 批量取出线路信息
        $line_ids = array_column($order_list, 'line_id');

        $line_list = $this->MLine->get_lists('id, name', array(
            'status' => C('status.common.success'),
            'in' => [
                'id' => $line_ids
            ]
        ));
        $line_ids = array_column($line_list, 'id');
        $line_names = array_column($line_list, 'name');
        $line_map = array_combine($line_ids, $line_names);

        foreach ($order_details as &$item) {
            $item['remarks'] = $order_remarks[$item['order_id']];
            $line = $line_map[$order_list[$item['order_id']]['line_id']];
            $item['line'] = isset($line) ? $line : '';
            $order_user   = $user_map[$order_list[$item['order_id']]['user_id']];
            $item['deliver_addr']    = $order_user['address'];
            $item['mobile']          = $order_user['mobile'];
            $item['shop_name']       = $order_user['shop_name'];
            $item['realname']        = $order_user['name'];
            //$item['geo']             = $order_user['geo'];
            $item['address']         = $order_user['address'];
        }
        $this->_return_json(array('status'=>'0', 'msg'=>'查询成功', 'data'=>$order_details));
    }

    /**
     * 获取配送时间
     * @since 2015-03-17
     */
    public function deliver_dropdown() {
        $site_id = $this->input->post('site_id');
        $deliver_time_list = C('order.deliver_time');
        $deliver_time_guo_list = C('order.deliver_time_guo');
        $request_time = $this->input->server('REQUEST_TIME');
        $daily_deliver = $this->input->post('daily_deliver'); // 是否开启当日到达

        if(empty($deliver_time_list) || empty($deliver_time_guo_list)) {
            $res = [
                'status' => C('status.req.failed'),
                    'msg'    => '获取配送时间列表失败'
                ];
            $this->_return_json($res);
        }

        $deliver_arr = [];
        // 23点截单，23点以后下单只能从后天开始选择
        $hour = intval(date('H', $request_time));
        if($hour < 23) {
            $incre = 1;
        } else {
            $incre = 2;
        }

        // 设置大厨和大果的配送时间段
        if($site_id == C('site.daguo')) {
            // 大果,上海的配送时间与北京不一致，临时配置解决
            if(isset($_POST['province_id']) && $_POST['province_id'] == C('open_cities.shanghai.id')) {
                $time = array(
                    C('order.deliver_time_guo.shanghai_early')
                );
            } else {
                $time = array(
                    C('order.deliver_time_guo.early')
                );
            }
        } else {
            // 大厨,天津只有上午配送
            if(isset($_POST['province_id']) && $_POST['province_id'] == C('open_cities.tianjing.id')) {
                $time = array(
                    C('order.deliver_time.early')
                );
            } else {
                $time = array(
                    C('order.deliver_time.early'),
                    C('order.deliver_time.late')
                );
            }
        }

        $start_time = strtotime(date('Y-m-d', $request_time)) + 86400 * $incre;

        // 如果是上午下单，可以选择下午配送
        if($daily_deliver && $site_id == C("site.dachu")) {
            if($hour == 23) {
                $deliver_arr[] = array(
                    'name' => date('Y/m/d', $request_time + 86400), // 取明天
                    'val'  => strtotime(date('Y-m-d', $request_time + 86400)),
                    'time' => array( // 明天只能下午
                        C('order.deliver_time.late')
                    )
                );
            } else if($hour < 11) {
                $deliver_arr[] = array(
                    'name' => date('Y/m/d', $request_time), // 取当天
                    'val'  => strtotime(date('Y-m-d', $request_time)),
                    'time' => array( // 当天只能下午
                        C('order.deliver_time.late')
                    )
                );
            }
        }
        $deliver_arr[] = array(
            'name' => date('Y/m/d', $start_time),
            'val'  => $start_time,
            'time' => $time
        );

        // 大厨可配送日期为7天，大果为1天
        if($site_id == C('site.dachu')) {
            for ($i=1; $i<=6; $i++) {
                $deliver_time = $start_time + 86400 * $i;
                $deliver_arr[] = array(
                    'name' => date('Y/m/d', $deliver_time),
                    'val'  => $deliver_time,
                    'time' => $time
                );
            }
        }

        $res = [
            'status' => C('status.req.success'),
                'msg'    => '获取成功',
                'list'   => $deliver_arr
            ];
        $this->_return_json($res);
    }

    private function _user_today_order_count_by_cate() {
        $user_id = empty($_POST['user_id']) ? 0 : intval($_POST['user_id']) ;
        $request_time = $this->input->server('REQUEST_TIME');
        $start = strtotime(date('Ymd', $request_time));
        $end = $start + 86400;
        $res = $this->MOrder->get_lists(
            'order_type, count(*) cnt',
            array(
                'user_id'         => $user_id,
                'created_time >=' => $start,
                'created_time <'  => $end
            ),
            array(),
            array('order_type')
        );
        $final_res = array();
        $res_map = array_column($res, "cnt", "order_type");
        $order_type_config = $this->order_split->get_config();
        foreach($order_type_config as $item) {
            $final_res[] = array(
                'order_type' => $item['id'],
                'cnt'        => !empty($res_map[$item['id']]) ? $res_map[$item['id']] : 0,
            );
        }
        return $final_res;
    }

    private function _user_today_order_count() {
        $user_id = empty($_POST['user_id']) ? 0 : intval($_POST['user_id']) ;
        $request_time = $this->input->server('REQUEST_TIME');
        $start = strtotime(date('Ymd', $request_time));
        $end = $start + 86400;
        $res = $this->MOrder->get_one(
            'count(*) cnt',
            array(
                'user_id'         => $user_id,
                //'status !='       => 0,
                'created_time >=' => $start,
                'created_time <'  => $end
            )
        );
        $count = empty($res['cnt']) ? 0 : $res['cnt'];
        return $count;
    }

    /**
     * @description 用户下单当天的总订单数
     */
    public function user_today_order_count() {
        $user_id = empty($_POST['user_id']) ? 0 : intval($_POST['user_id']) ;
        if(!$user_id) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg' => '用户未登录'
                )
            );
        }

        $count = $this->_user_today_order_count();
        $this->_return_json(
            array(
                'status' => 0,
                'info' => array(
                    'count' => $count
                )
            )
        );
    }

    /**
     * 为了支持鸡蛋押金的特殊需求，需要做特殊处理，之后要删掉特殊处理逻辑。
     */
    private function _calc_total_price($valid_products = array()) {
        //计算total_price
        $total_price = 0;

        foreach($valid_products as $item) {
            $product_id    = $item['id'];
            $price         = $item['price'];
            $quantity      = $item['quantity'];
            $sum_price     = $price * $quantity;
            $total_price  += $sum_price;
            $sku_number    = $item['sku_number'];
        }

        return $total_price;
    }

    //检测下单用户是否合法
    private function _check_order_user_valid() {
        //user数据处理
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : 0;
        $user = $this->MCustomer->get_one(
            '*',
            [
                'id' => $user_id
            ]
        );

        if(empty($user)) {
            $arr = array(
                'status' => -1,
                'msg'    => '订单创建失败，用户不存在',
            );
            $this->_return_json($arr);
        }

        return $user;
    }

    //检查下单时间是否合法，比如两次下单间隔不能超过30s
    private function _check_order_time_valid($user_id = 0) {
        //用户下单间隔时间要超过30秒
        $last_order = $this->MOrder->get_one(
            'max(created_time) created_time',
            array(
                'status !=' => 0,
                'user_id'   => $user_id
            )
        );

        $last_order_created_time = intval($last_order['created_time']);

        $time_gap = $this->input->server('REQUEST_TIME') - $last_order_created_time;
        if($time_gap <= 30) {
            $this->_return_json(
                $arr = array(
                    'status' => -1,
                    'msg'    => '两次下单的间隔时间太短，请稍后再试',
                )
            );
        }
    }

    //获取订单对应的销售
    private function _get_order_salesman($user = array()) {
        // 根据客户状态不同获取客户所属销售
        if($user['status'] == C('customer.status.allocated.code')) {
            $sale = $this->MUser->get_one('*', array('id' => $user['am_id']));
        } else {
            $sale = $this->MUser->get_one('*', array('id' => $user['invite_id']));
        }
        if(empty($sale)) {
            $sale = array(
                'id'      => 0,
                'role_id' => 0
            );
        }
        return $sale;
    }

    //检查订单中的商品是否合法
    private function _check_order_products_valid() {

        if(empty($_POST['products'])) {
            $arr = array(
                'status' => -1,
                'msg'    => '下单商品列表为空'
            );
            $this->_return_json($arr);
        }

        $post_products = $_POST['products'];
        $post_product_ids = array_column($post_products, "id");

        if(empty($post_product_ids)) {
            $arr = array(
                'status' => -1,
                'msg'    => '订单创建失败，请先选择想要预定的商品',
            );
            $this->_return_json($arr);
        }

        $post_product_quantity = array_column($post_products, "quantity", "id");

        //products      => 全部商品
        //products_up   => 上架商品
        //products_down => 下架商品
        $db_products = $this->MProduct->gets_by(
            array('id'),
            array($post_product_ids)
        );
        if(empty($db_products)) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '所选商品在数据库中不存在'
                )
            );
        }

        $db_product_ids = array_column($db_products, 'id');
        $db_product_map = array_combine($db_product_ids, $db_products);
        foreach($post_products as &$item) {
            $product_id = $item['id'];

            if(!isset($db_product_map[$product_id]))
            {
                $item['status']      = 4;
                $item['price_in_db'] = 0;
                continue;
            }
            $prod_in_db = $db_product_map[$product_id];
            if($prod_in_db['status'] != C('status.product.up')) {
                $item['status']      = 4;
                $item['price_in_db'] = 0;
                continue;
            }
            $item['price_in_db'] = $prod_in_db['price']/100;

            $item['status'] = 0;
            if($item['price']  > $item['price_in_db']) {
                $item['status'] = 3;
            }
            if($item['price']  < $item['price_in_db']) {
                $item['status'] = 2;
            }
        }
        unset($item);

        //检查是不是状态不正常的商品
        foreach($post_products as $item) {
            if($item['status'] != 0) {
                $arr = array(
                    'status'   => -1,
                    'msg'      => '订单创建失败',
                    'products' => $post_products
                );
                $this->_return_json($arr);
            }
        }

        foreach($db_product_map as $product_id => $product_info) {
            $db_product_map[$product_id]['quantity'] = $post_product_quantity[$product_id];
        }

        $valid_products = array_values($db_product_map);

        return $valid_products;
        //其实就是往db_product里面再塞一个quantity
    }

    //按照商品是否有冷冻进行分组
    //之后可能独立为模块。
    private function _group_products_by_type($post_products = array()) {
        return $this->order_split->group_products(
            $_POST['user_id'], $post_products
        );
        /*
        //下面是以前的产品拆分
        //获取数据库里的冻品id列表，爆款
        $frozen_category_code = C('category.category_type.frozen.code');
        $top_selling_category_code = C('category.category_type.top_selling.code');
        $db_frozen_categories = $this->MCategory->get_lists(
            'id',
            array(
                'like' => array(
                    //写配置文件
                    'path' => ".{$frozen_category_code}.",
                )
            )
        );

        $db_top_selling_categories = $this->MCategory->get_lists(
            'id',
            array(
                'like' => array(
                    'path' => ".{$top_selling_category_code}.",
                )
            )
        );

        $db_frozen_category_ids = empty($db_frozen_categories) ? [] : array_column($db_frozen_categories, 'id');
        $db_top_selling_category_ids = empty($db_top_selling_categories) ? [] :array_column($db_top_selling_categories, 'id');

        $post_product_ids = array_column($post_products, "id");
        $db_frozen_products = [];
        $db_frozen_products_ids = [];
        $db_top_selling_products = [];
        $db_top_selling_products_ids = [];
        if(!empty($db_frozen_category_ids)) {
            $db_frozen_products = $this->MProduct->get_lists(
                'id',
                array(
                    'in' => array(
                        'id'          => $post_product_ids,
                        'category_id' => $db_frozen_category_ids
                    )
                )
            );
            $db_frozen_products_ids = array_column($db_frozen_products, 'id');
        }

        if(!empty($db_top_selling_category_ids)) {
            $db_top_selling_products = $this->MProduct->get_lists(
                'id',
                array(
                    'in'              => array(
                        'id'          => $post_product_ids,
                        'category_id' => $db_top_selling_category_ids
                    )
                )
            );
            $db_top_selling_products_ids = array_column($db_top_selling_products, 'id');
        }


        //按照冻品和非冻品分组，还有爆款的商品也要拆单
        $grouped = [];
        $normal_products = [];
        $frozen_products = [];
        $top_selling_products = [];
        foreach($post_products as $item) {
            if(in_array($item['id'], $db_top_selling_products_ids)) {
                $top_selling_products[] = $item;
            } else {
                $normal_products[] = $item;
            }
        }

        $grouped = array(
            C('order.order_type.normal.code')      => $normal_products,
           // C('order.order_type.frozen.code')      => $frozen_products,
            C('order.order_type.top_selling.code') => $top_selling_products
        );
        return $grouped;
        */
    }

    //创建订单和订单详情
    private function _create_order_and_detail($order_info) {
        //订单内容
        $user               = $order_info['user'];
        $sale               = $order_info['sale'];
        $remarks            = $order_info['remarks'];
        $line_id            = $order_info['line_id'];
        $city_id            = $order_info['city_id'];
        $deliver_date       = $order_info['deliver_date'];
        $deliver_time       = $order_info['deliver_time'];
        $total_price        = $order_info['total_price'];
        $minus_amount       = $order_info['minus_amount'];
        $site_id            = $order_info['site_src'];
        $location_id        = $order_info['location_id'];
        $products           = $order_info['products'];
        $pay_type           = $this->_get_pay_type();
        $deliver_fee        = $order_info['deliver_fee'];
        $pay_reduce         = $this->_calc_pay_reduce($pay_type, $total_price - $minus_amount ,$user);
        //TODO
        //$promotion_ids      = $order_info['promotion_ids'];
        $customer_coupon_id = $order_info['customer_coupon_id'];
        //TODO
        $customer_type      = !empty($user['customer_type']) ? $user['customer_type'] : C('customer.type.normal.value');
        //母订单部分
        $main_order = array(
            'username'           => $user['name'],
            'user_id'            => $user['id'],
            'order_number'       => date("YmdHis") . mt_rand(1111, 9999),
            'remarks'            => $remarks,
            'line_id'            => $line_id,
            'city_id'            => $city_id,
            'deliver_time'       => $deliver_time,
            'deliver_date'       => $deliver_date,
            'created_time'       => $this->input->server('REQUEST_TIME'),
            'updated_time'       => $this->input->server('REQUEST_TIME'),
            'total_price'        => $total_price,
            'minus_amount'       => $minus_amount,
            'deal_price'         => 0,
            'site_src'           => $site_id,
            'location_id'        => $location_id,
            'status'             => C('order.status.wait_confirm.code'),
            'sale_id'            => $sale['id'],
            'sale_role'          => $sale['role_id'],
            'pay_type'           => $pay_type,
            'pay_reduce'         => $pay_reduce,
            'deliver_fee'        => $deliver_fee,
            'final_price'        => $total_price - $minus_amount + $deliver_fee - $pay_reduce,
            // 'promotion_ids'      => $promotion_ids,
            'customer_coupon_id' => $customer_coupon_id,
            'customer_type'      => $customer_type,
        );

        $order_id = $this->MOrder->create($main_order);

        //子订单部分
        $suborders = $order_info['suborders'];
        foreach($suborders as $suborder) {
            $suborder_info = array(
                'username'     => $user['name'],
                'user_id'      => $user['id'],
                'order_number' => date("YmdHis") . mt_rand(1111, 9999),
                'remarks'      => $remarks,
                'line_id'      => $line_id,
                'city_id'      => $city_id,
                'order_id'     => $order_id, //母订单id
                'deliver_time' => $deliver_time,
                'deliver_date' => $deliver_date,
                'created_time' => $this->input->server('REQUEST_TIME'),
                'updated_time' => $this->input->server('REQUEST_TIME'),
                'total_price'  => $suborder['total_price'],
                'minus_amount' => $suborder['minus_amount'],
                'deal_price'   => 0,
                'site_src'     => $site_id,
                'location_id'  => $location_id,
                'status'       => C('order.status.wait_confirm.code'),
                'sale_id'      => $sale['id'],
                'sale_role'    => $sale['role_id'],
                'pay_type'     => $pay_type,
                'order_type'   => $suborder['order_type'],
                'deliver_fee'  => $suborder['deliver_fee'],
                'final_price'  => $suborder['total_price'] - $suborder['minus_amount'] + $suborder['deliver_fee'],
                //'promotion_ids' => $suborder['promotion_ids']
                'customer_type'      => $customer_type,
            );
            $suborder_id = $this->MSuborder->create($suborder_info);
            $new_suborder_number = $this->skip32->get_serial_no($suborder_id);
            //记子订单日志
            $this->MWorkflow_log->record_order($suborder_id, C('order.status.wait_confirm.code'), $_POST['cur']);

            //更新订单号为skip32式
            $this->MSuborder->update_info(
                array(
                    'order_number' => $new_suborder_number
                ),
                array(
                    'id' => $suborder_id
                )
            );

            //插入订单详情表
            $details = [];
            foreach($suborder['products'] as $item) {
                $details[] = array(
                    'order_id'      => $order_id, //母订单id
                    'suborder_id'   => $suborder_id, //子订单id
                    'price'         => $item['price'],
                    'unit_id'       => $item['unit_id'],
                    'city_id'       => $city_id,
                    'close_unit'    => $item['close_unit'],
                    'single_price'  => $item['single_price'],
                    'sku_number'    => $item['sku_number'],
                    'product_id'    => $item['id'],
                    'category_id'   => $item['category_id'],
                    'quantity'      => $item['quantity'],
                    'sum_price'     => $item['price'] * $item['quantity'],
                    'status'        => C('order.status.wait_confirm.code'),
                    'name'          => $item['title'],
                    'spec'          => $item['spec'],
                    //'minus_amount'  => $item['minus_amount'],
                    //'promotion_ids' => $item['promotion_ids'],
                    'created_time'  => $this->input->server('REQUEST_TIME'),
                    'updated_time'  => $this->input->server('REQUEST_TIME')
                );
            }
            $this->MOrder_detail->create_batch($details);
        }

        if(!$order_id) {
            $arr = [
                'status' => -1,
                'msg'    => '订单创建失败'
            ];
            $this->_return_json($arr);
        }
        $arr = array(
            $order_id, $main_order['order_number']
        );

        return $arr;
    }
    //  优惠券减免
    private function _fill_coupon_minus_amount($order_info) {
        $total_price        = $order_info['total_price'];
        $minus_amount       = $order_info['minus_amount'];
        if(!empty($_POST['coupon_info'])) {
            $new_minus_amount = $minus_amount + $_POST['coupon_info']['minus_amount'];
            if($new_minus_amount <= $total_price) {
                $order_info['minus_amount'] = $new_minus_amount;
            }
        }

        $order_info['suborders'] = devide_minus($order_info['suborders'], $total_price, $order_info['minus_amount']);
        foreach($order_info['suborders'] as &$suborder) {
            foreach($suborder['products'] as &$product) {
                $product['total_price'] = $product['price'] * $product['quantity'];
            }
            // 通过sku得到每个子订单可均摊的优惠和参加的促销活动编号
            $suborder['products'] = devide_minus($suborder['products'], $suborder['total_price'], $suborder['minus_amount']);
        }
        unset($suborder);
        return $order_info;
   }

    /**
     * @author caochunhui@dachuwang.com
     * @description
     *
     */
    private function _get_rule($order_type) {
        $rules = empty($this->promotion_rules) ? [] : $this->promotion_rules;
        foreach($rules as $rule) {
            if($rule['order_type'] == $order_type) {
                return $rule;
            }
        }
        return [];
    }

    /**
     * @description 设置母订单/子订单的运费、总价等
     * @author caochunhui@dachuwang.com
     */
    private function _fill_order_extra_info($order_info = array()) {
        $deliver_fee = 0;
        $minus_amount = 0;
        $city_id = $order_info['city_id'];
        $site_id = $order_info['site_src'];
        //运费
        $deliver_fee_rule = $this->MDeliver_fee->get_one(
            '*',
            array(
                'city_id' => $city_id,
                'site_id' => $site_id,
                'status'  => 1
            )
        );

        $total_price = $order_info['total_price'];
        if(!empty($deliver_fee_rule)) {
            //预计算，需要按照货品分组前的总货品计算一次运费，要么拆单后就没法算运费了！ //算出的运费需要挂在非冻品的订单上，切记切记
            //如果订单总金额小于规定值，需要收取运费
            if($total_price < $deliver_fee_rule['free_amount']) {
                $deliver_fee = $deliver_fee_rule['fee'];//运费，根据city，site_id从配置里读
            }
        }

        //把运费挂在第一个子单。！
        $order_info['deliver_fee']  = $deliver_fee;
        if(!empty($order_info['suborders'])) {
            $order_info['suborders'][0]['deliver_fee'] = $deliver_fee;
        }

        return $order_info;
    }

    /**
     * @description 填充母订单和子订单的价格
     */
    private function _fill_order_total_price($order_info = array()) {
        $total_price = $this->_calc_total_price($order_info['products']);
        $order_info['total_price'] = $total_price;
        foreach($order_info['suborders'] as &$suborder) {
            $total_price = $this->_calc_total_price($suborder['products']);
            $suborder['total_price'] = $total_price;
        }
        unset($suborder);
        return $order_info;
    }

    private function _fill_order_minus_price($order_info = array()) {
        foreach($order_info['suborders'] as &$suborder) {
            // 通过sku得到每个子订单可均摊的优惠和参加的促销活动编号
            $minus_amount = 0;
            $promotion_ids = array();
            foreach($suborder['products'] as &$prod) {
                $prod['promotion_ids'] = !empty($prod['promotion_ids']) ? $prod['promotion_ids'] : array();
                $prod['minus_amount'] = !empty($prod['minus_amount']) ? $prod['minus_amount'] : 0;
                $minus_amount += !empty($prod['minus_amount']) ? $prod['minus_amount'] : 0;
                $promotion_ids = array_merge($promotion_ids, $prod['promotion_ids']);
                $prod['promotion_ids'] = implode(",", $prod['promotion_ids']); // 转成逗号隔开的id，用于存储
            }
            $suborder['minus_amount'] = $minus_amount;
            $suborder['promotion_ids'] = implode(",", $promotion_ids);
        }
        unset($suborder);
        return $order_info;
    }

    /**
     * @description 拆子订单，填充子订单信息
     * @author caochunhui@dachuwang.com
     */
    private function _fill_suborder($order_info = array()) {
        $valid_products = $order_info['products'];
        $order_info['suborders'] = array();

        //订单中的货品分组，这里之后需要拆成一个模块
        $grouped_products = $this->_group_products_by_type($valid_products);
        foreach($grouped_products as $order_type => $prods) {
            if(empty($prods)) {
                continue;
            }

            $suborder = array(
                'products'     => $prods,
                'minus_amount' => 0,
                'promotion_ids'=> "",
                'total_price'  => 0,
                'order_type'   => $order_type,
                'deliver_fee'  => 0,
            );

            $order_info['suborders'][] = $suborder;

        }

        return $order_info;
    }

    /**
     * @description 拼接符合的活动编号列表，粒度是sku
     * @author caiyilong@dachuwang.com
     * TODO 这段代码临时解决子母单问题，之后要删除并优化
     */
    private function _fill_order_promotion_info($order_info = array(), $rules = array(), $trick_rules = array()) {
        $products = $order_info['products'];
        $order_info['promotion_ids'] = array();
        $order_info['minus_amount'] = 0;
        $cat_ids = array_column($products, "category_id");
        $cat_list = $this->MCategory->get_lists(
            "id, path",
            array(
                'in' => array(
                    'id' => $cat_ids
                )
            )
        );
        $path_map = array_column($cat_list, "path", "id");
        $all_minus_amount = 0;
        if(!empty($rules)) {
            foreach($rules as $rule) {
                $is_all = 0; // 看看是不是全场活动
                if(empty($rule['category_ids'])) {
                    $is_all = 1;
                } else {
                    $cat_ids = explode($rule['category_ids']);
                }
                $minus_amount = json_decode($rule['rule_desc'], TRUE);
                $minus_amount = $minus_amount['return_profit'];
                $order_info['minus_amount'] += $minus_amount;
                $all_amount = 0;
                $match_key = array();
                $order_info['promotion_ids'][] = $rule['id'];
                foreach($products as $key => &$item) {
                    $item['promotion_ids'] = !empty($item['promotion_ids']) ? $item['promotion_ids'] : array();
                    $item['minus_amount'] = !isset($item['minus_amount']) ? 0 : $item['minus_amount'];
                    if($is_all) {
                        $item['promotion_ids'][] = $rule['id'];
                        $match_key[] = $key;
                        $all_amount += $item['quantity'] * $item['price'];
                        continue;
                    }
                    foreach($cat_ids as $cat) {
                        if(strpos($path_map[$item['category_id']], $cat) !== FALSE) {
                            $item['promotion_ids'][] = $rule['id'];
                            $match_key[] = $key;
                            $all_amount += $item['quantity'] * $item['price'];
                            break;
                        }
                    }
                }
                unset($item);
                $now_minus_amount = $minus_amount;
                foreach($match_key as $i => $key) {
                    if($i != count($match_key)) {
                        $prod_price = $products[$key]['quantity'] * $products[$key]['price'];
                        // 向下取整，尽量避免优惠额度有小数的情况
                        $products[$key]['minus_price'] = floor($prod_price / $all_amount * $minus_amount);
                        $now_minus_amount -= $products[$key]['minus_price'];
                    } else {
                        $products[$key]['minus_price'] = $now_minus_amount;
                    }
                }
            }
        }
        if(!empty($trick_rules)) {
            foreach($trick_rules as $key => $rule) {
                $is_all = 0;
                if(empty($rule['id'])) {
                    $is_all = 1;
                }
                $minus_amount = $rule['minus_amount'];
                $order_info['minus_amount'] += $minus_amount;
                $all_amount = 0;
                $match_key = array();
                foreach($products as $k => &$item) {
                    $item['minus_amount'] = !isset($item['minus_amount']) ? 0 : $item['minus_amount'];
                    $item['trick_rule_ids'] = !empty($item['trick_rule_ids']) ? $item['trick_rule_ids'] : array();
                    if($is_all) {
                        $item['trick_rule_ids'][] = $key;
                        $match_key[] = $k;
                        $all_amount = $item['quantity'] * $item['price'];
                        continue;
                    }
                    foreach($rule['id'] as $cat) {
                        if(strpos($path_map[$item['category_id']], $cat) !== FALSE) {
                            $item['trick_rule_ids'][] = $key;
                            $match_key[] = $k;
                            $all_amount = $item['quantity'] * $item['price'];
                            break;
                        }
                    }
                }
                unset($item);
                $now_minus_amount = $minus_amount;
                foreach($match_key as $i => $k) {
                    if($i != count($match_key)) {
                        $prod_price = $products[$k]['quantity'] * $products[$k]['price'];
                        // 向下取整，尽量避免优惠额度有小数的情况
                        $products[$k]['minus_price'] = floor($prod_price / $all_amount * $minus_amount);
                        $now_minus_amount -= $products[$k]['minus_price'];
                    } else {
                        $products[$k]['minus_price'] = $now_minus_amount;
                    }
                }
            }
        }
        $order_info['promotion_ids'] = implode(",", $order_info['promotion_ids']);
        $order_info['products'] = $products;
        return $order_info;
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 创建订单
     */
    public function add() {
        //用户合法性判断
        $user = $this->_check_order_user_valid();

        //下单时间合法性判断
        $this->_check_order_time_valid();

        //取到用户的邀请人信息
        $sale = $this->_get_order_salesman($user);

        $valid_products = $this->_check_order_products_valid();

        //用户所属线路
        $user_id      = $user['id'];
        $line_id      = $user['line_id'];
        $city_id      = $user['province_id'];
        $site_id      = isset($_POST['site_id']) ? $_POST['site_id'] : C('site.dachu');
        $remarks      = isset($_POST['remarks']) ? $_POST['remarks'] : '';
        $deliver_time = isset($_POST['deliver_time']) ? $_POST['deliver_time'] : '';
        $deliver_date = isset($_POST['deliver_date']) ? $_POST['deliver_date'] : '';
        $location_id  = isset($_POST['location_id']) ? $_POST['location_id'] : C('open_cities.beijing.id');

        $order_info = array(
            'user'               => $user,
            'remarks'            => $remarks,
            'line_id'            => $line_id,
            'city_id'            => $city_id,
            'deliver_time'       => $deliver_time,
            'deliver_date'       => $deliver_date,
            'total_price'        => 0,
            'minus_amount'       => 0,
            'site_src'           => $site_id,
            'location_id'        => $location_id,
            'sale'               => $sale,
            'products'           => $valid_products,
            //'promotion_ids'      => "",
            'customer_coupon_id'    => empty($_POST['coupon_info']) ? 0 : $_POST['coupon_info']['id'],
            'deliver_fee'        => 0,
        );

        // 根据活动类型，决定是否要均摊活动优惠费用
        // 目前只有满减才需要均摊
        $order_info = $this->_fill_order_promotion_info($order_info);
        // 拆分订单
        $order_info = $this->_fill_suborder($order_info);
        // 计算子单的总价和优惠和促销活动id
        $order_info = $this->_fill_order_total_price($order_info);
        $order_info = $this->_fill_order_minus_price($order_info);
        // 计算优惠券减免金额
        $order_info = $this->_fill_coupon_minus_amount($order_info);
        // 计算子单的运费
        $order_info = $this->_fill_order_extra_info($order_info);
        // 创建订单和详情
        list($order_id, $order_number) = $this->_create_order_and_detail($order_info);


        $arr = array(
            'status'   => 0,
            'msg'      => '订单创建成功',
            'number'   => $order_number,
            'order_id' => $order_id,
        );

        // 如果下单时用户状态为已注册未下单，则更新状态为下单未完成
        if ($user['status'] == C('customer.status.new.code')) {
            $this->MCustomer->update_info(array('status' => C('customer.status.undone.code')), array('id' => $user['id']));
        }

        // 公海用户下单需要特殊处理
        if ($user['invite_id'] == C('customer.public_sea_code')) {
            $invite_bd = $this->MUser->get_one('id, name', ['id' => $user['invite_bd'], 'role_id' => C('user.saleuser.BD.type'), 'status !=' => C('status.common.del'), 'province_id' => $user['province_id']]);
            if($invite_bd){
                // BD 在职,客户交还给原来BD
                $this->MCustomer->update_info(['status' => C('customer.status.undone.code'), 'invite_id' => $user['invite_bd']], ['id' => $user['id']]);
                // 更新订单的所属销售
                if (!empty($order_id)) {
                    $this->MOrder->update_info(['sale_id' => $user['invite_bd'], 'sale_role' => C('user.saleuser.BD.type')], ['id' => $order_id]);
                    $this->MSuborder->update_info(['sale_id' => $user['invite_bd'], 'sale_role' => C('user.saleuser.BD.type')], ['order_id' => $order_id]);
                }
            }
        }

        $this->_return_json($arr);
    }

    /**
     * 订单编辑
     * @author yugang@dachuwang.com
     * @since 2015-03-19
     */
    public function edit() {
        // 表单校验
        $this->form_validation->set_rules('id', 'ID', 'required|numeric');
        $this->form_validation->set_rules('deliver_date', '送货日期', 'required|numeric');
        $this->form_validation->set_rules('deliver_time', '送货时间', 'required|numeric');
        $this->validate_form();

        $data = array();
        $data['deliver_date'] = $_POST['deliver_date'];
        $data['deliver_time'] = $_POST['deliver_time'];
        $this->MOrder->update_info($data, array('id' => $this->input->post('id', TRUE)));
        $this->_return_json(
            array(
                'status' => C('status.req.success'),
                'msg'    => '订单修改成功',
            )
        );
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 更新订单状态
     */
    /*
    public function set_status() {
        if(!isset($_POST['status']) || !isset($_POST['order_id'])) {
            $arr = [
                'status' => -1,
                'msg'    => '需要提供订单号和订单id'
            ];
            $this->_return_json($arr);
        }

        $order_id = intval($_POST['order_id']);
        $status = intval($_POST['status']);

        $update_res = 0;
        // 签收和确认回款时需要更新订单实收数量、实收金额等
        if($status == C('order.status.success.code') || $status == C('order.status.wait_comment.code')) {
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
            $update_res = $this->MOrder->update_info($data, array('id' => $order_id));

        } else {
            $data = array();
            $data['sign_msg'] = $this->input->post('sign_msg', TRUE);
            $data['status'] = $status;

            //取消订单的话，需要清空pick_task_id和wave_id
            if($status == C('order.status.closed.code')) {
                $data['pick_task_id'] = 0;
                $data['wave_id'] = 0;
            }

            $update_res = $this->MOrder->update_info($data, array('id' => $order_id));
            if($update_res) {
                $update_res = $this->MOrder_detail->update_info(
                    array(
                        'status' => $status
                    ),
                    array(
                        'order_id' => $order_id
                    )
                );
            }
        }
        // 审核订单需要调wms系统的接口
        if ($status == C('order.status.confirmed.code')) {
            $url = C('service.wms') . '/order/addBillOut';
            $this->http->query($url, ['orderIds' => $order_id]);
        }

        if(!empty($_POST['driver'])) {
            $remark = isset($_POST['sign_msg']) ? $_POST['sign_msg'] : '';
            $cur = array('name'=>$_POST['driver'],'ip'=>' ','role_id'=>'0','id'=>$order_id);
            $this->MWorkflow_log->record_order($_POST['order_id'], $_POST['status'], $cur, $remark);
        }
        // 如果客户状态为下单未完成
        $order = $this->MOrder->get_one('*', array('id' => $order_id));
        $customer = $this->MCustomer->get_one('*', array('id' => $order['user_id']));
        if($customer['status'] == C('customer.status.undone.code') && $status == C('order.status.success.code')) {
            // 订单完成，更新客户状态为待分配
            $this->MCustomer->update_info(array('status' => C('customer.status.unallocated.code')), array('id' => $order['user_id']));
        } elseif ($customer['status'] == C('customer.status.undone.code') &&  $status == C('order.status.closed.code')) {
            // 关闭订单，更新客户状态为新注册用户
            $this->MCustomer->update_info(array('status' => C('customer.status.new.code')), array('id' => $order['user_id']));

        }

        if(!$update_res) {
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

        // 如果是审核订单，需要发送确认短信
        if($status == C('order.status.confirmed.code')) {
            $order = $this->MOrder->get_one('*', array('id' => $order_id));
            $deliver_date = date('Y年m月d日', $order['deliver_date']) . $this->_deliver_dict[$order['deliver_time']];
            $msg = $order['site_src'] == C('site.dachu') ? C('register_msg.sms_audit_chu') : C('register_msg.sms_audit_guo');
            $content = sprintf($msg, $order['order_number'], $deliver_date);
            $arr['content'] = $content;
            $order_customer = $this->MCustomer->get_one('*', array('id' => $order['user_id']));
            $arr['mobile'] = $order_customer['mobile'];
        }
        $this->_return_json($arr);

    }
     */

    /**
     * 统计每个时间段内下单总用户
     * @param unknown $stime 开始时间
     * @param unknown $etime 结束时间
     * @param unknown $tag 数据源 0默认大厨网，1大果网
     */
    public function count($tag=0,$stime=0,$etime=0){
        try {
            $count = 0 ;
            $where = array('site_src'=>$tag);
            //未传入时间，默认取总数
            if(!$stime && !$etime){
                //$count = $this->MOrder->count();
                $count = $this->MOrder->distinct_count('user_id',$where);
            }else{
                $where =array_merge(array('created_time >='=>$stime,'created_time <'=>$etime),$where);
                //$count = $this->MOrder->count($where);
                $count = $this->MOrder->distinct_count('user_id',$where);
            }
            $this->_return_json(
                array(
                    'status'  => C('status.req.success'),
                    'msg'     => 'success',
                    'data'   => $count,
                )
            );
        } catch (Exception $e) {
            $this->_return_json(
                array(
                    'status'  => C('status.req.failed'),
                    'msg'     => 'failed',
                    'data'   => $e->getMessage(),
                )
            );
        }
    }

    /**
     * 更新订单信息
     * @param unknown $datas 更新数据字段,使用serialize序列化
     * @param unknown $where where条件
     * @return boolean
     * @author yuanxiaolin@dachuwang.com
     */
    public function update(){
        $datas = unserialize(str_replace('\\', '', $this->input->post('fields')));
        $where = unserialize(str_replace('\\', '', $this->input->post('where')));
        if(!empty($datas) && !empty($where)){
            $affects = $this->MOrder->update_info($datas,$where);
        }
        if($affects){
            $this->_return_json(array('status'=>0,'msg'=>$affects));
        }else {
            $this->_return_json(array('status'=>-1,'msg'=>'update failed'));
        }
    }

    /**
     * @interface:获取在线支付订单lists
     * @method: post
     * @param int $pay_type 支付类型：0，货到付款 1，微信支付
     * @param int $pay_status 支付状态：0，未支付 1，支付成功 －1，支付失败
     * @param int $site_src 站点ID：1大厨网，2大果网
     * @param int $siteId   城市ID
     * @param string $searchValue 关键词检索
     * @param date $startTime
     * @param date $endTime
     * @param int @currentPage
     * @param string $itemsPerPage
     * @author yuanxiaolin@dachuwang.com
     */
    public function lists_online_pay(){

        $post_data['pay_type'] = $this->input->post('pay_type');//目前只有微信支付订单
        $post_data['pay_status'] = $this->input->post('pay_status');
        $post_data['site_src'] = $this->input->post('site_src');
        $post_data['cityId'] = $this->input->post('cityId');
        $post_data['searchValue'] = $this->input->post('searchValue');
        $post_data['startTime']   = $this->input->post('startTime');
        $post_data['endTime']   = $this->input->post('endTime');
        $post_data['currentPage']   = $this->input->post('currentPage');
        $post_data['itemsPerPage']   = $this->input->post('itemsPerPage');


        $where = array();
        try {
            // 配送时间筛选
            if(!empty($post_data['startTime'])) {
                $where['deliver_date >='] = strtotime($post_data['startTime']);
            }
            if(!empty($post_data['endTime'])) {
                $where['deliver_date <='] = strtotime($post_data['endTime']);
            }

            // 支付类型
            $where['pay_type'] = !empty($post_data['pay_type']) ? intval($post_data['pay_type']) : C('payment.type.weixin.code');
            $where['status !='] = C('order.status.closed.code');

            //支付状态筛选
            if ($post_data['pay_status'] != 'all') {

                if($post_data['pay_status'] == '-1'){
                    $where['pay_status'] = -1;
                }else{
                    $where['pay_status'] = $post_data['pay_status'];
                }
            }

            // 根据城市筛选
            if(!empty($post_data['cityId'])) {
                $where['location_id'] =intval($post_data['cityId']);
            }

            //查看大厨、大果的订单
            if(!empty($post_data['site_src'])) {
                $where['site_src'] = intval($post_data['site_src']);
            }

            // 客户筛选，根据姓名、手机号或订单号
            if(!empty($_POST['searchValue'])) {
                // 如果输入的为大于11位的数字，按照订单号查询
                if(preg_match("/^\d{12,}$/", $post_data['searchValue'])) {
                    $where['like'] = array('order_number' => $post_data['searchValue']);
                } else if (preg_match("/^\d+$/", $post_data['searchValue'])){
                    $where['id'] = $post_data['searchValue'];
                } else {
                    $where['like'] = array('username' => $post_data['searchValue']);
                }
            }
            $order_by['created_time'] = 'DESC';
            $offset = ($post_data['currentPage']-1)*$post_data['itemsPerPage'];
            $result = $this->MOrder->get_lists(array('*'),$where,$order_by,array(),$offset,$post_data['itemsPerPage']);
            $config_pay = C('payment');
            $count['count_waitting']['total'] = $this->MOrder->count(array_merge($where,array('pay_status' => $config_pay['status']['waiting']['code'])));
            $count['count_waitting']['code'] = $config_pay['status']['waiting']['code'];
            $count['count_success']['total'] = $this->MOrder->count(array_merge($where,array('pay_status' => $config_pay['status']['success']['code'])));
            $count['count_success']['code'] = $config_pay['status']['success']['code'];
            $count['count_failed']['total'] = $this->MOrder->count(array_merge($where,array('pay_status' => $config_pay['status']['failed']['code'])));
            $count['count_failed']['code'] = $config_pay['status']['failed']['code'];
            if (isset($where['pay_status'])){
                unset($where['pay_status']);
            }
            $count['count_all']['total'] = $this->MOrder->count(array_merge($where));
            $count['count_all']['code'] = 'all';

            $this->_return_json(array('status'=>0,'msg'=>$result,'count'=>$count));

        } catch (Exception $e) {
            $this->_return_json(array('status'=>－1,'msg'=>$e->getMessage()));
        }
    }
    /**
     * 通过order_number或者order_id 获取订单信息
     * @throws Exception
     * @author yuanxiaolin@dachuwang.com
     */
    public function get_order(){

        $order_number = $this->input->post('order_number');
        $order_id = $this->input->post('order_id');
        
        try {
            if (!empty($order_number)) {
                $where['order_number'] = $order_number;
            } else if (!empty($order_id)){
                $where['id'] = $order_id;
            } else {
                throw new Exception('order_bumber or order_id required,but empty be given');
            }
            $result = $this->MOrder->get_one(array('*'),$where);
            $this->_return_json(
                array(
                    'status'  => C('status.req.success'),
                    'msg'	 => 'success',
                    'data'	=> $result,
                )
            );
        } catch (Exception $e) {
            $this->_return_json(
                array(
                    'status'  => C('status.req.failed'),
                    'msg'	 => 'failed',
                    'data'   => $e->getMessage(),
                )
            );
        }
    }

    /** 判断post数据：支付类型
     * @author zhangxiao@dachuwang.com
     */
    private function _get_pay_type() {
        $pay_type = isset($_POST['pay_type']) ? $_POST['pay_type'] : C('payment.type.offline.code');
        $pay_types = C('payment.type');
        $inside = FALSE;
        foreach ($pay_types as $value) {
            if($pay_type == $value['code']) {
                $inside = TRUE;
                break;
            }
        }
        if(!$inside) {
            $arr = array(
                'status' => -1,
                'msg'    => '支付方式code不正确'
            );
            $this->_return_json($arr);
        }
        return $pay_type;
    }

    private function _format_spec($spec = array()) {
        $spec_str = '';
        if(empty($spec)) {
            return $spec_str;
        }
        foreach($spec as $item) {
            if(!empty($item['name'])) {
                $spec_str .= $item['name'] . ':' . $item['val'] . ';';
            }
        }
        return $spec_str;
    }

    public function get_order_by_time() {
        $order = $this->MOrder->get_one('*', array('user_id' => $_POST['user_id'], 'created_time <' => $_POST['valid_time']));
        if($order) {
            $response = array(
                'status' => C('tips.code.op_success'),
                'info' => $order
            );
        } else {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => '没有下过单'
            );
        }
        $this->_return_json($response);
    }

    /**
     * 计算微信支付减免金额
     * @see shared/conifg/payment.php 配置活动规则
     * @param unknown $total_price
     * @param unknown $users
     * @return number
     * @author yuanxiaolin@dachuwang.com
     */
    private function _calc_pay_reduce($pay_type, $total_price, $users) {
        $events = C('payment.events');
        $citye_id = $users['province_id'];

        // 如果是KA客户，不处理支付减免
        if ($users['customer_type'] == C('customer.type.KA.value')) {
            return 0;
        }
        // 如果是货到付款的订单，不处理支付减免
        if ($pay_type == C('payment.type.offline.code')) {
            return 0;
        }

        // 如果城市未使用微信支付优惠推广，不减免
        if (empty($citye_id) || empty($events[$citye_id])) {
            return 0;
        }

        //如果活动未开始或者过期，不减免
        $event_time = $this->input->server('REQUEST_TIME');
        $event_stime = strtotime($events[$citye_id]['start_time']);
        $event_etime = strtotime($events[$citye_id]['end_time'])+86400;
        if ($event_time < $event_stime || $event_time > $event_etime) {
            return 0;
        }

        //如果活动下线，不减免
        if (!$events[$citye_id]['online']) {
            return 0;
        }

        //如果当天有下单数量，不减免
        if($this->count_today_orders($users['id'], FALSE) > 0 ){
            return 0;
        }

        // 优先使用通用减免优惠规则,以分为单位,如果减免大于总金额，不减免
        if ($events[$citye_id]['reduce'] != 0) {
            if($events[$citye_id]['reduce']*100 > $total_price){
                return  0;
            }
            return $events[$citye_id]['reduce'] * 100;
        }

        // 通用减免规则不符合再使用梯度满额减免优惠，以分为单位
        $pay_event = $events[$citye_id] ;
        $reduce_rule = $pay_event['total_reduce'];
        krsort($reduce_rule);
        if (!empty($reduce_rule) && !empty($total_price)) {
            foreach ( $reduce_rule as $key => $value ) {
                if ($total_price >= $key * 100 && $total_price > $value * 100) {
                    return $value * 100;
                }
            }
        }
        return 0;
    }

    /**
     * @description 服务费
     */
    public function get_service_fee($customer_type = 1) {
        if(!empty($_POST['customer_type'])) {
            $customer_type = intval($_POST['customer_type']);
        }

        $fee_rate = 0;
        $service_fee = $this->MService_fee->get_one(
            '*',
            array(
                'status' => 1,
                'customer_type' => $customer_type
            )
        );
        if(!empty($service_fee)) {
            $fee_rate = $service_fee['fee_rate'];
        }

        if(!empty($_POST['customer_type'])) {
            $this->_return_json(
                array(
                    'status'   => 0,
                    'fee_rate' => $fee_rate
                )
            );
        }

        return $fee_rate;
    }

    /**
     * @description 用post数据填充where数组
     */
    private function _fill_where_arr() {
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

        return $where;
    }
    /**
     *
     * @description 母订单纬度的订单列表
     */
    public function lists() {
        $page  = $this->get_page();
        $where = $this->_fill_where_arr();

        // 排序
        if (!empty($_POST['order_by'])) {
            $order_by = $_POST['order_by'];
        } else {
            $order_by = array('created_time' => 'DESC');
        }

        // 获取订单列表
        $result = $this->MOrder->get_lists(
            '*',
            $where, $order_by,
            array(), $page['offset'], $page['page_size']
        );

        // TODO 优惠券逻辑有问题
        $coupon_id    = empty($_POST['coupon_info']) ? 0 : $_POST['coupon_info']['id'];
        $total_count = $this->MOrder->count($where);

        //计算每种状态的订单数目
        //从配置文件里取道所有的code
        $status_dict = array_column(
            array_values(
                C('order.status')
            ),
            'code'
        );

        $status_dict = array(
            C('order.status.all.code'),
            C('order.status.wait_confirm.code'),
            C('order.status.success.code'),
            C('order.status.closed.code'),
        );

        //母订单只有运营关注
        //因此只需要两个状态1：全部；2：待审核
        foreach($status_dict as $v) {
            if($v != -1) {
                $where['status'] = $v;
            } else {
                unset($where['status']);
            }
            $total[$v] = $this->MOrder->count($where);
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

    private function _format_suborder_list($suborder_list = array()) {
        foreach($suborder_list as &$item) {
            //价格和时间
            $item['total_price']  = $item['total_price'] / 100;
            $item['deal_price']   = $item['deal_price'] / 100;
            $item['minus_amount'] = $item['minus_amount'] / 100;
            $item['deliver_fee']  = $item['deliver_fee'] / 100;
            $item['created_time'] = date('Y/m/d H:i', $item['created_time']);
            $item['final_price']  = $item['final_price'] / 100;

            //订单状态
            $status            = $item['status'];
            $item['status_cn'] = isset($this->_status_dict[$status]) ? $this->_status_dict[$status] : '';
            $order_id          = $item['id'];

        }
        unset($item);
        return $suborder_list;
    }

    private function _format_order_detail_list($order_detail_list = array()) {
        foreach($order_detail_list as &$item) {
            $order_id = $item['order_id'];
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
        return $order_detail_list;
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 格式化订单列表
     * @todo 需要参考product的spec来合并属性
     */
    private function _format_order_list($order_list = array()) {
        if(empty($order_list)) {
            return $order_list;
        }

        //批量取出下单用户信息
        $user_ids = array_column($order_list, 'user_id');
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

        //批量取出订单详情
        $order_ids = array_column($order_list, 'id');
        $where = [
            'in' => [ 'order_id' => $order_ids ]
        ];

        //get details
        $order_details = $this->MOrder_detail->get_lists(
            '*',
            $where
        );
        $order_details = $this->_format_order_detail_list($order_details);
        //suborder_id_to_detail_map
        $suborder_id_to_detail_map = [];
        foreach($order_details as $order_detail) {
            $suborder_id = $order_detail['suborder_id'];
            if(empty($suborder_id_to_detail_map[$suborder_id])) {
                $suborder_id_to_detail_map[$suborder_id] = array($order_detail);
            } else {
                $suborder_id_to_detail_map[$suborder_id][] = $order_detail;
            }
        }

        //get suborders
        $suborders = $this->MSuborder->get_lists(
            '*',
            $where
        );
        $suborders = $this->_format_suborder_list($suborders);
        //$order_id_to_suborder_map
        $order_id_to_suborder_map = [];
        foreach($suborders as $suborder) {
            $suborder_id = $suborder['id'];
            $suborder['details'] = empty($suborder_id_to_detail_map[$suborder_id]) ? [] : $suborder_id_to_detail_map[$suborder_id];
            $order_id = $suborder['order_id'];
            if(empty($order_id_to_suborder_map[$order_id])) {
                $order_id_to_suborder_map[$order_id] = array($suborder);
            } else {
                $order_id_to_suborder_map[$order_id][] = $suborder;
            }
        }



        //group details by suborder

        foreach($order_list as &$item) {
            //价格和时间
            $item['total_price']  = $item['total_price'] / 100;
            $item['deal_price']   = $item['deal_price'] / 100;
            $item['minus_amount'] = $item['minus_amount'] / 100;
            $item['pay_reduce'] = $item['pay_reduce'] / 100;
            $item['deliver_fee']  = $item['deliver_fee'] / 100;
            $item['created_time'] = date('Y/m/d H:i', $item['created_time']);
            $item['final_price']  = $item['final_price'] / 100;
            $deliver_arr          = $this->_deliver_dict;
            $item['deliver_time'] = isset($deliver_arr[$item['deliver_time']]) ? $deliver_arr[$item['deliver_time']] : '';
            $item['deliver_date'] = date('Y/m/d', $item['deliver_date']);
            $item['city_name']    = isset($city_dict[$item['city_id']]) ? $city_dict[$item['city_id']] : '';
            $item['site_name']    = $item['site_src'] == C('site.dachu') ? '大厨' : '大果';
            $pay_type = $item['pay_type'];
            $pay_status = $item['pay_status'];
            $item['pay_type_cn'] = $this->_pay_type_dict[$pay_type];
            $item['pay_status_cn'] = $this->_pay_status_dict[$pay_status];

            //用户相关
            $user_id                 = $item['user_id'];
            $order_user              = $user_map[$user_id];
            $item['deliver_addr']    = $order_user['address'];
            $item['mobile']          = $order_user['mobile'];
            $item['shop_name']       = $order_user['shop_name'];
            $item['realname']        = $order_user['name'];
            $item['geo']             = $order_user['geo'];
            $item['address']         = $order_user['address'];
            $item['line']            = isset($line_map[$item['line_id']]) ? $line_map[$item['line_id']] : '';

            //订单状态
            $status            = $item['status'];
            $item['status_cn'] = isset($this->_status_dict[$status]) ? $this->_status_dict[$status] : '';
            $order_id          = $item['id'];

            $item['suborders'] = empty($order_id_to_suborder_map[$order_id]) ? [] : $order_id_to_suborder_map[$order_id];
        }
        unset($item);

        return $order_list;
    }

    /**
     *
     * @description 母订单纬度的订单详情
     */
    public function info() {
        $where = [];
        if(!empty($_POST['order_id'])) {
            $where['id'] = intval($_POST['order_id']);
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

        $order = $this->MOrder->get_one(
            '*',
            $where
        );

        if(empty($order)) {
            $res['msg'] = '没有相关的订单信息';
            $this->_return_json($res);
        }

        $customer = $this->MCustomer->get_one(
            'shop_name, mobile, address, invite_id, am_id, status',
            array(
                'id' => $order['user_id']
            )
        );
        $order['shop_name']    = $customer['shop_name'];
        $order['mobile']       = $customer['mobile'];
        $order['deliver_addr'] = $customer['address'];

        $line = $this->MLine->get_one(
            '*',
            array(
                'id' => $order['line_id']
            )
        );

        $order['line_name'] = $line['name'];
        $order['deliver_date'] = date('Y-m-d', $order['deliver_date']);
        $order['deliver_time'] = $order['deliver_time'] == 1 ? C('order.deliver_time.early.msg') : C('order.deliver_time.late.msg');
        $order['created_time'] = date('Y-m-d H:i:s', $order['created_time']);
        $order['updated_time'] = date('Y-m-d H:i:s', $order['updated_time']);

        //价格格式化
        $order['total_price'] = $order['total_price'] / 100;
        $order['final_price'] = $order['final_price'] / 100;
        $order['deal_price'] = $order['deal_price'] / 100;
        $order['deliver_fee'] = $order['deliver_fee'] / 100;
        $order['pay_reduce'] = $order['pay_reduce'] / 100;
        $order['minus_amount'] = $order['minus_amount'] / 100;

        //客户类型
        $customer_type_values = array_column(array_values(C('customer.type')), 'value');//type value
        $customer_type_names = array_column(array_values(C('customer.type')), 'name');
        $customer_type_dict = array_combine($customer_type_values, $customer_type_names);
        $customer_type = $order['customer_type'];
        $order['customer_type'] = $customer_type_dict[$customer_type];


        //bd信息
        $invite_id = $customer['invite_id'];
        $am_id = $customer['am_id'];
        $bd_info = $invite_id > 0 ? $this->MUser->get_one(
            'name, mobile, id',
            array(
                'id' => $invite_id
            )
        ) : [];
        $bd_info['role'] = 'BD';
        $am_info = $am_id > 0 ? $this->MUser->get_one(
            'name, mobile, id',
            array(
                'id' => $am_id
            )
        ) : [];
        $am_info['role'] = 'AM';
        if($customer['invite_id'] == C('customer.public_sea_code')) {
            $order['sale'] = ['role' => '公海客户', 'name' => '无对应销售'];
        } elseif ($customer['status'] == C('customer.status.allocated.code')) {
            $order['sale'] = $am_info;
        } else {
            $order['sale'] = $bd_info;
        }

        $order['status_cn'] = $this->_status_dict[$order['status']];

        $suborders = $this->MSuborder->get_lists(
            '*',
            array(
                'order_id' => $order['id']
            )
        );

        //fill products
        foreach($suborders as &$suborder) {
            $suborder_id = $suborder['id'];
            $prods = $this->MOrder_detail->get_lists(
                '*',
                array(
                    'suborder_id' => $suborder_id
                )
            );

            foreach($prods as &$product) {
                $product['spec'] = json_decode($product['spec'], TRUE);
                $product['price']     /= 100;
                $product['sum_price'] /= 100;
                $product['actual_price'] /= 100;
                $product['actual_sum_price'] /= 100;
                $product['single_price'] /= 100;
                $product['unit_id'] = $this->_unit_dict[$product['unit_id']];
                $product['close_unit'] = $this->_unit_dict[$product['close_unit']];
            }
            unset($product);

            $suborder['products'] = $prods;
            $suborder['log_list'] = $this->_get_order_logs($suborder_id);
        }
        unset($suborder);

        $order['suborders'] = $suborders;

        $arr = array(
            'status' => 0,
            'info'   => $order,
        );
        $this->_return_json($arr);
    }

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

    //这个一定传的是母订单的id
    //运营操作的都是母订单~
    public function set_status_confirmed() {

        if(!isset($_POST['cur'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '操作用户不能为空'
                )
            );
        }

        if(!isset($_POST['order_id'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '需要提供订单号和订单id'
                )
            );
        }
        $order_id = intval($_POST['order_id']);

        //事务start
        $this->db->trans_start();
        $this->MOrder->update_info(
            array(
                'status' => C('order.status.confirmed.code')
            ),
            array(
                'id' => $order_id
            )
        );
        $this->MSuborder->update_info(
            array(
                'status' => C('order.status.confirmed.code')
            ),
            array(
                'order_id' => $order_id
            )
        );
        $this->db->trans_complete();

        if($this->db->trans_status() == FALSE) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '审核失败！！'
                )
            );
        }
        //事务end

        //发短信
        $order = $this->MOrder->get_one(
            '*',
            array(
                'id' => $order_id
            )
        );
        $customer_id = $order['user_id'];

        $customer = $this->MCustomer->get_one(
            '*',
            array(
                'id' => $customer_id
            )
        );


        $deliver_date = date('Y年m月d日', $order['deliver_date']) . $this->_deliver_dict[$order['deliver_time']];
        $pattern = $order['site_src'] == C('site.dachu') ? C('register_msg.sms_audit_chu') : C('register_msg.sms_audit_guo');
        $content = sprintf($pattern, $order['order_number'], $deliver_date);
        $sms_data = array(
            'content' => $content,
            'mobile'  => $customer['mobile'],
            'site'    => $order['site_src']
        );
        $this->dachu_request->post(C('service.s') . '/sms/send_captcha', $sms_data);

        //记日志
        $suborders = $this->MSuborder->get_lists(
            'id',
            array(
                'order_id' => $order_id
            )
        );
        $suborder_ids = array_column($suborders, 'id');
        foreach($suborder_ids as $suborder_id) {
            $this->MWorkflow_log->record_order($suborder_id, C('order.status.confirmed.code'), $_POST['cur']);
        }
        //调用wms的接口
        $url = C('service.wms') . '/order/addBillOut';
        $suborder_ids = implode(',', $suborder_ids);
        $res = $this->http->query($url, ['orderIds' => $suborder_ids]);

        $this->_return_json(
            array(
                'status' => 0,
                'msg'    => '审核成功'
            )
        );
    }

    //这个一定传的是母订单的id
    //运营操作的都是母订单~
    public function set_status_closed() {
        if(!isset($_POST['order_id'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '需要提供订单号和订单id'
                )
            );
        }
        if(!isset($_POST['cur'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '操作用户不能为空'
                )
            );
        }

        $remark = !empty($_POST['remark']) ? $_POST['remark'] : '';
        $order_id = intval($_POST['order_id']);

        //事务start
        $this->db->trans_start();
        $this->MOrder->update_info(
            array(
                'status' => C('order.status.closed.code')
            ),
            array(
                'id' => $order_id
            )
        );
        $this->MSuborder->update_info(
            array(
                'status' => C('order.status.closed.code')
            ),
            array(
                'order_id' => $order_id
            )
        );

        $this->MOrder_detail->update_info(
            array(
                'status' => C('order.status.closed.code')
            ),
            array(
                'order_id' => $order_id
            )
        );

        $this->db->trans_complete();

        if($this->db->trans_status() == FALSE) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '取消订单失败!!'
                )
            );
        }

        //事务end
        //记日志
        $suborders = $this->MSuborder->get_lists(
            'id',
            array(
                'order_id' => $order_id
            )
        );
        $suborder_ids = array_column($suborders, 'id');
        $cur = $_POST['cur'];
        foreach($suborder_ids as $suborder_id) {
            $this->MWorkflow_log->record_order($suborder_id, C('order.status.closed.code'), $cur, $remark);
        }

        $this->_return_json(
            array(
                'status' => 0,
                'msg'    => '取消订单成功'
            )
        );
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

        if(empty($_POST['order_id'])) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '订单id不能为空'
                )
            );
        }

        $cur = $_POST['cur'];
        $remark = $_POST['remark'];

        $suborders = $this->MSuborder->get_lists(
            'id',
            array(
                'order_id' => intval($_POST['order_id'])
            )
        );

        if(empty($suborders)) {
            $this->_return_json(
                array(
                    'status' => -1,
                    'msg'    => '没有下属的子订单'
                )
            );
        }

        $suborder_ids = array_column($suborders, 'id');

        foreach($suborder_ids as $suborder_id) {
            $result = $this->MWorkflow_log->record_order_comment($suborder_id, $cur, $remark);
        }

        $this->_return_json(
            array(
                'status' => 0,
                'msg'    => '记录成功'
            )
        );
    }


    /**
     * @description 修改配送日期和配送时间
     */
    public function change_deliver_time() {
        $order_id = intval($_POST['order_id']);
        if(!$order_id) {
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

        $order_update_res = $this->MOrder->update_info(
            $update_arr,
            array(
                'id' => $order_id
            )
        );

        $suborder_update_res = $this->MSuborder->update_info(
            $update_arr,
            array(
                'order_id' => $order_id
            )
        );

        $this->_return_json(
            array(
                'status'              => 0,
                'msg'                 => '更新配送时间成功',
                'order_update_res'    => $order_update_res,
                'suborder_update_res' => $suborder_update_res
            )
        );

    }

    /**
     * 统计用户当天支付成功的订单单情况
     * @param unknown $user
     * @return Ambigous <number, unknown>
     * @author yuanxiaolin@dachuwang.com
     */
    public function count_today_orders($user_id,$return_json = TRUE){
        $event_time = $this->input->server('REQUEST_TIME');
        $today_timestamp = strtotime(date('Ymd',$event_time));
        $where['user_id'] = $user_id;
        $where['created_time >='] = $today_timestamp;
        $where['created_time <='] = $today_timestamp + 86400;
        $where['status !='] = C('order.status.closed.code');
        
        $today_orders = 0;
        if(!empty($user_id)){
            $where['pay_status'] = C('payment.status.success.code');
            $pay_success_orders = $this->MOrder->count($where);
            unset($where['pay_status']);
            $where['pay_status'] = C('payment.status.waiting.code');
            $pay_waiting_orders = $this->MOrder->count($where);
            $today_orders = $pay_success_orders + $pay_waiting_orders;
        }
        
        $today_orders = intval($today_orders) ;
        if($return_json === FALSE){
            return $today_orders;
        }else{
            $this->_return_json(array('status'=>0,'msg'=>$today_orders));
        }
    }

}

/* End of file order.php */
/* Location: ./application/controllers/order.php */
