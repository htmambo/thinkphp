<?php

namespace Think\Tests;

use Think\Driver\Translate\Google;

// 引入类文件 (假设自动加载未配置，手动引入)
require_once __DIR__ . '/../src/Driver/Translate/Google.php';

class GoogleDriverTest
{
    public function run()
    {
        echo "开始 Google 翻译驱动测试...\n";
        echo "--------------------------------------------------\n";

        $this->testTokenGeneration();
        echo "--------------------------------------------------\n";
        $this->testTranslation();

        echo "--------------------------------------------------\n";
        echo "测试结束。\n";
    }

    /**
     * 测试 Token 生成算法 (TL 方法)
     * 由于 TL 是私有方法，我们需要使用反射来访问
     */
    private function testTokenGeneration()
    {
        echo "[测试 1] Token 生成算法验证 (TL)...\n";

        try {
            $google = new Google();
            $reflection = new \ReflectionClass($google);
            $method = $reflection->getMethod('TL');
            $method->setAccessible(true);

            // 测试用例
            $cases = [
                'Hello' => '/^\d+\.\d+$/', // 验证格式
                '你好' => '/^\d+\.\d+$/',
            ];

            foreach ($cases as $text => $pattern) {
                $token = $method->invoke($google, $text);
                echo "  输入: \"$text\"\n";
                echo "  Token: $token\n";

                if (preg_match($pattern, $token)) {
                    echo "  结果: \033[32m通过\033[0m (格式正确)\n";
                } else {
                    echo "  结果: \033[31m失败\033[0m (格式错误)\n";
                }
            }
        } catch (\Exception $e) {
            echo "  异常: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 测试实际翻译功能 (translate 方法)
     * 这会发起真实的网络请求
     */
    private function testTranslation()
    {
        echo "[测试 2] 实际翻译接口测试 (translate)...\n";
        echo "  注意: 此测试依赖网络连接及 TKK 有效性。\n";

        $google = new Google();
        $text = 'Hello World';

        echo "  原文: $text\n";
        echo "  正在请求 Google API...\n";

        $result = $google->translate($text);

        if ($result === false) {
            echo "  结果: \033[31m失败\033[0m\n";
            echo "  错误信息: " . $google->getError() . "\n";
        } else {
            echo "  译文: $result\n";
            // 简单验证译文是否包含中文 (默认转 zh-CN -> en，等等，默认构造是 zh-CN -> en)
            // 让我们检查一下默认配置。
            // Google 类定义: private $from = 'zh-CN'; private $to = 'en';
            // 所以 'Hello World' (en) -> 'Hello World' (en) ? 
            // 还是说输入应该是 zh-CN?
            // 让我们尝试翻译中文到英文

            echo "\n  尝试中文到英文翻译:\n";
            $cnText = '你好世界';
            echo "  原文: $cnText\n";
            $resultCn = $google->translate($cnText);

            if ($resultCn) {
                echo "  译文: $resultCn\n";
                if (stripos($resultCn, 'Hello') !== false) {
                    echo "  结果: \033[32m通过\033[0m\n";
                } else {
                    echo "  结果: \033[33m警告\033[0m (返回了结果但未匹配预期关键词，可能是翻译差异)\n";
                }
            } else {
                echo "  结果: \033[31m失败\033[0m\n";
                echo "  错误信息: " . $google->getError() . "\n";
            }
        }
    }
}

// 运行测试
$test = new GoogleDriverTest();
$test->run();
