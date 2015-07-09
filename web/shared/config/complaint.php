<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
$config = array(
    'ctype' => array(
        'quality'  => array(
            'msg'  => '质量问题',
            'code' => 1
        ),
        'price'    => array(
            'msg'  => '价格问题',
            'code' => 2
        ),
        'quantity' => array(
            'msg'  => '数量问题',
            'code' => 3
        ),
        'miss'     => array(
            'msg'  => '漏单问题',
            'code' => 4
        ),
        'late'     => array(
            'msg'  => '配送不及时',
            'code' => 5
        ),
        'wrong'    => array(
            'msg'  => '送错货',
            'code' => 6
        ),
        'disagree' => array(
            'msg'  => '线上描述不符',
            'code' => 7
        ),
        'other'    => array(
            'msg'  => '其他',
            'code' => 8
        )
    ),
    'feedback'  => array(
        'customer' => array(
            'msg'  => '客户',
            'code' => 1
        ),
        'sale'     => array(
            'msg'  => '销售',
            'code' => 2
        )
    ),
    'status'  => array(
        'processing' => array(
            'msg'    => '处理中',
            'code'   => 1
        ),
        'finish'     => array(
            'msg'    => '已完成',
            'code'   => 2
        )
    ),
    'source'  => array(
        'mobile' => array(
            'msg'    => '电话',
            'code'   => 1
        ),
        'qq'     => array(
            'msg'    => 'QQ',
            'code'   => 2
        ),
        'wechat' => array(
            'msg'    => '微信',
            'code'   => 3
        )
    ),
    'owner_type' => 'complaint'
);
