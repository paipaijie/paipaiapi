<?php
namespace app\api\controller;
use app\api\controller\Base;
use think\Request;
use think\Db;
use app\api\model\Category;
class Index extends Base{
   
    //规则配置
    public function rule(){
        $rule = array(
            //接口地址
            'siteurl' => 'https://api.ppj.com/paipai/api/'
        );
        return json_encode(array('status'=>1,'msg'=>'成功','content'=>$rule));
    }

    //首页顶部分类数据返回
    public function topCate(){
    	$status = 1;//状态
    	$msg = "数据获取成功";
    	$categoryInfo = Db::name('category')->field('cat_id,cat_name')->where('parent_id=0 AND is_show=1')->select();
    	$persale_arr=array('cat_id'=>'1','cat_name'=>'预售');
        $categoryInfo[]=$persale_arr;
    	$categoryInfo = empty($categoryInfo) ? array() : $categoryInfo;
    	return json_encode(array('status'=>$status,'msg'=>$msg,'content'=>$categoryInfo ));
    }

    //顶部分类之下的首页数据返回
    public function index(){
    	//获取参数分类id
    	$cat_id = isset($_REQUEST['cat_id']) ? $_REQUEST['cat_id'] : 1;//分类id
    	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;//页数
    	$limit = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 10;//每页的数据个数

    	$return = array();
    	$code = 1;
    	$msg = '数据获取成功';
    	if(!$cat_id || $cat_id=='1'){
    		//分类广告位置信息
    		$adPositionInfo = Db::name('touch_ad_position')->field('position_id,ad_width')->where('position_desc=1')->select();
        }else{
        	//判断是否是有效的分类
        	$catInfo = Db::name('category')->field('cat_id,cat_name')->where('cat_id = '.$cat_id.' AND parent_id=0 AND is_show=1')->find();
            
            if(empty($catInfo)){
                $status = 0;
                $msg = '无效的分类ID';
                exit(json_encode(array('status'=>$status,'msg'=>$msg)));
            }else{
            	//当前分类下的广告位置信息
            	$adPositionInfo = Db::name('touch_ad_position')->field('position_id,ad_width')->where('position_desc='.$cat_id)->select();
            }
        }
        // var_dump($adPositionInfo);die;
        foreach($adPositionInfo as $key=>$value){
            if($value['ad_width']<200){
                $module_id=$value['position_id'];   //模块图
            }elseif($value['ad_width']>200){
                $banner_id=$value['position_id'];     //banner
            }
        }

        if(!$module_id || !$banner_id){
            $status = 0;
            $msg = '参数不足,无效数据';
            exit(json_encode(array('status'=>$status,'msg'=>$msg)));
        }

        //banner信息-轮播信息
        $bannerInfo = array();
        $bannerInfo = Db::name('touch_ad')->field('ad_id,position_id,ad_name,ad_link,ad_code')->where('position_id = '.$banner_id)->select();
        if(!empty($bannerInfo)){
            foreach($bannerInfo as $key=>$value){
                $bannerInfo[$key]['ad_code']='http://www.paipaistreet.com/data/afficheimg/'.$value['ad_code'];
            }
        }
        $return['banner'] = $bannerInfo;
        //轮播下面的分类模块
        $iconInfo = array();
        if($module_id){
        	$iconInfo = Db::name('touch_ad')->field('ad_id,ad_name,ad_link,ad_code')->where('position_id = '.$module_id)->order('ad_id asc')->select();
            
            if(!empty($iconInfo)){
                foreach($iconInfo as $key=>$val){
                    $iconInfo[$key]['ad_code']='http://www.paipaistreet.com/data/afficheimg/'.$val['ad_code'];
                }
                
            }
        }
        $return['icon'] = $iconInfo;
        
        //预售时间段信息只在预售分类下显示
        $timeInfo = array();
        if(!$cat_id || $cat_id=='1'){
	        $time = ['9:00','12:00','15:00','17:00','19:00','21:00'];
	        $timeInfo = array();
	        $date = date('Y-m-d',time());
	        foreach ($time as $key => $value) {
	        	$timeInfo[$key]['time'] = $value;
	        	$timeStringStart = strtotime($date.' '.$value); 
	        	if(count($time) == ($key+1)){
	        		$timeStringEnd = strtotime($date.' 23:59');
	        	}else{
	        		$timeStringEnd = strtotime($date.' '.$time[$key+1]);	
	        	}
	        	
	        	// echo $timeStringStart;die;
	        	$now = time();//当前时间戳
	        	if($now >= $timeStringStart && $now < $timeStringEnd){
	        		$timeInfo[$key]['isNow'] = 1;
	        		$timeInfo[$key]['title'] = '抢购中';
	        		$nowTime = $value;
	        	}
	        	if($now < $timeStringStart ){
	        		$timeInfo[$key]['isNow'] = 0;
	        		$timeInfo[$key]['title'] = '即将开抢';
	        	}
	        	if($now >= $timeStringEnd ){
	        		$timeInfo[$key]['isNow'] = 0;
	        		$timeInfo[$key]['title'] = '已开抢';
	        	}
	        }
	        //获取预售时间段下的分类信息
	        $saleTime = isset($_REQUEST['saleTime']) ? (int)$_REQUEST['saleTime'] : (int)$nowTime;//时间段
	        $pplist = Db::name('paipai_list')->where('is_delete = 0 and ppj_sale_time = '.$saleTime)->order('ppj_id desc')->page($page,$limit)->select();//分页获取拍拍活动
	        $gid_arr = array();
	        foreach ($pplist as $key => $value) {
	        	$gid_arr[] = $value['goods_id'];	
	        }
	        //查询商品信息
	        $where = array();
	        $where['goods_id'] = array('IN',$gid_arr);
	        $goodsList = Db::name('goods')->where($where)->select();
	        foreach ($goodsList as $key => $value) {
	        	$goodsList[$value['goods_id']] = $goodsList[$key];
	        }
	        //今天开始和结束的时间戳
	        $start=date("Y-m-d",time())." 0:0:0";
    		$end=date("Y-m-d",time())." 24:00:00";

	        $limit_min_time=strtotime($start);
        	$limit_max_time=strtotime($end);
	        //组合要返回的拍拍数据
	        $pai_row = array();
	        foreach ($pplist as $key => $val) {
	        	$pai_row[$key]['ppj_name']=$val['ppj_name'];
                $pai_row[$key]['goods_thumb']='http://www.paipaistreet.com/'.$goodsList[$value['goods_id']]['goods_thumb'];
                $pai_row[$key]['ppj_id']=$val['ppj_id'];
                $pai_row[$key]['ppj_no']=$val['ppj_no'];
                $pai_row[$key]['start_time']=date("Y-m-d H:i:s",$val['start_time']);
                $pai_row[$key]['end_time']=date("Y-m-d H:i:s",$val['end_time']);
                $pai_row[$key]['ppj_status']=$val['ppj_staus'];//0未开始1开始2结束
                $pai_row[$key]['ppj_sale_time']=$val['ppj_sale_time'];
                // $pai_row[$key]['url'] = 'http://www.paipaistreet.com/mapi/pai/details?pid='.$val['ppj_id'];
                //统计当前活动参与人数
                $pay_margin_amount = Db::name('paipai_seller_pay_margin')->where('ppj_id='.$val['ppj_id'].' AND ppj_no='.$val['ppj_no'].' AND ls_pay_ok=1')->count();
                
                $ext_info = unserialize($val['ext_info']);
                $val = array_merge($val, $ext_info);
                $price_ladder = $val['price_ladder'];
                foreach($price_ladder as $key2=>$val2){
                    if ($val2['amount'] <= $pay_margin_amount) {
                        $cur_price = $val2['price'];
                    }else if( $pay_margin_amount == 0 ) {
                        $cur_price=0;break;
                    }
                }

                $pai_row[$key]['pay_amount']=$pay_margin_amount;
                $pai_row[$key]['now_price']=$cur_price;//当前价

                if($val['end_time']<= $limit_min_time ){
                    $pai_row[$key]['sort_status']='end';
                }else if($val['start_time'] >= $limit_max_time){
                    $pai_row[$key]['sort_status']='nostart';
                }else{
                    $pai_row[$key]['sort_status']='ing';
                    $order_amount = Db::name('orderInfo')->where('WHERE ppj_id='.$val['ppj_id'].' AND ppj_no='.$val['ppj_no'])->count();
                    if($order_amount==$val['goods_count']){
                        $pai_row[$key]['sort_status']='end';
                    }else{
                        $pai_row[$key]['sort_status']='ing';
                    }
                }
            }
            $return['goodsList'] = $pai_row;
            // var_dump($pai_row);die;
	    }else{
	    	//根据分类信息获取商品信息
	    	$cateInfo = Db::name('category')->where('is_show = 1 AND parent_id = '.$cat_id)->select();
            foreach($cateInfo as $key=>$val){
                $catid_arr[] = $val['cat_id'];
            }
            $where = array();
            $where['cat_id'] = array('IN',$catid_arr);
            $goodsList = Db::name('goods')->field('goods_id,goods_name,goods_thumb,market_price,cost_price,shop_price,sales_volume,user_id')->where($where)->order('goods_id desc')->page($page,$limit)->select();
            foreach ($goodsList as $key => $value) {
            	$goodsList[$key]['goods_thumb'] = 'http://www.paipaistreet.com/'.$value['goods_thumb'];
            }
            $return['goodsList'] = $goodsList;
	    }
        $return['timeInfo'] = $timeInfo;//预售分类下的时间段信息

        // $str = "21:00";
        // echo (int)$str;die;
        //商品信息
        
        return json_encode(array('status'=>$status,'msg'=>$msg,'content'=>$return ));

    }

    //分类显示
    public function getCat(){
        $cat = new Category;
        $catInfo = $cat->catShow();
        return json_encode(array('status'=>1,'msg'=>'分类信息获取成功','content'=>$catInfo));
    }
}
