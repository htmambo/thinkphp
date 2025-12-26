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
 * 微信模板消息推送驱动
 *
 * 通过微信公众号模板消息接口向用户推送模板消息。
 * 支持模板变量替换、多用户批量推送、自定义跳转链接等功能。
 *
 * 配置参数：
 * - appid: 微信公众号 AppID
 * - secret: 微信公众号 AppSecret
 * - tplid: 模板 ID
 * - tplset: 模板变量映射数组
 * - users: 接收用户 openid 列表，多个用 | 分隔
 *
 * @package Think\Driver\Messager
 */
class WeChatTpl extends Driver
{
    /**
     * @var string 微信公众号 AppID
     */
    protected $appid;

    /**
     * @var string 微信公众号 AppSecret
     */
    protected $secret;

    /** @var int 请求超时时间（秒） */
    const TIMEOUT = 33;

    /**
     * @var string|null access_token 缓存文件路径（已弃用，改用缓存系统）
     * @deprecated
     */
    protected $accessTokenFile;

    protected function _initialize()
    {
        $this->appid = $this->config['appid'];
        $this->secret = $this->config['secret'];
    }

    /**
     * 获取 access_token
     *
     * @param bool $force
     * @return mixed 获取到的token
     * @throws \Think\Exception
     */
    public function getToken(bool $force = false): string
    {
        $key   = 'wechat_token_' . $this->appid;
        if(!$force) {
            $token = S($key);
            if($token) {
                return $token;
            }
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/token';
        $query = [
            'grant_type'=>'client_credential',
            'appid' => $this->appid,
            'secret' => $this->secret
        ];
        $data = $this->curl($url, $query);
        $data = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON decode failed: ' . json_last_error_msg());
        }
        if(!isset($data['access_token'])) {
            throw new \RuntimeException('获取AccessToken时出错：' . ($data['errmsg'] ?? '未知错误'));
        }
        $token = $data['access_token'];
        S($key, $token, $data['expires_in'] - 100);
        return $token;
    }

    /**
     * 送信
     *
     * 由于腾讯要求 markdown 语法消息必须使用 企业微信 APP 才能查看，然而我并不想单独安装 企业微信 APP，故本方法不使用 markdown 语法，
     * 而是直接使用纯文本 text 类型，纯文本类型里腾讯额外支持 a 标签，所以基本满足需求
     *
     * 参考：
     * https://work.weixin.qq.com/api/doc/90000/90135/91039
     * https://work.weixin.qq.com/api/doc/90000/90135/90236#%E6%96%87%E6%9C%AC%E6%B6%88%E6%81%AF
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
    public function send(string $content, string $subject = '', array $data = [], ?string $recipient = null, ...$params): bool
    {
        $this->check($content, $data);
        if(!isset($data['url'])) {
            $data['url'] = '';
        }
        if(!isset($data['color'])) {
            $data['color'] = '#004081';
        }

        try {
            $content = str_replace('\\n', "\n", $content);
            $set = [
                ':subject' => $subject,
                ':content' => $content,
                ':date' => date('Y-m-d'),
                ':time' => date('H:i:s'),
                ':datetime' => date('Y-m-d H:i:s')
            ];
            $set = array_merge($set, $data);
            $data['content'] = [];
            foreach($this->config['tplset'] as $k => $v) {
                if(isset($set[$v])) {
                    $v = $set[$v];
                } else if (isset($set[$k])) {
                    $v = $set[$k];
                }
                $data['content'][$k] = ['value' => $v];
            }
            $users = $this->config['users'];
            if(is_string($users)) {
                $users = explode('|', $users);
            }
            foreach($users as $openid) {
                $this->doSend($openid, $data);
            }
        } catch (\Exception $e) {
            $this->logError('微信模板消息送信失败', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    public function doSend(string $touser, array $data, string $topcolor = '#7B68EE'): ?string
    {
        $url = $data['url'];
        if(isset($data['color'])) {
            $topcolor = $data['color'];
        }
        $data = $data['content'];
        foreach ($data as &$row) {
            if (empty($row['color'])) {
                $row['color'] = $topcolor;
            }
        }
        $template      = [
            'touser' => $touser,
            'template_id' => $this->config['tplid'],
            'url' => $url,
            'topcolor' => $topcolor,
            'data' => $data
        ];
        $url           = "https://api.weixin.qq.com/cgi-bin/message/template/send";
        $query = ['access_token' => $this->getToken()];
        return $this->curl($url, $query, $template);
    }
}
