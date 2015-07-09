<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 通用product 处理
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: datetime
 */
class Product_lib {

    public $units = array();
    public function __construct() {

        $this->CI = &get_instance();
        $this->CI->load->model(
            array(
                'MProduct',
                'MCategory',
                'MLine',
                'MBucket',
                'MSku',
                'MLocation'
            )
        );
        $this->units = C('unit');
        $this->CI->load->library(array('redisclient'));
        $this->CI->load->helper(array('img_zoom'));
    }

    public function format_product_data(&$lists) {
        // 修改
        $units = $this->units;
        if(!empty($lists)) {
            $customer_type = array_values(C('customer.type'));
            $customer_type_values = array_column($customer_type, 'value');
            $customer_type_combine = array_combine($customer_type_values, $customer_type);
            $list_location_id = array_unique(array_column($lists, 'location_id'));
            $list_sku_numbers = array_unique(array_column($lists, 'sku_number'));
            $location = $this->CI->MLocation->get_lists__Cache30(
                'name, id',
                array(
                    'in' => array(
                        'id' => $list_location_id
                    )
                )
            );
            $lines = $this->CI->MLine->get_lists__Cache120(
                'name, id'
            );
            $new_lines = array_combine(array_column($lines, 'id'), $lines);
            // 获取sku信息
            $sku_lists = $this->CI->MSku->get_lists__Cache30(
                'pic_ids, sku_number',
                array(
                    'in' => array(
                        'sku_number' => $list_sku_numbers
                    )
                )
            );
            // 获取分类信息
            $cat_ids = array_column($lists, "category_id");
            $categories = $this->CI->MCategory->get_lists__Cache120(
                'id, path'
            );
            $cate_path_map = array_column($categories, "path", "id");
            // 合并键值
            $new_sku = array_combine(
                array_column($sku_lists, 'sku_number'), $sku_lists
            );
            $visiables = C('visiable');
            $new_visiable_arr = array_combine(array_column($visiables, 'id'), $visiables);

            // 线路默认位0
            $line_ids = array(0);
            if(!empty($_POST['user_info'])) {
                $user_info = $_POST['user_info'];
                $line_ids = array_merge($line_ids, array(intval($user_info['line_id'])));
            }
            foreach($lists as $key => &$v) {
                $v['category_path'] = $cate_path_map[$v['category_id']];
                $v['spec'] = json_decode($v['spec'], TRUE);
                $v['spec'] = $this->_check_unique_spec($v['spec']);
                $v['price'] = sprintf("%.2f", ($v['price'] / 100));
                $v['updated_origin_time'] = $v['updated_time'];
                $v['updated_time'] = date('Y-m-d H:i:s', $v['updated_time']);
                $v['market_price'] = sprintf("%.2f", ($v['market_price'] / 100));
                $v['single_price'] = sprintf("%.2f", ($v['single_price'] / 100));
                $customer_type_cn = isset($customer_type_combine[$v['customer_type']]) ? $customer_type_combine[$v['customer_type']]['name'] : C('customer.type.normal.name');
                $v['customer_type_cn'] = $customer_type_cn;

                if(isset($new_sku[$v['sku_number']])) {
                    $v['pic_ids'] = $new_sku[$v['sku_number']]['pic_ids'];
                }
                if(!empty($v['pic_ids'])) {
                    $pic_ids_arr = explode(',', $v['pic_ids']);
                    $v['pictures'] = $this->CI->MBucket->get_lists__Cache10(
                        'id, pic_url, file_size',
                        array(
                            'in' => array('id' => $pic_ids_arr)
                        ),
                        array(
                            'id' => 'ASC'
                        )
                    );
                    $pictures = $v['pictures'];
                    $v['pictures'] = img_zoom($pictures, '-240-');
                    $v['big_imgs'] = img_zoom($pictures, '-600-');
                }
                $v['unit'] = $this->get_unit_name($v['unit_id']);
                if($v['storage'] == -1) {
                    $v['storage_cn'] = '足量库存';
                } else {
                    $v['storage_cn'] = '剩余' . $v['storage'] . $v['unit'];
                }
                $v['buy_limit_cn'] = empty($v['buy_limit']) ? '不限购' : $v['buy_limit'] . $v['unit'];
                $v['visiable_cn'] = $new_visiable_arr[$v['visiable']]['name'];
                if(!empty($v['line_id'])){
                    $l_ids = explode(',', $v['line_id']);
                    $v['line_cn'] = '';
                    foreach($l_ids as $l_val) {
                        $v['line_cn'] .= $new_lines[$l_val]['name'] . ';';
                    }
                }
                $redis_prefix = C('redis_prefix.product_storage');
                // 设置库存池 检测库存池中有数据
                if(!empty($v['status']) && $v['storage'] != -1 && is_bool($this->CI->redisclient->hget($v['id'], 'storage'))) {
                    $this->CI->redisclient->hset($v['id'], 'storage', $v['storage']);
                }
                // 设置库存池 检测库存池中有数据
                if(!empty($v['status']) && $v['buy_limit'] && is_bool($this->CI->redisclient->hget($v['id'], 'buy_limit', $v['buy_limit']))) {
                    $this->CI->redisclient->hset($v['id'], 'buy_limit', $v['buy_limit']);
                }
                $location_name = '';
                foreach($location as $loc) {
                    if($loc['id'] == $v['location_id']) {
                        $location_name = $loc['name'];
                    }
                }
                $v['location_name'] = $location_name;
                $v['close_unit_name'] = $this->get_unit_name($v['close_unit']);
            }
        }
        return $lists;

    }

    public function get_unit_name($id) {
        $name = '';
        foreach($this->units as $unit_val) {
            if($unit_val['id'] == $id) {
                $name = $unit_val['name'];
            }
        }
        return $name;
    }
    // 确保spec 唯一
    private function _check_unique_spec($spec) {
        $name_arr = $new_spec = array();
        if($spec) {
            foreach($spec as $v) {
                if(isset($v['name']) && $v['name'] != '单价') {
                    if(!in_array($v['name'], $name_arr)) {
                        $new_spec[] = $v;
                    }
                    $name_arr[] = $v['name'];
                }
            }
        }
        return $new_spec;
    }

    public function get_unit_id($name) {
        $id = $this->units[0]['id'];
        foreach($this->units as $v) {
            if($v['name'] == $name) {
                $id = $v['id'];
            }
        }
        return $id;
    }

}

/* End of file product.php */
/* Location: ./application/controllers/product.php */
