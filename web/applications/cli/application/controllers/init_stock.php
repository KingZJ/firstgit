<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Init_stock extends MY_Controller {

    public function __construct () {
        parent::__construct();
        $this->load->model(
            array(
                'MSku',
                'MLine',
                'MOrder',
                'MOrder_detail',
            )
        );
        $this->load->library(
            array(
                'Dachu_request'
            )
        );
    }

    //初始化订单锁定的库存
    private function _update_stock_locked() {
        $orders = $this->MOrder->get_lists(
            'id, line_id',
            array(
                'deliver_date >=' => strtotime('2015-07-09'),
                //'deliver_date' => strtotime('2015-07-08'),
                //'deliver_time' => 2,
                'not_in' => array(
                    'status' => array(
                        C('order.status.closed.code'),
                    )
                )
            )
        );

        $lines = $this->MLine->get_lists('id, warehouse_id');
        $line_ids = array_column($lines, 'id');
        $line_map = array_combine($line_ids, $lines);

        foreach($orders as $order) {
            $order_details = $this->MOrder_detail->get_lists(
                'sku_number, quantity',
                array(
                    'order_id' => $order['id']
                )
            );
            $line_id = $order['line_id'];
            $warehouse_id = isset($line_map[$line_id]) ? $line_map[$line_id]['warehouse_id'] : 0;
            if($warehouse_id === 0) {
                continue;
            }
            foreach($order_details as $order_detail) {
                $quantity   = $order_detail['quantity'];
                $sku_number = $order_detail['sku_number'];
                $this->db->query(
                    "update t_stock set stock_locked = stock_locked + {$quantity} where sku_number = {$sku_number} and warehouse_id = {$warehouse_id}"
                );
            }
            echo $this->db->last_query() . "\n";
        }
    }

    public function init_sku_stock() {

        //拉odoo库存数据
        $sku_numbers = $this->MSku->get_lists(
            'sku_number',
            array(
                'status' => 1
            )
        );
        $sku_numbers = array_column($sku_numbers, 'sku_number');
        $sku_arr = [];
        foreach($sku_numbers as $idx => $sku) {
            $sku_arr[] = array(
                'product_code'    => $sku,
                'type'            => '',
                'picking_type_id' => '',
                'qty'             => ''
            );
            if($idx % 20 == 0) {
                $res = $this->dachu_request->post(
                    C('service.api') .'/mall_stock/notice_stock_update',
                    $sku_arr
                );
                print_r($res);
                $res = $this->dachu_request->post(
                    C('service.api') .'/mall_stock/update_mall_stock',
                    array()
                );
                print_r($res);
                unset($sku_arr);
                $sku_arr = [];
            }
        }

        //订单锁定库存
        $this->_update_stock_locked();
    }
}

/* End of file init_stock.php */
/* Location: ./application/controllers/init_stock.php */
