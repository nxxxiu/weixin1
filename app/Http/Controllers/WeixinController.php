<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\wxUser;
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

        //消息类型
        if($type=='text') {//文本
            $font = $obj->Content;
            $time = $obj->CreateTime;
            $info = [
                'type' => 'text',
                'openid' => $openid,
                'create_time' => $time,
                'font' => $font
            ];
            $id = WxText::insertGetId($info);
            //自动回复天气
            if (strpos($obj->Content,'+天气')){
//                echo $obj->Content;echo '<br>';
                //获取城市名
                $city=explode('+',$obj->Content)[0];
//                echo 'City:'.$city;
                $url='https://free-api.heweather.net/s6/weather/now?key=HE1904161027181313&location='.$city;
                $arr=json_decode(file_get_contents($url),true);
//                echo '<pre>';print_r($arr);echo '</pre>';
                $fl=$arr['HeWeather6'][0]['now']['fl']; //体感温度
                $wind_dir=$arr['HeWeather6'][0]['now']['wind_dir']; //风向
                $wind_sc=$arr['HeWeather6'][0]['now']['wind_sc']; //风力
                $hum=$arr['HeWeather6'][0]['now']['hum'];//湿度
                $str="温度：".$fl."\n"."风向：".$wind_dir."\n"."风力：".$wind_sc."\n"."湿度：".$hum."\n";
//                print_r($str);
                $response_xml='<xml>
                                    <ToUserName><![CDATA['.$openid.']]></ToUserName>
                                    <FromUserName><![CDATA['.$wx_id.']]></FromUserName>
                                    <CreateTime>.time().</CreateTime>
                                    <MsgType><![CDATA[text]]></MsgType>
                                    <Content><![CDATA['.$str.']]></Content>
                                </xml>';
                echo $response_xml;
            }
        } elseif($type=='image') {//图片
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
        } elseif($type=='voice'){//语音
            $font=$obj->Content;
            $time=$obj->CreateTime;
            $media_id=$obj->MediaId;
            $url="https://api.weixin.qq.com/cgi-bin/media/get?access_token=".$this->access_token()."&media_id=".$media_id;
            $voice=$client->get(new Uri($url));
            //获取文件类型
            $headers=$voice->getHeaders();
            $voice_name=$headers['Content-disposition'][0];
            $fileInfo=substr($voice_name,'-15');
            $voice_name=substr(md5(time().mt_rand(1111,9999)),5,8).$fileInfo;
            $voice_name=rtrim($voice_name,'"');
            //保存文件
            $res=Storage::put('weixin/voice/'.$voice_name, $voice->getBody());
            if($res=='1'){
                //文件路径入库
                $data=[
                    'type'=>'voice',
                    'openid'=>$openid,
                    'create_time'=>$time,
                    'font'=>$voice_name
                ];
                $id=WxText::insertGetId($data);
                if(!$data){
                    Storage::delete('weixin/voice/'.$voice_name);
                    echo "添加失败";
                }else{
                    echo "添加成功";
                }
            }else{
                echo "添加失败";
            }
        }elseif($type == 'event') {
            $event = $obj->Event; //事件类型
            if ($event == 'subscribe') {
                $userInfo = wxUser::where(['openid'=>$openid])->first();
                if ($userInfo) {
//                    dd('m');
                    echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['. '欢迎回来 '. $userInfo['nickname'] .']]></Content></xml>';
                } else {
//                    dd('hh');
                    $u = $this->WxUserTail($openid);
//                    dd($u);
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
        }
    }

    /**获取微信 AccessToren */
    public function accessToken()
    {
        //先获取缓存，如果不存在请求接口
        $redis_key='wx_access_token';
        $token=Redis::get($redis_key);
        if (!$token){
            $url='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . env('APPID') . '&secret=' . env('APPSECRET');
//        echo $url;die;
            $json_str=file_get_contents($url);
//        print_r($json_str);die;
            $arr=json_decode($json_str,true);
//            print_r($arr);die;
            $redis_key='wx_access_token';
            Redis::set($redis_key,$arr['access_token']);
            Redis::expire($redis_key,3600);
        }
        return $token;
    }

//    public  function test(){
//        $access_token=$this->accessToken();
//        echo $access_token;
//    }

    public function WxUserTail($openid)
    {
        $data = file_get_contents("https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $this->accessToken() . "&openid=" . $openid . "&lang=zh_CN");
//        print_r("https://api.weixin.qq.com/cgi-bin/user/info?accessToken=" . $this->accessToken() . "&openid=" . $openid . "&lang=zh_CN");die;
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
