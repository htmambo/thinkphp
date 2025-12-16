<?php

namespace Home\Controller;

use Think\Controller;

class IndexController extends Controller
{
    public function index()
    {
        $this->pageTitle = 'ThinkPHP 3.2 Demo';
        $this->assign('now', date('Y-m-d H:i:s'));
        $this->assign('thinkVersion', defined('THINK_VERSION') ? THINK_VERSION : '');
        $this->display();
    }
}
