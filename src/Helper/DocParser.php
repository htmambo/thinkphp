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

namespace Think\Helper;

/**
 * Parses the PHPDoc comments for metadata. Inspired by Documentor code base
 * @category   Framework
 * @package    restler
 * @subpackage helper
 * @author     Murray Picton <info@murraypicton.com>
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @link       https://github.com/murraypicton/Doqumentor
 */
class DocParser {
    private $params = array();
    private $_last = '';
    private $_last_space = -1;
    public $descName = 'title';
    public $longDescName = 'desc';

    function parse($doc) {
        if(!$doc) {
            return $this->params;
        }
        if(!is_string($doc)) {
            $doc = $doc->getDocComment();
        }
        $this->params = array();
        $this->_last = '';
        $this->_last_space = -1;
        // 获取块注释内容
        if (preg_match('#^/\*\*(.*)\*/#s', $doc, $matched) === false) {
            return $this->params;
        }
        $comment = trim($matched[1]);
        // 分析块注释内容
        if (preg_match_all('#^\s*\*(.*)#m', $comment, $matched) === false) {
            return $this->params;
        }
        $lines = $matched[1];
        $desc = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if(!$line) {
                continue;
            }
            $parsedLine = $this->parseLine($line); // Parse the line
            if ($parsedLine !== false) {
                if(!isset($this->params[$this->descName]) || !$this->params[$this->descName]) {
                    $this->params[$this->descName] = $parsedLine;
                } else {
                    $desc [] = $parsedLine; // Store the line in the long description
                }
            }
        }
        if($desc) {
            $desc = implode("\n", $desc);
            $this->params [$this->longDescName] = $desc;
        }
        if(isset($this->params['param']) && is_array($this->params['param'])) {
            $this->params['param'] = array_values($this->params['param']);
        }
        if(isset($this->params['return']) && is_array($this->params['return'])) {
            $this->params['return'] = array_values($this->params['return']);
            $this->params['responseSuccess'] = $this->params['return'];
        }
        return $this->params;
    }

    private function parseLine($_line) {
        // trim the whitespace from the line
        $line = trim($_line);
        if (empty($line)) {
            $this->_last = '';
            $this->_last_space = -1;
            return false; // Empty line
        }

        if (strpos($line, '@') === 0) {
            $line = substr($line, 1);
            if (strpos($line, ' ') > 0) {
                list($tag, $value) = explode(' ', $line, 2);
            } else {
                $tag = $line;
                $value = '';
            }
            if(substr($tag, -1)==':') {
                $tag = substr($tag, 0, -1);
            }
            // Parse the line and return false if the parameter is valid
            if ($this->parseTag($tag, $value)) {
                return false;
            }
        } else if ($this->_last) {
            if($this->_last_space == -1) {
                $this->_last_space = strpos($_line, $line);
                $_line = trim($_line);
            } else {
                if(strpos($_line, $line)>=$this->_last_space) {
                    $_line = substr($_line, $this->_last_space);
                }
            }
            $key = $var = '';
            if(strpos($this->_last, '.')) {
                list($key, $var) = explode('.', $this->_last, 2);
            } else {
                $key = $this->_last;
            }
            $vv = &$this->params[$key];
            if($var) {
                $vv = &$vv[$var];
            }
            if(!isset($vv['desc']) || !$vv['desc']) {
                $vv['desc'] = $_line;
            } else if(is_string($vv)) {
                $vv .= "\n" . $_line;
            } else {
                if(substr($this->_last, 0, 6)=='param.') {
                    $this->params['param'][substr($this->_last, 6)]['note'] .= "\n" . $_line;
                }
            }
            return false;
        }

        return $line;
    }

    private function parseTag($tag, $value) {
        /**
         * 只保留值
         */
        $onlyValueKeys = ['method', 'menu', 'auth'];
        if(in_array($tag, $onlyValueKeys)) {
            if(!$value) {
                $value = false;
            } else {
                list($tmp, ) = explode(' ', trim($value), 2);
                $value = trim($tmp);
                $tmp = strtolower($value);
                if ($tmp === 'true'){
                    $value = true;
                } else if ($tmp === 'false'){
                    $value = false;
                } else if ($tmp === 'null'){
                    $value = null;
                }
            }
        } else if ($tag == 'param' || $tag == 'return' || $tag == 'header') {
            $value = $this->formatParamOrReturn($value, $tag);
        } else if ($tag == 'class') {
            list ($tag, $value) = $this->formatClass($value);
        }
        $this->_last = '';
        if ($tag == 'param' || $tag == 'header' || $tag == 'return') {
            $this->params [$tag][$value['name']] = $value;
            $this->_last = 'param.'.$value['name'];
        } else if (empty($this->params [$tag])) {
            $this->params [$tag] = $value;
            $this->_last = $tag;
        } else {
            $this->_last = $tag;
            $this->params [$tag] = $value . $this->params [$tag];
        }
        return true;
    }

    private function formatClass($value) {
        $r = preg_split("[|]", $value);
        if (is_array($r)) {
            $param = $r [0];
            parse_str($r [1], $value);
            foreach ($value as $key => $val) {
                $val = explode(',', $val);
                if (count($val) > 1) {
                    $value [$key] = $val;
                }
            }
        } else {
            $param = 'Unknown';
        }
        return array(
            $param,
            $value
        );
    }

    private function formatParamOrReturn($string, $type = "param") {
        $extra = [];
        $keys = [
            'header' => [
                'name', 'desc'
            ],
            'param' => [
                'type', 'name', 'desc'
            ],
            'return' => [
                'type', 'name', 'desc'
            ]
        ];
        $anything = preg_match_all('/\[([^\]]+)\]/', $string, $matches);
        if($anything) {
            // 格式为 key:value
            foreach($matches[1] as $tag) {
                list($key, $value) = explode(':', $tag);
                $extra[$key] = $value;
            }
            $string = str_replace($matches[0], '', $string);
        }

        $string = preg_replace('/\s+/i', ' ', $string);
        $result = [];
        $tmp = explode(' ', $string);
        foreach($keys[$type] as $key) {
            if($tmp) {
                $result[$key] = array_shift($tmp);
            }
            if(substr($result[$key], 0, 1)=='$') {
                $result[$key] = substr($result[$key], 1);
            }
        }
        $tmp = trim(implode(' ', $tmp));
        if($tmp) {
            $result['note'] = $tmp;
        }
        if($extra) {
            $result = array_merge($result, $extra);
        }
        ksort($result);
        return $result;
    }

}
