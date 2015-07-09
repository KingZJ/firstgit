<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 客户模型
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: datetime
 */
class Customer_coupon extends MY_Controller {
    private $_rules_type;
    public function __construct() {
        parent::__construct();
        $this->load->model(
            array(
                'MCustomer_coupons',
                'MCustomer',
                'MOrder',
                'MLocation',
                'MCoupons',
                'MCategory_map',
                'MCoupon_rules'
            )
        );
        $this->load->helper(array('coupon_code', 'compare_minus_amount'));
        $this->_rules_type  = C('coupon_rule_type');
        $this->app_sites = array_values(C('app_sites'));
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 用户优惠券
     */
    public function lists() {
        $_POST['where'] = empty($_POST['where']) ? array() : $_POST['where'];
        $where = '';
        $current = strtotime(date('Y-m-d', $this->input->server('REQUEST_TIME')));
        if(isset($_POST['status'])) {
            if(intval($_POST['status']) === 0) {
                $status = C('coupon_status.used.value') . ',' .C('coupon_status.exceed_time.value');
                $where = "(valid_time > {$current} OR invalid_time < {$current} OR status in ($status)) AND status != 0";
            } else {
                $status = C('coupon_status.valid.value') . ',' .C('coupon_status.invalid.value');
                //$status = C('coupon_status.valid.value');
                $where = "valid_time <= {$current} AND invalid_time >= {$current} and status in ($status)";
            }
        }
        if(isset($_POST['where']['customer_id'])) {
            $where .= ' AND customer_id =' . $_POST['where']['customer_id'];
        }
        $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : 'created_time DESC';
        $page = $this->get_page();
        $total = $this->MCustomer_coupons->count_by_sql($where);
        $customer_total_where = array();
        if(isset($_POST['where']['customer_id'])) {
            $customer_total_where = array('customer_id' => $_POST['where']['customer_id']);
        }
        $all_coupon_nums = $this->MCustomer_coupons->count($customer_total_where);
        $data = $this->MCustomer_coupons->get_lists_by_sql(
            '*',
            $where,
            'id desc',
            array(),
            $page['offset'],
            $page['page_size']
        );
        if($data) {
            $response = $this->_format_customer_coupon_data($data);
            $response['all_nums'] = $all_coupon_nums;
            $response['total'] = $total;
        } else {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => '暂无优惠券'
            );
        }
        $this->_return_json($response);
        // 查出用户拥有的优惠券
        // 优惠券信息的常规信息
        // 优惠券对应的减免规则
    }


