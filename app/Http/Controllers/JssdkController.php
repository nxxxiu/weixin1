<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
class JssdkController extends Controller
{
    public function jstest(){
//        test();die;
//        echo '<pre>';print_r($_SERVER);echo '</pre>';
        //计算签名
        $nonceStr=Str::random(10);
        $ticket=getJsapiTicket();
//        var_dump($ticket);
        $timestamp=time();
        $current_url=$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
//        echo 'nonceStr:'.$nonceStr;echo '</br>';
//        echo 'ticket:'.$ticket;echo '</br>';
//        echo 'timestamp:'.$timestamp;echo '</br>';
//        echo 'current_url:'.$current_url;echo '</br>';die;
        $str="jsapi_ticket=$ticket&noncestr=$nonceStr&timestamp=$timestamp&url=$current_url";
//        echo $str;
        $sign=sha1($str);
//        echo 'signature:'.$sign;die;
        $js_config=[
            'appId'=>env('APPID'),//公众号appid
            'timestamp'=>$timestamp,
            'nonceStr'=>$nonceStr,//随机字符串
            'signature'=>$sign,//签名
        ];
        $data=[
            'jsconfig'=>$js_config
        ];
        return view('weixin.jssdk',$data);

    }

    public function getimg()
    {
        echo '<pre>';print_r($_GET);echo '</pre>';
    }

//    //图片下载
//    public function download(){
//        $client=new Client;
//        $serverId=$_GET['serverId'];
//        dd($serverId);
//    }
}
