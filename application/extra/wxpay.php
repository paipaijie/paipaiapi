<?php
/**
* 	配置账号信息
*/

class WxPayConf_pub
{	
	//=======【基本信息设置】=====================================
	//微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
	const APPID = 'wxd1a74dee97a0c0e6';
	//受理商ID，身份标识
	const MCHID = '1530066071';
	//商户支付API密钥Key
	const KEY = 'dongfanglangkunjiaoyu12345678901';
	//JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
	const APPSECRET = 'd523876dff882ad7cc278a8397c27bfe';
	
	//=======【证书路径设置】=====================================
	//证书路径,注意应该填写绝对路径
	const SSLCERT_PATH = 'home/wwwroot/lkwechat/payment/apiclient_cert.pem';
	const SSLKEY_PATH = 'home/wwwroot/lkwechat/payment/apiclient_key.pem';
	//=======【curl超时设置】===================================
	//本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
	const CURL_TIMEOUT = 30;
	//=======【异步通知url设置】===================================-
	//异步通知url，商户根据实际开发过程设定
	//正式
	const NOTIFY_URL = 'https://langkunjy.com/lkwechat/api/Order/notify';
}

?>