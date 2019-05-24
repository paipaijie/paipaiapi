<?php
namespace app\api\model;
use think\Model;
use think\Db;
class Coupon extends Model{
    // protected $table = 'desc_order';
    public function register_coupons($user_id){
        $res = $this->get_coupons_type_info2(1);

        if (!empty($res)) {
            foreach ($res as $k => $v) {
                $num = Db::name('coupons_user')->where('cou_id = '.$v['cou_id'])->count();
                if ($v['cou_total'] <= $num) {
                    continue;
                }

                $other['user_id'] = $user_id;
                $other['cou_id'] = $v['cou_id'];
                $other['cou_money'] = $v['cou_money'];
                $other['uc_sn'] = $v['uc_sn'];
                $other['is_use'] = 0;
                $other['order_id'] = 0;
                $other['is_use_time'] = 0;
                Db::name('coupons_user')->insert($other);
            }
        }
    }

    public function get_coupons_type_info2($cou_type = '1,2,3,4'){
        $time = time();
        $arr = Db::name('coupons')->where('cou_type IN (' . $cou_type . ') AND ' . $time . ' < cou_end_time ')->select();
        // echo Db::name('coupons')->getlastsql();die;
        foreach ($arr as $k => $v) {
            $arr[$k]['uc_sn'] = $time . rand(10, 99);
        }

        return $arr;
    }

    //获取用户的优惠券信息
    public function getUserCoupon($condition){
        $where = $condition;
        //用户拥有的优惠券信息
        $coupons = Db::name('coupons_user')->where($where)->select();
        $return = array();
        foreach ($coupons as $key => $value) {
            //查询对应的优惠券信息
            $cou_info = Db::name('coupons')->where('cou_id = '.$value['cou_id'])->find();
            $return[$key]['cou_id'] = $value['cou_id'];
            $return[$key]['cou_name'] = $cou_info['cou_name'];
            $return[$key]['cou_total'] = $cou_info['cou_total'];
            $return[$key]['cou_man'] = $cou_info['cou_man'];//满多少
            $return[$key]['cou_money'] = $cou_info['cou_money'];//减多少
            $return[$key]['cou_start_time'] = date('Y.m.d ',$cou_info['cou_start_time']);
            $return[$key]['cou_end_time'] = date('Y.m.d ',$cou_info['cou_end_time']);
        }

        return $return;
    }
}