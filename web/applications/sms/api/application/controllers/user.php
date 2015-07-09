<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 用户操作
 * @author yugang@dachuwang.com
 * @version 1.0.0
 * @since 2015-03-05
 */
class user extends MY_Controller {
    protected $_salt  = NULL;

    public function __construct() {
        parent::__construct();
        $this->load->model(
            array(
                'MProduct',
                'MLocation',
                'MUser',
                'MOrder',
            )
        );
        $this->load->library(
            array(
                'form_validation',
            )
        );
        // 激活分析器以调试程序
        // $this->output->enable_profiler(TRUE);
    }

    /**
     * 用户注册
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function register() {

    }

    /**
     * 用户登陆
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function login() {
        $is_logined = $this->userauth->current(FALSE);
        if( !empty($is_logined) ) {
            $this->userauth->logout(FALSE);
        }
        // 表单数据校验
        $this->form_validation->set_rules('mobile', '手机号', 'trim|required|exact_length[11]|numeric');
        $this->form_validation->set_rules('password', '密码', 'required');
        if($this->form_validation->run() === FALSE) {
            $this->_return_json(array('status' => C("userauth.invalid_info.id"), 'msg' => C("userauth.invalid_info.msg")));
        }

        $login_result = $this->userauth->login($_POST['mobile'], $_POST['password'], FALSE);
        if(!empty($login_result['info']['type'])){
            if(in_array($login_result['info']['type'],
                array(
                    C('user.normaluser.purchase.type'),
                    C('user.normaluser.supply.type')
                ))) {
                    $this->userauth->logout();
                    $this->_return_json(
                        array(
                            'status' => C('tips.code.op_failed'),
                            'msg'    => '没有权限访问'
                        )
                    );
                }
        }

        if(!empty($login_result)) {
            $this->_return_json($login_result);
        }

        $this->_return_json(array('status' => C("userauth.default.id"), 'msg' => C("userauth.default.msg")));

    }

    /**
     * 用户退出
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function logout() {
        $this->userauth->logout(FALSE);
        $this->_return_json(
            array(
                'status' => C('status.req.success'),
                'msg' => '退出成功'
            )
        );
    }

    /**
     * 修改密码
     * @author: yugang@ymt360.com
     * @description 用户修改个人密码
     */
    public function update_password() {
        // 权限校验
        $this->check_validation('user', 'edit_personal_data', '', FALSE);
        $cur = $this->userauth->current(FALSE);
        // 设置校验规则
        $this->form_validation->set_rules('password', '原密码', 'required');
        $this->form_validation->set_rules('newPassword', '新密码', 'required');
        $this->form_validation->set_rules('newRePassword', '确认密码', 'required|matches[newPassword]');
        $this->validate_form();

       // 验证用户输入密码是否正确
        $password = $this->create_password($this->input->post('password'), $cur['salt']);
        if($password != $cur['password']) {
            $this->_return_json(
                array(
                    'status'  => FALSE,
                    'msg' => '密码输入错误，请重新输入',
                )
            );
        }
        // 修改用户密码
        $new_password = $this->create_password($this->input->post('newPassword'), $cur['salt']);
        $result = $this->MUser->update_by('id', $cur['id'], 
            array('password' => $new_password));
        $this->_return($result);
    }
 
    /**
     * 用户列表
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function lists() {
        // 调用基础服务接口
        $return = $this->format_query('/user/lists', $_POST);
        $this->_return_json($return);
    }

    /**
     * 角色列表
     * @author yugang@dachuwang.com
     * @since 2015-03-07
     */
    public function role_list() {
        // 调用基础服务接口
        $return = $this->format_query('/user/role_list', $_POST);
        $this->_return_json($return);
    }

    /**
     * 查看用户
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function view() {
        // 调用基础服务接口
        $return = $this->format_query('/user/view', $_POST);
        $this->_return_json($return);
    }

    /**
     * 添加用户输入页面
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function create_input() {
        // 调用基础服务接口
        $return = $this->format_query('/user/create_input', $_POST);
        $this->_return_json($return);
    }

    /**
     * 添加用户
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function create() {
        // 调用基础服务接口
        $return = $this->format_query('/user/create', $_POST);
        if(intval($return['status']) === 0) {
            $sms_return = $this->format_query('/sms/send_captcha',
                array('content' => $return['content'], 'mobile' => $_POST['mobile'])
            );
            unset($return['content']);
        }
        $this->_return_json($return);
    }

    /**
     * 编辑用户输入页面
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function edit_input() {
        // 调用基础服务接口
        $return = $this->format_query('/user/edit_input', $_POST);
        $this->_return_json($return);
    }

    /**
     * 编辑用户
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function edit() {
        // 调用基础服务接口
        $return = $this->format_query('/user/edit', $_POST);
        $this->_return_json($return);
    }

    /**
     * 删除用户
     * @author yugang@dachuwang.com
     * @since 2015-03-05
     */
    public function delete() {
        // 调用基础服务接口
        $return = $this->format_query('/user/delete', $_POST);
        $this->_return_json($return);
    }

    /**
     * 重置密码
     * @author yugang@dachuwang.com
     * @since 2015-03-12
     */
    public function reset_password() {
        // 权限校验
        $this->check_validation('user', 'edit', '', FALSE);
        $data = $this->MUser->get_one('*', array('id' => $this->input->post('uid', TRUE)));
        // 调用基础服务接口
        $return = $this->format_query('/user/reset_password', $_POST);
        // 发送短信
        if(intval($return['status']) === 0) {
            $sms_return = $this->format_query('/sms/send_captcha',
                array('content' => $return['content'], 'mobile' => $data[
            'mobile'])
            );
            unset($return['content']);
        }
        $this->_return_json($return);
    }

    /**
     * 修改状态
     * @author yugang@dachuwang.com
     * @since 2015-03-12
     */
    public function toggle_status() {
        // 权限校验
        $this->check_validation('user', 'edit', '', FALSE);
        // 调用基础服务接口
        $return = $this->format_query('/user/toggle_status', $_POST);
        $this->_return_json($return);
    }

    /**
     * @author yugang@dachuwang.com
     * @description 创建密码
     */
    protected function create_password($str, $salt) {
        return md5(md5($str) . $salt);
    }
}

/* End of file user.php */
/* Location: :./application/controllers/user.php */
