# Patches 日志记录

本文档记录所有应用于本项目的补丁及其历史。记录按时间倒序排列，最新的修复在最上面。

---

## 2025-12-29 14:30 - 修复：调整补丁上下文匹配

### 问题描述

初始版本的补丁从 `composer.json` 文件第 1 行开始匹配，但实际的 `illuminate/support/composer.json` 包含大量元数据字段（name、description、license、require 等），`autoload` 部分不在文件开头。这导致补丁无法正确应用。

同时配置了两个互斥的补丁文件（针对 `src/helpers.php` 和 `helpers.php`），其中必定有一个失败，导致 composer 安装中断。

### 解决方案

1. 从 GitHub 获取 `illuminate/support` 11.x 的实际 `composer.json` 结构
2. 创建单个补丁文件，从第 32 行（`autoload` 部分）开始匹配
3. 配置 `exit-on-patch-failure: false` 允许补丁失败继续
4. 移除 helpers 和 functions 两个文件的自动加载

### 修改内容

**删除的文件**：
- `patches/illuminate-support-disable-helpers-src.patch`
- `patches/illuminate-support-disable-helpers-root.patch`

**新增的文件**：
- `patches/illuminate-support-disable-helpers.patch`（基于 11.x 实际结构）

**composer.json 修改**：
- 添加 `"exit-on-patch-failure": false` 配置
- 将两个补丁配置合并为一个

### 验证方法

```bash
# 检查补丁格式
cat patches/illuminate-support-disable-helpers.patch

# 补丁应该从 autoload 部分开始匹配（@@ -32,9 +32,6 @@）
```

### 相关记录

- 前置版本：[2025-12-29 13:00 - 初始版本](#2025-12-29-1300---初始版本创建-helpers-禁用补丁)

---

## 2025-12-29 13:00 - 初始版本：创建 helpers 禁用补丁

### 问题描述

ThinkPHP 框架定义了全局函数 `E()` 和 `env()`，与 Illuminate Support 的 `e()` 和 `env()` 冲突（PHP 函数名大小写不敏感）。

### 解决方案

使用 `composer-patches` 插件，在安装 `illuminate/support` 时自动打补丁，移除其 helpers 文件的自动加载配置。

### 修改内容

1. **composer.json 修改**：
   - 添加 `"cweagans/composer-patches": "^1.7"` 依赖
   - 配置 `allow-plugins` 允许补丁插件
   - 添加 `patches` 配置

2. **补丁文件**：
   - `patches/illuminate-support-disable-helpers-src.patch` - 针对 `src/helpers.php` 路径
   - `patches/illuminate-support-disable-helpers-root.patch` - 针对 `helpers.php` 路径

3. **文档**：
   - `patches/README.md`（后更名为 `PatchLogs.md`）

### 问题与缺陷

- ❌ 补丁从文件开头匹配，与实际的 `composer.json` 结构不符
- ❌ 两个互斥补丁导致 composer 安装失败
- ❌ 缺少 `exit-on-patch-failure` 配置

### 后续修复

此版本的问题在 [2025-12-29 14:30](#2025-12-29-1430---修复调整补丁上下文匹配) 中已修复。

---

## 补丁文件清单

| 文件名 | 目标包 | 描述 | 创建日期 | 状态 |
|--------|--------|------|----------|------|
| `illuminate-support-disable-helpers.patch` | illuminate/support ^11.0 | 移除 helpers 自动加载，避免与 ThinkPHP 全局函数冲突 | 2025-12-29 | ✅ 有效 |

---

## 问题描述总结

### 根本原因

ThinkPHP 框架定义了以下全局函数：
- `E()` - 抛异常函数（`src/Helper/functions.php:110`）
- `env()` - 读取环境变量（`src/Helper/functions.php:1829`）

Illuminate Support 包也定义了全局函数：
- `e()` - HTML escape
- `env()` - 读取环境变量

由于 PHP 函数名大小写不敏感，`E()` 和 `e()` 被视为同一个函数名，无法同时存在，会导致 "Cannot redeclare" 错误。

### 最终解决方案

通过 `composer-patches` 插件，在安装 `illuminate/support` 时自动应用补丁，移除 `composer.json` 中 `autoload.files` 配置（包括 `functions.php` 和 `helpers.php`），从而避免全局函数冲突。

### 注意事项

1. 禁用 support helpers 后，Illuminate 的全局 helper 函数不可用
2. 如果运行时出现 "Call to undefined function" 错误，需要根据实际情况补充兼容层
3. 本补丁基于 `illuminate/support` 11.x，如果使用其他版本可能需要调整上下文行数
