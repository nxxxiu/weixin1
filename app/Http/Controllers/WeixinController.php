<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\WxUser;
use App\WxText;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
class WeixinController extends Controller
{
    public function valid()
    {
        echo $_GET['echostr'];
    }

    public function wxvalid()
    {
        //接收微信服务器推送
        $client=new Client();
        $data = file_get_contents("php://input");
//        print_r($data);die;
        $time=date('Y-m-d H:i:s');
        $str=$time.$data."\n";
        is_dir('logs') or mkdir('logs',0777,true);
        file_put_contents("logs/wx_valid.log",$str,FILE_APPEND);
        $obj=simplexml_load_string($data);
//        print_r($obj);die;
        $wx_id = $obj->ToUserName;  //开发者微信号
//        print_r($wx_id);die;
        $openid = $obj->FromUserName; //用户的openid
//        print_r($openid);die;
        $type = $obj->MsgType;
        $event = $obj->Event; //事件类型
        //消息类型
        if($type=='text') {
            $font = $obj->Content;
            $time = $obj->CreateTime;
            $info = [
                'type' => 'text',
                'openid' => $openid,
                'create_time' => $time,
                'font' => $font
            ];
            $id = WxText::insertGetId($info);
        }

        if($type=='image') {
            $font = $obj->Content;
            $time = $obj->CreateTime;
            $media_id = $obj->MediaId;
            $url = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=" . $this->accessToken() . "&media_id=" . $media_id;
            // echo "PicUrl:".$obj->PicUrl;
            $img = $client->get(new Uri($url));
            //获取文件类型
            $headers = $img->getHeaders();
//            echo "<pre>";print_r($headers);echo "</pre>";die;
            $img_name = $headers['Content-disposition'][0];
            $fileInfo = substr($img_name, '-15');
            $img_name = substr(md5(time() . mt_rand(1111, 9999)), 5, 8) . $fileInfo;
            $img_name = rtrim($img_name, '"');
            // 保存文件
            $res = Storage::put('weixin/img/' . $img_name, $img->getBody());
            if ($res == '1') {
                //文件路径入库
                $data = [
                    'type' => 'img',
                    'openid' => $openid,
                    'create_time' => $time,
                    'font' => $img_name
                ];
                $id = WxText::insertGetId($data);
                if (!$data) {
                    Storage::delete('weixin/img/' . $img_name);
                    echo "添加失败";
                } else {
                    echo "添加成功";
                }
            } else {
                echo "添加失败";
            }
            // $imgname=time().rand(11111,99999).'.jpg';
            // file_put_contents('wx/img/'.$imgname,$img);
        }

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
        $data = file_get_contents("https://api.weixin.qq.com/cgi-bin/user/info?accessToken=" . $this->accessToken() . "&openid=" . $openid . "&lang=zh_CN");
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
