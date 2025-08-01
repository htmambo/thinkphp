<?php
namespace Think\Driver\Translate;

class Google {
    private $tkk = '435578.903374698';
    private $url = 'https://translate.google.com/translate_a/single';
    private $from = 'zh-CN';
    private $to = 'en';
    private $error = '';
    
    public function __construct($from = '', $to = '', $tk = '') {
        if($from) {
            $this->from = $from;
        }
        if($to) {
            $this->to = $to;
        }
        if($tk) {
            $this->tkk = $tk;
        }
    }
    
    public function translate($text) {
        $text = trim($text);
        if(!$text) {
            return '';
        }
        $text = str_replace(['<br>', '<p>', '</p>', '<br />', '<br/>'], "\r\n", $text);
        $text = strip_tags($text);
        $lines = explode("\n", $text);
        $result = '';
        $tmp = '';
        foreach($lines as $line) {
            $line = trim($line);
            if(!$line) {
                continue;
            }
            if(!$tmp || (mb_strlen($tmp, 'utf-8') + mb_strlen($line, 'utf-8')<500)) {
                $tmp .= "\n".$line;
            } else {
                //调用API进行翻译
                $res = $this->_trans($tmp);
                if($res===false) {
                    return $res;
                } else {
                    $result .= "\n" . $res;
                    $tmp = '';
                }
            }
        }
        if($tmp) {
            $res = $this->_trans($tmp);
            if($res === false) {
                return $res;
            } else {
                $result .= "\n" . $this->_trans($tmp);
                $tmp = '';
            }
        }
        return trim($result);
    }
    public function getError(){
        return $this->error;
    }
    
    private function _trans($text) {
        $text = trim($text);
        if(!$text) {
            return 'NULL';
        }
        $url = 'https://translate.google.com/translate_a/single?client=webapp&sl=auto&tl=en&hl=auto&dt=at&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&otf=1&ssel=3&tsel=3&kc=1&';
        $query = [
            'tk' => $this->TL($text),
            'q' => $text
        ];
//         pre($query['tk']);
        $query_str = http_build_query($query);
        $url = $url . $query_str;
//         pre($url);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36"
            ),
        ));
        $response = curl_exec($curl);
        if(curl_errno($curl)) {
            $this->error = 'CURL_ERROR:'.curl_error($curl);
            $response = false;
        }
        curl_close($curl);
        $result = json_decode($response);
        if($result) {
            $tmp = $result[0];
            //处理翻译结果
            $result = '';
            foreach($tmp as $v) {
                if(isset($v['8'])) {
                    $result .= $v[0];
                }
            }
        } else {
            pre($text);
            pre($response);
            $this->error = strip_tags($response);
            $result = false;
        }
        return $result;
    }

    
    private function shr32($x, $bits) {
        if($bits <= 0){
            return $x;
        }
        if($bits >= 32){
            return 0;
        }
        $bin = decbin($x);
        $l = strlen($bin);
        if($l > 32){
            $bin = substr($bin, $l - 32, 32);
        }elseif($l < 32){
            $bin = str_pad($bin, 32, '0', STR_PAD_LEFT);
        }
        return bindec(str_pad(substr($bin, 0, 32 - $bits), 32, '0', STR_PAD_LEFT));
    }
    
    private function charCodeAt($str, $index) {
        $char = mb_substr($str, $index, 1, 'UTF-8');
        $res = json_encode($char);
        $res = substr($res, 1, -1);
        if(substr($res, 0, 2) == '\\u') {
            $ret = hexdec(substr($res, 2));
        } else {
            $ret = ord($char);
        }
        return $ret;
    }

    private function RL($a, $b) {
        for($c = 0; $c < strlen($b) - 2; $c +=3) {
            $d = substr($b, $c+2, 1);
            $d = $d >= 'a' ? $this->charCodeAt($d,0) - 87 : intval($d);
            $d = substr($b, $c+1, 1) == '+' ? $this->shr32($a, $d) : $a << $d;
            $a = substr($b, $c, 1) == '+' ? ($a + $d & 4294967295) : $a ^ $d;
        }
        return $a;
    }
    
    //直接复制google
    private function TL($a) {
        $tkk = explode('.', $this->tkk);
        $b = $tkk[0];
        for($d = array(), $e = 0, $f = 0; $f < mb_strlen ( $a, 'UTF-8' ); $f ++) {
            $g = $this->charCodeAt ( $a, $f );
            if (128 > $g) {
                $d [$e ++] = $g;
            } else {
                if (2048 > $g) {
                    $d [$e ++] = $g >> 6 | 192;
                } else {
                    if (55296 == ($g & 64512) && $f + 1 < mb_strlen ( $a, 'UTF-8' ) && 56320 == ($this->charCodeAt ( $a, $f + 1 ) & 64512)) {
                        $g = 65536 + (($g & 1023) << 10) + ($this->charCodeAt ( $a, ++ $f ) & 1023);
                        $d [$e ++] = $g >> 18 | 240;
                        $d [$e ++] = $g >> 12 & 63 | 128;
                    } else {
                        $d [$e ++] = $g >> 12 | 224;
                        $d [$e ++] = $g >> 6 & 63 | 128;
                    }
                }
                $d [$e ++] = $g & 63 | 128;
            }
        }
        $a = $b;
        for($e = 0; $e < count ( $d ); $e ++) {
            $a += $d [$e];
            $a = $this->RL ( $a, '+-a^+6' );
        }
        $a = $this->RL ( $a, "+-3^+b+-f" );
        $a ^= $tkk[1];
        if (0 > $a) {
            $a = ($a & 2147483647) + 2147483648;
        }
        $a = fmod ( $a, pow ( 10, 6 ) );
        return $a . "." . ($a ^ $b);
    }
}