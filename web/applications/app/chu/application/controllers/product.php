<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 货物的模型
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: 2014-12-10
 */
class Product extends MY_Controller {

    private $_page_size = 100;
    protected $_cities = array();

    public function __construct() {
        parent::__construct();
        $this->load->model(array('MLine', 'MStock'));
    }
   /**
     * @author: liaoxianwen@ymt360.com
     * @description 产品列表
     */
    public function lists() {
        $post = $this->post;
        $page = $this->get_page();
        $show_page = empty($_POST['itemsPerPage']) ? FALSE : TRUE;
        $ip_address = '';// 当前id地址
        if(empty($post['upid'])) {
            $this->_return_json(
                array(
                    'status'    => C('tips.code.op_failed'),
                    'msg'   => '查询条件不满足'
                )
            );
        }
        // 查询所属城市
        if(!empty($post['locationId'])) {
            $post['location_id'] = intval($post['locationId']);
        }
        // 检测用户是否已经登录,
        // 登录用户不允许切换城市
        // 优先取登录用户的所在城市
        $cur = $this->userauth->current(TRUE);
        $user_info = array();
        if($cur) {
            $post['location_id'] = $cur['province_id'];
            $local_info = $this->format_query('/location/info', array('where' => array('id' => $cur['province_id'])));
            // 所在城市info信息
            if(intval($local_info['status']) === 0) {
                $user_info = array(
                    'location_id' => $cur['province_id'],
                    'line_id' => $cur['line_id'],
                    'name' => $local_info['info']['name']
                );
            }
        }
        $customer_type = !empty($cur['customer_type']) ? $cur['customer_type'] : C('customer.type.normal.value');
        $data = $this->format_query('/product/lists',
            array(
                'upid' => $post['upid'],
                'offset' => $page['offset'],
                'location_id' => $post['location_id'],
                'page_size' => $show_page ? $page['page_size'] : 0,
                'user_info'   => $user_info,
                'customer_type' => $customer_type,
            )
        );
        if(!empty($data['list'])) {
            $this->_format_data_by_line_id($cur, $data['list']);
            $this->_format_data_by_real_stock($cur, $data['list']);
        }
        $this->_return_json($data);
    }

    private function _format_data_by_real_stock($cur, &$products) {
        //实时库存的开关
        if(C('realtime_stock.switch') != 'on') {
            return TRUE;
        }

        // 没登陆都显示充足
        if(empty($cur)) {
            return TRUE;
        }

        // 只考虑开通实时库存的城市
        $line_id = $cur['line_id'];
        $city_id = $cur['province_id'];
        if(!in_array($city_id, C('realtime_stock.cities'))) {
            return TRUE;
        }

        $warehouse_id = $this->MLine->get_one(
            'warehouse_id',
            array(
                'id' => $line_id
            )
        );

        // 查询当前客户的线路
        if(empty($warehouse_id)) {
            $warehouse_id = 0;
        } else {
            $warehouse_id = $warehouse_id['warehouse_id'];
        }

        // 没有商品也不用继续了
        if(empty($products)) {
            return TRUE;
        }

        // 根据sku分别到各个仓库取实际库存
        $sku_numbers = array_column($products, 'sku_number');
        $sku_to_prod = array_combine($sku_numbers, $products);
        $quantity_in_db = $this->MStock->get_lists(
            '*',
            array(
                'warehouse_id' => $warehouse_id,
                'in' => array(
                    'sku_number'   => $sku_numbers
                )
            )
        );

        // 排除部分品类
        $skus = array_column($quantity_in_db, 'sku_number');
        $sku_to_stock = array_combine($skus, $quantity_in_db);
        foreach($products as &$item) {
            // 检查是否是排除实时库存的分类
            $flag = FALSE;

            if($item['collect_type'] == C('foods_collect_type.type.now_collect.value')) {
                $flag = TRUE;
            }
            // 排除的分类或者是有设置限购库存的，直接不考虑实时库存
            if($flag || $item['storage'] != -1) {
                continue;
            }
            // 如果实时库存没数据的，直接认为库存充足
            if(!isset($sku_to_stock[$item['sku_number']])) {
                continue;
            }
            $stock = $sku_to_stock[$item['sku_number']];
            $stock_can_be_sold = $stock['in_stock'] - $stock['stock_locked'];

            // 少于50件就提示数目，大于50件不提示数目
            if($stock_can_be_sold < 50) {
                $item['storage'] = $stock_can_be_sold > 0 ? $stock_can_be_sold : 0;
                $item['storage_cn'] = '剩余' . $item['storage'] . $item['unit'];
            } else {
                $item['storage'] = -1;
                $item['storage_cn'] = '足量库存';
            }
        }
        unset($item);
        return TRUE;
    }


    private function _format_data_by_line_id($cur, &$lists) {
        $is_login = $cur ? TRUE : FALSE;
        if($is_login) {
            $line_ids = array($cur['line_id']);
        } else {
            $line_ids = array(0);
        }
        $new_lists = [];
        foreach($lists as $key => $v) {
            $ori_lines = explode(',', $v['line_id']);
            if($v['line_id'] != 0) {
                if(!$inter = array_intersect($ori_lines, $line_ids)) {
                    unset($lists[$key]);
                    continue;
                }
            }
            $new_lists[] = $v;
        }
        $lists = $new_lists;
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 增加搜索，根据名称搜索
     */
    public function search() {
        $response = array(
            'status' => C('tips.code.op_failed'),
            'msg' => '暂无信息'
        );
        $page = $this->get_page();
        $cur = $this->userauth->current(TRUE);
        $location_id = C('open_cities.beijing.id');
        // 查询所属城市
        if(!empty($this->post['locationId'])) {
            $location_id = intval($this->post['locationId']);
        }
        if($cur) {
            $location_id =$cur['province_id'];
        }
        $site_id = C('app_sites.chu.id');
        $customer_type = empty($cur) ? C('customer.type.normal.value') : $cur['customer_type'];
        if(!empty($this->post['searchVal'])) {
            $fruit_category_id = empty($cur) || $cur['customer_type'] == 1 ? C("category.category_type.fruit.code") : 0 ;
            $response = $this->format_query('/product/manage',
                array(
                    'where' => array(
                        'like' => array(
                            'title' => $this->post['searchVal']
                        ),
                        'customer_type' => $customer_type,
                        'location_id' => $location_id ,
                        'status' => C('status.product.up')
                    ),
                    'currentPage' => $page['page'],
                    // 是否查询水果相关的产品
                    'fruit_category_id' => $fruit_category_id,
                    'itemsPerPage' => $page['page_size']
                )
            );
        }
        if(!empty($response['list'])) {
            $this->_format_data_by_line_id($cur, $response['list']);
            $this->_format_data_by_real_stock($cur, $response['list']);
        }
        $this->_return_json($response);
    }
  }
/* End of file product.php */
/* Location: :./application/controllers/product.php */
