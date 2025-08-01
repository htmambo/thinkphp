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

class TelegramBot extends Driver
{
    const TIMEOUT = 33;

    /**
     * @var string chat_id
     */
    protected $chatID;

    /**
     * @var string 机器人令牌
     */
    protected $token;

    /**
     * @var string Telegram 主机地址
     */
    protected $host;
    protected function _initialize()
    {
        $this->chatID = $this->config['chat_id'];
        $this->token = $this->config['token'];
        $this->host = $this->getTelegramHost();
    }

    /**
     * 获取 Telegram 主机地址
     *
     * @return string
     */
    private function getTelegramHost()
    {
        $host = (string)$this->config['host'];

        if (preg_match('/^(?:https?:\/\/)?(?P<host>[^\/?\n]+)/iu', $host, $m)) {
            return $m['host'];
        }

        return 'api.telegram.org';
    }

    /**
     * 获取 MarkDown 表格映射的原始数组
     *
     * @param string $markDownTable
     *
     * @return array
     */
    public function getMarkDownRawArr(string $markDownTable)
    {
        $rawArr = [];
        $markDownTableArr = preg_split("/(?:\n|\r\n)+/", $markDownTable);

        foreach ($markDownTableArr as $row) {
            $row = (string)preg_replace('/^\s+|\s+$|\s+|(?<=\|)\s+|\s+(?=\|)/', '', $row);

            if ($row === '') {
                continue;
            }

            $rowArr = explode('|', trim($row, '|'));
            $rawArr[] = $rowArr;
        }

        return $rawArr;
    }

    /**
     * 送信
     *
     * @param string $content 支持 markdown 语法，但记得对非标记部分进行转义
     * @param string $subject
     * @param array $data
     * @param string|null $recipient 可单独指定 chat_id 参数
     * @param mixed ...$params
     *
     * @desc
     * 注意对 markdown 标记占用的字符进行转义，否则无法正确发送，根据官方说明，以下字符如果不想被 Telegram Bot 识别为 markdown 标记，
     * 应转义后传入，官方说明如下：
     * In all other places characters '_‘, ’*‘, ’[‘, ’]‘, ’(‘, ’)‘, ’~‘, ’`‘, ’>‘, ’#‘, ’+‘, ’-‘, ’=‘, ’|‘,
     * ’{‘, ’}‘, ’.‘, ’!‘ must be escaped with the preceding character ’\'.
     * 如果不转义则电报返回 400 错误
     *
     * 官方markdown语法示例：
     * *bold \*text*
     * _italic \*text_
     * __underline__
     * ~strikethrough~
     * *bold _italic bold ~italic bold strikethrough~ __underline italic bold___ bold*
     * [inline URL](http://www.example.com/)
     * [inline mention of a user](tg://user?id=123456789)
     * `inline fixed-width code`
     * ```
     * pre-formatted fixed-width code block
     * ```
     * ```python
     * pre-formatted fixed-width code block written in the Python programming language
     * ```
     * 需要注意的是，普通 markdown 语法中加粗字体使用的是“**正文**”的形式，但是 Telegram Bot 中是“*加粗我*”的形式
     * 更多相关信息请参考官网：https://core.telegram.org/bots/api#sendmessage
     * 另外我干掉了“_”、“~”、“-”、“.”和“>”关键字，分别对应斜体、删除线、无序列表、有序列表和引用符号，因为这几个比较容易在正常文本里出现，而
     * 我又不想每次都手动转义传入，故做了自动转义处理，况且 telegram 大多不支持
     *
     * 由于 telegram bot 的 markdown 语法不支持表格（https://core.telegram.org/bots/api#markdownv2-style），故表格部分由我自行解析
     * 为字符形式的表格，坐等 telegram bot 支持表格
     *
     * @return bool
     */
    public function send($content, $subject = '', $data = [], $recipient = null, ...$params)
    {
        $this->check($content, $data);

        $isMarkdown = true;

        // 使用可变参数控制 telegram 送信类型，一般不会用到
        if ($params && isset($params[1]) && $params[0] === 'TG') {
            $isMarkdown = $params[1];
        }

        if ($subject !== '') {
            $content = $subject . "\n\n" . $content;
        }

        if ($isMarkdown) {
            // 这几个比较容易在正常文本里出现，而我不想每次都手动转义传入，所以直接干掉了
            $content = preg_replace('/([.>~_{}|`!+=#-])/u', '\\\\$1', $content);

            // 转义非链接格式的 [] 以及 ()
            $content = preg_replace_callback_array(
                [
                    '/(?<!\\\\)\[(?P<brackets>.*?)(?!\]\()(?<!\\\\)\]/' => function ($match) {
                        return '\\[' . $match['brackets'] . '\\]';
                    },
                    '/(?<!\\\\)(?<!\])\((?P<parentheses>.*?)(?<!\\\\)\)/' => function ($match) {
                        return '\\(' . $match['parentheses'] . '\\)';
                    }
                ],
                $content
            );
        }

        try {
            $url = sprintf('https://%s/bot%s/sendMessage', $this->host, $this->token);
            $data = [
                'form_params' => [
                    'chat_id' => $recipient ?: $this->chatID,
                    'text' => $content, // Text of the message to be sent, 1-4096 characters after entities parsing
                    'parse_mode' => $isMarkdown ? 'MarkdownV2' : 'HTML',
                    'disable_web_page_preview' => true,
                    'disable_notification' => false
                ],
            ];
            $resp = $this->curl($url, [], $data);
            $resp = json_decode($resp, true);

            return $resp['ok'] ?: false;
        } catch (\Exception $e) {
            E(L('TelegramBot 送信失败：<red>{$message}</red>', ['message' => $e->getMessage()]));
            return false;
        }
    }
}
