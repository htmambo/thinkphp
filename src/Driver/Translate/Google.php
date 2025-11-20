<?php

namespace Think\Driver\Translate;

/**
 * Google 翻译驱动类
 *
 * 用于调用 Google 翻译接口进行文本翻译。
 * 包含文本分块处理、Token 生成算法以及 HTTP 请求封装。
 */
class Google
{
    /**
     * @var string 翻译 API 的 Token Key (TKK)
     * 注意：此值可能会随 Google 更新而失效，需要定期更新或动态获取。
     */
    private $tkk = '435578.903374698';

    /**
     * @var string Google 翻译 API 地址
     */
    private $url = 'https://translate.google.com/translate_a/single';

    /**
     * @var string 源语言代码 (默认: zh-CN)
     */
    private $from = 'zh-CN';

    /**
     * @var string 目标语言代码 (默认: en)
     */
    private $to = 'en';

    /**
     * @var string 错误信息
     */
    private $error = '';

    /**
     * 构造函数
     *
     * @param string $from 源语言
     * @param string $to   目标语言
     * @param string $tk   Token Key (可选)
     */
    public function __construct(string $from = '', string $to = '', string $tk = '')
    {
        if ($from) {
            $this->from = $from;
        }
        if ($to) {
            $this->to = $to;
        }
        if ($tk) {
            $this->tkk = $tk;
        }
    }

    /**
     * 翻译文本
     *
     * 将输入文本进行预处理、分块，并调用 Google API 进行翻译。
     *
     * @param string $text 待翻译的文本
     * @return string|false 翻译后的文本，失败返回 false
     */
    public function translate(string $text)
    {
        $text = trim($text);
        if (!$text) {
            return '';
        }

        // 规范化换行符，将 HTML 换行标签转换为标准换行符
        $text = str_replace(['<br>', '<p>', '</p>', '<br />', '<br/>'], "\r\n", $text);
        // 去除 HTML 标签
        $text = strip_tags($text);

        $lines = explode("\n", $text);
        $result = [];
        $chunk = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            // 检查当前块加上新行是否超过长度限制 (500字符)
            // Google 翻译 API 对 GET 请求长度有限制，因此需要分块处理
            if (!$chunk || (mb_strlen($chunk, 'utf-8') + mb_strlen($line, 'utf-8') < 500)) {
                $chunk .= ($chunk ? "\n" : '') . $line;
            } else {
                // 当前块已满，执行翻译
                $res = $this->_trans($chunk);
                if ($res === false) {
                    return false;
                }
                $result[] = $res;
                // 开始新的块
                $chunk = $line;
            }
        }

        // 处理剩余的块
        if ($chunk) {
            $res = $this->_trans($chunk);
            if ($res === false) {
                return false;
            }
            $result[] = $res;
        }

