<?php
namespace app\api\model;
use think\Model;
use think\Db;
class Order1 extends Model{
    protected $table = 'lk_order';
    //订单
    public function orderList($condition,$page,$limit,$sort,$type){
        //订单列表
        $list = Db::name('order')->where($condition)->order($sort)->page($page,$limit)->select();
        $uid_arr = array();
        foreach ($list as $key => $value) {
            $list[$key]['pay_money'] = price_yuan($value['pay_money']);
            $list[$key]['discount'] = trim($value['discount'],'0');
            $list[$key]['dateline'] = date('Y-m-d H:i',$value['dateline']);//时间格式
            $gid_arr = explode(',',$value['gid']);//一个订单多个商品
            $num_arr = explode(',',$value['num']);
            $good = array();
            foreach ($gid_arr as $k => $v) {
                $goodsInfo = Db::name('goods')->where('gid='.$v)->find();
                $good[$k]['gid'] = $goodsInfo['gid'];
                $good[$k]['title'] = $goodsInfo['title'];
                $good[$k]['price'] = price_yuan($goodsInfo['price']);
                $good[$k]['cover'] = UPLOAD_URL.'/goods/'.$goodsInfo['cover'];
                $good[$k]['num'] = $num_arr[$k];
            }
            $list[$key]['goodsInfo'] = $good;
            // $uid_arr[] = $value['buyeruid']; //买家uid方便后续查询买家信息
        }
        if($type == 'sell'){//销售订单需显示买家
            
            foreach ($list as $key => $value) {
                $uinfo = Db::name('user')->where('uid = '.$value['buyeruid'])->find();//用户信息
                if(empty($uinfo)){
                    $list[$key]['buyer'] = '';
                }else{
                    $fInfo = Db::name('franchisee')->where('phone like "'.$uinfo['phone'].'"')->find();
                    // echo Db::name('franchisee')->getlastsql();die;
                    $list[$key]['buyer'] = $fInfo['title'].'    '.$fInfo['name'];//买家信息 
                }   
            }
        }
        $return = array();
        $return['list'] = $list ? $list : array();//排名列表
        return $return;
    }
    //获取订单信息
    public function orderInfo($orderId){
        $info = Db::name('order')->where('orderid = '.$orderId)->find();
        // echo Db::name('order')->getlastsql();
        $gid_arr = explode(',', $info['gid']);
        $num_arr = explode(',', $info['num']);
        $price = 0;
        $good = array();
        foreach ($gid_arr as $k => $v) {
            $goodsInfo = Db::name('goods')->where('gid='.$v)->find();
            $good[$k]['gid'] = $goodsInfo['gid'];
            $good[$k]['title'] = $goodsInfo['title'];
            $good[$k]['price'] = price_yuan($goodsInfo['price']);
            $good[$k]['cover'] = UPLOAD_URL.'/goods/'.$goodsInfo['cover'];
            $good[$k]['num'] = $num_arr[$k]; 
            $price += $goodsInfo['price']* $num_arr[$k];
        } 
        $total_price = $price*$info['discount']/100;
        $discount_price = $price*(100-$info['discount'])/100;
        $info['goodList'] = $good;
        $info['good_price'] = price_yuan($price); //原价
        $info['total_price'] = price_yuan($total_price);//合计价
        $info['discount_price'] = price_yuan($discount_price);
        $info = empty($info) ? array() : $info;
        return $info;
    }
}