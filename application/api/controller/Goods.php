<?php
namespace app\api\controller;
use app\api\controller\Base;
use think\Request;
use think\Db;
use app\api\model\Good;
use app\api\model\Coupon;
class Goods extends Base{

    //根据分类获取商品列表，普通商品
    public function getList(){
        $cat_id = isset($_REQUEST['cat_id']) ? $_REQUEST['cat_id'] : '';//分类id
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;//分页
        $limit = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 10;//每页限制每页获取条数
        $sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : '';//排序字段
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'asc';//排序顺序升序asc 降序desc
        $keyword = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : '';//搜索关键字

        $condition = array();//筛选条件
        $fields = 'goods_id,goods_name,goods_thumb,market_price,shop_price,sales_volume,user_id,goods_number';//要获取的字段
        // $where = 'g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ';
        $condition['is_on_sale'] = 1;
        $condition['is_alone_sale'] = 1;
        $condition['is_delete'] = 0;
        //分类id
        if($cat_id == ''){
            exit(json_encode(array('status'=>0,'msg'=>'缺少参数分类id')));
        }else{
            $condition['cat_id'] = $cat_id;    
        }
        //搜索关键字
        if($keyword != ''){
            $condition['goods_name'] = array('like','%'.$keyword.'%'); 
        }
        //排序
        switch ($sort) {
            //综合
            case 'zh':
                $sort = ' goods_id desc';
                break;
            //销量
            case 'xl':
                $sort = ' sales_volume '.$order;
                break;
            //价格
            case 'jg':
                $sort = ' shop_price '.$order;
                break;
            default:
                $sort = ' goods_id desc';
                break;
        }
    	$good = new Good;
        $goodsList = $good->getGoodsList($fields,$condition,$page,$limit,$sort);
        $status = 1;
        $msg = '获取商品列表成功';
    	return json_encode(array('status'=>$status,'msg'=>$msg,'content'=>$goodsList ));
    }

    //根据商品id获取商品详情
    public function goodsInfo(){
        $gid = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : '';//商品id
        if($gid == ''){
            exit(json_encode(array('status'=>0,'msg'=>'缺少参数商品id')));
        }else{
            $condition = array();//筛选条件
            $fields = 'goods_id,goods_name,goods_thumb,market_price,shop_price,sales_volume,user_id,goods_number,goods_desc';//要获取的字段
            $condition['goods_id'] = $gid;
            $good = new Good;
            $goodsInfo = $good->getGoodDetail($fields,$condition);
            $coupon_count = 0;//可使用的优惠券数量
            // echo UID;die;
            //如果登陆状态的话返回优惠券信息
            if(UID){
                $uid = UID;
                $where = array();
                $where['user_id'] = $uid;
                $where['is_use'] = 0;
                $coupon = new Coupon;
                $couponList = $coupon->getUserCoupon($where);
                $coupon_count = count($couponList);
            }else{
                //没登录返回空数组
                $couponList = array();
                $coupon_count = 0;    
            }
            $return = array();
            $return['goodsInfo'] = $goodsInfo;
            $return['coupon_count'] = $coupon_count;   
            return json_encode(array('status'=>1,'msg'=>'获取商品信息成功','content'=>$return,'couponList'=>$couponList));   
        }
    }

    
}
