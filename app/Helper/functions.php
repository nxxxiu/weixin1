<?php
    use Illuminate\Support\Facades\Redis;

    function getWxAccessToken(){
        $key='wx_access_token';//1809a_wx_access_token
        //判断是否有缓存
        $access_token=Redis::get($key);
        if ($access_token){
            return $access_token;
        }else{
            $url='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('APPID').'&secret='.env('APPSECRET');
            $response=json_decode(file_get_contents($url),true);
            if (isset($response['access_token'])){
                Redis::set($key,$response['access_token']);
                Redis::expire($key,3600);
                return $response['access_token'];
            }else{
                return false;
            }
        }

    }

    function getJsapiTicket(){
        $key='wx_jsapi_ticket';
        $ticket=Redis::get($key);
        if ($ticket){
            return $ticket;
        }else{
            $access_token =getWxAccessToken();
            $url='https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
            $ticket_info=json_decode(file_get_contents($url),true);
//        echo '<pre>';print_r($ticket_info);echo '</pre>';
            if (isset($ticket_info['ticket'])){
                Redis::set($key,$ticket_info['ticket']);
                Redis::expire($key,3600);
                return $ticket_info['ticket'];
            }else{
                return false;
            }
        }

    }