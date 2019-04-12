<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\wxUser;
use GuzzleHttp\Client;
class WeixinController extends Controller
{
    public function valid()
    {
        echo $_GET['echostr'];
    }

    public function wxvalid()
    {
        //接收微信服务器推送
        $content = file_get_contents("php://input");
        $obj = simplexml_load_string($content);

        $time = date('Y-m-d H:i:s');
        $str = $time . $content . "\n";
        is_dir('logs') or mkdir('logs', 0777, true);
        file_put_contents("logs/wx_valid.log", $str, FILE_APPEND);
        $wx_id = $obj->ToUserName;  //开发者微信号
        $openid = $obj->FromUserName; //用户的openid
        $event = $obj->Event;
        if ($event == 'subscribe') {
            $userInfo = wxUser::where(['openid'=>$openid])->first();
            if ($userInfo) {
                echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['. '欢迎回来 '. $userInfo['nickname'] .']]></Content></xml>';
            } else {
                $u = $this->WxUserTail($obj->FromUserName);
                //用户信息入库
                $data=[
                    'openid'=>$u['openid'],
                    'nickname'=>$u['nickname'],
                    'sex'=>$u['sex'],
                    'city'=>$u['city'],
                    'province'=>$u['province'],
                    'country'=>$u['country'],
                    'headimgurl'=>$u['headimgurl'],
                    'subscribe_time'=>$u['subscribe_time'],
                    'subscribe_scene'=>$u['subscribe_scene']
                ];
                $res = wxUser::insert($data);
                echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['. '欢迎关注 '. $u['nickname'] .']]></Content></xml>';

            }

        }

//        file_put_contents("/tmp/aaa.log",1111,FILE_APPEND);
    }

    /**获取微信 AccessToren */
    public function accessToken()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . env('APPID') . '&secret=' . env('APPSECRET');
//        echo $url;die;
        $response = file_get_contents($url);
//         echo $response;die;
        $key = 'wx_access_token';
        Cache::get($key);
        // dd(Cache::get($key));
        // Cache::forget($key);
        $arr = json_decode($response, true);
        // dd($arr['access_token']);
        Cache::put($key, $arr['access_token'], 3600);
        // print_R($arr);
        return $arr['access_token'];

    }

//    public  function test(){
//        $access_token=$this->accessToken();
//        echo $access_token;
//    }

    public function WxUserTail($openid)
    {
        $data = file_get_contents("https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $this->accessToken() . "&openid=" . $openid . "&lang=zh_CN");
        $arr = json_decode($data, true);
        return $arr;
    }

    //创建菜单
    public function create_menu(){
        $url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$this->accessToken();
        $arr=[
            'button'=>[
                [
                    'type'=>'click',
                    'name'=>'🐷',
                    'key'=> 'V1001_TODAY_TWLY',
                ],
                [
                    'type'=>'click',
                    'name'=>'😀',
                    'key'=> 'V1001_TODAY_JZSC',
                ]
            ]
        ];
        $str=json_encode($arr,JSON_UNESCAPED_UNICODE);
        $client=new Client();
        $respons=$client->request('POST',$url,[
            'body'=>$str
        ]);
        $ass=$respons->getBody();
        $ar=json_decode($ass,true);
        if($ar['errcode']>0){
            echo "创建菜单失败";
        }else{
            echo "创建菜单成功";
        }
    }
}
