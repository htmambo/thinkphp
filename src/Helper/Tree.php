<?php

// +-----------------------------------------------------------------------------------------------
// | 简易CMS
// +-----------------------------------------------------------------------------------------------
// | [请手动修改文件描述]
// +-----------------------------------------------------------------------------------------------
// | Author: IT果农 <htmambo@163.com> <http://www.haolie.net>
// +-----------------------------------------------------------------------------------------------
// | Version $Id: Tree.php 25 2016-10-15 23:01:38Z IT果农 <htmambo@163.com> $
// +-----------------------------------------------------------------------------------------------

namespace Think\Helper;

/**
 * 生成多层树状下拉选框的工具模型
 */
class Tree
{
    /**
     * 生成树型结构所需要的2维数组
     *
     * @var array
     */
    public $arr = [];

    /**
     * 生成树型结构所需修饰符号，可以换成图片
     *
     * @var array
     */
    public $icon = ['│', '├', '└', '┬', '─', '　'];
    // public $icon = ['|', '+', '+', '+', '-', '&nbsp;'];

    /**
     * @access private
     */
    public $ret  = [];
    public $pid  = 'parentid';
    public $myid = 'id';

    /**
     * 构造函数，初始化类
     *
     * @param array 2维数组，例如：
     *      array(
     *      1 => array('id'=>'1','parentid'=>0,'name'=>'一级栏目一'),
     *      2 => array('id'=>'2','parentid'=>0,'name'=>'一级栏目二'),
     *      3 => array('id'=>'3','parentid'=>1,'name'=>'二级栏目一'),
     *      4 => array('id'=>'4','parentid'=>1,'name'=>'二级栏目二'),
     *      5 => array('id'=>'5','parentid'=>2,'name'=>'二级栏目三'),
     *      6 => array('id'=>'6','parentid'=>3,'name'=>'三级栏目一'),
     *      7 => array('id'=>'7','parentid'=>3,'name'=>'三级栏目二')
     *      )
     *
     * @throws \Think\Exception
     */
    public function __construct($arr = [], $id = '', $pid = '')
    {
        if ($id) {
            $this->myid = $id;
        }
        if ($pid) {
            $this->pid = $pid;
        }
        if ($arr && is_array($arr)) {
            $tmp = current($arr);
            if (!isset($tmp[$this->myid]) || !isset($tmp[$this->pid])) {
                E('数组格式无效', 502);
            }
            foreach ($arr as $v) {
                $id              = $v[$this->myid];
                $this->arr[$id] = $v;
            }
        } else {
            E('不是数组');
        }
        $this->ret = [];
    }

    /**
     * 生成树状数组
     *
     * @param mixed $root
     *
     * @return array
     */
    public function buildTree($root = 0, $level = 1, $subname = 'sub')
    {
        $tree = [];
        if ($ls = $this->get_child($root)) {
            foreach ($ls as $key => $v) {
                $sub         = [];
                $v['_level'] = $level;
                if (empty($v['icon'])) {
                    $v['icon'] = 'fa-th-list';
                }
                if ($this->get_child($v[$this->myid])) {
                    $i   = $level + 1;
                    $sub = $this->buildTree($v[$this->myid], $i, $subname);
                }
                if ($sub) {
                    $v[$subname] = $sub;
                }
                $tree[] = $v;
            }
        }
        return $tree;
    }

    /**
     * 得到父级数组
     *
     * @param int $myid
     *
     * @return array
     */
    public function get_parent($myid)
    {
        $newarr   = [];
        $parentid = $this->pid;
        if (!isset($this->arr[$myid])) {
            return false;
        }
        $pid = $this->arr[$myid][$parentid];
        $pid = $this->arr[$pid][$parentid];
        if (is_array($this->arr)) {
            foreach ($this->arr as $a) {
                $id = $a[$this->myid];
                if ($a[$parentid] == $pid) {
                    $newarr[$id] = $a;
                }
            }
        }
        return $newarr;
    }

    /**
     * 获取当前节点的所有父分类
     *
     * @param int $myid
     *
     * @return array|unknown[]
     */
    public function get_all_parent_id($myid = 0)
    {
        $result = [];
        foreach ($this->arr as $a) {
            if ($a[$this->myid] == $myid && $a[$this->pid] > 0) {
                $result[] = $a[$this->pid];
                $pid      = $a[$this->pid];
                $result   = array_merge($result, $this->get_all_parent_id($pid));
            }
        }
        return $result;
    }

