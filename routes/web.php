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
Route::any('/weixin/accessToken','WeixinController@accessToken');//获取微信accesstoken
Route::any('/weixin/test','WeixinController@test');
Route::any('/weixin/responseMsg','WeixinController@responseMsg');
Route::any('/create_menu','WxController@create_menu');//创建微信菜单