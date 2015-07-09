<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 商品服务
 * @author: liaoxianwen@ymt360.com
 * @version: 2.0.0
 * @since: 2015-3-3
 */
class Product extends MY_Controller {

    public $units = array();
    public $product_status = array();

    public function __construct() {
        parent::__construct();
        $this->load->model(
            array(
                'MCategory',
                'MProduct',
                'MSku',
                'MLocation',
                'MWorkflow_log',
                'MOrder',
                'MOrder_detail',
                'MProperty'
            )
        );
        $this->units = C('unit');
        $this->product_status = C('product');
        $this->load->library(array('Cate_logic','product_lib'));
        $this->load->helper(array('img_zoom'));
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 获取单个商品信息
     */
    public function info() {
        $info = $this->MProduct->get_one('*', array('id' => $_POST['id']));
        // 获取分类信息
        if($info) {
            $cate = $this->MCategory->get_one('name,path', array('id' => $info['category_id']));
            $info['cate_info'] = $cate['name'];
            $info['path'] = explode('.', trim($cate['path'], '.'));
            $info['price'] = $info['price'] / 100;
            $info['market_price'] = $info['market_price'] / 100;
            $info['single_price'] = $info['single_price'] / 100;
            $info['unit_name'] = $this->product_lib->get_unit_name($info['unit_id']);
            $info['close_unit_name'] = $this->product_lib->get_unit_name($info['close_unit']);
            // 获取日志
            $info['op_logs'] = $this->MWorkflow_log->get_lists('*', array('obj_id' => $info['id']), array('id' => 'desc'));
        }
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'info'   => $info
            )
        );
    }
    /**
     * 所属线路
     * @author: liaoxianwen@ymt360.com
     * @description 获取列表
     */
    public function lists() {
        $products = array();
        $cate_ids= explode(',', rtrim($_POST['upid'], ','));
        $childs = $this->cate_logic->get_child($cate_ids);
        if($childs) {
            $category_ids = array_column($childs, "id");
        }
        foreach($cate_ids as $v) {
            $category_ids[] = $v;
        }
        if(empty($_POST['location_id'])) {
            $_POST['location_id'] = C('open_cities.beijing.id');
        }
        $page_size = isset($_POST['page_size']) ? intval($_POST['page_size']) : 100;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        $customer_type = empty($_POST['customer_type']) ? C('customer.type.normal.value') : intval($_POST['customer_type']);
       // 查询列表, visiable =3 位不可见 1为全部可见，2为部分可见
        $lists = $this->MProduct->get_lists('*',array(
                'in' => array(
                    'category_id' => $category_ids,
                ),
                'location_id' => $_POST['location_id'],
                'customer_type' => $customer_type,
                'status' => C('status.product.up'),
                'visiable !=' => C('visio.none')
            ),
            array('updated_time' => 'desc'),
            array(),
            $offset,
            $page_size
        );
        $lists = $this->product_lib->format_product_data($lists);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $lists
            )
        );
    }
   /**
     * @author: liaoxianwen@ymt360.com
     * @description 产品创建
     */
    public function create(){
        $format_data = $this->_format_data();
        extract($format_data);
        $product_id = $this->MProduct->create($product);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'info' => array('id' => $product_id),
                'msg' => '保存成功'
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 编辑后保存
     */
    public function save() {
        $format_data = $this->_format_data();
        extract($format_data);
        $this->MProduct->update_info($product, array('id' =>$_POST['id']));
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'msg' => '保存成功'
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 更新库存，限购
     */
    public function update_storage() {
        if(!empty($_POST['data']) && is_array($_POST['data'])) {
            foreach($_POST['data'] as $data) {
                $where = array('id' => $data['id']);
                $product = array('storage' => $data['storage']);
                $this->MProduct->update_info($product, $where);
            }
        }
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'msg' => '保存成功'
            )
        );

    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 后台管理接口
     */
    public function manage() {
        $page = $this->get_page();
        $where = array();
        if(!empty($_POST['where'])) {
            $where = $_POST['where'];
        }
        $total =  $this->MProduct->count($where);
        $data = $this->MProduct->get_lists(
            array(),
            $where,
            array('id' => 'DESC'),
            array(),
            $page['offset'],
            $page['page_size']
        );
        $data = $this->product_lib->format_product_data($data);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $data,
                'total' => $total
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 创建商品组合数据
     */
    private function _format_data() {
        $req_time = $this->input->server('REQUEST_TIME');
        $sku_info = $this->MSku->get_one('category_id, sku_number, spec', array('sku_number' => $_POST['sku_number']));
        if(!$sku_info) {
            $this->_return_json(
                array(
                    'status' => C('tips.code.op_failed'),
                    'msg' => '您查找的货物不存在'
                )
            );
        }
        // 拼接属性
        if(isset($_POST['unit_name'])) {
            $unit_id = $this->product_lib->get_unit_id($_POST['unit_name']);
        } else {
            $unit_id = $this->units[0]['id'];
        }
        // 拼接属性
        if(isset($_POST['close_unit'])) {
            $close_unit_id = $this->product_lib->get_unit_id($_POST['close_unit']);
        } else {
            $close_unit_id = $this->units[0]['id'];
        }
        if(is_array($_POST['line_id']) && !empty($_POST['line_id'])) {
            $_POST['line_id'] = implode(',', $_POST['line_id']);
        }
        $product = array(
            'title'        => $_POST['title'],
            'category_id'  => $sku_info['category_id'],
            'adv_words'    => $_POST['adv_words'],
            'location_id'  => $_POST['location_id'],
            'customer_type'=> $_POST['customer_type'],
            'collect_type' => $_POST['collect_type'],
            'price'        => $_POST['price'],
            'market_price' => $_POST['market_price'],
            'single_price' => $_POST['single_price'],
            'storage'      => $_POST['storage'],
            'buy_limit'    => $_POST['buy_limit'],
            'line_id'      => $_POST['line_id'],
            'visiable'     => $_POST['visiable'],
            'is_round'     => $_POST['is_round'],
            'unit_id'      => $unit_id,
            'close_unit'   => $close_unit_id,
            'spec'         => $sku_info['spec'],
            'status'       => $_POST['status'],
            'created_time' => $req_time,
            'updated_time' => $req_time,
            'sku_number'   => $sku_info['sku_number']
        );
        return array(
            'product' => $product
        );
    }
    /**
     * @author: liaoxianwen@dachuwang.com
     * @description 
     */
    public function set_status() {
        $data = array(
            'status' => $_POST['status']
        );
        if(isset($_POST['is_active'])) {
            $data['is_active'] = $_POST['is_active'];
        }
        if(!empty($_POST['updated_time'])) {
            $data['updated_time'] = $_POST['updated_time'];
        }
        $this->MProduct->update_info($data, $_POST['where']);
        $this->_return_json(
            array(
                'status'    => C('tips.code.op_success')
            )
        );

    }

    public function units() {
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $this->units
            )
        );
    }
    /**
     * 获取当天所购买的商品，有限购的，就返回，没有就返回为空
     * 当前时间，若是大于23点得，那么就只查23点-24点之间是否下过订单，
     * 切订单里有限购的，若是小于23，那么就是从0点后到23点之间是否有下单有限购
     * @author: liaoxianwen@ymt360.com
     * @description
     */
    public function get_today_bought_products() {
        $request_time =  $this->input->server('REQUEST_TIME');
        $hour = intval(date('H', $request_time));
        $user_info = $_POST['user_info'];
        // 23点
        if($hour == 23) {
            $min_date = strtotime(date('Y-m-d', $request_time) . '23:00');
            $max_date = $request_time;
        } else {
            // 其他点数
            $min_date = strtotime(date('Y-m-d', strtotime('-1 day')) . '23:00');
            $max_date = $request_time;
        }
        if(isset($user_info['id'])) {
            $where = array(
                'user_id' => $user_info['id'],
                'created_time >=' => $min_date,
                'created_time >=' => $min_date,
                'status !=' => C('status.common.del')
            );
            $orders = $this->MOrder->get_lists('*', $where);
            if($orders) {
                $order_ids = array_column($orders, 'id');
                $details = $this->MOrder_detail->get_lists('product_id, quantity', array('in' => array('order_id' => $order_ids)));
                $return = array('list' => $details, 'status' => C('tips.code.op_success'));
            } else {
                $return = array('status' => C('tips.code.op_success'), 'msg' => '无限购产品');
            }
        } else {

            $return = array('status' => C('tips.code.op_success'), 'msg' => '无限购产品');
        }

        $this->_return_json($return);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 获取用户经常购买
     */
    public function get_always_buy_products() {
        $page = $this->get_page();
        $response = array(
            'status' => C('tips.code.op_success'),
            'list' => array(),
            'msg' => '您还未下过单'
        );
        if( !empty($_POST['location_id']) && !empty($_POST['user_id']) && !empty($_POST['customer_type'])) {
            $order = $this->MOrder->get_lists__Cache60('id', array('user_id' => $_POST['user_id']));
            if(!empty($order)) {
                $order_ids = array_column($order, 'id');
                $detail = $this->MOrder_detail->get_lists__Cache10('*', array('in' =>array('order_id' => $order_ids)));
                $sku_numbers = array_column($detail, 'sku_number');
                $where = array(
                    'in' => array(
                        'sku_number' => $sku_numbers,
                    ),
                    'location_id' => $_POST['location_id'],
                    'customer_type' => $_POST['customer_type'],
                    'status' => C('status.product.up'),
                    'visiable !=' => C('visio.none')
                );
                $total = $this->MProduct->count($where);
                // 查询列表, visiable =3 位不可见 1为全部可见，2为部分可见
                $lists = $this->MProduct->get_lists__Cache30('*',
                    $where,
                    array('updated_time' => 'desc'),
                    array(),
                    $page['offset'],
                    $page['page_size']
                );
                $lists = $this->product_lib->format_product_data($lists);
                $response = array(
                    'status' => C('tips.code.op_success'),
                    'list' => $lists,
                    'total' => $total
                );
            }
        } else {
            $response['msg'] = '提交参数错误';
        }
        $this->_return_json($response);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description api层检测提交订单的products接口
     */
    public function check_valid_order_products() {
        $where = array();
        if(!empty($_POST['where'])) {
            $where = $_POST['where'];
        }
        $total =  $this->MProduct->count($where);
        $data = $this->MProduct->get_lists(
            array(),
            $where,
            array('id' => 'DESC'),
            array()
        );
        $data = $this->product_lib->format_product_data($data);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $data
            )
        );
    }
}

/* End of file product.php */
/* Location: ./application/controllers/product.php */
