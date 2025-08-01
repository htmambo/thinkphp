<?php
namespace Think\Driver\Translate;

class Bing {
    private $url = 'http://api.microsofttranslator.com/V2/Ajax.svc/Translate';
    private $from = 'zh-cn';
    private $to = 'en';
    private $error = '';

    public function __construct($from = '', $to = '') {
        if($from) {
            $this->from = $from;
        }
        if($to) {
            $this->to = $to;
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
            if(mb_strlen($tmp, 'utf-8') + mb_strlen($line, 'utf-8')<3000) {
                $tmp .= "\n".$line;
            } else {
                //调用API进行翻译
                $res = $this->_trans($tmp);
                if($res) {
                    $result .= "\n" . $this->_trans($tmp);
                    $tmp = '';
                } else {
                    return $res;
                }
            }
        }
        if($tmp) {
            $res = $this->_trans($tmp);
            if($res) {
                $result .= "\n" . $this->_trans($tmp);
                $tmp = '';
            } else {
                return $res;
            }
        }
        return $result;
    }
    public function getError(){
        return $this->error;
    }

    private function _trans($text) {
        $query = [
            'oncomplete' => 'json_decode',
            'appId' => 'A4D660A48A6A97CCA791C34935E4C02BBB1BEC1C',
            'from' => $this->from,
            'to' => $this->to,
            'text' => $text
        ];
        $query_str = http_build_query($query);
        $url = $this->url . '?' . $query_str;
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
        } else if(substr($response, 0, 11)!='json_decode') {
            $this->error = $response;
            $response = false;
        }
        curl_close($curl);
        return $response;
    }
}