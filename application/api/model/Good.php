<?php
namespace app\api\model;
use think\Model;
use think\Db;
class Good extends Model{
    //获取商品列表
    public function getGoodsList($keywords,$condition,$page,$limit,$sort){
        $where = array();
        $where = $condition;
        $goodsList = Db::name('goods')->field($keywords)->where($where)->page($page,$limit)->order($sort)->select();
        if(!empty($goodsList)){
            foreach ($goodsList as $key => $value) {
                $goodsList[$key]['goods_thumb'] = SITE_URL.$value['goods_thumb'];//商品缩略图   
            }
        }

        return empty($goodsList) ? array() : $goodsList;
    
    }

    //获取商品详情
    public function getGoodDetail($keywords,$condition){
        $where = array();
        $where = $condition;
        $goodsDetail = Db::name('goods')->field($keywords)->where($where)->find();  
        if(!empty($goodsDetail)){
            $goodsDetail['goods_thumb'] = SITE_URL.$goodsDetail['goods_thumb'];//商品缩略图      
        }

        return empty($goodsDetail) ? array() : $goodsDetail;

    }
}