        return implode("\n", $result);
    }

    /**
     * 获取错误信息
     *
     * @return string 错误描述
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 执行翻译请求
     *
     * 构造 HTTP 请求并解析响应。
     *
     * @param string $text 待翻译的文本块
     * @return string|false 翻译结果，失败返回 false
     */
    private function _trans(string $text)
    {
        $text = trim($text);
        if (!$text) {
            return '';
        }

        // 构造查询参数
        $query = [
            'client' => 'webapp',
            'sl'     => $this->from,
            'tl'     => $this->to,
            'hl'     => 'auto',
            'dt'     => 't', // 请求的数据类型
            'otf'    => 1,
            'ssel'   => 3,
            'tsel'   => 3,
            'kc'     => 1,
            'tk'     => $this->TL($text), // 计算 Token
            'q'      => $text,
        ];

        $url = $this->url . '?' . http_build_query($query);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST  => "GET",
            CURLOPT_HTTPHEADER     => [
                "Cache-Control: no-cache",
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
            ],
        ]);

        $response = curl_exec($curl);
        $errno    = curl_errno($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($errno) {
            $this->error = 'CURL_ERROR: ' . $error;
            return false;
        }

        $result = json_decode($response, true);

        if (!is_array($result)) {
            $this->error = strip_tags($response);
            return false;
        }

        // 解析返回的 JSON 结构提取翻译文本
        // Google 返回的结构是一个多维数组，翻译结果在第一部分的每个块的第一个元素中
        $translatedText = '';
        if (isset($result[0]) && is_array($result[0])) {
            foreach ($result[0] as $block) {
                if (isset($block[0])) {
                    $translatedText .= $block[0];
                }
            }
        }

        return $translatedText;
    }

    /**
     * 32位无符号右移
     *
     * 模拟 JavaScript 的 >>> 运算符。
     *
     * @param int $x   要移动的数
     * @param int $bits 移动的位数
     * @return int 移动后的结果
     */
    private function shr32($x, $bits)
    {
        if ($bits <= 0) {
            return $x;
        }
        if ($bits >= 32) {
            return 0;
        }
        $bin = decbin($x);
        $l   = strlen($bin);
        if ($l > 32) {
            $bin = substr($bin, $l - 32, 32);
        } elseif ($l < 32) {
            $bin = str_pad($bin, 32, '0', STR_PAD_LEFT);
        }
        return bindec(str_pad(substr($bin, 0, 32 - $bits), 32, '0', STR_PAD_LEFT));
    }

    /**
     * 获取字符的 Unicode 码点
     *
     * @param string $str   字符串
     * @param int    $index 字符索引
     * @return int Unicode 码点
     */
    private function charCodeAt($str, $index)
    {
        $char = mb_substr($str, $index, 1, 'UTF-8');
        if (function_exists('mb_ord')) {
            return mb_ord($char);
        }
        // 兼容性回退：将字符转换为 UTF-32BE 并解析为整数
        $ret = mb_convert_encoding($char, 'UTF-32BE', 'UTF-8');
        return hexdec(bin2hex($ret));
    }

    /**
     * 计算 RL (Rotate Left / Bitwise op helper)
     *
     * Google Token 算法的一部分，对数值进行位运算混淆。
     *
     * @param int    $a 初始值
     * @param string $b 混淆字符串
     * @return int 计算结果
     */
    private function RL($a, $b)
    {
        for ($c = 0; $c < strlen($b) - 2; $c += 3) {
            $d = $b[$c + 2];
            $d = $d >= 'a' ? $this->charCodeAt($d, 0) - 87 : intval($d);
            $d = $b[$c + 1] == '+' ? $this->shr32($a, $d) : $a << $d;
            $a = $b[$c] == '+' ? ($a + $d & 4294967295) : $a ^ $d;
        }
        return $a;
    }

    /**
     * 计算 Token (TL)
     *
     * 根据输入文本和 TKK 生成请求所需的 Token。
     * 这是调用 Google 翻译 API 的关键验证参数。
     *
     * @param string $a 输入文本
     * @return string 生成的 Token
     */
    private function TL($a)
    {
        $tkk = explode('.', $this->tkk);
        $b   = $tkk[0];

        $d = [];
        for ($f = 0; $f < mb_strlen($a, 'UTF-8'); $f++) {
            $g = $this->charCodeAt($a, $f);
            if (128 > $g) {
                $d[] = $g;
            } else {
                if (2048 > $g) {
                    $d[] = $g >> 6 | 192;
                } else {
                    // 处理代理对 (Surrogate Pairs)
                    if (55296 == ($g & 64512) && $f + 1 < mb_strlen($a, 'UTF-8') && 56320 == ($this->charCodeAt($a, $f + 1) & 64512)) {
                        $g   = 65536 + (($g & 1023) << 10) + ($this->charCodeAt($a, ++$f) & 1023);
                        $d[] = $g >> 18 | 240;
                        $d[] = $g >> 12 & 63 | 128;
                    } else {
                        $d[] = $g >> 12 | 224;
                        $d[] = $g >> 6 & 63 | 128;
                    }
                }
                $d[] = $g & 63 | 128;
            }
        }

        $a = $b;
        foreach ($d as $value) {
            $a += $value;
            $a = $this->RL($a, '+-a^+6');
        }
        $a = $this->RL($a, "+-3^+b+-f");
        $a ^= $tkk[1];
        if (0 > $a) {
            $a = ($a & 2147483647) + 2147483648;
        }
        $a = fmod($a, pow(10, 6));
        return $a . "." . ($a ^ $b);
    }
}
