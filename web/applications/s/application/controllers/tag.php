<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 面向DM系统标签的基础服务
 * @author yugang@dachuwang.com
 * @version: 1.0.0
 * @since: 2015-06-25
 */
class Tag extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(array('MLine', 'MLocation', 'MCustomer'));
        $this->load->library(array('form_validation'));
        // 激活分析器以调试程序
        // $this->output->enable_profiler(TRUE);
    }

    /**
     * 返回固有标签列表
     * @author yugang@dachuwang.com
     * @since 2015-06-25
     */
    public function attr_list() {
        // 数据处理
        $sites = array_values(C('app_sites'));
        $citys = $this->MLocation->get_lists('*', ['upid' => '0']);
        $lines = $this->MLine->get_lists('*', ['status' => C('status.common.success')]);
        $dimensions = array_values(C('customer.dimension'));
        $shop_types = array_values(C('customer_type.top'));
        $data = [
            'sites'      => $sites,
            'lines'      => $lines,
            'citys'      => $citys,
            'dimensions' => $dimensions,
            'shop_types' => $shop_types,
        ];
        // 返回结果
        $this->_return_json(
            array(
                'status' => C('status.req.success'),
                'data'   => $data,
            )
        );
    }

}

/* End of file line.php */
/* Location: :./application/controllers/line.php */
