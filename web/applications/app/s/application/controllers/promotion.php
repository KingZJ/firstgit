<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 促销活动规则
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: datetime
 */
class Promotion extends MY_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(
            array(
                'MLocation',
                'MPromotion',
                'MCategory'
            )
        );
    }

    /**
     * 编辑活动
     * @author: caiyilong@ymt360.com
     * @version: 1.0.0
     * @since: 2015-05-12
     */
    public function save() {
    }

    /**
     * 创建活动
     * @author: caiyilong@ymt360.com
     * @version: 1.0.0
     * @since: 2015-05-12
     */
    public function create() {
        $req_time = $this->input->server('REQUEST_TIME');
        $data = array(
            'title'               => $_POST['title'],
            'site_id'             => $_POST['site_id'],
            'location_id'         => $_POST['location_id'],
            'start_time'          => $_POST['start_time'],
            'end_time'            => $_POST['end_time'],
            'latest_deliver_time' => $_POST['latest_deliver_time'],
            'category_ids'        => $_POST['category_ids'],
            'category_limit_num'  => $_POST['category_limit_num'],
            'order_type'          => $_POST['order_type'],
            'limit_first'         => $_POST['limit_first'],
            'status'              => C('status.common.del'),
            'created_time'        => $req_time,
            'updated_time'        => $req_time,
        );

        // 满减
        if($_POST['rule_type'] == C("promotion.code.manjian")) {
            $arr = explode('-', trim($_POST['rule']));
            $event_rule = array(
                'return_profit' => $arr[1] * 100,
                'require_rmb'   => $arr[0] * 100,
            );
            $data['rule_desc'] = json_encode($event_rule);
            $data['rule_type'] = $_POST['rule_type'];
            $this->MPromotion->create($data);
        } else {
            $this->_return_json(
                array(
                    'status' => C('tips.code.op_success'),
                    'msg' => '创建失败，暂不支持此活动类型'
                )
            );
        }

        // 可能需要回滚，目前只是打通流程
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'msg'    => '活动发布成功，默认未上线，请根据需要安排上线。'
            )
        );
    }

    public function lists() {
        $where = isset($_POST['where']) ? $_POST['where'] : '';
        $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : array('created_time' => 'DESC');
        $page = $this->get_page();
        $total = $this->MPromotion->count($where);
        $data = $this->MPromotion->get_lists(
            '*',
            $where,
            $orderBy,
            $page['offset'],
            $page['page_size']
        );
        if($data) {
            $this->_deal_data($data);
            $response = array(
                'status' => C('tips.code.op_success'),
                'total' => $total,
                'list' => $data
            );
        } else {
            $response = array(
                'status' => C('tips.code.op_success'),
                'total'  => 0,
                'list'   => array(),
                'msg'    => '没有数据'
            );
        }
        $this->_return_json($response);
    }

    private function _deal_data(&$data) {
        $locations = $this->MLocation->get_lists('id, name', array('upid' => 0));
        $sites = C("site.code");
        $site_map = array_column($sites, 'name', 'id');
        $location_map = array_column($locations, 'name', 'id');
        foreach($data as &$v) {
            $rule_json_decode = json_decode($v['rule_desc'], TRUE);
            // 满减规则
            if(!empty($v['category_ids'])) {
                // 获取参加活动的分类名字
                $categories = $this->MCategory->get_lists("id, name", array(
                    'in' => array(
                        'id' => explode(",", $v['category_ids'])
                    ),
                ));
                $cate_name = array_column($categories, "name");
                $cate_str = implode(",", $cate_name);
            } else {
                $cate_str = "";
            }
            if($v['rule_type'] == C("promotion.code.manjian")) {
                $rule_json_decode['require_rmb'] /= 100;
                $rule_json_decode['return_profit'] /= 100;
                $v['rule'] = $cate_str . "满{$rule_json_decode['require_rmb']}元，立减{$rule_json_decode['return_profit']}元";
            }
            $v['act_start_time'] = date('Y-m-d', $v['start_time']);
            $v['act_end_time'] = date('Y-m-d', $v['end_time']);
            $v['latest_deliver_timestamp'] = $v['latest_deliver_time'];
            $v['latest_deliver_time'] = date('Y-m-d', $v['latest_deliver_time']);
            $v['site_cn'] = !empty($site_map[$v['site_id']]) ? $site_map[$v['site_id']] : '全网';
            $v['location_cn'] = !empty($location_map[$v['location_id']]) ? $location_map[$v['location_id']] : '全地区';
            $v['updated_time'] = date('Y-m-d H:i:s', $v['updated_time']);
        }
        unset($v);
    }

    public function set_status() {
        $data = array(
            'status' => $_POST['status'],
            'updated_time' => $this->input->server('REQUEST_TIME')
        );
        $where = array(
            'id' => $_POST['id']
        );
        $this->MPromotion->update_info($data, $where);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'msg' => '设置成功'
            )
        );
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 获取指定活动的规则集map
     */
    public function get_rule() {
        $req_time = $this->input->server("REQUEST_TIME");
        $events = array();
        $filter = array(
            'site_id'       => intval($_POST['site_id']) > 0 ? intval($_POST['site_id']) : 0,
            'location_id'   => intval($_POST['location_id']) > 0 ? intval($_POST['location_id']) : 0,
            'start_time <=' => $req_time,
            'end_time >'    => $req_time,
            'status'        => C("status.common.success"),
        );
        if(!empty($_POST['deliver_date'])) {
            $filter['latest_deliver_time >='] = $_POST['deliver_date'];
        }
        if(!empty($_POST['order_type_cnt'])) {
            foreach($_POST['order_type_cnt'] as $type => $cnt) {
                if($cnt > 0) {
                    $filter['limit_first'] = 0;
                } else {
                    unset($filter['limit_first']);
                }
                $filter['order_type'] = $type;
                $valid_events = $this->MPromotion->get_lists("*", $filter);
                if(!empty($valid_events)) {
                    $events = array_merge($events, $valid_events);
                }
            }
        }
        if(empty($events)) {
            $this->_return_json(array(
                'status' => C("tips.code.op_success"),
                'list'   => $events
            ));
        }
        $category_ids = array_column($_POST['cartlist'], "category_id");
        $categories = $this->MCategory->get_lists("id, path", array(
            'in' => array(
                'id' => $category_ids
            ),
        ));
        $path_map = array_column($categories, "path", "id");

        // 检查在分类上是否满足条件
        $category_ids = array();
        foreach($events as $item) {
            if(empty($item['category_ids'])) {
                continue;
            }
            $category_arr = explode(",", $item['category_ids']);
            $category_ids = array_merge($category_ids, $category_arr);
        }

        // 统计每个分类
        $category_sum = array();
        foreach($category_ids as $cate) {
            $category_sum[$cate] = 0;
            foreach($_POST['cartlist'] as $item) {
                $path = $path_map[$item['category_id']];
                if(strpos($path, ".{$cate}.") !== FALSE) {
                    $category_sum[$cate] += $item['price'] * $item['quantity'];
                    // 如果有鸡蛋，则扣掉蛋框钱
                    if( in_array($item['sku_number'], array(1000013, 100020, 100026)) ) {
                        $category_sum[$cate] -= 20 * $item['quantity'];
                    }
                }
            }
        }

        // 统计总价
        $all_sum = 0;
        foreach($_POST['cartlist'] as $item) {
            $all_sum += $item['price'] * $item['quantity'];
        }

        // 生成最终符合条件的规则
        $final_events = array();
        $final_minus = array();
        foreach($events as $item) {
            // 非满减活动先不支持
            if($item['rule_type'] != C("promotion.code.manjian")) {
                continue;
            }

            // 满减活动 如果分类不限，直接按总价计算满减
            $rule_desc = json_decode($item['rule_desc'], TRUE);
            $sum = 0;
            $cate_num = 0;
            $minus = !empty($final_minus[$item['order_type']]) ? $final_minus[$item['order_type']] : 0;
            if(empty($item['category_ids'])) {
                $sum = $all_sum;
                // 如果满足，则加入最终结果集
                if($rule_desc['require_rmb']/100 <= $sum && $rule_desc['return_profit'] > $minus) {
                    $final_events[$item['order_type']] = $item;
                    $final_minus[$item['order_type']] = $rule_desc['return_profit'];
                }
            } else {
                $category_arr = explode(",", $item['category_ids']);
                $cate_sum = 0;
                foreach($category_arr as $cate) {
                    $sum += $category_sum[$cate];
                    $cate_num += $category_sum[$cate] > 0 ? 1 : 0;
                }
                // 如果满足分类总价及分类种类限制，则加入最终结果集
                if($rule_desc['require_rmb']/100 <= $sum && $item['category_limit_num'] <= $cate_num && $rule_desc['return_profit'] > $minus) {
                    $final_events[$item['order_type']] = $item;
                    $final_minus[$item['order_type']] = $rule_desc['return_profit'];
                }
            }
        }

        // 把活动详情内容再丰富下
        $final_events = array_values($final_events);
        $this->_deal_data($final_events);

        // 返回结果
        $this->_return_json(array(
            'status' => C("tips.code.op_success"),
            'list'   => $final_events
        ));
    }
}

/* End of file promotion.php */
/* Location: ./application/controllers/promotion.php */
