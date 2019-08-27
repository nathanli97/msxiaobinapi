/*
*This file is part of MSXiaoBinApi.
*
*    MSXiaoBinApi is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    MSXiaoBinApi is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with Foobar.  If not, see <https://www.gnu.org/licenses/>.
*/
<?php
use Workerman\Worker;
use Workerman\Lib\Timer;
use Workerman\Connection\TcpConnection;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/xiaobinapi.php';
$config_file_name='options.json';
if($argc!=1)
{
    switch ($argv[1])
    {
        case '-h':
        case '--help':
            echo "Usage: php ".$argv[0]." [OPTIONS]\n".
                "\t --generate -g\t\tgenerate a new options.json \n".
                "\t --load -l JSON_FILE\t\tload config file from JSON_FILE\n".
                "\t --help -h\t\tshow this help message\n";
            die();
            break;
        case '-g':
        case '--generate':
            echo "Generate config file...  ";
            $option=array();
            $option['load_cookie_from_file']='cookie.txt';
            $option['overwrite_cookie']='disabled';
            $option['websocket']=array();
            $option['websocket']['status']='enabled';
            $option['websocket']['host']='0.0.0.0';
            $option['websocket']['port']=50357;
            $option['websocket']['worker_count']=4;
            $option['send_msg_via_http']=array();
            $option['send_msg_via_http']['status']='disabled';
            $option['send_msg_via_http']['host']='0.0.0.0';
            $option['send_msg_via_http']['port']=50357;
            $option['send_msg_via_http']['worker_count']=4;
            $option['recv_msg_http_callback']='disabled';
            $option['recv_msg_http_callback_post']='disabled';
            $option['recv_msg_http_callback_text_base64']='disabled';
            @file_put_contents("options.json",@json_encode($option,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) or die("[FAILED]\n");
            echo "[OK]\n";
            die();
            break;
        case '-l':
        case '--load':
            if($argc<=2)
            {
                echo "I need a config file name\n";
                die();
            }else
                $config_file_name=$argv[2];
    }
}
echo "Loading options from file $config_file_name ...  ";
$option=json_decode(@file_get_contents($config_file_name),true) or die("[FAILED]\nIf you are using firstly,please run php ".$argv[0]." --generate");
echo "[OK]\n";
if(isset($option['load_cookie_from_file']) && $option['load_cookie_from_file']!='disabled')
{
    echo "Loading cookies from file ".$option['load_cookie_from_file']."...  ";
    $cookies=file_get_contents("cookies.txt") or die("[FAILED]\n");
    echo "[OK]\n";
}
else if (isset($option['overwrite_cookie']) && $option['overwrite_cookie'] != 'disabled')
{
    echo "Overwriting cookie...  ";
    $cookies=$option['overwrite_cookie'];
    echo "[OK]\n";
}
$xiaobin = new MSXiaobinApi(array(
    'Accept: application/json, text/plain, */* , text/javascript ',
    'Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    $cookies,
    'Host: api.weibo.com',
    'Referer: https://api.weibo.com/chat/',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-origin',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36'
));
if(!$xiaobin->init())
    die();
if(isset($option['websocket']['status']) && $option['websocket']['status']!='disabled')
{
    echo "Running at websocket mode.\nStarting workerman...\n";
    $url="websocket://";
    $url.=isset($option['websocket']['host'])?$option['websocket']['host']:'0.0.0.0';
    $url.=":";
    $url.=isset($option['websocket']['port'])?$option['websocket']['port']:50357;
    $ss_worker = new Worker($url);
    $ss_worker->count = isset($option['websocket']['worker_count'])?$option['websocket']['worker_count']:4;
    $ss_worker->onMessage = function(\Workerman\Connection\TcpConnection $connection, $data) use($xiaobin)
    {
        $xiaobin->sendMsg($data);
    };
    $ss_worker->onWorkerStart = function($worker) use ($xiaobin,$ss_worker,$option)
    {
        Timer::add(1,function () use ($xiaobin,$ss_worker,$option){
            $data=$xiaobin->recvMsg();
            if($data!=FALSE)
            {
                foreach($ss_worker->connections as $connection)
                {
                    if(!$connection->send($data))
                        continue;
                }
                if (isset($option['recv_msg_http_callback']) && $option['recv_msg_http_callback']!="disabled")
                {
                    $type='text';
                    $text='';
                    $image='';
                    if(strlen($data)>10 && substr($data,0,10)=='data:image')
                    {
                        $type='image';
                        $image=$data;
                    }else if(isset($option['recv_msg_http_callback_text_base64']) && $option['recv_msg_http_callback_text_base64']!='disabled')
                        $text=base64_encode($data);
                    else
                        $text=$data;
                    $http_callback=preg_replace("~{TEXT}~",$text,$option['recv_msg_http_callback']);
                    $http_callback=preg_replace("~{TYPE}~",$type,$http_callback);
                    $http_callback=preg_replace("~{IMAGE}~",$image,$http_callback);
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $http_callback);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    @curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    @curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
                    if(isset($option['recv_msg_http_callback_post']) && $option['recv_msg_http_callback_post']!='disabled')
                    {
                        curl_setopt($curl, CURLOPT_POST, 1);
                        curl_setopt($curl,CURLOPT_POSTFIELDS,"type=$type".$text!=''?"&text=$text":''.$image!=''?"&image=$image":'');
                    }
                    @curl_exec($curl);
                }
            }
        });
    };
}elseif (isset($option['send_msg_via_http']['status']) && $option['send_msg_via_http']['status']!="disabled")
{
    $url="http://";
    $url.=isset($option['send_msg_via_http']['host'])?$option['send_msg_via_http']['host']:'0.0.0.0';
    $url.=":";
    $url.=isset($option['send_msg_via_http']['port'])?$option['send_msg_via_http']['port']:50357;
    $http_worker=new Worker($url);
    $http_worker->count = isset($option['send_msg_via_http']['worker_count'])?$option['send_msg_via_http']['worker_count']:4;
    $http_worker->onMessage = function (TcpConnection $connection,$data) use ($xiaobin)
    {
        $msg=null;
        if(isset($data['get']['msg']) && !empty($data['get']['msg']))
            $msg=$data['get']['msg'];
        elseif (isset($data['post']['msg']) && !empty($data['post']['msg']))
            $msg=$data['post']['msg'];
        if($msg==null)
        {
            $connection->send("FAILED:EMPTY MESSAGE");
            return;
        }
        if($xiaobin->sendMsg($msg))
            $connection->send("OK");
        else
            $connection->send("FAILED:UNKNOWN ERROR");
    };

    $http_worker->onWorkerStart = function($worker) use ($xiaobin,$option)
    {
        Timer::add(1,function () use ($xiaobin,$option){
            $data=$xiaobin->recvMsg();
            if($data!=FALSE)
            {
                $type='text';
                $text='';
                $image='';
                if(strlen($data)>10 && substr($data,0,10)=='data:image')
                {
                    $type='image';
                    $image=$data;

                }else if(isset($option['recv_msg_http_callback_text_base64']) && $option['recv_msg_http_callback_text_base64']!='disabled')
                    $text=base64_encode($data);
                else
                    $text=$data;
                $http_callback=preg_replace("~{TEXT}~",$text,$option['recv_msg_http_callback']);
                $http_callback=preg_replace("~{TYPE}~",$type,$http_callback);
                $http_callback=preg_replace("~{IMAGE}~",$image,$http_callback);
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $http_callback);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                @curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                @curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
                if(isset($option['recv_msg_http_callback_post']) && $option['recv_msg_http_callback_post']!='disabled')
                {
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl,CURLOPT_POSTFIELDS,"type=$type".$text!=''?"&text=$text":''.$image!=''?"&image=$image":'');
                }
                @curl_exec($curl);
            }
        });
    };
}else
    die("PROGRAM MUST RUN IN WebSocket Mode or Http_Callback Mode");

// 运行worker
Worker::runAll();
