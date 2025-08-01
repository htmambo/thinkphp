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

class Bark extends Driver
{
    const TIMEOUT = 33;

    /**
     * @var string Bark Key
     */
    protected $barkKey;

    /**
     * @var string Bark 域名
     */
    protected $barkUrl;

    /**
     * @var integer|string 指定是否需要保存推送信息到历史记录，1 为保存，其他值为不保存。如果值为空字符串，则推送信息将按照 APP 内设置来决定是否保存
     */
    protected $isArchive;

    /**
     * @var string 指定推送消息分组，可在历史记录中按分组查看推送
     */
    protected $group;

    /**
     * 可选参数值
     * active：不设置时的默认值，系统会立即亮屏显示通知
     * timeSensitive：时效性通知，可在专注状态下显示通知
     * passive：仅将通知添加到通知列表，不会亮屏提醒
     *
     * @var string 时效性通知
     */
    protected $level;

    /**
     * @var string 指定推送消息图标 (仅 iOS15 或以上支持）http://day.app/assets/images/avatar.jpg
     */
    protected $icon;

    /**
     * @var string 点击推送将跳转到url的地址（发送时，URL参数需要编码），GuzzleHttp 库会自动编码
     */
    protected $jumpUrl;

    /**
     * IOS14.5 之后长按或下拉推送即可触发自动复制，IOS14.5 之前无需任何操作即可自动复制
     *
     * @var integer 携带参数 automaticallyCopy=1， 收到推送时，推送内容会自动复制到粘贴板（如发现不能自动复制，可尝试重启一下手机）
     */
    protected $automaticallyCopy = 1;

    /**
     * @var string 携带 copy 参数， 则上面两种复制操作，将只复制 copy 参数的值
     */
    protected $copy = 'https://my.freenom.com/domains.php?a=renewals';

    /**
     * @var string 通知铃声
     */
    protected $sound;

    protected function _initialize()
    {
        $this->barkKey = $this->parseBarkKey($this->config['bark_key']);
        $this->barkUrl = rtrim($this->config['bark_url'], '/');

        $this->isArchive = $this->config['bark_is_archive'];
        $this->group = $this->config['bark_group'];
        $this->level = $this->config['bark_level'];
        $this->icon = $this->config['bark_icon'];
        $this->jumpUrl = $this->config['bark_jump_url'];
        $this->sound = $this->config['bark_sound'];
    }

    /**
     * 解析 Bark Key
     *
     * 支持从这类 url 地址中提取 Bark Key
     * https://api.day.app/xxx/这里改成你自己的推送内容
     *
     * @param string $barkKey
     *
     * @return string
     */
    public function parseBarkKey(string $barkKey)
    {
        if (preg_match('/^https?:\/\/[^\/]+?\/(?P<barkKey>.+?)\//iu', $barkKey, $m)) {
            return $m['barkKey'];
        }

        return $barkKey;
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
     * @return bool|mixed
     * @throws \Exception
     */
    public function send($content, $subject = '', $data = [], $recipient = null, ...$params)
    {
        $this->check($content, $data);

        $query = [
            'level' => $this->level,
            'automaticallyCopy' => $this->automaticallyCopy, // 携带参数 automaticallyCopy=1， 收到推送时，推送内容会自动复制到粘贴板（如发现不能自动复制，可尝试重启一下手机）
            'copy' => isset($data['html_url']) ? $data['html_url'] : $this->copy, // 携带 copy 参数，则上面的复制操作，将只复制 copy 参数的值
        ];

        if ($this->isArchive !== null) {
            $query['isArchive'] = $this->isArchive;
        }
        if ($this->group !== null) {
            $query['group'] = $this->group;
        }
        if ($this->icon !== null) {
            $query['icon'] = $this->icon;
        }
        if ($this->jumpUrl !== null) {
            $query['url'] = $this->jumpUrl;
        }
        if ($this->sound !== null) {
            $query['sound'] = $this->sound;
        }
        if (isset($data['badge'])) { // 设置角标
            $query['badge'] = $data['badge'];
        }

        $formParams = [
            'body' => $content, // 推送内容 换行请使用换行符 \n
        ];

        if ($subject !== '') {
            $formParams['title'] = $subject; // 推送标题 比 body 字号粗一点
        }

        try {
            $url = sprintf('%s/%s/', $this->barkUrl, $this->barkKey);
            $data = [
                'query' => $query,
                'form_params' => $formParams,
            ];

            $resp = $this->curl($url, $query, $data);

            $resp = json_decode($resp, true);

            if (isset($resp['code']) && $resp['code'] === 200) {
                return true;
            }

            throw new \Exception($resp['message'] ?:'未知错误');
        } catch (\Exception $e) {
            E(sprintf('Bark 送信失败：<red>%s</red>', $e->getMessage()));

            return false;
        }
    }
}