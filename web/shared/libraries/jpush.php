<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

//加载通过composer安装的组件
require_once dirname(__FILE__) . "/third_party/vendor/autoload.php";
use JPush\Model as M;
use JPush\JPushClient;
use JPush\Exception\APIConnectionException;
use JPush\Exception\APIRequestException;

class Jpush {

    public function __construct () {
        $this->CI = &get_instance();
    }

    private function _get_appkey($app_type_id = 1) {
        switch($app_type_id) {
        case 1: //大厨
            $appkey = C('jpush.key.dachu.appkey');
            $secret = C('jpush.key.dachu.secret');
            break;
        case 2: //大果
            $appkey = C('jpush.key.daguo.appkey');
            $secret = C('jpush.key.daguo.secret');
            break;
        case 3: //crm
            $appkey = C('jpush.key.crm.appkey');
            $secret = C('jpush.key.crm.secret');
            break;
        default:
            $appkey =  '';
            $secret = '';
            break;
        }

        return array($appkey, $secret);
    }

    private function _get_platform($platform_id = 0) {
        $platform = M\all;
        switch($platform_id) {
        case C('jpush.platform.all'):
            $platform = M\all;
            break;
        case C('jpush.platform.android'):
            $platform = M\platform("android");
            break;
        case C('jpush.platform.ios'):
            $platform = M\platform("android");
            break;
        default:
            break;
        }
        return $platform;
    }

    private function _get_audience($alias = array()) {
        $audience = M\all;
        if(!empty($alias)) {
            $audience = M\audience(M\alias($alias));
        }
        return $audience;
    }

    /**
     * @description 通过极光进行推送
     */
    public function jpush($push_type = 2, $alias = array(), $title = 'test', $notification = 'test', $msg_type = 1, $app_type_id = 1, $platform_id = 0) {
        if($push_type == C('jpush.push_type.direct')) {
            if(empty($alias)) {
                echo 'empty alias';
                return;
            }
        }

        list($appkey, $secret) = $this->_get_appkey($app_type_id);

        if(empty($appkey) || empty($secret)) {
            echo 'empty appkey';
            return;
        }

        $platform = $this->_get_platform($platform_id);
        $audience = $this->_get_audience($alias);

        $client = new JPushClient($appkey, $secret);

        $br = "<br/>\n";
        try {
            switch($msg_type) {
            case C('jpush.message_type.message'):
                $result = $client->push()
                    ->setPlatform($platform)
                    ->setAudience($audience)
                    ->setMessage(M\message($notification))
                    ->send();
                break;
            case C('jpush.message_type.notification'):
                $result = $client->push()
                    ->setPlatform($platform)
                    ->setAudience($audience)
                    ->setNotification(M\notification($notification))
                    ->send();
                break;
            }
            echo 'Push Success.' . $br;
            echo 'sendno : ' . $result->sendno . $br;
            echo 'msg_id : ' .$result->msg_id . $br;
            echo 'Response JSON : ' . $result->json . $br;
        } catch (APIRequestException $e) {
            echo 'Push Fail.' . $br;
            echo 'Http Code : ' . $e->httpCode . $br;
            echo 'code : ' . $e->code . $br;
            echo 'message : ' . $e->message . $br;
            echo 'Response JSON : ' . $e->json . $br;
            echo 'rateLimitLimit : ' . $e->rateLimitLimit . $br;
            echo 'rateLimitRemaining : ' . $e->rateLimitRemaining . $br;
            echo 'rateLimitReset : ' . $e->rateLimitReset . $br;
        } catch (APIConnectionException $e) {
            echo 'Push Fail.' . $br;
            echo 'message' . $e->getMessage() . $br;
        }

    }

}

/* End of file jpush.php */
/* Location: ./application/controllers/jpush.php */
