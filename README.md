# ThinkPHP 3.2.x 现代化维护版

本项目是在经典的 ThinkPHP 3.2.x 核心之上持续维护的社区版本，面向已经投入生产的存量应用，帮助它们顺利升级到 PHP 7.4 及以上环境，同时引入现代化的生态组件与更规范的代码组织方式。

## 环境要求

- PHP \>= 7.4
- PDO、JSON、Mbstring 等常用扩展
- Composer (用于安装与依赖管理)

## 核心特性

- **保持 3.2.x 的开发体验**：入口、配置、MVC 模型、行为扩展等机制与官方 3.2.x 版本一致，老项目无需大幅重构即可迁移。
- **现代化依赖整合**：内置 Monolog 2 进行日志记录，提供 GeoIP2、nikic/php-parser 等工具，更易对接当下常用能力。
- **完善的组件体系**：
  - `Driver/` 目录下提供缓存、数据库、Session、存储、消息、文件上传、图片处理等驱动抽象。
  - `Form/` 模块封装常见表单字段与动态组件，便于后台快速搭建。
  - `Helper/`、`Translate/`、`Verify/` 等工具类覆盖数据校验、国际化、分页、验证码等业务常见场景。
- **多模板引擎支持**：保留原有模板引擎，额外引入 Blade 移植版，满足更现代的视图开发需求。
- **命令行与队列**：`Console/` 目录提供命令行运行时、队列处理等能力，可基于 `think` 脚本扩展自定义指令。
- **数据库迁移**：集成 Phinx，配合 `Migration/` 模块实现数据库迁移脚本的生成与执行。

## 目录速览

- `src/ThinkPHP.php`：框架入口文件，负责初始化常量并启动核心。
- `src/Think.php`、`src/App.php`、`src/Dispatcher.php`、`src/Route.php`：请求调度、路由解析与应用生命周期管理的核心代码。
- `src/Container.php`、`src/Hook.php`、`src/Behavior/`：依赖注入容器与事件/行为扩展机制。
- `src/Controller/`、`src/Model/`、`src/View.php`：控制器、模型与视图基类。
- `src/Console/`、`think`：命令行内核与入口脚本。
- `src/Migration/`、`Phinx/`：数据库迁移相关逻辑及第三方依赖。
- `Readability/`：内嵌的 HTML Readability 解析库，便于内容清洗提取。

## 快速上手

1. 通过 Composer 安装：

   ```bash
   composer require hoping/thinkphp
   ```

2. 在入口文件中引导框架（示例为 `public/index.php`）：

   ```php
   <?php
   require __DIR__ . '/../vendor/autoload.php';

   define('APP_PATH', __DIR__ . '/../Application/');

   require __DIR__ . '/../vendor/hoping/thinkphp/src/ThinkPHP.php';
   ```

3. 使用命令行工具：

   ```bash
   php vendor/hoping/thinkphp/src/think list
   ```

   可查看已有命令，或在 `Console/Command` 目录下扩展自定义指令。

## 适用场景

- 已有 ThinkPHP 3.2.x 项目，希望在不重写业务的前提下升级到更高版本的 PHP 运行环境。
- 需要与 Monolog、Phinx、Blade 等现代生态结合的传统项目。
- 希望继续利用 3.2.x 的路由、模型、行为体系，并获得持续修复与维护支持的团队。

## 许可证

项目以 [MIT License](./LICENSE) 开源，欢迎在遵循协议的前提下自由使用与贡献。