    /**
     * 获取当前节点的所有子分类
     *
     * @param int $myid
     *
     * @return array
     */
    public function get_all_child_id($myid = 0)
    {
        $result = [];
        foreach ($this->arr as $a) {
            if ($a[$this->pid] == $myid) {
                $result[] = $a[$this->myid];
                $pid      = $a[$this->myid];
                $result   = array_merge($result, $this->get_all_child_id($pid));
            }
        }
        return $result;
    }

    /**
     * 得到子级数组
     *
     * @param int
     *
     * @return array
     */
    public function get_child($myid = 0)
    {
        $upid = $this->pid;
        $a    = $newarr = [];
        if (is_array($this->arr)) {
            foreach ($this->arr as $a) {
                $id = $a[$this->myid];
                if ($a[$upid] == $myid) {
                    $newarr[$id] = $a;
                }
            }
        }
        return $newarr ? $newarr : false;
    }

    /**
     * 得到当前位置数组
     *
     * @param int   $myid
     * @param array $newarr
     *
     * @return array
     */
    public function get_pos($myid, &$newarr)
    {
        $parentid = $this->pid;
        $a        = [];
        if (!isset($this->arr[$myid])) {
            return false;
        }
        $newarr[] = $this->arr[$myid];
        $pid       = $this->arr[$myid][$parentid];
        if (isset($this->arr[$pid])) {
            $this->get_pos($pid, $newarr);
        }
        if (is_array($newarr)) {
            krsort($newarr);
            foreach ($newarr as $v) {
                $a[$v[$this->myid]] = $v;
            }
        }
        return $a;
    }

    /**
     * 得到树型结构
     *
     * @param int    $myid 表示获得这个ID下的所有子级
     * @param int    $sid  被选中的ID, 比如在做树形下拉框的时候需要用到
     * @param string $adds
     * @param int    $level
     *
     * @return array
     */
    public function getTree($myid = 0, $sid = 0, $adds = '', $level = 0)
    {
        $number = 1;
        $child  = $this->get_child($myid);
        if (is_array($child)) {
            $total = count($child);
            foreach ($child as $a) {
                $id = $a[$this->myid];
                $j  = $k = '';
                if ($number == $total) {
                    $k = $this->icon[5];
                    if ($number == 1) {
                        if ($level != 0) {
                            $j .= $this->icon[2]; // . $this->icon[4];
                        }
                    } else {
                        $j .= $this->icon[2];
                    }
                } else {
                    $j .= $this->icon[1];
                    $k = $this->icon[0];
                }
                $j .=  $this->icon[4];
                if ($this->get_child($a[$this->myid])) {
                    $j  .= $this->icon[3];
                }
                $xx = $adds . $k . $this->icon[5];
                // if ($number == $total && $level == 0 && $number == 1) {
                //     $xx = substr($xx, 0, strlen($xx) - 12);  //两个&nbsp;?
                // }
                $spacer = $adds ? $adds . $j : $j;
                $spacer = str_replace($this->icon[1] . $this->icon[3], $this->icon[1] . $this->icon[4] . $this->icon[3], $spacer);
                $a['__SELECTED__'] = $id == $sid ? 'selected' : '';
                $a['__TREE_STR__']       = $spacer;
                $a['__TREE_LEVEL__']    = $level;
                $this->ret[$id] = $a;
                $level1 = $level + 1;
                $this->getTree($id, $sid, $xx, $level1);
                $number++;
            }
        }
        return $this->ret;
    }

    /**
     * 同上一方法类似,但允许多选
     */
    public function getFullTree()
    {
        $arr = [];
        foreach ($this->arr as $v) {
            $id     = $v[$this->myid];
            $arr[] = $v;
            if (!$this->get_parent($id)) {
                $r = $this->getTree($id);
                if ($r) {
                    $arr = array_merge($arr, $this->getTree($id));
                }
            }
        }
        return $arr;
    }

    public function have($list, $item)
    {
        return (strpos(',,' . $list . ',', ',' . $item . ','));
    }
}
