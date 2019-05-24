<?php
namespace app\api\controller;
use app\api\controller\Base;
use think\Loader;
use think\Request;
use think\Db;
use app\api\model\Coupon;
use app\api\model\Account;
use think\Session;
class Ucenter extends Base{
    /*
    *注册
    *@author chenxj
    *@time 2019.5.18
    *所需参数 mobile手机号 smsCode 短信验证码 password 密码 inviteCode 邀请码
     */
    public function register(){
        //手机号
        if(isset($_REQUEST['mobile'])){
            $mobile = trim($_REQUEST['mobile']);//手机号
            if(!isMobileNum($mobile)){//判断手机号格式是否正确
                exit(json_encode(array('status'=>0,'msg'=>'无效的手机号')));    
            }else{//该手机号是否已经注册过了
                $info = Db::name('users')->where('mobile_phone like "'.$mobile.'"')->find();
                if(!empty($info)){
                    exit(json_encode(array('status'=>0,'msg'=>'该手机号已经注册过了，请更换或者直接登录')));
                }
            }
        }else{
            exit(json_encode(array('status'=>0,'msg'=>'请输入手机号')));
        }
        //密码
        if(isset($_REQUEST['password'])){
            $ec_salt = rand(1000, 9999);
            $password = trim($_REQUEST['password']);
            $password = MD5(MD5($password) . $ec_salt);//密码双md5加密加调料包
        }else{
            exit(json_encode(array('status'=>0,'msg'=>'请输入密码')));
        }
        //验证码
        if(isset($_REQUEST['smsCode'])){
            //判断验证码是否正确
            $smsCode = trim($_REQUEST['smsCode']);
            $smsInfo = Db::name('sms')->where('`phone` = ' . floatval($mobile) . ' AND `code` != 0 AND `time` > ' . (time() - 1800))->field('code')->order('`time` DESC')->find();
            $code = $_REQUEST['smsCode'];
            /* # 判断验证码是否为空 */
            if (!$code) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码不能为空')));
                /* # 判断是否有发送记录数据 */
            } elseif (!$smsInfo) {
                exit(json_encode(array('status'=>0,'msg'=>'没有发送记录')));
                /* # 判断是否匹配 */
            } elseif ($smsInfo['code'] != $code) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码不正确')));
            }
        }else{
            exit(json_encode(array('status'=>0,'msg'=>'请输入短信验证码')));
        }
        //邀请码非必填
        $inviteCode = isset($_REQUEST['inviteCode']) ? trim($_REQUEST['inviteCode']) : '';
        //邀请码是否正确 邀请码就是邀请人的uid
        if($inviteCode != ''){
            $inviteInfo = Db::name('users')->where('user_id = '.$inviteCode)->find();
            if(empty($inviteInfo)){
                exit(json_encode(array('status'=>0,'msg'=>'无效的邀请码')));
            }    
        }
        
        //要插入的数据
        $data = array(
            'mobile_phone'=>$mobile,
            'email'=>$mobile.'@163.com',
            'password'=>$password,
            'ec_salt'=>$ec_salt,
            'parent_id'=>$inviteCode,//推荐人id
            'reg_time'=>time(),
            'last_login'=>time(),
            'user_name'=>$mobile
        );
        
        $result = Db::name('users')->insert($data);//插入数据
        $uid = Db::name('users')->getLastInsID();//获取uid
        
        //注册成功的后续操作赠送等级积分赠送消费积分，注册小店
        //注册立即送优惠券
        $coupon = new Coupon;
        $coupon->register_coupons($uid);
        
        $account = new Account;
        $account->log_account_change($uid, 0, 0, 100, 100, '注册送积分',99);
        
        // 注册赠送赠送权益券的操作  
        $selle_have =true; 
        $re = Db::name('quan')->select();
        foreach($re as $k => $v ){
        
            $goods_id=$v['goods_id'];
                
            $endtime=time()+30*24*3600;
             
            $beizhu="注册赠送";
            $ppj_no=0;
             
            if($selle_have){
                
                $arr = array(
                    'goods_id'=>$goods_id,
                    'createtime'=>time(),
                    'usestaus'=>0,
                    'user_id'=>$uid,
                    'endtime'=>$endtime,
                    'beizhu'=>$beizhu,
                    'ppj_no'=>0
                );
                Db::name('paipai_seller')->insert($arr);
            }       
        }
        //给推荐人的奖励 留下等奖励规则定了再写
        
        $auth = authcode(random(6) . "\t" . $uid, 'ENCODE');//uid加密处理
        $return['auth'] = base64_encode($auth);
        $return['uid'] = (string) $uid;
        $return['user_name'] = $mobile;
        exit(json_encode(array('status'=>1,'msg'=>'注册成功','content'=>$return)));
        
    }

    /**
     * 发送验证码
     *@author chenxj
     *@time 2019.5.18
     * @return [type] [description]
     */
    public function send_sms() {
        //发送短信验证码的类型判断用不同的模板 注册 register 快捷登录 quikLogin 忘记密码 fogetPwd
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        $mobile = trim($_REQUEST['mobile']);
        $_mobile_reg = '/^1[345789][0-9]{1}[0-9]{8}$/';           
        $res = preg_match($_mobile_reg, $mobile, $matches) !== 0;
        if (!$res) {
            exit(json_encode(array('status'=>0,'msg'=>'无效的手机号!')));
        }
        switch ($type) {
            case 'register':
                $parm['TemplateCode'] = 'SMS_141520044';//注册的短信模板
                break;
            case 'quikLogin':
                $parm['TemplateCode'] = 'SMS_141580198';//手机号快捷登录的短信模板
                break;
            case 'forgetPwd':
                $parm['TemplateCode'] = 'SMS_141190769';//手机号忘记密码的短信模板
                break;
            default:
                $parm['TemplateCode'] = 'SMS_141520044';//注册的短信模板
                break;
        }
        $parm['phone'] = $mobile;
        $parm['SignName'] = '拍拍街';
        
        $parm['params'] = array('code'=>random(6,1));
        $result = sendSms($parm);
        if($result->Code == 'OK'){
            
            //存到短信发送记录表里
            $arr = array(
                'phone'   => $parm['phone'],
                'code'    => $parm['params']['code'],
                'time'    => time()
            );
            Db::name('sms')->insert($arr);
            return json_encode(array('status'=>1,'msg'=>'短信发送成功'));
        }else{
            if(strstr($result->Message, '级流控')){
                $msg = '操作太频繁了,请稍后重试';
            }else{
                $msg = $result->Message;
            }
            exit(json_encode(array('status'=>0,'msg'=>$msg,'mes'=>$result->Message)));
        }
    }

    /**
     * 账号密码登录
     * 入参用户名或者手机号 username 密码 password
     * @return [int] [uid] [string] [auth]
     */
    public function login() {
        $username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';//可能是用户名也可能是手机号
        if(!isset($_REQUEST['password'])){
            exit(json_encode(array('status'=>0,'msg'=>'请输入密码')));
        }
        $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';//密码
        //通过用户名查询信息
        $u_info = Db::name('users')->where('user_name like "'.$username.'"')->find();
        //通过手机号查询信息
        $m_info = Db::name('users')->where('mobile_phone like "'.$username.'"')->find();
        // echo Db::name('users')->getlastsql();
        // var_dump($m_info);
        if(empty($u_info) && empty($m_info)){
            exit(json_encode(array('status'=>0,'msg'=>'用户不存在，请重新登录')));
        }elseif(!empty($u_info)){//用户名登录
            $uid = $u_info['user_id'];
            $ec_salt = $u_info['ec_salt'];
            $pwd = $u_info['password'];//查询到的密码
            $nickname = $u_info['nick_name'];
            $username = $u_info['user_name'];
            $headpic = SITE_URL.'/'.$u_info['user_picture'];//头像
            $getpwd = MD5(MD5($password).$u_info['ec_salt']);//获取到的密码
            $no_pwd = MD5(MD5($password));//没有调料包的密码
        }elseif(!empty($m_info)){
            $uid = $m_info['user_id'];
            $ec_salt = $m_info['ec_salt'];
            $pwd = $m_info['password'];//查询到的密码
            $nickname = $m_info['nick_name'];
            $username = $m_info['user_name'];
            $headpic = SITE_URL.'/'.$m_info['user_picture'];//头像
            $getpwd = MD5(MD5($password).$m_info['ec_salt']);//获取到的密码
            $no_pwd = MD5(MD5($password));//没有调料包的密码    
        }

        //判断密码是否正确
        if($pwd == $getpwd || $pwd == $no_pwd){
            //密码正确，登录成功，返回数据
            $auth = authcode(random(6) . "\t" . $uid, 'ENCODE');//uid加密处理
            $return['auth'] = base64_encode($auth);
            $return['uid'] = (string) $uid;
            $return['user_name'] = $username;
            $return['nick_name'] = $nickname;
            $return['headpic'] = $headpic;
            exit(json_encode(array('status'=>1,'msg'=>'登录成功','content'=>$return)));
        }else{
            exit(json_encode(array('status'=>0,'msg'=>'密码错误，请重新输入')));    
        }
    } 
    
    /**
     * 手机号快捷登录
     * 入参手机号 mobile 短信验证码 smsCode
     * @return [string] [mobile] [string] [smsCode]
     */
    public function quikLogin() {
        $mobile = isset($_REQUEST['mobile']) ? $_REQUEST['mobile'] : '';//手机号
        if(!isset($_REQUEST['mobile'])){
            exit(json_encode(array('status'=>0,'msg'=>'请输入手机号')));
        }

        //验证码
        if(isset($_REQUEST['smsCode'])){
            //判断验证码是否正确
            $smsCode = trim($_REQUEST['smsCode']);
            $smsInfo = Db::name('sms')->where('`phone` = ' . floatval($mobile) . ' AND `code` != 0 AND `time` > ' . (time() - 1800))->field('code')->order('`time` DESC')->find();
            $code = $_REQUEST['smsCode'];
            /* # 判断验证码是否为空 */
            if (!$code) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码不能为空')));
                /* # 判断是否有发送记录数据 */
            } elseif (!$smsInfo) {
                exit(json_encode(array('status'=>0,'msg'=>'没有发送记录')));
                /* # 判断是否匹配 */
            } elseif ($smsInfo['code'] != $code) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码不正确')));
            }
        }else{
            exit(json_encode(array('status'=>0,'msg'=>'请输入短信验证码')));
        }

        //通过手机号查询信息
        $m_info = Db::name('users')->where('mobile_phone like "'.$mobile.'"')->find();
        if(empty($m_info)){
            //该手机号还未注册，执行注册流程密码为短信验证码
            $ec_salt = rand(1000, 9999);
            $password = trim($_REQUEST['smsCode']);
            $password = MD5(MD5($code) . $ec_salt);//密码双md5加密加调料包
            //要插入的数据
            $data = array(
                'mobile_phone'=>$mobile,
                'email'=>$mobile.'@163.com',
                'password'=>$password,
                'ec_salt'=>$ec_salt,
                'reg_time'=>time(),
                'last_login'=>time(),
                'user_name'=>$mobile
            );
            
            $result = Db::name('users')->insert($data);//插入数据
            $uid = Db::name('users')->getLastInsID();//获取uid
            
            //注册成功的后续操作赠送等级积分赠送消费积分，注册小店
            //注册立即送优惠券
            $coupon = new Coupon;
            $coupon->register_coupons($uid);
            
            $account = new Account;
            $account->log_account_change($uid, 0, 0, 100, 100, '注册送积分',99);
            
            // 注册赠送赠送权益券的操作  
            $selle_have =true; 
            $re = Db::name('quan')->select();
            foreach($re as $k => $v ){
            
                $goods_id=$v['goods_id'];
                    
                $endtime=time()+30*24*3600;
                 
                $beizhu="注册赠送";
                $ppj_no=0;
                 
                if($selle_have){
                    
                    $arr = array(
                        'goods_id'=>$goods_id,
                        'createtime'=>time(),
                        'usestaus'=>0,
                        'user_id'=>$uid,
                        'endtime'=>$endtime,
                        'beizhu'=>$beizhu,
                        'ppj_no'=>0
                    );
                    Db::name('paipai_seller')->insert($arr);
                }       
            }
            
            $auth = authcode(random(6) . "\t" . $uid, 'ENCODE');//uid加密处理
            $return['auth'] = base64_encode($auth);
            $return['uid'] = (string) $uid;
            $return['user_name'] = $mobile;
            $return['nick_name'] = $mobile;
            exit(json_encode(array('status'=>1,'msg'=>'注册成功','content'=>$return)));           

        }else{//执行登录
            $uid = $m_info['user_id'];
            $nickname = $m_info['nick_name'];
            $username = $m_info['user_name'];
            $headpic = SITE_URL.'/'.$m_info['user_picture'];//头像
            //登录成功，返回数据
            $auth = authcode(random(6) . "\t" . $uid, 'ENCODE');//uid加密处理
            $return['auth'] = base64_encode($auth);
            $return['uid'] = (string) $uid;
            $return['user_name'] = $username;
            $return['nick_name'] = $nickname;
            $return['headpic'] = $headpic;
            exit(json_encode(array('status'=>1,'msg'=>'登录成功','content'=>$return)));      
        }
        
    } 

    /**
     * 修改密码
     * 入参 新密码 newPwd 确认新密码：confirmPwd 用户信息标识 auth
     * @return [string] [newPwd] [string] [confirmPwd]
     */
    public function changePwd(){
        if(!UID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录','auth'=>$_POST['auth'])));//登录判断
        }
        $uid = UID; 
        $newPwd = isset($_REQUEST['newPwd']) ? $_REQUEST['newPwd'] : ''; 
        $confirmPwd = isset($_REQUEST['confirmPwd']) ? $_REQUEST['confirmPwd'] : '';  
        if($newPwd == ''){
            $status = 0;
            $msg = '请输入新密码';
        }

        if($confirmPwd == ''){
            $status = 0;
            $msg = '请输入确认密码';
        }

        if($newPwd != $confirmPwd){
            $status = 0;
            $msg = '两次输入密码不一致';
        }else{
            $uinfo = Db::name('users')->where('user_id = '.$uid)->find();
            $password = MD5(MD5($newPwd).$uinfo['ec_salt']);
            $save = array();
            $save['password'] = $password;
            $result = Db::name('users')->where('user_id = '.$uid)->update($save);
            if($result !== false){
                $status = 1;
                $msg = '修改密码成功';
            }else{
                $status = 0;
                $msg = '未知错误';    
            }
        }
        return json_encode(array('status'=>$status,'msg'=>$msg));

    }

    /**
     * 忘记密码
     * 入参 新密码 newPwd 手机号：mobile 短信验证码 smsCode
     * @return [string] [newPwd] [string] [mobile] [string] [smsCode]
     */
    public function forgetPwd(){
        $newPwd = isset($_REQUEST['newPwd']) ? $_REQUEST['newPwd'] : ''; 
        $mobile = isset($_REQUEST['mobile']) ? $_REQUEST['mobile'] : ''; 
        $smsCode = isset($_REQUEST['smsCode']) ? $_REQUEST['smsCode'] : '';
        
        if($mobile == ''){
            $status = 0;
            $msg = '请输入手机号';
        }
        if($smsCode == ''){
            $status = 0;
            $msg = '请输入短信验证码';
        }

        if($newPwd == ''){
            $status = 0;
            $msg = '请输入新密码';
        }
        $smsInfo = Db::name('sms')->where('`phone` = ' . floatval($mobile) . ' AND `code` != 0 AND `time` > ' . (time() - 1800))->field('code')->order('`time` DESC')->find();
        $code = $_REQUEST['smsCode'];
        /* # 判断是否有发送记录 */
        if(!$smsInfo) {
            exit(json_encode(array('status'=>0,'msg'=>'没有发送记录')));
            /* # 判断是否匹配 */
        } elseif ($smsInfo['code'] != $code) {
            exit(json_encode(array('status'=>0,'msg'=>'验证码不正确')));
        }else{
            $uinfo = Db::name('users')->where('mobile_phone = '.$mobile)->find();
            $password = MD5(MD5($newPwd).$uinfo['ec_salt']);
            $save = array();
            $save['password'] = $password;
            $result = Db::name('users')->where('user_id = '.$uinfo['user_id'])->update($save);
            if($result !== false){
                $status = 1;
                $msg = '修改密码成功';
            }else{
                $status = 0;
                $msg = '未知错误';    
            }
        }
        return json_encode(array('status'=>$status,'msg'=>$msg));

    }







    /**
    * 添加/编辑地址操作
    * @author chenxj
    * @time 2018.4.20
    */
    public function addAddress(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录','auth'=>$_POST['auth'])));//登录判断
        }
        $uid = USER_ID;
        $defaultInfo = Db::name('order_shipadd')->where('isdefault = 1 and uid='.$uid)->find();//默认地址信息
        
        !isset($_REQUEST['username']) && exit(json_encode(array('status'=>0,'msg'=>'请填写收货人姓名')));
        !isset($_REQUEST['mobile']) && exit(json_encode(array('status'=>0,'msg'=>'请填写收件人手机号')));
        !isset($_REQUEST['province']) && exit(json_encode(array('status'=>0,'msg'=>'请选择省份')));
        !isset($_REQUEST['city']) && exit(json_encode(array('status'=>0,'msg'=>'请选择城市')));
        !isset($_REQUEST['area']) && exit(json_encode(array('status'=>0,'msg'=>'请选择地区')));
        !isset($_REQUEST['address']) && exit(json_encode(array('status'=>0,'msg'=>'请填写详细收货地址')));
        !isMobileNum($_REQUEST['mobile'])&& exit(json_encode(array('status'=>0,'msg'=>'请输入有效的手机号码')));
        //地址信息
        $address = array();
        $address['province'] = $_REQUEST['province'];
        $address['city'] = $_REQUEST['city'];
        $address['area'] = $_REQUEST['area'];
        $address['address'] = $_REQUEST['address'];

        $username = $_REQUEST['username'];
        $mobile = $_REQUEST['mobile'];
        $detail = serialize($address);//序列化存储
        $osaid = isset($_REQUEST['osaid']) ? intval($_REQUEST['osaid']) : 0;
        $existOsaid = Db::name('order_shipadd')->where('osaid='.$osaid)->find();
        $shipAdd = array(
            'uid' => $uid,
            'username' => $username,
            'mobile' => $mobile,
            'detail' => $detail,
            'isdefault' => isset($_REQUEST['isdefault']) ? $_REQUEST['isdefault'] : 0
        );
        if($osaid!= 0 && $existOsaid){
            $rst = Db::name('order_shipadd')->where('osaid='.$osaid)->update($shipAdd);
        }else{
            Db::name('order_shipadd')->insert($shipAdd);
            $osaid = Db::name('order_shipadd')->getLastInsID();
        }
        //如果新设的默认地址，把该用户的其他地址设为非默认
        if($shipAdd['isdefault'] == 1 && !empty($defaultInfo)) {
            $save = array();
            $save['isdefault'] = 0;
            Db::name('order_shipadd')->where('osaid='.$defaultInfo['osaid'])->update($save);   
        }

        if($osaid || $rst){
            return json_encode(array('status'=>1,'msg'=>'成功','content'=>array('osaid'=>$osaid ? (string)$osaid : '')));
        }else{
            return json_encode(array('status'=>0,'msg'=>'失败','content'=>array('osaid'=>$osaid ? (string)$osaid : '')));
        }
    }

    //删除地址
    public function deleteAddress(Request $request){
        if(!isset($_REQUEST['osaid'])){//地址id
            exit(json_encode(array('status'=>0,'msg'=>'缺少地址id')));
        }
        
        if(!USER_ID){//登陆判断
            exit(json_encode(array('status'=> 0,'msg'=>'请授权')));//请授权之后才可以抢红包
        }

        $cartinfo = Db::name('order_shipadd')->where('osaid = '.$_REQUEST['osaid'])->find();
        if($cartinfo['uid'] != USER_ID){
            exit(json_encode(array('status'=>0,'msg'=>'非法操作')));
        }
        $save = array();
        $save['display'] = 0;
        $result = Db::name('order_shipadd')->where('osaid = '.$cartinfo['osaid'])->update($save);
        if ($result !== false) {
            return json_encode(array('status'=>1,'msg'=>"删除成功"));
        }else{
            exit(json_encode(array('status'=> 0,'msg'=>'未知错误')));   
        } 
    }
    /**
    * 获取地址列表
    * @author chenxj
    * @time 2018.4.20
    */
    public function getAddress(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录判断
        }
        $uid = USER_ID;//uid
        $where['uid'] = $uid;
        $where['display'] = 1;
        $list = Db::name('order_shipadd')->where($where)->select();
        foreach ($list as $key => $value) {
            // echo $value['detail']."</br>";
            $list[$key]['detail'] = unserialize($value['detail']);
        }
        return json_encode(array('status'=>1,'msg'=>'获取地址列表成功','content'=>$list));
    }

    /**
     *个人中心
     *@author chenxj
     *@time 2019.4.3
     **/
    public function myCenter(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录之后
        }
        $uid = USER_ID;//当前登录者的uid
        $uinfo = getUserInfo($uid);//获取用户信息
        $info['orders'] = Db::name('order')->where('stat = 0 and buyeruid ='.$uid)->count();//待付款订单数
        $info['nickname'] = $uinfo['nickname'];//昵称
        $info['headpic'] = $uinfo['headpic'];//头像
        $info['isbind'] = $uinfo['phone'] ? 1 : 0;//是否绑定手机
        $info['phone'] = $uinfo['phone'];//手机
        $info['isshow'] = 0;//是否显示销售订单
        if($uinfo['phone']){
            $franchisee = Db::name('franchisee')->where('phone like "'.$uinfo['phone'].'"')->find();
            if(!empty($franchisee)){
                $info['isshow'] = 1;
            }
        }
        return json_encode(array('status'=>1,'msg'=>'成功','content'=>$info));
    }

    
    /**
     *绑定手机号
     *@author chenxj
     *@time 2019.4.11
     **/
    public function bindPhone(){
        if(!USER_ID){
            exit(json_encode(array('status'=> 0,'msg'=>'请授权登录')));//登录之后才可以绑定手机号
        }
        $uid = USER_ID;//当前登录者的uid
        $mobile = trim($_REQUEST['mobile']);//手机号
        $type = $_REQUEST['type'];
        //微信一键绑定无需验证短信验证码
        if($type == 'other'){
            $smsCode = $_REQUEST['smsCode'];//填进来的短信验证码
            $code = intval($smsCode);
            $smsInfo = Db::name('sms')->where('`phone` = ' . floatval($mobile) . ' AND `code` != 0 AND `time` > ' . (time() - 1800))->field('code')->order('`time` DESC')->find();
            /* # 判断验证码是否为空 */
            if (!$code) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码不能为空')));
                /* # 判断是否有发送记录数据 */
            } elseif (!$smsInfo) {
                exit(json_encode(array('status'=>0,'msg'=>'没有发送记录')));
                /* # 判断是否匹配 */
            } elseif ($smsInfo['code'] != $code) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码不正确')));
            }
        }
        //绑定手机，更新年龄
        $save = array();
        $save['phone'] = $mobile;
        $result = Db::name('user')->where('uid = '.$uid)->update($save);
        if($result > 0){
            return json_encode(array('status'=>1,'msg'=>'手机绑定成功'));
        }else{
            return json_encode(array('status'=>0,'msg'=>'未知错误'));    
        }
        
    }

    
    //短信测试
    public function test(){
        $parm['phone'] = '18734832621';
        $parm['SignName'] = '阿里云短信测试专用';
        $parm['TemplateCode'] = 'SMS_115790040';
        $parm['params'] = array('code'=>random(6,1));
        var_dump($parm);
        sendSms($parm);
        print_r(sendSms($parm));

        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end =  date('Y-m-d', strtotime("$start +1 month"));
        echo $start .'rr'.$end;
    }

}