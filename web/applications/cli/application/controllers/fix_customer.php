<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Fix_customer extends MY_Controller {

    public function __construct () {
        parent::__construct();
        $this->load->model(
            array(
                'MCustomer',
                'MUser',
                'MPotential_customer',
                'MOrder',
            )
        );
    }

    /**
     * 将用户的geo信息分别存储
     * @author yugang@dachuwang.com
     * @since 2015-05-25
     */
    public function fix_customer_data() {
        // 将14天内未下单的北京非KA客户置为已删除
        $this->db->query("update t_customer set status = 0 where id not in (select distinct(user_id) from t_order where created_time > unix_timestamp('2015-06-17') and status != 0) and province_id = 804 and customer_type !=2");

        // 北京AM旗下所有客户还给注册BD，如果找不到这个BD的，移交给测试账号
        $list = $this->MCustomer->get_lists('*', ['status' => 12, 'province_id' => 804]);
        $count = 0;
        foreach ($list as $item) {
            $bd = $this->MUser->get_one('*', ['id' => $item['invite_bd'], 'status' => 1]);
            if (empty($bd)) {
                $this->MCustomer->update_info(['status' => 11, 'am_id' => 0, 'invite_id' => 340], ['id' => $item['id']]);
            } else {
                $this->MCustomer->update_info(['status' => 11, 'am_id' => 0, 'invite_id' => $item['invite_bd']], ['id' => $item['id']]);
            }
            $count++;
        }

        // 所有北京的AM变为BD
        $this->db->query("update t_user set role_id = 12 where role_id = 14 and province_id = 804");

        // 北京大果BD全部变成大厨BD
        $this->db->query("update t_user set site_id = 1 where site_id = 2 and role_id = 12 and province_id = 804");

        echo $count . "user edit,done\n";
    }

}

/* End of file fix_customer.php */
/* Location: ./application/controllers/fix_customer.php */
