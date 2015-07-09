<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class MY_Controller extends CI_Controller {
    public $post = array();
    protected $_sites = array();

    // post数据
    public function __construct() {
        parent::__construct();
        $this->load->library(array('UserAuth'));
        // 判断是否开启调试
        if(C("xhprof.switch")) {
            start_xhprof();
        }

        $this->load->library(array('UserAuth', 'Http'));
        $this->post = json_decode(file_get_contents("php://input"), TRUE);
        // 从post中json字符串中解析出变量并合并到$_POST
        if(!empty($this->post)) {
            $this->post = xss_clean($this->post);
            $_POST = array_merge($_POST, $this->post);
        }
        $this->_sites = array(C('app_sites.chu'), C('app_sites.guo'));
        // $this->output->enable_profiler(TRUE);
    }

    /**
     * @author: liaoxianwen@ymt360.com
     * @description
     * @param: array arr 需要转成json的数组
     */
    public function _return_json($arr) {
        if(C("xhprof.switch")) {
            $arr['xhprof'] = end_xhprof();
        }
        echo json_encode($arr);exit;
    }

    /**
     * ajax请求通用返回接口
     * @author: yugang@ymt360.com
     * @param: result 操作结果
     * @since 2015-01-27
     */
    public function _return($result, $success_msg = '操作成功', $failure_msg = '操作失败') {
        if ($result) {
            $this->_return_json(
                array(
                    'status'    => C('status.req.success'),
                    'msg'       => $success_msg,
                    'xhprof' => C("xhprof.switch") ? end_xhprof() : "",
                )
            );
        } else {
            $this->_return_json(
                array(
                    'status'    => C('status.req.failed'),
                    'msg'       => $failure_msg,
                    'xhprof' => C("xhprof.switch") ? end_xhprof() : "",
                )
            );
        }
    }
    /**
     * 导出csv数据报表
     * @author yugang@ymt360.com
     * @since 2015-01-30
     */
    public function export_csv($title_arr, $data, $file_path = 'default.csv') {
        array_unshift($data, $title_arr);
        // 在公共的方法中使用导出时间不限制
        // 以防止数据表太大造成csv文件过大,造成超时
        set_time_limit(0);
        $temp_file_path = BASEPATH . '../application/temp/'. uniqid() . '.csv';
        $file = fopen($temp_file_path, 'w');
        $keys = array_keys($title_arr);
        foreach($data as $item) {
            // 对数组中的内容按照标题的顺序排序，去除不需要的内容
            $info = array();
            foreach ($keys as $v) {
                $info[$v] = isset($item[$v]) ? $item[$v] : '';
            }
            fputcsv($file, $info);
        }
        fclose($file);

        //在win下看utf8的csv会有点问题
        $str = file_get_contents($temp_file_path);
        $str = iconv('UTF-8', 'GBK', $str);
        unlink($temp_file_path);
        // 下载文件
        $this->load->helper('download');
        force_download($file_path, $str);
    }

    /**
     * 进行表单验证
     * @author yugang@ymt360.com
     * @description 进行表单验证，如果失败返回错误提示
     */
    public function validate_form() {
        if ($this->form_validation->run() === FALSE) {
            $this->_return_json(
                array(
                    'status'  => C('status.req.invalid'),
                    'msg'     => '请填写完整必填的信息', // 表单验证错误提示信息validation_errors()
                )
            );
        }
    }

    /**
     * 获取分页相关参数
     * @author yugang@ymt360.com
     * @since 2015-02-03
     */
    public function get_page() {
        $page = empty($_POST['currentPage']) ? 1 : $_POST['currentPage'];
        $page_size = empty($_POST['itemsPerPage']) ? 10 : $_POST['itemsPerPage'];
        $offset = $page_size * ($page - 1);
        $page = array(
            'page'      => $page,
            'offset'    => $offset,
            'page_size' => $page_size,
        );

        return $page;
    }

    public function get_sites() {
        $chu = C('app_sites.chu');
        $guo = C('app_sites.guo');
        return array($chu, $guo);
    }

    /**
     * 获取变量值,如果变量不存在，则返回默认值
     * @author yugang@dachuwang.com
     * @since 2015-04-29
     */
    public function get_value($value, $default = '') {
        return isset($value) ? $value : $default;
    }
}
