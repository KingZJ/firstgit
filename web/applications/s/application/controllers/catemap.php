<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 分类映射 
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: 2015-3-12
 */
class Catemap extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(
            array('MCategory_map', 'MLocation', 'MProduct')
        );
        $this->load->library(array('Cate_logic'));
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 取单个信息
     */
    public function info() {
        $data = $this->MCategory_map->get_one(
            '*',
            array('id' => $_POST['id'])
        );
        if($data) {
            if($data['upid']) {
                $up_info = $this->MCategory_map->get_one('origin_id', array('id' => $data['upid']));
                $data['origin_upid'] = $up_info['origin_id'];
            } else {
                $data['origin_upid'] = 0;
            }
        }
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'info' => $data
            )
        );
    }

    // 获取分类映射
    public function lists() {
        $page = $this->get_page();
        extract($page);
        if(empty($_POST['site_id'])) {
            $where = array(
                'upid' => 0
            );
        } else {
            $where = array(
                'upid' => 0,
                'site_id' => $_POST['site_id']
            );
        }
        // 判断所属城市
        if(!isset($_POST['location_id'])) {
            $default = $this->MLocation->get_one('id, name', array('upid' => 0), 'id ASC');
            // 默认北京
            $_POST['location_id'] = $default['id'];
        } else {
            $default = $this->MLocation->get_one('id, name', array('id' => $_POST['location_id']));
        }
        $customer_type = $where['customer_type'] = empty($_POST['customer_type']) ? C('customer.type.normal.value') : intval($_POST['customer_type']);
        $where['location_id'] = $_POST['location_id'];
        // 状态筛选
        if(!empty($_POST['status'])) {
            $where['status'] = $_POST['status'];
        }
        $method_name = 'get_lists__Cache3600';
        if(isset($_POST['no_cache'])) {
            $method_name = rtrim($method_name, '__Cache3600');
        }
        $lists = $this->MCategory_map->$method_name('*', $where,
            array('weight' => 'DESC','updated_time' => 'DESC')
        );
        $ids = array_column($lists, 'id');
        $childs = array();
        if($ids) {
            $child_where = array(
                'in' => array('upid' => $ids)
            );
            $child_where['location_id'] = $_POST['location_id'];
            $child_where['customer_type'] = $where['customer_type'];
            if(isset($where['status'])) {
                $child_where['status'] = $where['status'];
            }
            $childs  = $this->MCategory_map->$method_name(
                "*",
                $child_where,
                array('weight' => 'DESC','updated_time' => 'DESC')
            );
        }
        // 检测没有商品的分类，过滤掉
        if($lists && $childs) {
            $this->_check_cate_empty($lists, $childs, $customer_type);
        }
        $lists = array_merge($lists, $childs);
        foreach($lists as &$v) {
            $v['updated_time'] = date('Y-m-d H:i:s', $v['updated_time']);
        }
        unset($v);
        // 查出子类
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $lists
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 获取所有
     */
    public function get_all() {
        $where = isset($_POST['where']) ? $_POST['where'] : '';
        $all_catemaps = $this->MCategory_map->get_lists('*', $where);
        if($all_catemaps) {
            $response = array(
                'status' => C('tips.code.op_success'),
                'list' => $all_catemaps
            );
        } else {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => '没有数据'
            );
        }
        $this->_return_json($response);
    }

    /**
     * @author: liaoxianwen@ymt360.com
     * @description 后台获取数据接口
     */
    public function backend_list() {
        $page = $this->get_page();
        extract($page);
        $where = array();
        if(!empty($_POST['site_id'])) {
            $where = array(
                'site_id' => $_POST['site_id']
            );
        }
        // 判断所属城市
        if(!isset($_POST['location_id'])) {
            $default = $this->MLocation->get_one('id, name', array('upid' => 0), 'id ASC');
            // 默认北京
            $_POST['location_id'] = $default['id'];
        } else {
            $default = $this->MLocation->get_one('id, name', array('id' => $_POST['location_id']));
        }
        $where['location_id'] = $_POST['location_id'];
        // 状态筛选
        $where['status'] = isset($_POST['status']) ? $_POST['status'] : 1;
        $method_name = 'get_lists__Cache30';
        if(isset($_POST['no_cache'])) {
            $method_name = rtrim($method_name, '__Cache30');
        }

        $where['customer_type'] = empty($_POST['customer_type']) ? C('customer.type.normal.value') : intval($_POST['customer_type']);
        $total_catemaps = $this->MCategory_map->count($where);
        $lists = $this->MCategory_map->$method_name('*', $where,
            array('weight' => 'DESC','updated_time' => 'DESC'),
            array(),
            $page_size * ($page -1),
            $page_size
        );
        $this->_format_catemap_list_data($lists);
        // 查出子类
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $lists,
                'total' => $total_catemaps
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 格式化catemap_list 的数据
     */

    private function _format_catemap_list_data(&$lists) {
        $customer_type = array_values(C('customer.type'));
        $customer_type_values = array_column($customer_type, 'value');
        $customer_type_combine = array_combine($customer_type_values, $customer_type);
        foreach($lists as &$v) {
            $v['updated_time'] = date('Y-m-d H:i:s', $v['updated_time']);
            $customer_type_cn = isset($customer_type_combine[$v['customer_type']]) ? $customer_type_combine[$v['customer_type']]['name'] : C('customer.type.normal.name');

            $v['customer_type_cn'] = $customer_type_cn; 
        }
        unset($v);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 创建分类映射
     */
    public function create() {

        $this->_check_map_name(
            array(
                'name' => $_POST['name'],
                'location_id' => $_POST['location_id'],
            )
        );
        $up_info = array();
        if(!empty($_POST['initUpid'])) {
            $up_info = $this->_check_upid();
        }

        $data = $this->_format_catemap_post_data();
        $create_id = $this->MCategory_map->create($data);
        if($create_id) {
            if($up_info) {
                $path = $up_info['path'] . $create_id . '.';
                $upid = $up_info['id'];
            } else {
                $path = ".$create_id.";
                $upid = 0;
            }
            // 更新
            $up_data = array(
                'path' => $path,
                'upid' => $upid
            );
            $this->MCategory_map->update_info(
                $up_data,
                array('id' => $create_id)
            );
        }
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'id' => $create_id,
                'msg' => '添加成功'
            )
        );
    }
    public function child($id) {
        $childs = $this->MCategory_map->get_lists(
            '*',
            array(
                'upid' => $id
            ),
            array('weight' => 'DESC', 'updated_time' => 'DESC')
        );
        return $childs;
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 格式化分类映射数据
     */
    private function _format_catemap_post_data() {
        $req_time = $this->input->server('REQUEST_TIME');
        $data = array(
            'name'          => $_POST['name'],
            'upid'          => $_POST['initUpid'],
            'site_id'       => $_POST['siteId'],
            'location_id'   => $_POST['location_id'],
            'customer_type' => empty($_POST['customerType']) ? C('customer.type.normal.value') : intval($_POST['customerType']) ,
            'origin_id'     => $_POST['initId'],
            'weight'        => $_POST['weight'],
            'origin_name'   => $_POST['initName'],
            'updated_time'  => $req_time,
            'status'        => C('status.common.success')
        );
        return $data;
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 保存信息
     */
    public function save() {
        $up_info = array();
        if(!empty($_POST['initUpid'])) {
            $up_info = $this->_check_upid();
        }
        $this->_check_map_name(
            array(
                'name' => $_POST['name'],
                'location_id' => $_POST['location_id']
            ),
            $_POST['id']
        );
        $data = $this->_format_catemap_post_data();
        $this->MCategory_map->update_info($data, array('id' => $_POST['id']));
        if($up_info) {
            $path = $up_info['path'] . $_POST['id'] . '.';
            $upid = $up_info['id'];
        } else {
            $path = "." . $_POST['id'] . ".";
            $upid = 0;
        }
        // 更新
        $up_data = array(
            'path' => $path,
            'upid' => $upid
        );
        $this->MCategory_map->update_info(
            $up_data,
            array('id' => $_POST['id'])
        );
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'id' => $_POST['id'],
                'msg' => '保存成功'
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 检测下上级id的信息
     */
    private function _check_upid() {
        $customer_type = empty($_POST['customerType']) ? C('customer.type.normal.value') : intval($_POST['customerType']);
        $up_info = $this->MCategory_map->get_one(
            'path,id',
            array(
                'origin_id' => $_POST['initUpid'],
                'customer_type' => $customer_type,
                'location_id' => $_POST['location_id'],
                'site_id' => empty($_POST['siteId']) ? 1 : $_POST['siteId']
            )
        );
        if(!$up_info) {
            $this->_return_json(
                array(
                    'status' => C('tips.code.op_failed'),
                    'msg' => '一级分类映射尚不存在'
                )
            );
        }
        return $up_info;
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 检测下map名称的重复
     */
    private function _check_map_name($where, $id = array()) {
        $customer_type = empty($_POST['customerType']) ? C('customer.type.normal.value') : intval($_POST['customerType']);
        $where['customer_type'] = $customer_type;
        if(empty($id)) {
            $info = $this->MCategory_map->get_one('id', $where);
        } else {
            $info = $this->MCategory_map->get_one(
                'id',
                array_merge(
                    array('not_in' => array('id' => $id)), $where
                )
            );
        }
        extract($where);
        $name = trim($name);
        return FALSE;
        if($info || !$name) {
            $this->_return_json(
                array(
                    'status' => C('tips.code.op_failed'),
                    'msg' => '映射名称已存在'
                )
            );
        }
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 根据名称搜素
     */
    public function search() {
        $data = $this->MCategory_map->get_lists('*', $_POST['where']);
        $this->_format_catemap_list_data($data);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'list' => $data
            )
        );
    }
    /**
     * @author: liaoxianwen@dachuwang.com
     * @description 设置禁用/启用 状态
     */
    public function set_status() {
        $res = $this->MCategory_map->update_info(
            array(
                'status'    => $_POST['status']
            ),
            $_POST['where']
        );
        $info = array(
            'status' => C('tips.code.op_success'),
            'msg' => C('tips.msg.op_success')
        );
        $this->_return_json($info);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 检测分类下是否二级分类都有商品
     * ，二级下都没有商品，则不显示以及分类
     */
    private function _check_cate_empty(&$parent, &$child, $customer_type) {
        $cate_child_origin_ids = array_column($child, 'origin_id');
        $childs = $this->cate_logic->get_child($cate_child_origin_ids);
        $child_cate_ids = array_column($childs, 'id');
        // 有子类的
        $not_final_cate_ids = array_unique(array_intersect(array_column($childs, 'upid'), $cate_child_origin_ids));
        // 没有子类
        $final_cate_ids = array();
        foreach($cate_child_origin_ids as $v) {
            if(!in_array($v, $not_final_cate_ids)) {
                $final_cate_ids[] = $v;
            }
        }
        // cate_ids 表示映射分类的orgin_id
        // product_cate_ids = 挂载商品的分类
        $where = array(
            'cate_ids' => $cate_child_origin_ids,
            'category_ids' => array_unique(array_merge($child_cate_ids, $cate_child_origin_ids)),
            'location_id' => $_POST['location_id'],
            'customer_type' => $customer_type,
        );
        $child_id_arr = $this->_check_product($where);
        $new_child = [];
        foreach($child as $child_v) {
            if(in_array($child_v['origin_id'], $child_id_arr)) {
                $new_child[] = $child_v;
            }
        }
        $child = $new_child;
        // 将顶层映射没有的，也过滤掉
        $cate_map_ids = array_column($child, 'upid');
        $new_parent = [];
        foreach($parent as $parent_val) {
            if(in_array($parent_val['id'], $cate_map_ids)) {
                $new_parent[] = $parent_val;
            }

        }
        $parent = $new_parent;
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 提交条件查询列表
     */
    private function _check_product($where) {
        // 查询列表, visiable =3 位不可见 1为全部可见，2为部分可见
        extract($where);
        if(empty($category_ids) && $cate_ids) {
            foreach($cate_ids as $v) {
                $category_ids[] = $v;
            }
        }

        // 线路默认位0
        $line_ids = array(0);
        if(!empty($_POST['line_id'])) {
            $line_ids = array_merge($line_ids, array(intval($_POST['line_id'])));
        }
        $lists = $this->MProduct->get_lists__Cache3600('*',
            array(
                'in' => array(
                    'category_id' => $category_ids
                ),
                'location_id' => $location_id,
                'status' => C('status.product.up'),
                'customer_type' => isset($where['customer_type']) ? $where['customer_type'] : C('customer.type.normal.value'),
                'visiable !=' => C('visio.none')
            ),
            array('updated_time' => 'desc')
        );
        foreach($lists as $key => &$v) {
            $ori_lines = explode(',', $v['line_id']);
            if($v['line_id'] != 0) {
                if(!$inter = array_intersect($ori_lines, $line_ids)) {
                    unset($lists[$key]);
                    continue;
                }
            }
        }

        // category
        $product_cates = array_column($lists, 'category_id');
        // 若是path中有查询的cate_ids
        $cates_arr = $this->MCategory->get_lists__Cache3600('id, path', array('in' => array('id' => $product_cates)));
        $new_arr = [];
        foreach($cates_arr as $cate) {
            foreach($cate_ids as $id_val) {
                $path = ".{$id_val}.";
                if(!is_bool(strpos($cate['path'], $path))) {
                    $new_arr[] = $id_val;
                }
            }
        }
        return array_unique($new_arr);
    }
}

/* End of file catemap.php */
/* Location: ./application/controllers/catemap.php */
