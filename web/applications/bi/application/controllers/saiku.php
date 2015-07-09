<?php
if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Saiku extends MY_Controller {
    const PINLEI = 6; //品类分析页面
    public function __construct () {
        parent::__construct();
        $this->load->helper('url');
    }
    public function index(){
        $data = $this->data;
        $site_id = $this->input->get('site_id');
        $table_id = $this->input->get('table_id');
        $city_id = $this->input->get('city_id');
        $data['current_url'] = current_url();
        $data['site_id']  = $site_id ? $site_id : C('site.dachu');
        $data['city_id']  = $city_id ? $city_id : C('open_cities.quanguo.id'); //默认全国;
        $data['table_id'] = $table_id ? $table_id : SELF::PINLEI;
        $data['tab_id']=0;
        $this->load->view('saiku',$data);
    }
    public function login(){
        $this->load->view('saiku_login');
    }
}
