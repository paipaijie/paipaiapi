<?php
namespace app\api\controller;
use app\api\controller\Base;
use think\Loader;
use think\Request;
use think\Db;
use app\api\model\Order1;
class Order extends Base{
    //创建订单
	public function createOrder(Request $request){

        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }else{
        	$userInfo = Db::name('user')->where('uid = '.USER_ID)->find();
            if(!$userInfo['phone']){
                $discount = 100;//未绑定手机显示原价
                $seller = 1;//如果没有上级加盟商默认为从总代购买
            }else{
                $franchiseeInfo = Db::name('franchisee')->where('display = 1 and phone like "'.$userInfo['phone'].'"')->find();//代理商信息
                $discount = empty($franchiseeInfo) ? 100 : $franchiseeInfo['discount'];//折扣显示
                if($franchiseeInfo['parentid'] == 0){
                	$seller = 1;//如果没有上级加盟商默认为从总代购买
                }else{
                	$info = Db::name('franchisee')->where('id = '.$franchiseeInfo['parentid'])->find();//代理商信息	
                	$duinfo = Db::name('user')->where('phone like "'.$info['phone'].'"')->find();
                	$seller = $duinfo['uid'];//卖家uid
                }
            }
        }

        $uid = USER_ID;//当前登录者的uid
        $gid = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : '';//商品id多个商品逗号隔开
        $num = isset($_REQUEST['num']) ? $_REQUEST['num'] : '';//数量多个商品逗号隔开
        $pay_money = $_REQUEST['pay_money'];
        $gid_arr = explode(',',$gid);
        $num_arr = explode(',',$num);
        //购物车结算
        if(isset($_REQUEST['ocartids'])){
            $gid_arr = array();
            $num_arr = array();
            $ocartid_arr = explode(',',$_REQUEST['ocartids']);
            foreach ($ocartid_arr as $key => $value) {
                $cartInfo = Db::name('order_cart')->where('ocartid = '.$value)->find();
                $gid_arr[] = $cartInfo['infoid'];
                $num_arr[] = $cartInfo['num'];
            }
            $gid = implode(',', $gid_arr);
            $num = implode(',', $num_arr);
        }
        
        $where = array();
        $where['gid'] = array('IN',$gid_arr);
        $goodsInfo = Db::name('goods')->where($where)->select();//订单提交的商品信息
        // echo Db::name('goods')->getlastsql();
        $should_money = 0;//应支付的钱
        foreach ($goodsInfo as $key => $value) {
        	$value['display'] == 0 && exit(json_encode(array('status'=> 0,'msg'=>'该订单里商品'.$value['title'].'已下架，请重新选择后提交')));
        	$should_money += $value['price']*$num_arr[$key]*$discount/100;
        	
        }
        // //收货地址判断
        // $osaid = isset($_REQUEST['osaid']) ? (int)$_REQUEST['osaid'] : 0;
        // if($osaid == 0){
        //     exit(json_encode(array('status'=> 0,'msg'=>'请添加收货地址')));
        // }
        // echo $pay_money."_".$should_money.'_'.$uid.'-'.$discount;die;
        if(price_fen($pay_money) != $should_money){
            exit(json_encode(array('status'=> 0,'msg'=>'参数错误，非法操作')));
        }
        $money = $should_money;
        
        $ordersn = orderCreateSn();
        
