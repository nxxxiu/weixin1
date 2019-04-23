<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/phpinfo', function () {
    return view('phpinfo');
});

//微信接口
Route::get('/weixin/valid','WeixinController@valid');//原样返回echostr 第一次get请求
Route::post('/weixin/valid','WeixinController@wxvalid');//接收微信的推送事件 post
Route::get('/weixin/accessToken','WeixinController@accessToken');//获取微信accesstoken
Route::get('/weixin/create_menu','WeixinController@create_menu');//创建微信菜单
Route::get('/weixin/sendMsg','WeixinController@sendMsg');//微信群发
Route::get('/weixin/send','WeixinController@send');//微信群发

//微信支付
Route::get('/wxpay/test','WxpayController@test');//消息群发
Route::post('/wxpay/notify','WxpayController@notify');//微信支付回调地址


//JS-SDK
Route::get('/jssdk/jstest','JssdkController@jstest');
Route::get('jssdk/getimg', 'JssdkController@getimg');//获取JSSDK上传的照片