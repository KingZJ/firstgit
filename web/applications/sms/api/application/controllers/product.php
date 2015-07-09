<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 货物的模型
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: 2014-12-10
 */
class Product extends MY_Controller {

    private $_page_size = 10;
    protected $_cities = array();

    public function __construct() {
        parent::__construct();
        $this->load->library(array('Cate_logic', 'Price_async', 'redisclient'));
        $this->load->model(array('MWorkflow_log'));
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 产品列表
     */
    public function lists() {
        $post = $this->post;
        $page = isset($post['page']) && intval($post['page']) >= 1 ? intval($post['page']) : 1;
        $ip_address = '';// 当前id地址
        if(empty($post['upid'])) {
            $this->_return_json(
                array(
                    'status'    => C('tips.code.op_failed'),
                    'msg'   => '查询条件不满足'
                )
            );
        }
        // 获取当前用户
        $user_info = $this->userauth->current();
        $data = $this->format_query('/product/lists',
            array(
                'upid' => $post['upid'],
                'page' => $page,
                'page_size' => $this->_page_size,
                'user_info'   => $user_info
            )
        );

        $this->_return_json($data);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 设置货物状态
     */
    public function set_status() {
        $this->check_validation('product', 'edit', '', FALSE);
        $cur = $this->userauth->current(FALSE);
        $post = $this->post;
        $where = array(
            'id'    => $post['id'],
        );

        $this->MWorkflow_log->record_op_log($post['id'], $post['status'], $cur, '商品被上下架操作', json_encode($post), C('workflow_log.edit_type.product'));
        $data =  $this->format_query('/product/set_status',array('where' => $where, 'status' => $post['status']));
        $this->_return_json($data);
    }

    /**
     * @author: liaoxianwen@ymt360.com
     * @description 管理货物
     */
    public function manage() {
        // $this->check_validation('product', 'list');
        $post  = $this->post;
        $post['where'] = array();
        if(!empty($post['searchVal'])) {
            $pattern = '/^1(\d+){6}$/';
            if(preg_match($pattern, $post['searchVal'])) {
                $post['where'] = array('sku_number' => $post['searchVal']);
            } else {
                $post['where'] = array('like' => array('title' => $post['searchVal']));
            }
        }
        if(isset($post['status'])) {
            if($post['status'] != 'all') {
                $post['where']['status'] = $post['status'];
            }
            unset($post['status']);
        } else {
            $post['where']['status'] = C('status.common.success');
        }
        if(empty($post['locationId'])) {
            $post['where']['location_id'] = C('open_cities.beijing.id');
        } else {
            $post['where']['location_id'] = $post['locationId'];
            unset($post['locationId']);
        }
        if(empty($post['customerType'])) {
            $post['where']['customer_type'] = C('customer.type.normal.value');
        } else {
            $post['where']['customer_type'] = $post['customerType'];
            unset($post['customerType']);
        }
        // 若是运营人员，那么应该可以看到所有的货物
        $data = $this->format_query('/product/manage', $post);
        $this->_set_location($data);
        $this->_return_json($data);
    }

    private function _set_location(&$data) {
        $location = $this->format_query('/location/get_child');
        $lines = $this->format_query('/line/get_all');
        $new_lines = array();
        foreach($lines['list'] as $v) {
            $new_lines[$v['location_id']][] = $v;
        }
        $data['location'] = $location['list'];
        $data['line_options'] = $new_lines;
        $data['customer_type_options'] = array_values(C('customer.type'));
        $data['collect_type_options'] = array_values(C('foods_collect_type.type'));
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 设置可见范围
     */
    private function _set_visiable(&$data) {
        $data['visiable_options'] = C('visiable');
        $data['customer_type_options'] = array_values(C('customer.type'));
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 物品信息维护
     */
    public function edit() {
        $this->check_validation('product', 'edit', '', FALSE);
        $post = $this->post;
        $data = $this->format_query('/product/info', $post);
        $data['log_list'] = $this->_log_detail();
        $this->_return_json($data);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 更具sku获取信息
     */
    public function get_sku_info() {
        if(isset($this->post['skuNumber'])) {
            $where['sku_number'] = $this->post['skuNumber'];
        } else if(isset($this->post['id'])) {
            $where['id'] = $this->post['id'];
        }
        // 获取sku信息，需要是已经
        $where['status'] = C('status.common.success');
        $condition = array('where' => $where);
        $data = $this->format_query('/sku/info', $condition);
        // 设置城市选项
        $this->_set_location($data);
        // 设置商品可见性范围选项
        $this->_set_visiable($data);
        $this->_return_json($data);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 增加货物
     */
    public function save() {
        $this->check_validation('product', 'create', '', FALSE);
        $post = $this->post;
        $url ='/product/create';
        $cur = $this->userauth->current(FALSE);
        $post['customerType'] = empty($post['customerType']) ? C('customer.type.normal.value') : intval($post['customerType']);
        $post['collectType'] = empty($post['collectType']) ? C('foods_collect_type.type.pre_collect.value') : intval($post['collectType']);
        // 更新原来的
        if(!empty($post['id'])) {
            $update_url = '/product/set_status';

            $edit_data = $this->format_query('/product/info', array('id' => $post['id']));
            if(intval($edit_data['status']) === 0) {
                $edit_info = $edit_data['info'];
                if($edit_info['title'] != $post['title']
                    || $edit_info['unit_name'] != $post['unitName']
                    || $edit_info['close_unit_name'] != $post['closeUnit']
                    || $edit_info['location_id'] != $post['locationId']
                    || $edit_info['line_id'] != $post['lineId']
                    || $edit_info['customer_type'] != $post['customerType']
                    || $edit_info['collect_type'] != $post['collectType']
                    || $edit_info['is_round'] != $post['isRound']
                    || $edit_info['visiable'] != $post['visiable']
                    || $edit_info['buy_limit'] != $post['buyLimit']
                    || $edit_info['storage'] != $post['storage']
                    || $edit_info['single_price'] * 100 != $post['singlePrice'] * 100
                    || $edit_info['market_price'] * 100 != $post['marketPrice'] * 100
                    || $edit_info['adv_words'] != $post['advWords']) {
                        $up_data = array(
                            'where' => array(
                                'id' => $post['id']
                            ),
                            'status' => C('status.common.del'),
                            'is_active' => C('status.common.del')
                        );
                        $this->format_query($update_url, $up_data);

                        $this->MWorkflow_log->record_op_log($post['id'], $up_data['status'], $cur, '商品被下架', json_encode($up_data), C('workflow_log.edit_type.product'));
                    } else {
                        $return = $this->format_query($update_url,
                            array('where' => array('id' => $post['id']), 'status' => $post['status'])
                        );
                        $this->MWorkflow_log->record_op_log($post['id'], $post['status'], $cur, '设置商品状态', json_encode($post), C('workflow_log.edit_type.product'));
                        // 记录日志
                        $this->_return_json($return);
                    }
            }

            sleep(1);
        }

        $post_data = array(
            'unit_name'    => $post['unitName'],
            'close_unit'   => $post['closeUnit'],
            'customer_type'=> $post['customerType'],
            'collect_type' => $post['collectType'],
            'title'        => $post['title'],
            'location_id'  => $post['locationId'],
            'sku_number'   => $post['skuNumber'],
            'status'       => $post['status'],
            'adv_words'    => $post['advWords'],
            'storage'      => empty($post['storage']) ? -1 : $post['storage'] ,
            'buy_limit'    => $post['buyLimit'],
            'line_id'      => empty($post['lineId']) ? 0 : $post['lineId'],
            'visiable'     => empty($post['visiable']) ? 1 : $post['visiable'],
            'is_round'     => $post['isRound'],
            'price'        => $post['price'] * 100,
            'market_price' => $post['marketPrice'] * 100,
            'single_price' => $post['singlePrice'] * 100
        );
        $data = $this->format_query($url, $post_data);
        // 同步price
        if(!empty($data['info'])) {
            // 设置库存池
            if(intval($post_data['storage']) > 0) {
                $this->redisclient->hset($data['info']['id'], 'storage',$post_data['storage']);
            }
            if(intval($post_data['buy_limit']) > 0) {
                $this->redisclient->hset($data['info']['id'], 'buy_limit', $post_data['buy_limit']);
            }
            $async_price_data = array(
                'ids_json' => json_encode(array(
                    'prod_id' => $data['info']['id'],
                    'sku_id' => $post['skuNumber'],
                    'location_id' => $post['locationId']
                )),
                'timeout' => 1
            );
            // $this->price_async($async_price_data);
            // 写日志
            $this->MWorkflow_log->record_op_log($data['info']['id'], $post['status'], $cur, '创建商品', json_encode($post_data), C('workflow_log.edit_type.product'));
        }
        $this->_return_json($data);
    }

    public function units() {
        $data = $this->format_query('product/units');
        $this->_return_json($data);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 日志详情
     */
    private function _log_detail() {
        $post_data = array(
            'edit_type' => C('workflow_log.edit_type.product'),
            'obj_id' => isset($_POST['id']) ? $_POST['id'] : 0
        );
        $response = $this->format_query('/workflow_log/info', $post_data);
        return isset($response['list']) ? $response['list'] : array();
    }
}

/* End of file product.php */
/* Location: :./application/controllers/product.php */
