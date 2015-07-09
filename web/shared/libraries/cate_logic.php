<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cate_logic {

    public function __construct() {
        $this->CI = &get_instance();
        $this->CI->load->model(
            array("MCategory", "MProperty")
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 获取顶级分类，然后将其中的所有的分类都取出来
     */

    public function lists($post = array(), $front = TRUE) {
        if($front) {
            $app_name = $post['app_name'];
            $cates = $this->_category_map($app_name);
        } else {
            $cates = $this->_backend_category();
        }
        extract($cates);
        // 三级分类
        $second_ids = array_column($second_cate, 'id');
        $third_cate = $this->get_child($second_ids);
        $third_ids = array_column($third_cate, 'id');
        $forth_cate = $this->get_child($third_ids);
        return array(
            'top_cate'   => $top_cate,
            'second_cate'=> $second_cate,
            'third_cate' => $third_cate,
            'forth_cate' => $forth_cate
        );
    }
    /*
     * 后台分类显示
     *
     */
    private function _backend_category() {
        $where = array(
            'upid'  => 0,
            'status'=> intval(C('status.common.success'))
        );
        $top_cate = $this->CI->MCategory->get_lists(
            'path, name, id',
            $where,
            array('weight' => 'DESC', 'updated_time' => 'DESC')
        );
        // 如果是二级分类，那么隐藏，显示直接三级分类
        $top_ids = array_column($top_cate, 'id');
        $second_cate = $this->get_child($top_ids);
        return array(
            'top_cate' => $top_cate,
            'second_cate' => $second_cate
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 前台商品分类映射
     */
    private function _category_map($app_name) {
        // top
        $site_default = $app_name;
        $top_categories = C('categories.' . $site_default  . '.top');
        // 二级类
        $second_categories = C('categories.' . $site_default .'.second');
        // 根据映射表
        $top_cate = $this->_get_by_name($top_categories);
        //二级分类
        $top_ids = array_column($top_cate, 'id');
        $second_arr = array_combine($top_ids, $second_categories);
        // 获取二级类
        $second_categories = $this->_deal_arr_category($second_categories);
        $second_cate = array();
        // 将二级拼接
        foreach($second_arr as $second_val) {
            $second = $this->_get_by_name($second_val);
            foreach($second as $v) {
                $second_cate[] = $v;
            }
        }
        return array(
            'top_cate' => $top_cate,
            'second_cate' => $second_cate
        );
    }
    private function _deal_arr_category($multi_arr_cate) {
        $new_cate_arr = array();
        foreach($multi_arr_cate as $mcate_val) {
            foreach($mcate_val as $v) {
                $new_cate_arr[] = $v;
            }
        }
        return $new_cate_arr;
    }

    private function _get_by_name($names) {
         $where = array(
            'in' => array('name' => $names),
            'status'=> intval(C('status.common.success'))
        );
        // top
        $cates = $this->CI->MCategory
            ->get_lists(
                'path, name, id',
                $where,
                array('weight' => 'DESC')
            );
        return $cates;
    }

    public function get_child($up_ids) {
        $childs = $this->_get_child_by_ids($up_ids);
        $new_child = array();
        if($childs) {
            $child_ids = array_column($childs, 'id');
            $new_child = $this->_get_child_by_ids($child_ids);
        }
        $childs = array_merge($childs, $new_child);
        return $childs;
    }

    private function _get_child_by_ids($up_ids) {
        $childs = array();
        if(!empty($up_ids)) {
            $childs = $this->CI->MCategory ->get_lists__Cache3600("path, name, id, upid",
                array(
                    'in' => array(
                        'upid'  => $up_ids
                    ),
                    'status'    => intval(C('status.common.success'))
                ),
                array('weight'  => 'DESC')
            );
        }
        return $childs;
    }
    public function get_spec($path, $id) {
        $path = trim($path, '.');
        $path_arr = explode('.', $path);
        $property = $this->_get_specs($path_arr); 
        $new_arr = array_combine($path_arr, $property);
        $spec = [];
        foreach($new_arrr as $v) {
            if($v) {
                $spec = $v;
            }
        }
        return $spec;
    }
    private function _get_specs($ids) {
        $property = $this->CI->MProperty->get_lists(
            "id,name",
            array(
                'in' => array(
                    'category_id' => $ids
                )
            )
        );
        return $property;
    }

    public function get_by_ids($category_ids) {
        $categories = $this->CI->MCategory->get_lists('id, name, path',
            array(
                'in'    => array(
                    'id'    => $category_ids
                )
            )
        );
        return $categories;
    }

    public function get_map() {
        $data = $this->CI->MCategory->get_lists('id,name,path,upid');
        $top = $second = array();
        // 一级、二级
        foreach($data as $v) {
            $path = trim($v['path'], '.');
            $path_arr = explode('.', $path);
            $nums = count($path_arr);
            if( $nums === 1) {
                // 可能是一级
                $top[] = $v;
            } else if($nums === 2 ) {
                $second[] = $v;
            }
        }

        $top_child = $this->_get_map_child($top);
        $second_child = $this->_get_map_child($second);
        return array(
            'top' => $top,
            'second' => $second,
            'top_child' => $top_child,
            'second_child' => $second_child
        );
    }

    private function _get_map_child($top) {
        $childs = array();
        foreach($top as $v) {
            $data = $this->CI->MCategory->get_lists(
                'id,name,path,upid',
                array(
                    'id !=' => $v['id'],
                    'like' => array('path' => $v['path'])
                )
            );
            if($data) {
                foreach($data as $v) {
                    $childs[] = $v;
                }
            }
        }
        $ids = $new_child = array();
        foreach($childs as $v) {
            if(!in_array($v['id'], $ids)) {
                $new_child[] = $v;
            }
            $ids[] = $v['id'];
        }
        return $new_child;
    }
}

/* End of file  cate_logic.php*/
/* Location: :./application/libraries/cate_logic.php/ */