    /**
     * @author: liaoxianwen@ymt360.com
     * @description 用户优惠券
     */
    public function manage() {
        $_POST['where'] = empty($_POST['where']) ? array() : $_POST['where'];
        $where = '';
        $user_info = [];
        if(isset($_POST['where']['mobile'])) {
            $user_info = $this->MCustomer->get_one('id', array('mobile' => $_POST['where']['mobile']));
            $_POST['where']['customer_id'] = $user_info['id'];
        }

        $current = strtotime(date('Y-m-d', $this->input->server('REQUEST_TIME')));
        if(isset($_POST['where']['status'])) {
            $_POST['status'] = $_POST['where']['status'];
            if(intval($_POST['status']) === 0 || $_POST['status'] == 3) {
                $where = "status = {$_POST['status']}";
            } else if($_POST['status'] == 1) {
                $status = C('coupon_status.valid.value') . ',' .C('coupon_status.invalid.value');
                $where = "valid_time <= {$current} AND invalid_time >= {$current} and status in ($status)";
            } else if($_POST['status'] == 2) {
                $where = "valid_time > {$current}";
                //已使用
            } else {
                $where = "invalid_time <  {$current}";
                // 过期
            }
        }
        if(isset($_POST['where']['customer_id'])) {
            if($where) {
                $where .= ' AND ';
            }
            $where .= 'customer_id =' . $_POST['where']['customer_id'];
        }
        // 查询优惠券的有效期
        $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : 'created_time DESC';
        $page = $this->get_page();
        $total = $this->MCustomer_coupons->count_by_sql($where);
        $customer_total_where = array();
        if(isset($_POST['where']['customer_id'])) {
            $customer_total_where = array('customer_id' => $_POST['where']['customer_id']);
        }
        $all_coupon_nums = $this->MCustomer_coupons->count($customer_total_where);
        $data = $this->MCustomer_coupons->get_lists_by_sql(
            '*',
            $where,
            'id desc',
            array(),
            $page['offset'],
            $page['page_size']
        );
        if($data) {
            $response = $this->_format_customer_coupon_data($data);
            $response['all_nums'] = $all_coupon_nums;
            $response['total'] = $total;
        } else {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => '暂无优惠券'
            );
        }
        $this->_return_json($response);
        // 查出用户拥有的优惠券
        // 优惠券信息的常规信息
        // 优惠券对应的减免规则
    }

    public function count() {
        $current = strtotime(date('Y-m-d', $this->input->server('REQUEST_TIME')));
        $where = array();
        if(isset($_POST['status'])) {
            $where['status'] = $_POST['status'];
        }
        if(isset($_POST['customer_id'])) {
            $where['customer_id'] = $_POST['customer_id'];
        }

        $where['valid_time <='] =$current;
        $where['invalid_time >='] = $current;
        $total = $this->MCustomer_coupons->count($where);
        $this->_return_json(
            array(
                'status' => C('tips.code.op_success'),
                'total' => $total
            )
        );
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 获取有效的优惠券
     */
    public function valid_coupon() {
        try{
            $current = strtotime(date('Y-m-d', $this->input->server('REQUEST_TIME')));
            $where['valid_time <='] = $current;
            $where['customer_id'] = $_POST['customer_id'];
            $where['status'] = C('coupon_status.valid.value');
            $where['invalid_time >='] = $current;
            $where['require_amount <='] = $_POST['total_price'] * 100;
            $where['minus_amount <='] = $_POST['total_price'] * 100;
            // 一次把所有的都返回
            $data = $this->MCustomer_coupons->get_lists('*', $where);
            if($data) {
                $data = $this->_format_customer_coupon_data($data);
                //
                $response = array(
                    'status' => C('tips.code.op_success'),
                    'list' => $data['list']
                );
            } else {
                $response = array(
                    'status' => C('tips.code.op_failed'),
                    'msg' => '暂无优惠券'
                );
            }
        } catch(Exception $e) {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => $e->getMessage()
            );
        }
        $this->_return_json($response);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 格式化用户优惠券的数据
     */
    private function _format_customer_coupon_data($data) {
        $coupon_ids = array_column($data, 'coupon_id');
        $customer_ids = array_unique(array_column($data, 'customer_id'));
        $customers = $this->MCustomer->get_lists('shop_name, id, mobile', array('in' => array('id' => $customer_ids)));
        $customer_ids = array_column($customers, 'id');
        $new_customers = array_combine($customer_ids, $customers);
        $coupon_where = array(
            'in' => array(
                'id' => $coupon_ids
            ),
        );
        if(!empty($_POST['site_id'])) {
            $coupon_where['site_id'] = $_POST['site_id'];
        }
        $coupons = $this->MCoupons->get_lists('*', $coupon_where);
        if($coupons) {
            $coupon_detail = [];
            $locations = $this->MLocation->get_lists__Cache120('*', array('upid' => 0));
            $new_locations = array_combine(array_column($locations, 'id'), $locations);
            $new_sites = array_combine(array_column($this->app_sites, 'id'), $this->app_sites);
            $new_rules_type = array_combine(array_column($this->_rules_type, 'id'), $this->_rules_type);
            foreach($coupons as $cou_val) {
                $rule_info = $this->MCoupon_rules->get_one('*', array('id' => $cou_val['coupon_rule_id']));
                // 支持某个分类的优惠券
                if($cou_val['category_ids'] != 0) {
                    $category_ids = explode(',', $cou_val['category_ids']);
                    $catemaps = $this->MCategory_map->get_lists('*', array('id' => $category_ids));
                    $description = '仅限' . implode(',', array_unique(array_column($catemaps, 'name')));
                } else {
                    $description = '全品类通用';
                }
                $cou_val['site_cn'] = $new_sites[$cou_val['site_id']]['name'];
                $cou_val['location_cn'] = $new_locations[$cou_val['location_id']]['name'];

                $rule_info['rule_type_cn'] = $new_rules_type[$rule_info['rule_type']]['name'];
                $cou_val['valid_time'] = date('Y-m-d', $cou_val['valid_time']);
                $cou_val['invalid_time'] = date('Y-m-d', $cou_val['invalid_time']);
                $coupon_detail[$cou_val['id']] = array(
                    'coupon_info' => $cou_val,
                    'description' => $description,
                    'rule_info' => $rule_info
                );
            }
            $current = strtotime(date('Y-m-d', $this->input->server('REQUEST_TIME')));
            // 优惠券活动的location_id site_id
            foreach($data as &$v) {
                $detail = $coupon_detail[$v['coupon_id']];
                $v['updated_time'] = date('Y-m-d H:i', $v['updated_time']);
                $v['customer_info'] = empty($new_customers[$v['customer_id']]) ? array() : $new_customers[$v['customer_id']];
                $v['require_amount'] /= 100;
                $v['minus_amount'] /= 100;
                if(($v['valid_time'] > $current || $v['invalid_time'] < $current) &&  $v['status'] != 3 & $v['status'] != 0) {
                    if($v['invalid_time'] < $current) {
                        $v['status'] = C('coupon_status.exceed_time.value');
                        $v['status_cn'] = C('coupon_status.exceed_time.name');
                    } else {
                        $v['status'] = C('coupon_status.invalid.value');
                        $v['status_cn'] = C('coupon_status.invalid.name');
                    }
                } else {
                    if($v['status'] == 1) {
                        $v['status_cn'] = C('coupon_status.valid.name');
                    } else if($v['status'] == 3) {
                        $v['status_cn'] = C('coupon_status.used.name');
                    } else {
                        $v['status_cn'] = C('coupon_status.forbid.name');
                    }
                }
                if(empty($v['customer_id'])) {
                    $v['status'] = 0;
                    $v['status_cn'] = C('coupon_status.forbid.name');
                }

                $v['detail'] = array(
                    'require_amount' => $v['require_amount'],
                    'minus_amount' => $v['minus_amount'],
                    'valid_time' => $detail['coupon_info']['valid_time'],
                    'site_cn' => $detail['coupon_info']['site_cn'],
                    'location_cn' => $detail['coupon_info']['location_cn'],
                    'invalid_time' => $detail['coupon_info']['invalid_time'],
                    'rule_type_cn' => $detail['rule_info']['rule_type_cn'],
                    'description' => $detail['description']
                );
            }
            $response = array(
                'status' => C('tips.code.op_success'),
                'list' => $data
            );
        }
        return !empty($response) ? $response : array();
    }
    public function check_coupon_valid() {
        // 若是全品类的或者全部商品优惠
        // 当前提交的优惠券
        $info = $this->MCustomer_coupons->get_one('*', array('id' => $_POST['id'], 'customer_id' => $_POST['customer_id']));
        // 检测是否为全品类
        $date_format_time = strtotime(date('Y-m-d', $this->input->server('REQUEST_TIME')));
        $where = array(
            'valid_time <=' => $date_format_time,
            'invalid_time >=' => $date_format_time,
            'require_amount <=' => $_POST['total_price'],
            'minus_amount <=' => $_POST['total_price'],
            'id' => $_POST['id'],
            'customer_id' => $_POST['customer_id'],
            'status' => C('coupon_status.valid.value')
        );
        $info = $this->MCustomer_coupons->get_one('*', $where);
        if($info) {
            $response = array(
                'status' => C('tips.code.op_success'),
                'info' => $info
            );
        } else {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => '此券无效'
            );
        }
        $this->_return_json($response);
    }

    private function _check_coupon_valid_by_category() {
        // 检测购买的商品的分类是否在规定的优惠分类中
    }

    private function _check_coupon_valid_by_product() {
        // 检测商品中是否有在优惠中得商品
    }

    private function _check_coupon_resolve($coupon) {
        if(!empty($coupon['category_ids'])) {
            return $this->_check_coupon_valid_by_category();
        } else if(!empty($coupon['product_ids'])) {
            return $this->_check_coupon_valid_by_product();
        }
        return TRUE;
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 
     */
    public function create() {
        $coupon_info = $this->MCoupons->get_one('*', array('coupon_nums >' => 0, 'id' => $_POST['coupon_id']));
        if($coupon_info) {
            $rule_info = $this->MCoupon_rules->get_one('*', array('id' => $coupon_info['coupon_rule_id']));
            // 比较减免3000额度
            if($compare_info = compare_minus_amount($rule_info['minus_amount']))  {
                $this->_return_json($compare_info);
            }
            // 根据优惠券活动的site_id location_id line_ids 来确定用户数量
            $customer_where = array(
                'province_id' => $coupon_info['location_id'],
                'status !=' => C('status.common.del'),
            );
            if($coupon_info['line_ids'] != 0) {
                $line_ids = explode(',', $coupon_info['line_ids']);
                $customer_where['in'] = array('line_id' => $line_ids);
            }
            if(isset($_POST['customer_ids']) && is_array($_POST['customer_ids']) && $_POST['customer_ids']) {
                $customer_where['in']['id'] = $_POST['customer_ids'];
            }
            $customers = $this->MCustomer->get_lists('*', $customer_where);
            if($customers) {
                $coupon_codes = coupon_code_create(count($customers));
                $coupon_nums = empty($_POST['coupon_nums']) ? 1 : $_POST['coupon_nums'];
                foreach($customers as $key => $customer) {
                    //用户优惠券的创建
                    $data[] = array(
                        'coupon_id' => $_POST['coupon_id'],
                        'customer_id' => $customer['id'],
                        'coupon_rule_id' => $coupon_info['coupon_rule_id'],
                        'coupon_code' => $coupon_codes[$key],
                        'require_amount' => $rule_info['require_amount'],
                        'minus_amount' => $rule_info['minus_amount'],
                        'coupon_nums' => $coupon_nums,
                        'status' => C('status.common.success'),
                        'valid_time' => $coupon_info['valid_time'],
                        'invalid_time' => $coupon_info['invalid_time'],
                        'created_time' => $this->input->server('REQUEST_TIME'),
                        'updated_time' => $this->input->server('REQUEST_TIME')
                    );
                }
                $affect_rows = $this->MCustomer_coupons->create_batch($data);
                if($affect_rows) {
                    // 更新coupons 的coupon_nums coupon_used_nums
                    $coupon_updata = array(
                        'coupon_nums' => $coupon_info['coupon_nums'] -1,
                        'coupon_used_nums' => $coupon_info['coupon_used_nums'] + 1
                    );
                    $this->MCoupons->update_info($coupon_updata, array('id' => $coupon_info['id']));
                    $total_coupon = $affect_rows * $coupon_nums;
                    $response = array(
                        'status' => C('tips.code.op_success'),
                        'msg' => "券码分发成功,共发放{$affect_rows}个用户，发放了{$total_coupon}"
                    );
                }
            } else {
                $response = array(
                    'status' => C('tips.code.op_failed'),
                    'msg' => '券活动信息有误'
                );
            }
        } else {
            $response = array(
                'status' => C('tips.code.op_failed'),
                'msg' => '券活动信息有误'
            );
        }
        $this->_return_json($response);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 单个的优惠券信息
     */
    public function info() {
        $coupon_info = $this->MCustomer_coupon->get_one('*', array('id' => $_POST['id']));
        $response = array('status' => C('tips.code.op_faild') , 'msg' => '没有此优惠券');
        if($coupon_info) {
            $response = array(
                'status' => C('tips.code.op_success'),
                'info' => $coupon_info
            );
        }
        $this->_return_json($response);
    }
    /**
     * @author: liaoxianwen@ymt360.com
     * @description 
     */
    public function set_status() {
        $updata = array(
            'status' => $_POST['status']
        );
        $where = array('id' => $_POST['id']);
        $this->MCustomer_coupons->update_info($updata, $where);
        $this->_return_json(array('status' => C('tips.code.op_success'), 'msg' => '设置成功'));
    }

    public function set_coupon_used_nums() {
        $where = array('id' => $_POST['id'], 'status' => C('coupon_status.valid.value'));
        $info = $this->MCustomer_coupons->get_one("*", $where);
        $response = array(
            'status' => C('tips.code.op_failed'),
            'msg' => '无优惠券'
        );
        $order_info = $this->MOrder->get_one('*', array('customer_coupon_id' => $_POST['id']));
        if($info && $order_info) {
            if($info['coupon_nums'] >= 1) {
                $data = array(
                    'coupon_nums' => 0,
                    'coupon_used_nums' => 1,
                );
                $data['status'] = C('coupon_status.used.value');
                $affect_rows = $this->MCustomer_coupons->update_info($data, $where);
                $response = array(
                    'status' => C('tips.code.op_success')
                );
            }
        }

        $this->_return_json($response);
    }
}

/* End of file customer_coupon.php */
/* Location: ./application/controllers/customer_coupon.php */
