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
class MSXiaobinApi{
    private $headers;
    private $ext_id='##';
    private $lastid=0;
    private $self_id;
    function __construct($headers)
    {
        //$this->headers=$headers;
        $headers_cp=array();
        foreach ($headers as $h)
        {
            if(strstr($h,'Encoding')=='')
            {
                $headers_cp[]=$h;
            }
        }
        $this->headers=$headers_cp;
    }

    function sendMsg($text)
    {
        $url='https://api.weibo.com/webim/2/direct_messages/new.json';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "text=$text%0A&uid=5175429989&extensions=%7B%22clientid%22%3A%22$this->ext_id%22%7D&is_encoded=0&decodetime=1&source=".$this->self_id."'");
        $tmp = curl_exec($curl);
        $data=json_decode($tmp,true);
        if(empty($data['created_at']))
        {
            echo "ERROR：FAILED TO SEND MESSAGE.Maybe your cookie is expired.\n";
			print_r($data);
			echo "\n"."text=$text%0A&uid=5175429989&extensions=%7B%22clientid%22%3A%22$this->ext_id%22%7D&is_encoded=0&decodetime=1&source=".$this->self_id."'";
            return FALSE;
        }else
            return $data['idstr'];
    }
    function init()
    {
        echo "Initialing...";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://api.weibo.com/chat");
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->headers);
		curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
        $tmp = curl_exec($curl);
        preg_match_all("~<script src=[A-Za-z./0-9-]+~",$tmp,$matches);
        if(sizeof($matches)!=1)
            return FALSE;
        $initok=false;
        foreach ($matches[0] as $match)
        {
            preg_match_all("~[:A-Za-z./0-9-]+app[:A-Za-z./0-9-]+~",$match,$matches2);
            if(!empty($matches2[0]))
            {
				//echo $matches2[0][0];
                curl_setopt($curl, CURLOPT_URL, "http:".$matches2[0][0]);
                $headers_cp=array();
                foreach ($this->headers as $h)
                {
                    if(strstr($h,'Host')=='')
                    {
                        $headers_cp[]=$h;
                    }
                    else
                        $headers_cp[]='Host: conchfairy.sinajs.cn';
                }
                curl_setopt($curl,CURLOPT_HTTPHEADER,$headers_cp);
                $tmp= curl_exec($curl);
                curl_setopt($curl,CURLOPT_HTTPHEADER,$this->headers);
                preg_match("~&source=[0-9]+~",$tmp,$matches3);
                $this->self_id=$matches3[0];
                $this->self_id=preg_replace("~&source=~",'',$this->self_id);
                if($this->self_id!='')
                    $initok=true;
            }

        }
        if(!$initok)
        {
            echo "  [FAILED]\nCANNOT GET USER ID\n";
            return FALSE;
        }
        $t="".microtime(true)*1000;
        $t=substr($t,0,strlen($t)-2);
        $url="https://web.im.weibo.com/im/handshake?jsonp=jQuery112407448468793375431_1566663732903&message=%5B%7B%22version%22%3A%221.0%22%2C%22minimumVersion%22%3A%221.0%22%2C%22channel%22%3A%22%2Fmeta%2Fhandshake%22%2C%22supportedConnectionTypes%22%3A%5B%22callback-polling%22%5D%2C%22advice%22%3A%7B%22timeout%22%3A60000%2C%22interval%22%3A0%7D%2C%22id%22%3A%222%22%7D%5D&_=$t";
        $headers_cp=array();
        foreach ($this->headers as $h)
        {
            if(strstr($h,'Host')=='')
            {
                $headers_cp[]=$h;
            }
            else
                $headers_cp[]='Host: web.im.weibo.com';
        }
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers_cp);
        curl_setopt($curl, CURLOPT_URL, $url);
        $tmp= curl_exec($curl);
        $tmp=substr($tmp,0,sizeof($tmp)-13);
        $tmp=preg_replace("/try{[A-Za-z0-9_]+\\(/",'',$tmp);
        $recv_data=json_decode($tmp,true);
        $this->ext_id=$recv_data[0]['clientId'];
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->headers);
        curl_close($curl);
        if($this->ext_id=='')
        {
            echo "  [FAILED]\nCANNOT GET EXT_ID";
            return FALSE;
        }
        echo " [OK]\n";
        return true;
    }
    function recvMsg()
    {
        $t="".microtime(true)*1000;
        $t=substr($t,0,strlen($t)-2);
        $url="https://web.im.weibo.com/im/connect?jsonp=jQuery112409435710518476348_1566634416078&message=%5B%7B%22channel%22%3A%22%2Fmeta%2Fconnect%22%2C%22connectionType%22%3A%22callback-polling%22%2C%22id%22%3A%226%22%2C%22clientId%22%3A%22$this->ext_id%22%7D%5D&_=$t";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$this->headers);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        @curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
        $tmp = curl_exec($curl);
        $tmp=substr($tmp,0,sizeof($tmp)-13);
        $tmp=preg_replace("/try{[A-Za-z0-9_]+\\(/",'',$tmp);
        $recv_data=json_decode($tmp,true);
        //echo $tmp;
        if($recv_data!=FALSE && $recv_data!=NULL)
        {

            foreach ($recv_data as $d)
            {

                if(!empty($d['data']['ext']['autoReply']) && $d['data']['ext']['autoReply']===true && $this->lastid<$d['data']['info']['dmid'])
                {

                    $this->lastid=$d['data']['info']['dmid'];
                    if(empty($d['data']['info']['att_ids']))
                        return $d['data']['items'][0][1];
                    else{
                        $id=$d['data']['info']['att_ids'];
                        $id=preg_replace("~,[0-9]+~","",$id);
                        $t="".microtime(true)*1000;
                        $t=substr($t,0,strlen($t)-2);
                        curl_setopt($curl, CURLOPT_URL, "https://upload.api.weibo.com/2/mss/msget?fid=$id&source=209678993&imageType=origin&ts=$t");
                        $image=base64_encode(@curl_exec($curl));
                        return "IMAGE/BASE64:$image";
                    }
                }

            }
        }
        return FALSE;
    }
}
