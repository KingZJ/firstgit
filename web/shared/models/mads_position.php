<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 广告位
 * @author: liaoxianwen@ymt360.com
 * @version: 1.0.0
 * @since: 2014-12-10
 */
class MAds_position extends MY_Model {
    use MemAuto;

    private $table = 't_ads_position';

    public function __construct() {
        parent::__construct($this->table);
    }
}

/* End of file mads_position.php */
/* Location: :./application/models/mads_position.php */
