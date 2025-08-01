<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think\Driver\Messager;

class ServerChan extends Driver
{
    const TIMEOUT = 33;

    /**
     * @var string SendKey
     */
    protected $sendKey;

    protected function _initialize()
    {
        $this->sendKey = $this->config['send_key'];
    }

    /**
     * 送信
     *
     * @param string $content
     * @param string $subject
     * @param array $data
     * @param string|null $recipient
     * @param mixed ...$params
     *
     * @return bool
     * @throws \Exception
     */
    public function send($content, $subject = '', $data = [], $recipient = null, ...$params)
    {
        $this->check($content, $data);

        $footer = $this->getFooter();
        if($footer) {
            $content .= "\n\n" . $content;
        }

        $subject = $subject === '' ? mb_substr($content, 0, 12) . '...' : $subject;

        try {
            $url = sprintf('https://sctapi.ftqq.com/%s.send', $this->sendKey);
            $data = [
                'form_params' => [
                    'title' => $subject,
                    'desp' => str_replace("\n", "\n\n", $content), // Server酱 接口限定，两个 \n 等于一个换行
                ],
            ];
            $resp = $this->curl($url,[], $data);
            $resp = json_decode($resp, true);
            if (isset($resp['code']) && $resp['code'] === 0) {
                return true;
            }

            throw new \Exception('ServerChan 推送出错：' . ($resp['message'] ?:'未知原因'));
        } catch (\Exception $e) {
            E(sprintf('ServerChan 送信失败：<red>%s</red>', $e->getMessage()));

            return false;
        }
    }
}
