<?php
namespace Think;
/**
 * 翻译器
 */
class Translate {

    // 翻译驱动
    protected $api   =   null;
    private $source_lang = 'zh-cn';
    private $target_lang = 'en';
    private $cache = [];
    public function __construct($api, $from = 'zh-cn', $to = 'en', $other = ''){
        $class = 'Think\\Driver\\Translate\\' . ucwords($api);
        if(!class_exists($class)){
            throw new \RuntimeException($api . ' is not found!');
        }
        if($from) {
            $this->source_lang = $from;
        }
        if($to) {
            $this->target_lang = $to;
        }
        $this->api = new $class($this->source_lang, $this->target_lang, $other);
    }

    public function getError() {
        return $this->api->getError();
    }

    /**
     * 翻译指定内容
     *
     * @param string $text 要翻译的内容
     *
     * @return string
     * @throws Exception
     */
    public function trans($text): string
    {
        if(!$this->api) {
            throw new \RuntimeException(L('The translation engine does not exist!'));
        }
        $cached = [];
        if(APP_DEBUG) {
            $cached = S('_TRANSLATE_ANY_THING');
            if(isset($cached[md5($text)])) {
                return $cached[md5($text)];
            }
        }
        $result = $this->api->translate($text);
        if(APP_DEBUG) {
            $cached[md5($text)] = $result;
            S('_TRANSLATE_ANY_THING', $cached, 86400 * 365);
        }
        return $result;
    }
}