<?php
namespace app\api\model;
use think\Model;
use think\Db;
class Sms extends Model{
    // protected $table = 'desc_order';
    //检验手机号及验证码是否正确
    public function check_sms_code($phone,$code){
        
        //判断验证码是否正确
        $code = trim($_REQUEST['code']);
        $smsInfo = Db::name('sms')->where('`phone` = ' . floatval($phone) . ' AND `code` != 0 AND `time` > ' . (time() - 1800))->field('code')->order('`time` DESC')->find();
        if (empty($smsInfo) || $smsInfo['code'] != $code) {
            return false;
        }else{
            return true;
        } 


    }
}