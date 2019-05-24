<?php
namespace app\api\controller;
use think\Controller;
use think\Session;
use think\Db;
class Base extends controller
{
    protected function _initialize()
    {   
        parent::_initialize();
        //当前访问路径
        $request=  \think\Request::instance();
        $contoller = $request->controller();
        $module = $request->module();
        $action = $request->action();
        if($action != 'upload' && $action != 'login'){
            $this->verifyUser(); 
            define('UID',$this->mid);
            $userInfo = $this->minfo;
        }

    }

    /**
     * 用户身份认证
     * @return void
     */
    private function verifyUser() {
        if (isset($_REQUEST['auth'])) {
            $req_auth = base64_decode(trim($_REQUEST['auth']));
            $req_auth_des = authcode($req_auth, 'DECODE');
            $auth = $this->daddslashes(explode("\t", $req_auth_des));
            list($req_pw, $req_uid) = empty($auth) || count($auth) < 2 ? array('', '') : $auth;
            if (intval($req_uid) > 0) {
                // 不存在的uid要过滤掉 
                $userInfo = Db::name('users')->where('user_id='.$req_uid)->find();  
                if(empty($userInfo)){
                    exit(json_encode(array('status'=>0,'msg'=>'用户不存在，请重新登录')));
                }

                $this->mid = intval($req_uid);
                $this->minfo = $userInfo;
            } else {
                $this->mid = 0;
                $this->minfo = array();
            }
        } else {
            $this->mid = 0;
            $this->minfo = array();
        }
    }

    private function daddslashes($string, $force = 1) {
        if (is_array($string)) {
            $keys = array_keys($string);
            foreach ($keys as $key) {
                $val = $string[$key];
                unset($string[$key]);
                $string[addslashes($key)] = $this->daddslashes($val, $force);
            }
        } else {
            $string = addslashes($string);
        }
        return $string;
    }
}
