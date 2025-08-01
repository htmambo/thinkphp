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

namespace Think\Console\Command\Tools;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Output;
use Think\Exception\InvalidArgumentException;
use Think\IpLocation;

class GetIpLocation extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('tools:getiplocation');
        // 设置参数
        $this->addArgument('ip', Input\Argument::OPTIONAL, '要查询的IP', '58.213.197.202')
             ->addArgument('type', Input\Argument::OPTIONAL, '要使用的驱动，支持ipip,geoip,qqwry', 'ipip')
             ->setDescription('显示指定IP的归属地');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $ip = $input->getArgument('ip');
        $type = $input->getArgument('type');
        dump(IpLocation::find($ip, $type));
    }
}
