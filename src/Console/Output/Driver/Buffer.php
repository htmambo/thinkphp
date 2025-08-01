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

namespace Think\Console\Output\Driver;

use Think\Console\Output;

class Buffer
{
    /**
     * @var string
     */
    private $buffer = '';

    public function __construct(Output $output)
    {
        // do nothing
    }

    public function fetch()
    {
        $content      = $this->buffer;
        $this->buffer = '';
        return $content;
    }

    public function write($messages, $newline = false, $options = Output::OUTPUT_NORMAL)
    {
        $messages = (array)$messages;

        foreach ($messages as $message) {
            $this->buffer .= $message;
        }
        if ($newline) {
            $this->buffer .= "\n";
        }
    }

    public function writerArrayByStyle($messages, $style = '')
    {
        $this->write($messages);
    }

    public function renderException(\Exception $e)
    {
        // do nothing
    }

    public function getTerminalDimensions()
    {
        return 99999999;
    }

    /**
     * @param string $string
     * @param int $width
     * @param int $pad
     * @param string $other
     * @param int $lineSpace
     * @return array|false
     */
    public function splitStringByWidth($string, $width, $pad = STR_PAD_RIGHT, $other = '', $lineSpace = 0)
    {
        return [$string];
    }
}
