<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Session;
use think\Db;
use think\Loader;
// 应用公共文件

/**
 * 根据用户uid获取用户信息
 * @params  uid   field
 * @author chenxj
 * @time:2015-09-05
 */
function getUserInfo($uid,$field = 'all') {
    $userInfo = Db::name('user')->where('uid = '.$uid)->find();
    $userInfo['nickname'] = preg_replace_callback('/@E(.{6}==)/', function($r) {return base64_decode($r[1]);}, $userInfo['nickname']);
    if ($field == 'all') {
        return $userInfo ? $userInfo : array();
    } else {
        return $userInfo[$field];
    }
}

/**
 * 随机生成1个不重复n位的字符串
 * @param integer $digit[位数]
 * @author zl 2018.1.16
 */
function getRandCode($digit){
	$code = '';
	$pattern = '1234567890abcdefghijklmnopqrstuvwxyz';
	$code = substr(str_shuffle($pattern),0,$digit);
	return $code;
}

/**
 * curlget请求
 * @author chenxj 
 * @time:2017.0904
 */
function getCurl($url){
    //初始化
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息不输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    //显示获得的数据
    return $data;

}

/**
 * 生成6位随机值
 * @author chenxj 
 * @time:2017.09.01
 */
function random($length, $numeric = 0) {
    $seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
    $seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
    if($numeric) {
        $hash = '';
    } else {
        $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
        $length--;
    }
    $max = strlen($seed) - 1;
    for($i = 0; $i < $length; $i++) {
        $hash .= $seed{mt_rand(0, $max)};
    }
    return $hash;
}

//随机生成字符串
 function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}

/**
 * 将 金额 "分"转换成 "元"为单位的 金额
 * @for，用于页面显示等
 * @author chenxj
 * @time:2017-09-05
 */
function price_yuan($price) {
    $price = strval(intval($price));
    if ($price == 0) {
        return '0';
    } elseif (strlen($price) == 1) {
        return "0.0" . $price;
    } elseif (strlen($price) == 2) {
        return "0." . $price;
    } else {
        return substr($price, 0, -2) . "." . substr($price, -2);
    }
}

/**
 * 将 金额 "元"转换成 "分"为单位的 金额
 * @for，用于数据库储存，运算等
 * @author chenxj
 * @time:2015-09-05
 */
function price_fen($price) {
    if (is_numeric($price)) {
        if ($price == 0) {
            return '0';
        } else {
            $price = sprintf("%.2f", $price);
            return str_replace(".", "", $price);
        }
    } else {
        return 0;
    }
}

/**
 * 发送短信
 */
function sendSms($param) {
    Loader::import('Sms.SignatureHelper');
    $params = array ();

    // *** 需用户填写部分 ***
  //   'access_key_id' => 'LTAIVB7Z2nIDEq4y',
  // 'access_key_secret' => 'DFwiQwzUdhvgQ3sMQKh9GK9A1dJ5l1',
    // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
    $accessKeyId = "LTAIVB7Z2nIDEq4y";
    $accessKeySecret = "DFwiQwzUdhvgQ3sMQKh9GK9A1dJ5l1";

    // fixme 必填: 短信接收号码
    $params["PhoneNumbers"] = $param['phone'];

    // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
    $params["SignName"] = $param['SignName'];

    // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
    $params["TemplateCode"] = $param['TemplateCode'];

    // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
    $params['TemplateParam'] = $param['params'];

    // // fixme 可选: 设置发送短信流水号
    // $params['OutId'] = "12345";

    // // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
    // $params['SmsUpExtendCode'] = "1234567";


    // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
    if(!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
        $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
    }

    // 初始化SignatureHelper实例用于设置参数，签名以及发送请求
    $helper = new \SignatureHelper();

    // 此处可能会抛出异常，注意catch
    $content = $helper->request(
        $accessKeyId,
        $accessKeySecret,
        "dysmsapi.aliyuncs.com",
        array_merge($params, array(
            "RegionId" => "cn-hangzhou",
            "Action" => "SendSms",
            "Version" => "2017-05-25",
        ))
        // fixme 选填: 启用https
        // ,true
    );

    return $content;
}

/** 
 * 导出Excel数据表格并下载 
 * @param  array   $list        要导出的数组格式的数据 
 * @param  string  $filename    导出的Excel表格数据表的文件名 
 * @param  array   $indexKey    $list数组中与Excel表格表头$header中每个项目对应的字段的名字(key值) 
 * @param  array   $startRow    第一条数据在Excel表格中起始行 
 * 比如: $indexKey与$list数组对应关系如下: 
 *     $indexKey = array('id','username','sex','age'); 
 *     $list = array(array('id'=>1,'username'=>'YQJ','sex'=>'男','age'=>24)); 
 * @author zl
 * @time:2018-06-27
 */  
