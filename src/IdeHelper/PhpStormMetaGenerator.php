<?php

declare(strict_types=1);

namespace Think\IdeHelper;

use Think\Container;

/**
 * PhpStorm Meta 文件生成器
 *
 * 为 ThinkPHP Container 生成 PhpStorm 类型推断映射
 *
 * @package Think\IdeHelper
 */
class PhpStormMetaGenerator
{
    /**
     * 服务映射配置
     *
     * @var array
     */
    protected array $services = [];

    /**
     * 构造函数
     *
     * @param array $services 服务映射配置 ['service_name' => 'Fully\Qualified\ClassName']
     */
    public function __construct(array $services = [])
    {
        $this->services = $services;
    }

    /**
     * 生成 .phpstorm.meta.php 文件内容
     *
     * @return string
     */
    public function generate(): string
    {
        $overrides = $this->generateOverrides();

        return <<<PHP
<?php

namespace PHPSTORM_META {

$overrides

}
PHP;
    }

    /**
     * 生成 override 语句
     *
     * @return string
     */
    protected function generateOverrides(): string
    {
        $lines = [];

        // 为 Container::getInstance()->get() 生成映射
        $lines[] = $this->generateOverride(
            '\\Think\\Container::getInstance()->get(0)',
            $this->services
        );

        // 为 Container::getInstance()->make() 生成映射
        $lines[] = $this->generateOverride(
            '\\Think\\Container::getInstance()->make(0)',
            $this->services
        );

        // 为 Container::pull() 生成映射
        $lines[] = $this->generateOverride(
            '\\Think\\Container::pull(0)',
            $this->services
        );

        // 为 Think\Container::get() 生成映射（静态调用）
        $lines[] = $this->generateOverride(
            '\\Think\\Container::get(0)',
            $this->services
        );

        // 为 Think\Container::make() 生成映射（静态调用）
        $lines[] = $this->generateOverride(
            '\\Think\\Container::make(0)',
            $this->services
        );

        return implode("\n\n", $lines);
    }

    /**
     * 生成单个 override 语句
     *
     * @param string $function 函数签名
     * @param array $map 映射数组
     * @return string
     */
    protected function generateOverride(string $function, array $map): string
    {
        if ($map === []) {
            return "// No mappings for {$function}";
        }

        $mapLines = [];
        foreach ($map as $key => $value) {
            $mapLines[] = sprintf("        '%s' => \\%s::class,", $key, $value);
        }

        $mapStr = implode("\n", $mapLines);

        return <<<PHP
    override({$function}, map([
{$mapStr}
    ]));
PHP;
    }

    /**
     * 添加服务映射
     *
     * @param string $serviceName 服务名称
     * @param string $className 类名
     * @return self
     */
    public function addService(string $serviceName, string $className): self
    {
        $this->services[$serviceName] = $className;
        return $this;
    }

    /**
     * 批量添加服务映射
     *
     * @param array $services 服务映射
     * @return self
     */
    public function addServices(array $services): self
    {
        foreach ($services as $serviceName => $className) {
            $this->services[$serviceName] = $className;
        }
        return $this;
    }

    /**
     * 从配置加载服务映射
     *
     * @return self
     */
    public function loadFromConfig(): self
    {
        $services = [];

        // 从 ThinkPHP 配置读取 IDE_HELPER_SERVICES
        if (function_exists('C')) {
            $configServices = C('IDE_HELPER_SERVICES');
            if (is_array($configServices)) {
                $services = $configServices;
            }
        }

        // 添加默认的核心服务映射
        $defaultServices = [
            'request' => \Think\Http\Request::class,
            'response' => \Think\Http\Response::class,
            'session' => \Think\Session\Session::class,
            'cookie' => \Think\Cookie\Cookie::class,
            'view' => \Think\View\View::class,
            'dispatcher' => \Think\Dispatcher::class,
            'router' => \Think\Routing\Router::class,
            'validator' => \Think\Validation\Validator::class,
            'db' => \Think\Db\Db::class,
            'cache' => \Think\Cache\Cache::class,
            'redis' => \Think\Redis\Redis::class,
            'queue' => \Illuminate\Queue\QueueManager::class,
        ];

        // 合并配置和默认值（配置优先）
        $this->services = array_merge($defaultServices, $services);

        return $this;
    }
}