        //订单信息
        $data = array(
            'ordersn'=> $ordersn,
            'buyeruid' => $uid,
            'selleruid' => $seller,//卖家uid及上级加盟商的uid
            'gid' => $gid, 
            'num' => $num,
            'discount' => $discount,
            'stat' => 0, //状态订单生成
            'pay_money' => $money, //实际支付的钱
            'osaid' => isset($_REQUEST['osaid']) ? (int)$_REQUEST['osaid'] : 0,
            'dateline' => time()
        );
        $result = Db::name('order')->insert($data);//插入订单信息
        $orderId = Db::name('order')->getLastInsID();
        // echo $discount;
        // echo Db::name('order')->getlastsql();
        if($result){
            //返回订单信息
            $order = new Order1;
            $orderInfo = $order->orderInfo($orderId); 
            // echo USER_ID.'-'.$orderId;die;
            if(isset($_REQUEST['ocartids'])){//生成订单之后清除购物车信息
                $where = array();
                $where['ocartid'] = array('IN',$ocartid_arr);
                Db::name('order_cart')->where($where)->delete();
            }
            //返回地址信息
            $uid = USER_ID;//uid
            $where = array();
            $where['uid'] = $uid;
            $where['display'] = 1;
            $where['isdefault'] = 1;//默认
            $default = Db::name('order_shipadd')->where($where)->find();
            if(empty($default)){
                unset($where['isdefault']);
                $address = Db::name('order_shipadd')->where($where)->order('osaid desc')->find();
            }else{
                $address = $default;
            } 
            $address['detail'] = unserialize($address['detail']);
            return json_encode(array('status'=>1,'msg'=>'提交订单成功','content'=>$orderInfo,'address'=>$address));   
        }else{
            exit(json_encode(array('status'=> 0,'msg'=>'未知错误')));    
        }
    }

    /**
     *取消订单
     *@author chenxj
     *@time 2018.5.8
     **/
    public function cancelOrder(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }
        $uid = USER_ID;//当前登录者的uid
        !isset($_REQUEST['ordersn']) && exit(json_encode(array('status'=> 0,'msg'=>'缺少参数')));
        $ordersn = $_REQUEST['ordersn'];//订单号 
        $orderInfo = Db::name('order')->where('ordersn="'.$ordersn.'"')->find();//订单信息
        if($orderInfo['buyeruid'] != $uid){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//非法操作判断
        }
        if($orderInfo['stat'] != 0){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//只有待付款可以取消
        }  
        $save = array();
        $save['stat'] = -1; //取消订单
        $result = Db::name('order')->where('ordersn="'.$ordersn.'"')->update($save); //更新订单状态
        if ($result > 0) {
            return json_encode(array('status'=>1,'msg'=>'取消订单成功'));
        }else{
            exit(json_encode(array('status'=> 0,'msg'=>'未知错误')));//错误判断    
        }
        
    }
    /**
     *去付款
     *@author chenxj
     *@time 2018.5.8
     **/
    public function toPay(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }
        $uid = USER_ID;//当前登录者的uid
        !isset($_REQUEST['ordersn']) && exit(json_encode(array('status'=> 0,'msg'=>'缺少参数')));
        $ordersn = $_REQUEST['ordersn'];//订单号
        $userInfo = getUserInfo($uid);//用户信息
        $orderInfo = Db::name('order')->where('ordersn="'.$ordersn.'"')->find();//订单信息
        if($orderInfo['buyeruid'] != $uid){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//一致性判断
        } 
        //收货地址判断
        $osaid = isset($_REQUEST['osaid']) ? (int)$_REQUEST['osaid'] : 0;
        if($osaid == 0 && $orderInfo['osaid']==0){
            exit(json_encode(array('status'=> 0,'msg'=>'请添加收货地址')));
        }
        //更新订单的地址信息
        $save = array();
        $save['osaid'] = $osaid;
        Db::name('order')->where('ordersn="'.$ordersn.'"')->update($save);
        // $orderInfo = Db::name('order')->where('ordersn="'.$ordersn.'"')->find();//订单信息
        //开始支付
        Loader::import('payment.WxPayPubHelper.Wx');
        $wx = new \Wx();
        $salt = 'lkjy-'.random(5);
        $ordersnWX = $ordersn.'_'.$salt;
        
        if($orderInfo) {
            
            //微信支付
            $openid = $userInfo['openid'];//'o0nQe0dGczO-5-vHOdTklKRhLn1w';
            $total = 1;//$orderInfo['pay_money'];//price_yuan($orderInfo['pay_money'])
            $title = '朗坤教育';
            $returnData = $wx->start_payment($ordersnWX,$title,$total,$openid);
            if($returnData['return_code'] == 'SUCCESS' && $returnData['result_code'] == 'SUCCESS'){
                //订单号调料包更新
                Db::name('order')->where('ordersn="'.$ordersn.'"')->update(array('salt' => $salt));
                $time = time();
                $signArr = array(
                    'appId' => $returnData['appid'],
                    'nonceStr' => $returnData['nonce_str'],
                    'package' => 'prepay_id='.$returnData['prepay_id'],
                    'signType' => 'MD5',
                    'timeStamp' => $time    
                );
 
                $newSign = $wx->getSign($signArr);
                $return['wxReturn'] = $returnData;
                $return['wxReturn']['sign'] = $newSign;
                $return['wxReturn']['time_stamp'] = (string)$time;
                $return['wxReturn']['package'] = 'prepay_id='.$returnData['prepay_id'];
                $return['wxReturn']['ordersn'] = $ordersn;
                $return['wxReturn']['total_fee'] = $total;
                return json_encode(array('status' => 1, 'msg' => '预支付成功','content'=>$return));
            }else{
                // var_dump($returnData);die;
               return json_encode(array('status' => 0, 'msg' => $returnData['return_msg'],'content'=>$returnData));
                // $this->error($returnData['msg']);
            }
            
        }
    }

    //支付回调
    public function notify(){
        error_reporting(0);
        /**
         * 支付完成后，微信会把相关支付和用户信息发送到商户设定的通知URL，
         * 商户接收回调信息后，根据需要设定相应的处理流程。
         */
        Loader::import('payment.WxPayPubHelper.WxPayPubHelper');
        date_default_timezone_set('PRC');
        $input = file_get_contents('php://input');
        // require("WxPayPubHelper/WxPayPubHelper.php");
        //使用通用通知接口
        $notify = new \Notify_pub();
        //存储微信的回调
        $xml = $input;
        $notify->saveData($xml);
        // //验证签名，并回应微信。
        // //对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
        // //微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
        // //尽可能提高通知的成功率，但微信不保证通知最终能成功。
        // // 初始化，返回信息
        $notify->setReturnParameter("return_code", "FAIL"); //返回状态码
        $notify->setReturnParameter("return_msg", "签名失败"); //返回信息    
        $data = $notify->getData();
        if ($notify->checkSign() == TRUE && $data && $data['result_code'] == 'SUCCESS' && $data['return_code'] == 'SUCCESS') {
            list($ordersn, $salt) = explode('_', $data['out_trade_no']);
             
            if (strstr($salt, 'lkjy-')) {
                $type = 'mall';
            }
        
            switch ($type) {
                case 'mall':
                    $order = Db::name('order')->where('ordersn="'.$ordersn.'"')->find();
                    
                    if ($ordersn && $order) {
                        $notify->setReturnParameter("return_code", "SUCCESS"); //设置返回码
                        $notify->setReturnParameter("return_msg", "OK"); //返回信息
                    
                        if ($order['stat'] == 0) {//未支付
                            // 处理业务逻辑
                            $save = array();
                            $save['pay_type'] = 2;//微信支付
                            $save['stat'] = 1;
                            $save['paytime'] = time();
                            Db::name('order')->where('ordersn="'.$ordersn.'"')->update($save);   //status 0未付款 1已付款 
                        }

                        //修改商品库存 
                        // $save = array();
                        // $save['stock'] =array('exp','stock-'.$order['num']);
                        // $save['solds'] =array('exp','solds+'.$order['num']);
                        // Db::name('goods')->where('gid='.$order['gid'])->update($save);
                        
                        //用户支付记录
                        $existPayment = Db::name('money_payment')->where('tradesn='."'".$data['transaction_id']."'")->find();
                        if(!$existPayment){
                            $insert_payment = array(
                                'ordersn' => $ordersn,
                                'stat' => 1,
                                'type' => 3,
                                'tradesn' => $data['transaction_id'],
                                'price' => $data['total_fee'],
                                'ext3' => serialize($data),
                                'wechatconf' => 1
                            );
                            Db::name('money_payment')->insert($insert_payment);
                        }
                    }
                    
                    // 2. 更新 接受通知记录 LOG
                    if ($notify->data["return_code"] == "FAIL") {
                        $type = 1;//通信出错
                    } elseif ($notify->data["result_code"] == "FAIL") {
                        $type = 2;//业务出错
                    } else {
                        $type = 3;//支付成功
                    }
                    
                    $existLog = Db::name('money_payment_log')->where('tradesn='."'".$data['transaction_id']."'")->find();
                    if(!$existLog){
                        $insert_log = array(
                            'type' => $type,
                            'ordersn' => $ordersn,
                            'tradesn' => $data['transaction_id'],
                            'message' => serialize($data),
                            'wechatconf' => 1,
                            'dateline' => time() 
                        );
                        Db::name('money_payment_log')->insert($insert_log);       
                    }
                    
                    break;  
            }
        }

        $returnXml = $notify->returnXml();

        file_put_contents("notify.txt", date('Y-m-d H:i:s', time()) . "\r\n" . var_export($input, true) . "\r\n" . var_export($_GET, true) . "\r\n" . var_export($_POST, true) . "\r\n" . "\r\n", FILE_APPEND);

        exit($returnXml);
    }
    /**
     *个人中心--订单列表
     *@author chenxj
     *@time 2019.4.11
     **/
    public function orderList(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录之后
        }
        $uid = USER_ID;//当前登录者的uid
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
        $limit = 10;
        $sort = 'dateline desc';//排序
        $order = new Order1;
        $condition = array();
        //获取订单类型
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'buy'; //buy是购物订单sell是销售订单
        $orderType = isset($_REQUEST['orderType']) ? $_REQUEST['orderType'] : 'all';
        if($type == 'buy'){//购物订单
        	$condition['buyeruid'] = $uid;
        	switch ($orderType) {
        		//全部
        		case 'all':
        			break;
        		case 'pay':
        			$condition['stat'] = 0;//待付款订单
        			break;
        		case 'send':
        			$condition['stat'] = 1;//待发货订单
        			break;
        		case 'take':
        			$condition['stat'] = 2;//待收货订单
        			break;
        		default:
        			# code...
        			break;
        	}
            
            $list = $order->orderList($condition,$page,$limit,$sort,$type);
        }elseif($type == 'sell'){//销售订单
            $condition['selleruid'] = $uid;
            switch ($orderType) {
        		case 'pay':
        			$condition['stat'] = 0;//待付款订单
        			break;
        		case 'send':
        			$condition['stat'] = 1;//待发货订单
        			break;
        		case 'take':
        			$condition['stat'] = 2;//待收货订单
        			break;
        		case 'strike':
        			$condition['stat'] = 3;//成交订单
        			break;
        		default:
        			# code...
        			break;
        	}
            $list = $order->orderList($condition,$page,$limit,$sort,$type);
        } 
        return  json_encode(array('status'=>1,'msg'=>'获取订单列表成功','content'=>$list));
    }

    //修改价格和添加备注
    public function editOrder(Request $request){
    	if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }
        $uid = USER_ID;//当前登录者的uid
        !isset($_REQUEST['ordersn']) && exit(json_encode(array('status'=> 0,'msg'=>'缺少参数')));
        $ordersn = $_REQUEST['ordersn'];//订单号 
        $orderInfo = Db::name('order')->where('ordersn like "'.$ordersn.'"')->find();//订单信息
        if($orderInfo['selleruid'] != $uid){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//非法操作判断
        }
        if($orderInfo['stat'] != 0){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//只有待付款可以修改
        }  
        $save = array();
        $save['comment'] = isset($_REQUEST['comment']) ? $_REQUEST['comment'] : $orderInfo['comment']; //有提交备注更新，没有的话保持原来的
        $save['pay_money'] = isset($_REQUEST['money']) ? price_fen($_REQUEST['money']) : $orderInfo['pay_money']; //有修改价格更新，没有的话保持原来的
        $result = Db::name('order')->where('ordersn="'.$ordersn.'"')->update($save); //更新订单状态
        if ($result > 0) {
            return json_encode(array('status'=>1,'msg'=>'更新订单成功','comment'=>$save['comment'],'pay_money'=>price_yuan($save['pay_money'])));
        }else{
            exit(json_encode(array('status'=> 0,'msg'=>'未知错误')));//错误判断    
        }
    }
    //确认收货
    public function confirm(){
    	if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }
        $uid = USER_ID;//当前登录者的uid
        !isset($_REQUEST['ordersn']) && exit(json_encode(array('status'=> 0,'msg'=>'缺少参数')));
        $ordersn = $_REQUEST['ordersn'];//订单号 
        $orderInfo = Db::name('order')->where('ordersn="'.$ordersn.'"')->find();//订单信息
        if($orderInfo['buyeruid'] != $uid){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//非法操作判断
        }
        if($orderInfo['stat'] != 2){
            exit(json_encode(array('status'=> 0,'msg'=>'非法操作')));//只有待收货可以确认收货
        }  
        $save = array();
        $save['stat'] = 3; //确认收货，订单完成
        $result = Db::name('order')->where('ordersn="'.$ordersn.'"')->update($save); //更新订单状态
        if ($result > 0) {
            return json_encode(array('status'=>1,'msg'=>'确认收货成功'));
        }else{
            exit(json_encode(array('status'=> 0,'msg'=>'未知错误')));//错误判断    
        }
    }

    //订单详情
    public function orderDetail(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }
        $uid = USER_ID;//当前登录者的uid
        !isset($_REQUEST['orderId']) && exit(json_encode(array('status'=> 0,'msg'=>'缺少参数')));
        $orderId = $_REQUEST['orderId'];//订单id 

        $order = new Order1;
        $orderInfo = $order->orderInfo($orderId); 
        if($orderInfo['osaid'] != 0){
            $address = Db::name('order_shipadd')->where('osaid = '.$orderInfo['osaid'])->find();
            $address['detail'] = unserialize($address['detail']);
        }else{
            //返回地址信息
            $uid = USER_ID;//uid
            $where = array();
            $where['uid'] = $orderInfo['buyeruid'];
            $where['display'] = 1;
            $where['isdefault'] = 1;//默认
            $default = Db::name('order_shipadd')->where($where)->find();
            if(empty($default)){
                unset($where['isdefault']);
                $address = Db::name('order_shipadd')->where($where)->order('osaid desc')->find();
            }else{
                $address = $default;
            } 
            $address['detail'] = unserialize($address['detail']);    
        }
        $buyerInfo = Db::name('user')->where('uid = '.$orderInfo['buyeruid'])->find();//用户信息
        if($buyerInfo['phone'] == ''){
            $buyer = '普通用户'.$buyerInfo['nickname'];
        }else{
            $fInfo = Db::name('franchisee')->where('phone like "'.$buyerInfo['phone'].'"')->find();
            // echo Db::name('franchisee')->getlastsql();die;
            if(empty($fInfo)){
                $buyer = '普通用户'.$buyerInfo['nickname'];
            }else{
                $buyer = $fInfo['title'].'    '.$fInfo['name'];//买家信息     
            }    
        }
        
        if($orderInfo['selleruid'] == 1){
            $seller = '总部';
        }else{
            $sellerInfo = Db::name('user')->where('uid = '.$orderInfo['selleruid'])->find();//用户信息
            $fInfo = Db::name('franchisee')->where('phone like "'.$sellerInfo['phone'].'"')->find();
            // echo Db::name('franchisee')->getlastsql();die;
            $seller = $fInfo['title'].'    '.$fInfo['name'];//买家信息 
        }
        $orderInfo['buyerTitle'] = $buyer;//买家
        $orderInfo['sellerTitle'] = $seller;//卖家
        return json_encode(array('status'=>1,'msg'=>'订单详情获取成功','content'=>$orderInfo,'address'=>$address));       
    }
}