function exportExcel($list,$fileName,$indexKey,$startRow=1){
    require_once EXTEND_PATH.'/Excel/PHPExcel.php';  
    require_once EXTEND_PATH.'/Excel/PHPExcel/Writer/Excel2007.php';  
    $objPHPExcel = new \PHPExcel();  //初始化excel
    $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);  //设置版本保存格式
    $header_arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M', 'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'); 
    //接下来就是写数据到表格里面去  
    $objActSheet = $objPHPExcel->getActiveSheet();  
    foreach ($list as $row) {  
        foreach ($indexKey as $key => $value){  
            //这里是设置单元格的内容  
            $objActSheet->setCellValue($header_arr[$key].$startRow,$row[$value]);  
        }  
        $startRow++;  
    }  
    // 下载这个表格，在浏览器输出  
    header("Pragma: public");  
    header("Expires: 0");  
    header("Cache-Control:must-revalidate, post-check=0, pre-check=0");  
    header("Content-Type:application/force-download");  
    header("Content-Type:application/vnd.ms-execl");  
    header("Content-Type:application/octet-stream");  
    header("Content-Type:application/download");;  
    header('Content-Disposition:attachment;filename='.$fileName.'');  
    header("Content-Transfer-Encoding:binary");  
    $objWriter->save('php://output');  
}


/**
 * [authcode 加密解密函数]
 * @param  string  $string    [待加密解密字符串]
 * @param  string  $operation [类型]
 * @param  string  $key       [密钥]
 * @param  integer $expiry    [过期时间]
 * @return string             [返回加密解密字符串]
 */
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
    $ckey_length = 4;
    $key = md5($key != '' ? $key : 'ldcang');
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya . md5($keya . $keyc);
    $key_length = strlen($cryptkey);

    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = array();
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation == 'DECODE') {
        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc . str_replace('=', '', base64_encode($result));
    }
}

/**
 * 生成订单号（现已 年月日时分秒毫秒+6位随机值）
 * @author chenxj 
 * @time:2017.09.01
 */
function orderCreateSn() {
    $sn = date('YmdHis', time()) . random(6,1);
    return $sn;
}

/**
* 验证手机号是否正确
* @author chenxj 2018.6.7
* @param bool
*/
 function isMobileNum($mobile) {
    if (!is_numeric($mobile)) {
        return false;
    }
    return preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,3,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
 }


 /**
 * 模拟微信post请求
 * @param string 请求地址
 * @param array 数组
 * @author chenxj
 * @time:2019-05-01
 */
function weixinPostCurl($sendUrl,$postData){
    $time = 30;
    $ch = curl_init();
    // 设置变量
    curl_setopt($ch, CURLOPT_URL, $sendUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);//执行结果是否被返回，0是返回，1是不返回
    curl_setopt($ch, CURLOPT_HEADER, 0);//参数设置，是否显示头部信息，1为显示，0为不显示

    //伪造网页来源地址,伪造来自百度的表单提交
    // curl_setopt($ch, CURLOPT_REFERER, "http://www.baidu.com");
    //表单数据，是正规的表单设置值为非0
    curl_setopt($ch, CURLOPT_POST, 1);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    //curl_setopt ( $ch, CURLOPT_SAFE_UPLOAD, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//支持https协议

    curl_setopt($ch, CURLOPT_SSLCERT,ROOT_PATH.'/payment/apiclient_cert.pem');
    curl_setopt($ch, CURLOPT_SSLKEY,ROOT_PATH.'/payment/apiclient_key.pem');
    curl_setopt($ch, CURLOPT_CAINFO, ROOT_PATH.'/payment/rootca.pem'); // CA根证书（用来验证的网站证书是否是CA颁布）

    curl_setopt($ch, CURLOPT_TIMEOUT, $time);//设置curl执行超时时间最大是多少

    //使用数组提供post数据时，CURL组件大概是为了兼容@filename这种上传文件的写法，
    //默认把content_type设为了multipart/form-data。虽然对于大多数web服务器并
    //没有影响，但是还是有少部分服务器不兼容。本文得出的结论是，在没有需要上传文件的
    //情况下，尽量对post提交的数据进行http_build_query，然后发送出去，能实现更好的兼容性，更小的请求数据包。
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $data = curl_exec($ch);
    $return = xmlToArray($data);
    file_put_contents('return.txt', json_encode($return));
    if(curl_errno($ch))
    {
        echo 'Curl error: ' . curl_error($ch);
    }
    if(curl_error($ch)){
        echo 'Curl error: ' . curl_error($ch);
    }
    return $return;
}
//将XML转为array
function xmlToArray($xml){
    $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $array_data;
}