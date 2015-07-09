<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dirtyrun extends MY_Controller {

    public function __construct () {
        parent::__construct();
        $this->load->model(
            array(
                'MCategory',
                'MProduct',
                'MOrder_detail',
                'MOrder',
                'MSuborder',
                'MLine',
                'MCustomer',
                'MPotential_customer',
                'MCustomer_transfer_log',
                'MAbnormal_order',
                'MAbnormal_content',
                'MComplaint',
                'MUser',
            )
        );
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 修复t_order_detail表中未写入的product_id
     */
    public function fix_order_detail() {
        $where = ['product_id' => 0];
        $details = $this->MOrder_detail->get_lists(
            'id, name, spec',
            $where
        );
        foreach($details as $item) {
            $product = $this->MProduct->get_one(
                'id',
                array(
                    'title' => $item['name'],
                    'spec'  => $item['spec']
                )
            );
            if($product) {
                $res = $this->MOrder_detail->update_info(
                    array(
                        "product_id" => $product['id']
                    ),
                    array(
                        "id" => $item["id"]
                    )
                );
                if(!$res) {
                    echo "update detail " . $item['id'] . " failed\n";
                }
            } else {
                echo "detail " . $item['id'] . " has no product record in product table \n";
            }
        }
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 修复现在的t_order表里的site_src字段
     */
    public function fix_order_siteid() {
        //获取全部order
        $orders = $this->MOrder->get_lists(
            'id, user_id',
            array(
                'site_src' => 0
            )
        );
        if(empty($orders)) {
            echo "orders all have site_src, no need to fix";
            return;
        }

        //下单用户批量查询
        $user_ids = array_column($orders, 'user_id');
        $users = $this->MCustomer->get_lists(
            'id, site_id',
            array(
                'in' => array(
                    'id' => $user_ids
                )
            )
        );
        $user_ids = array_column($users, 'id');
        $user_map = array_combine($user_ids, $users);

        //update site_src in t_order
        foreach($orders as $order_item) {
            $user_id = $order_item['user_id'];
            $order_user = isset($user_map[$user_id]) ? $user_map[$user_id] : [];
            if($user_id == 0 || empty($order_user)) {
                echo "order {$order_item['id']} has no user data\n";
                continue;
            }
            $site_id = $order_user['site_id'];
            if($site_id == 0) {
                echo "the site_id of user {$order_user['id']} in order {$order_item['id']} \n";
                continue;
            }
            $order_id = $order_item['id'];
            $this->MOrder->update_info(
                array(//data
                    'site_src' => $site_id
                ),
                array(//where
                    'id' => $order_id
                )
            );
        }
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 修复因为t_order_detail表中的spec字段长度不够时
     * 导致从product表拷spec截断的问题
     */
    public function fix_order_detail_spec() {
        $order_details = $this->MOrder_detail->get_lists(
            'id, product_id',
            []
        );
        $product_ids = array_column($order_details, 'product_id');
        $products = $this->MProduct->get_lists(
            'id, spec',
            array(
                'in' => array(
                    'id' => $product_ids
                )
            )
        );
        $product_ids = array_column($products, 'id');
        $product_map = array_combine($product_ids, $products);

        foreach($order_details as $detail_item) {
            $product_id = $detail_item['product_id'];
            $detail_id = $detail_item['id'];
            if($product_id == 0) {
                echo "the product_id of detail $detail_id is 0\n";
                continue;
            }
            if(empty($product_map[$product_id])) {
                echo "detail {$detail_id} has no product info\n";
                continue;
            }
            $product = $product_map[$product_id];
            $spec = $product['spec'];
            $this->MOrder_detail->update_info(
                //data, where
                array(
                    'spec' => $spec
                ),
                array(
                    'id' => $detail_id
                )
            );
        }
    }

    /**
     * 处理没有商圈的数据
     * id从372至499
     * @author yugang@dachuwang.com
     * @since 2015-03-18
     */
    public function deal_data() {
        $file = fopen('/tmp/customer.csv', 'r');
        $count = 1;
        while(!feof($file)){
            $row = fgetcsv($file);
            if($row['1'] >= 372 && $row['1'] <=499){
                $count++;
                $data['county_id'] = $row['6'];
                $this->MCustomer->update_info($data, array('id' => $row['1']));
                echo '<br/>' . $count . ' : ' . $this->db->last_query(). '<br/>';
            }
        }
        fclose($file);
    }

    /**
     * 修复商圈线路数据
     * @author: caiyilong@ymt360.com
     * @version: 1.0.0
     * @since: 2015-03-19
     */
    public function deal_line_data() {
        $file = fopen('/tmp/customer_lines_utf8.csv', 'r');
        $lines = $this->MLine->get_lists(
            array('id', 'name'),
            array('status' => 1)
        );
        $line_map = array_column($lines, 'id', 'name');
        while(!feof($file)) {
            $row = fgetcsv($file);
            $id = $row[0];
            $name = $row[1];
            if($id > 0) {
                $data = array(
                    'line_id' => !empty($line_map[$name]) ? $line_map[$name] : 0
                );
                echo "{$id}\t{$line_map[$name]}\t{$name}\r\n";
                //$this->MCustomer->update_info($data, array('id' => $id));
            }
        }
    }

    /**
     * @author caochunhui@dachuwang.com
     * @description 修复t_order表里的line_id
     */
    public function fix_order_line_id() {
        $deliver_date = strtotime(date('Y-m-d')) + 86400;
        $orders = $this->MOrder->get_lists(
            'id, user_id',
            array(
                'deliver_date >=' => $deliver_date,
                'status !=' => 0
            )
        );
        $user_ids  = array_column($orders, 'user_id');
        $users = $this->MCustomer->get_lists(
            'id, line_id',
            array(
                'in' => array(
                    'id' => $user_ids
                )
            )
        );
        $user_ids = array_column($users, 'id');
        $user_map = array_combine($user_ids, $users);
        foreach($orders as $item) {
            $user_id = $item['user_id'];
            $order_id = $item['id'];
            $line_id = isset($user_map[$user_id]) ? $user_map[$user_id]['line_id'] : 0;
            if(!$line_id) {
                echo "the line_id of order {$item['id']} is 0\n";
                continue;
            }
            $this->MOrder->update_info(
                array(
                    'line_id' => $line_id
                ),
                array(
                    'id' => $order_id
                )
            );
        }
        echo "done\n";
    }

    /**
     * 修复配送日期包含时分秒的订单数据
     * @author yugang@dachuwang.com
     * @since 2015-03-20
     */
    public function deal_deliver_date() {
        $order_list = $this->MOrder->get_lists('*');
        echo '修复订单异常配送日期sql语句列表：<br>';
        $count = 0;
        foreach ($order_list as $order) {
            $deliver_date = $order['deliver_date'];
            $right_deliver_date = strtotime(date('Y-m-d', $deliver_date));
            if($deliver_date != $right_deliver_date) {
                $data = array();
                $data['deliver_date'] = $right_deliver_date;
                $this->MOrder->update_info($data, array('id' => $order['id']));
                echo $this->db->last_query() . ' : 错误送货日期为'. date('Y-m-d H:i:s', $deliver_date) . ' ' . $deliver_date . '<br/>';
                $count++;
            }
        }
        echo '共修复订单配送日期异常数据' . $count . '条<br>';
    }

    //修正t_order里的status为0的order对应的t_order_detail里的status
    public function fix_order_detail_status() {
        $orders = $this->MOrder->get_lists(
            'id, status',
            array(
            )
        );
        $order_ids = array_column($orders, 'id');
        $order_status = array_column($orders, 'status');
        $status_map = array_combine($order_ids, $order_status);
        foreach($order_ids as $order_id) {
            $status = $status_map[$order_id];
            $this->MOrder_detail->update_info(
                array(
                    'status'   => $status
                ),
                array(
                    'order_id' => $order_id
                )
            );
        }
    }


    //修复大厨的商品
    public function fix_dachu_product_close_unit_and_single_price() {
        $dachu_category_sql = "select id from t_category where path like '.1.%' or path like '.6.%'";
        $category_ids = $this->db->query($dachu_category_sql)->result_array();
        $category_ids = array_column($category_ids, 'id');
        //var_dump($category_ids);
        $products = $this->MProduct->get_lists(
            'id, price, unit_id',
            array(
                'single_price' => 0,
                'in' => array(
                    'category_id' => $category_ids
                )
            )
        );
        //var_dump($products);
        //print_r($this->db->last_query());
        foreach($products as $item) {
            $this->MProduct->update_info(
                array(
                    'close_unit'   => $item['unit_id'],
                    'single_price' => $item['price']
                ),
                array(
                    'id' => $item['id']
                )
            );
            echo $this->db->last_query();
            echo "\n";
        }
    }

    //根据product表修复t_order_detail表中的category_id
    public function fix_category_id_for_order_detail() {
        $products = $this->MProduct->get_lists(
            'id, category_id',
            array()
        );

        $product_ids = array_column($products, 'id');
        $product_map = array_combine($product_ids, $products);

        foreach($product_map as $product_id => $product) {
            $category_id = $product['category_id'];
            $update_res = $this->MOrder_detail->update_info(
                array(
                    'category_id' => $category_id
                ),
                array(
                    'product_id' => $product_id
                )
            );
            echo $this->db->last_query();
            echo "\n";
            if(!$update_res) {
                echo "update product {$product_id} failed\n";
            }
        }
    }

    /**
     * 处理历史订单对应销售信息
     * @author yugang@dachuwang.com
     * @since 2015-05-05
     */
    public function fix_order_sale_data() {
        ini_set("memory_limit", "1024M");
        set_time_limit(0);

        // 获取所有的销售列表
        $sale_role = array('12', '13', '14', '15', '16');
        $sale_list = $this->MUser->get_lists('*', array('in' => array('role_id' => $sale_role)));
        $sale_ids = array_column($sale_list, 'id');
        $sale_dict = array_combine($sale_ids, $sale_list);

        $order_list = $this->MOrder->get_lists('id, user_id');
        $update_list = array();
        $count = 1;

        foreach ($order_list as $order) {
            $customer = $this->MCustomer->get_one('*', array('id' => $order['user_id']));
            $sale_role = isset($sale_dict[$customer['invite_id']]) ? $sale_dict[$customer['invite_id']]['role_id'] : 0;
            if($sale_role != 12 && $sale_role != 13){
                $sale_role = 12;
            }
            $data = array(
                'sale_id'   => isset($sale_dict[$customer['invite_id']]) ? $sale_dict[$customer['invite_id']]['id'] : 0,
                'sale_role' => $sale_role,
            );

            // 更新订单的所属销售
            $this->MOrder->update_info($data, array('id' => $order['id']));
            echo '<br/>' . $count++ . ' : ' . $this->db->last_query(). '<br/>\r\n';
        }
    }

    /**
     * 批量处理客户移交
     * @author yugang@dachuwang.com
     * @since 2015-05-05
     */
    public function fix_customer_transfer_data() {
        ini_set("memory_limit", "1024M");
        set_time_limit(0);

        $file = fopen('/tmp/t_customer_transfer.csv', 'r');
        $count = 1;
        $am_arr = array(14, 15, 16);
        $bd_arr = array(12, 13);
        $operator = $this->MUser->get_one('*', array('id' => 1));
        $operator['ip'] = '0.0.0.0';

        while(!feof($file)){
            $row = fgetcsv($file);
            $sale_mobile = $row['11'];
            $sale_name = $row['10'];
            $cid = $row['0'];
            if(empty($cid) || empty($sale_mobile)){
                continue;
            }
            // echo $count++ . ' -:' . $cid . ':' . $sale_mobile . ':<br>';
            // continue;

            $sale = $this->MUser->get_one('*', array('mobile' => $sale_mobile));
            // 如果根据手机号查询不到销售，则根据姓名查询
            if(empty($sale)){
                $sale = $this->MUser->get_one('*', array('name' => $sale_name));
            }
            // 查询不到销售，则不更新该客户
            if(empty($sale)){
                echo '##' . $cid . '##';
                continue;
            }

            if(in_array($sale['role_id'], $am_arr)){
                $data = array(
                    'am_id' => $sale['id'],
                    'status' => C('customer.status.allocated.code'),
                );
            } else {
                $data = array(
                    'invite_id' => $sale['id'],
                    'status' => C('customer.status.new.code'),
                );
            }
            $this->MCustomer->update_info($data, array('id' => $cid));
            echo '<br/>' . $count++ . ' : ' . $this->db->last_query(). '<br/>\r\n';
            // 记录移交日志
            $this->MCustomer_transfer_log->record($sale['id'], $cid, $operator);
        }

        fclose($file);
    }

    /**
     * 批量处理潜在客户移交
     * @author yugang@dachuwang.com
     * @since 2015-05-05
     */
    public function fix_potential_customer_transfer_data() {
        ini_set("memory_limit", "1024M");
        set_time_limit(0);

        $file = fopen('/tmp/t_potential_customer_transfer.csv', 'r');
        $count = 1;
        $am_arr = array(14, 15, 16);
        $bd_arr = array(12, 13);
        $operator = $this->MUser->get_one('*', array('id' => 1));
        $operator['ip'] = '0.0.0.0';

        while(!feof($file)){
            $row = fgetcsv($file);
            $sale_mobile = $row['11'];
            $sale_name = $row['10'];
            $cid = $row['0'];
            if(empty($cid) || empty($sale_mobile)){
                continue;
            }

            $sale = $this->MUser->get_one('*', array('mobile' => $sale_mobile));
            // 如果根据手机号查询不到销售，则根据姓名查询
            if(empty($sale)){
                $sale = $this->MUser->get_one('*', array('name' => $sale_name));
            }
            // 查询不到销售，则不更新该客户
            if(empty($sale)){
                echo '##' . $cid . '##';
                continue;
            }
            $data = array(
                'invite_id' => $sale['id'],
            );
            $this->MPotential_customer->update_info($data, array('id' => $cid));
            echo '<br/>' . $count++ . ' : ' . $this->db->last_query(). '<br/>\r\n';
            // 记录移交日志
            // $this->MCustomer_transfer_log->record($sale['id'], $cid, $operator);
        }

        fclose($file);
    }

    /**
     * 批量处理天津和上海最近5天内客户，所有所有天津的5天内下单客户分配给董常青，上海的5天内下单客户分配给李亮
     * @author yugang@dachuwang.com
     * @since 2015-05-05
     */
    public function fix_customer_cm_data() {
        ini_set("memory_limit", "1024M");
        set_time_limit(0);

        $order_list = $this->MOrder->get_lists('id, user_id', array('location_id' => 993, 'created_time >=' => 1430409600));
        $count = 1;

        // 上海的5天内下单客户分配给李亮-35
        foreach ($order_list as $order) {
            $customer = $this->MCustomer->get_one('*', array('id' => $order['user_id']));
            $data = array(
                'am_id' => 35,
                'status' => C('customer.status.allocated.code'),
            );

            // 更新订单的所属销售
            $this->MCustomer->update_info($data, array('id' => $customer['id']));
            echo '<br/>' . $count++ . ' : ' . $this->db->last_query(). '<br/>\r\n';
        }

        // 所有所有天津的5天内下单客户分配给董常青-18
        $order_list = $this->MOrder->get_lists('id, user_id', array('location_id' => 1206, 'created_time >=' => 1430409600));

        foreach ($order_list as $order) {
            $customer = $this->MCustomer->get_one('*', array('id' => $order['user_id']));
            $data = array(
                'am_id' => 18,
                'status' => C('customer.status.allocated.code'),
            );

            // 更新订单的所属销售
            $this->MCustomer->update_info($data, array('id' => $customer['id']));
            echo '<br/>' . $count++ . ' : ' . $this->db->last_query(). '<br/>\r\n';
        }
    }

    /**
     * 批量处理异常单数据
     * @author yugang@dachuwang.com
     * @since 2015-05-21
     */
    public function fix_abnormal_data() {
        $ao_list = $this->MAbnormal_order->get_lists('*', ['status' => 1]);
        foreach ($ao_list as $ao) {
            $data = [];
            $data['aid'] = $ao['id'];
            $data['order_id'] = $ao['order_id'];
            $data['product_id'] = $ao['product_id'];
            $data['name'] = $ao['product_name'];
            $data['created_time'] = $this->input->server("REQUEST_TIME");
            $data['updated_time'] = $this->input->server("REQUEST_TIME");
            $data['status'] = 1;
            $this->MAbnormal_content->create($data);
        }
    }

    /**
     * 批量处理投诉单客户id
     * @author yugang@dachuwang.com
     * @since 2015-05-27
     */
    public function fix_complaint_data() {
        $list = $this->MComplaint->get_lists('*');
        $count = 0;
        foreach ($list as $item) {
            $order = $this->MOrder->get_one('*', ['id' => $item['order_id']]);
            if(empty($order)){
                continue;
            }
            $data = [];
            $data['user_id'] = $order['user_id'];
            $this->MComplaint->update_info($data, ['id' => $item['id']]);
            echo $this->db->last_query() . '\r\n <br>';
            $count++;
        }
        echo $count . ' complaint changes done.';
    }

    /**
     * 批量处理CRM客户将所有潜在客户与注册客户放到公海
     * @author yugang@dachuwang.com
     * @since 2015-05-27
     */
    public function fix_customer_public_sea() {
        $count = 0;
        $cur = ['id' => '-1', 'name' => '系统', 'ip' => '127.0.0.1'];
        $where = ['status' => 1, 'province_id' => 804, 'invite_id !=' => 111];
        // 获取北京的已注册未下单客户
        $customer_list = $this->MCustomer->get_lists('*', $where);
        echo $this->db->last_query();
        foreach ($customer_list as $item) {
            // 记录日志
            $this->MCustomer_transfer_log->record(C('customer.public_sea_code'), $item['id'], $cur);
            // 将客户踢到公海
            $this->MCustomer->update_info(['invite_id' => -1], ['id' => $item['id']]);
            $count++;
        }

        echo ' \n ' . $count . ' customer changes done.';
        $count = 0;
        // 获取北京的潜在客户
        $potential_customer_list = $this->MPotential_customer->get_lists('*', $where);
        echo $this->db->last_query();
        foreach ($potential_customer_list as $item) {
            // 记录日志
            $src_user = $this->MUser->get_one('*', ['id' => $item['invite_id']]);
            $dest_user = ['id' => C('customer.public_sea_code'), 'role_id' => 0];
            $this->MCustomer_transfer_log->record_potential($src_user, $dest_user, $item['id'], $cur);
            // 将潜在客户踢到公海
            $this->MPotential_customer->update_info(['invite_id' => -1], ['id' => $item['id']]);
            $count++;
        }

        echo ' \n ' . $count . ' potential customer changes done.';
    }

    /**
     * 修复部分天津订单挂在北京的am上的异常数据
     * @author yugang@dachuwang.com
     * @since 2015-06-26
     */
    public function fix_wrong_order() {
        // 1.更新订单
        $orders = $this->MOrder->get_lists('*', ['sale_id' => 9, 'city_id' => 1206]);
        echo $this->db->last_query() . '<br>\r\n';
        echo count($orders);
        $count = 1;
        foreach ($orders as $order) {
            $customer = $this->MCustomer->get_one('*', ['id' => $order['user_id']]);
            $this->MOrder->update_info(['sale_id' => $customer['invite_id'], 'sale_role' => 12], ['id' => $order['id']]);
            echo $this->db->last_query() . '<br>\r\n';
            $this->MSuborder->update_info(['sale_id' => $customer['invite_id'], 'sale_role' => 12], ['order_id' => $order['id']]);
            echo $this->db->last_query() . '<br>\r\n';
            $count++;
        }

        echo '共修复了' . $count . '条数据';

        // 2.更新客户
        $this->MCustomer->update_info(['status' => 11, 'am_id' => 0], ['am_id' => 9, 'status' => 12, 'province_id' => 1206]);
        echo $this->db->last_query() . '<br>\r\n';
    }

    /**
     * 根据suborder修复order表的sale_id 和 sale_role
     * @author yugang@dachuwang.com
     * @since 2015-07-01
     */
    public function fix_order_sale() {
        $list = $this->MSuborder->get_lists('*', ['id <' => 60205]);
        echo count($list);
        echo '------';
        $count = 0;
        foreach ($list as $item) {
            $this->MOrder->update_by('id', $item['order_id'], ['sale_id' => $item['sale_id'], 'sale_role' => $item['sale_role']]);
            $count++;
        }

        echo '共更新了' . $count . '条数据';
    }

    /**
     * 根据备份数据修复order表和suborder表的sale_id 和 sale_role
     * @author yugang@dachuwang.com
     * @since 2015-07-06
     */
    public function fix_history_order_sale() {
        // 根据备份恢复20150704日之前的订单
        $list = $this->db->select('id, sale_id, sale_role')->from('t_order_bak')->get()->result_array();
        echo count($list);
        echo '------';
        $count = 0;
        foreach ($list as $item) {
            $this->MOrder->update_by('id', $item['id'], ['sale_id' => $item['sale_id'], 'sale_role' => $item['sale_role']]);
            $count++;
        }
        unset($item);
        echo '共更新了' . $count . '条数据\r\n';

        // 根据订单表的user_id恢复订单的order_id
        $list = $this->MOrder->get_lists('id, user_id', ['id >' => 61769]);
        echo count($list);
        echo '------';
        $count = 0;
        foreach ($list as $item) {
            $customer = $this->MCustomer->get_one('id,invite_id', ['id' => $item['user_id']]);
            if ($customer['invite_id'] > 0) {
                $user = $this->MUser->get_one('id,role_id', ['id' => $customer['invite_id']]);
                if (empty($user)) {
                    $user = ['id' => 0, 'role_id' => 0];
                }
                $this->MOrder->update_by('id', $item['id'], ['sale_id' => $user['id'], 'sale_role' => $user['role_id']]);
            }
            $count++;
        }
        unset($item);
        echo '共更新了' . $count . '条数据\r\n';

        // 根据t_order表恢复t_suborder表的sale_id和sale_role
        $list = $this->MOrder->get_lists('id, sale_id, sale_role');
        echo count($list);
        echo '------';
        $count = 0;
        foreach ($list as $item) {
            $this->MSuborder->update_by('order_id', $item['id'], ['sale_id' => $item['sale_id'], 'sale_role' => $item['sale_role']]);
            $count++;
        }

        echo '共更新了' . $count . '条数据';
    }
}

/* End of file demo.php */
/* Location: ./application/controllers/demo.php */
