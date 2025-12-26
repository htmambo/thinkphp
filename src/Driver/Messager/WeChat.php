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

/**
 * 企业微信消息推送驱动
 *
 * 通过企业微信应用消息接口推送文本消息到企业微信。
 * 支持 access_token 自动获取和缓存，支持 token 失效自动重试。
 *
 * 配置参数：
 * - corp_id: 企业 ID
 * - corp_secret: 应用凭证密钥
 * - agent_id: 应用 ID
 * - user_id: 接收用户 ID，默认 '@all' 表示全体成员
 *
 * @package Think\Driver\Messager
 */
class WeChat extends Driver
{
    /** @var int 请求超时时间（秒） */
    const TIMEOUT = 33;

    /**
     * @var string 企业 ID
     */
    protected $corpId;

    /**
     * @var string 企业微信应用的凭证密钥
     */
    protected $corpSecret;

    /**
     * @var int 企业微信应用 ID
     */
    protected $agentId;

    /**
     * @var string 企业微信用户 ID
     *
     * 用于指定接收消息的用户，多个用户用 "|" 分隔
     * 特殊值 "@all" 表示发送给全体成员
     *
     * @see https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token=ACCESS_TOKEN&fetch_child=FETCH_CHILD&department_id=1
     */
    protected $userId;

    /**
     * 初始化配置
     *
     * 从配置数组中读取企业微信相关配置参数
     *
     * @return void
     */
    protected function _initialize(): void
    {
        $this->corpId = $this->config['corp_id'];
        $this->corpSecret = $this->config['corp_secret'];
        $this->agentId = $this->config['agent_id'];
        $this->userId = $this->config['user_id'] ?: '@all';
    }

    /**
     * 获取企业微信 access_token
     *
     * 自动从缓存获取或从企业微信 API 获取，支持强制刷新。
     * 获取的 token 会缓存到本地，有效期减去 100 秒以避免边界情况。
     *
     * @param bool $force 是否强制刷新 token，默认 false
     * @return string access_token
     * @throws \Exception 当获取失败时抛出
     * @see https://work.weixin.qq.com/api/doc/90000/90135/91039
     */
    protected function getAccessToken(bool $force = false): string
    {
        $key = 'wechat_' . $this->corpId . '_' . $this->agentId;
        if (!$force) {
            $accessToken = S($key);
            if($accessToken) {
                return $accessToken;
            }
        }
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
        $query = [
            'corpid' => $this->corpId,
            'corpsecret' => $this->corpSecret
        ];
        $resp = $this->curl($url, $query);
        if(!$resp) {
            throw new \RuntimeException('HTTP request failed while getting access token');
        }
        $resp = (array)json_decode($resp, true);

        if (isset($resp['errcode']) && $resp['errcode'] === 0 && isset($resp['access_token']) && isset($resp['expires_in'])) {
            S($key, $resp['access_token'], $resp['expires_in'] - 100);
            return $resp['access_token'];
        }

        throw new \Exception('获取企业微信 access_token 失败：' . ($resp['errmsg'] ?:'未知原因'));
    }

    /**
     * 发送企业微信消息
     *
     * 使用纯文本 text 类型消息（而非 markdown），因为 markdown 需要企业微信 APP 才能查看。
     * text 类型支持 <a> 标签和 \n 换行，基本满足使用需求。
     *
     * @param string $content 消息内容
     * @param string $subject 消息标题，会添加到���容开头
     * @param array $data 额外数据（本驱动不使用）
     * @param string|null $recipient 单独指定接收者（本驱动不使用，使用配置的 user_id）
     * @param mixed ...$params 其他参数
     * @return bool 发送成功返回 true
     * @throws \Exception 当发送失败时抛出
     * @see https://work.weixin.qq.com/api/doc/90000/90135/90236#文本消息
     */
    public function send(string $content, string $subject = '', array $data = [], ?string $recipient = null, ...$params): bool
    {
        $this->check($content, $data);

        if ($subject !== '') {
            $content = $subject . "\n\n" . $content;
        }
        $footer = $this->getFooter();
        if($footer) {
            $content .= "\n\n" . $footer;
        }
        try {
            $accessToken = $this->getAccessToken();

            $body = [
                'touser' => $this->userId, // 可直接通过此地址获取 userId，指定接收用户，多个用户用“|”分割 https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token=ACCESS_TOKEN&fetch_child=FETCH_CHILD&department_id=1
                'msgtype' => 'text', // 消息类型，text 类型支持 a 标签以及 \n 换行，基本满足需求。由于腾讯要求 markdown 语法必须使用 企业微信APP 才能查看，不想安装，故弃之
                'agentid' => $this->agentId, // 企业应用的 ID，整型，可在应用的设置页面查看
                'text' => [
                    'content' => $content, // 消息内容，最长不超过 2048 个字节，超过将截断
                ],
                'enable_duplicate_check' => 1,
                'duplicate_check_interval' => 60,
            ];

            return $this->doSend($accessToken, $body);
        } catch (\Exception $e) {
            $this->logError('企业微信送信失败', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * 执行送信
     *
     * @param string $accessToken
     * @param array $body
     * @param int $numOfRetries
     *
     * @return bool
     * @throws \Exception
     */
    private function doSend(string $accessToken, array $body, int &$numOfRetries = 0): bool
    {
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/message/send';
        $query = [
            'access_token' => $accessToken
        ];
        $header = [
            'Content-Type' => 'application/json',
        ];
        $resp = $this->curl($url, $query, $body, $header);
        if(!$resp) {
            throw new \RuntimeException('HTTP request failed: ' . $url);
        }
        $resp = (array)json_decode($resp, true);

        if (!isset($resp['errcode']) || !isset($resp['errmsg'])) {
            throw new \Exception('企业微信接口未返回预期的数据响应，本次响应数据为：' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        }

        if ($resp['errcode'] === 0) {
            return true;
        } else if ($resp['errcode'] === 40014) { // invalid access_token
            $accessToken = $this->getAccessToken(true);

            if ($numOfRetries > 2) {
                throw new \Exception('检测到多次提示 access_token 失效，可能是未能正确获取 access_token，请介入调查：' . $resp['errmsg']);
            }

            $numOfRetries++;

            return $this->doSend($accessToken, $body, $numOfRetries);
        }

        throw new \Exception($resp['errmsg']);
    }
}
