# ThinkPHP 安全补丁应用指南

本目录包含针对ThinkPHP框架严重安全漏洞的补丁文件。

## 📦 补丁列表

| 补丁文件 | 优先级 | 问题描述 | CVSS评分 |
|---------|--------|---------|----------|
| 0001-fix-eval-code-injection-in-Auth.patch | P0 | Auth.php中的eval()代码注入 | 9.8 (Critical) |
| 0002-fix-extract-variable-pollution.patch | P0 | 多个文件中的extract()变量污染 | 8.1 (High) |
| 0003-fix-host-header-injection.patch | P0 | Dispatcher.php中的Host头注入 | 7.5 (High) |
| 0004-fix-parse-str-variable-pollution.patch | P0 | App.php中的parse_str()变量污染 | 7.3 (High) |

## 🚀 快速应用所有补丁

在ThinkPHP根目录执行：

```bash
# 应用所有P0优先级补丁
git apply patches/0001-fix-eval-code-injection-in-Auth.patch
git apply patches/0002-fix-extract-variable-pollution.patch
git apply patches/0003-fix-host-header-injection.patch
git apply patches/0004-fix-parse-str-variable-pollution.patch
```

或者使用循环一次性应用：

```bash
for patch in patches/*.patch; do
    echo "应用补丁: $patch"
    git apply "$patch" || echo "补丁应用失败: $patch"
done
```

## 📋 逐个应用补丁

### 1. 修复eval()代码注入（最高优先级）

```bash
cd /path/to/thinkphp
git apply patches/0001-fix-eval-code-injection-in-Auth.patch
```

**验证修复：**
```bash
# 检查Auth.php中是否还有eval()
grep -n "eval" src/Auth.php
```

**测试：**
- 测试权限验证功能
- 确保条件规则仍然正常工作
- 尝试注入恶意代码验证是否被阻止

---

### 2. 修复extract()变量污染

```bash
git apply patches/0002-fix-extract-variable-pollution.patch
```

**验证修复：**
```bash
# 检查受影响的文件
grep -n "extract" src/Driver/Upload/Ftp.php
grep -n "extract" src/Driver/Storage/File.php
grep -n "extract" src/Exception/Handle.php
```

**测试：**
- 测试FTP上传功能
- 测试文件存储功能
- 测试异常处理显示

---

### 3. 修复Host头注入

```bash
git apply patches/0003-fix-host-header-injection.patch
```

**配置白名单（推荐）：**

在应用配置文件中添加：

```php
// Application/Common/Conf/config.php
return [
    // 允许的域名白名单
    'ALLOWED_HOSTS' => [
        'yourdomain.com',
        'www.yourdomain.com',
        'api.yourdomain.com',
        'localhost',
        '127.0.0.1',
    ],

    // 其他配置...
];
```

**验证修复：**
```bash
# 检查Dispatcher.php中的validateHost方法
grep -A 20 "validateHost" src/Dispatcher.php
```

**测试：**
- 测试正常域名访问
- 测试子域名部署功能
- 尝试使用恶意Host头验证是否被拒绝

---

### 4. 修复parse_str()变量污染

```bash
git apply patches/0004-fix-parse-str-variable-pollution.patch
```

**验证修复：**
```bash
# 检查App.php中的parse_str使用
grep -n "parse_str" src/App.php
```

**测试：**
- 测试PUT请求处理
- 测试参数绑定功能
- 验证变量不会被意外覆盖

---

## 🔍 补丁应用前检查

在应用补丁前，建议执行以下检查：

```bash
# 1. 检查当前工作目录是否干净
git status

# 2. 创建备份分支
git checkout -b security-patches-backup

# 3. 查看补丁内容（可选）
git apply --stat patches/0001-fix-eval-code-injection-in-Auth.patch

# 4. 测试补丁是否可以应用（不实际应用）
git apply --check patches/0001-fix-eval-code-injection-in-Auth.patch
```

## ⚠️ 补丁冲突处理

如果补丁应用失败，可能是因为：

1. **文件已被修改**
   ```bash
   # 查看冲突详情
   git apply --reject patches/xxx.patch
   # 这会生成.rej文件，显示无法应用的部分
   ```

2. **手动应用补丁**
   - 打开.rej文件查看冲突内容
   - 手动编辑源文件应用更改
   - 参考补丁文件中的修复方案

3. **版本不匹配**
   - 这些补丁基于特定版本创建
   - 如果您的代码版本不同，可能需要手动调整

## 🧪 测试建议

应用补丁后，建议执行以下测试：

### 1. 单元测试
```bash
# 运行现有的单元测试
phpunit tests/
```

### 2. 功能测试

**权限系统测试：**
- 登录不同权限的用户
- 验证权限检查是否正常
- 测试条件规则是否生效

**文件上传测试：**
- 测试本地文件上传
- 测试FTP上传
- 验证文件存储功能

**路由测试：**
- 测试普通路由
- 测试子域名路由
- 测试重定向功能

**请求处理测试：**
- 测试GET请求
- 测试POST请求
- 测试PUT请求
- 测试DELETE请求

### 3. 安全测试

**代码注入测试：**
```bash
# 尝试在权限规则中注入恶意代码
# 应该被阻止或安全处理
```

**Host头注入测试：**
```bash
# 使用curl测试恶意Host头
curl -H "Host: evil.com" http://yoursite.com
# 应该被拒绝或使用默认配置
```

**变量污染测试：**
```bash
# 尝试通过PUT请求污染变量
# 应该被隔离到独立的变量中
```

## 📝 提交补丁

应用并测试补丁后，建议创建Git提交：

```bash
# 查看更改
git diff

# 添加更改
git add src/

# 创建提交
git commit -m "安全修复: 应用P0优先级安全补丁

- 修复Auth.php中的eval()代码注入漏洞 (CVSS 9.8)
- 修复多个文件中的extract()变量污染漏洞 (CVSS 8.1)
- 修复Dispatcher.php中的Host头注入漏洞 (CVSS 7.5)
- 修复App.php中的parse_str()变量污染漏洞 (CVSS 7.3)

参考: SECURITY_AUDIT_REPORT.md"
```

## 🔄 回滚补丁

如果需要回滚补丁：

```bash
# 回滚所有更改
git reset --hard HEAD

# 或者回滚到特定提交
git reset --hard <commit-hash>

# 或者使用git revert
git revert <commit-hash>
```

## 📚 相关文档

- **安全审计报告：** `../SECURITY_AUDIT_REPORT.md`
- **详细修复方案：** `../FIX_PLAN.md`
- **ThinkPHP官方文档：** http://thinkphp.cn

## ⚡ 紧急修复建议

如果您的应用正在生产环境运行，建议：

1. **立即应用补丁1（eval注入）** - 这是最严重的漏洞
2. **配置Host白名单** - 防止Host头注入
3. **应用其他P0补丁**
4. **在测试环境充分测试后再部署到生产**

## 🆘 获取帮助

如果在应用补丁过程中遇到问题：

1. 查看补丁文件中的注释和说明
2. 参考FIX_PLAN.md中的详细修复方案
3. 检查SECURITY_AUDIT_REPORT.md了解问题背景
4. 在测试环境中先进行测试

## 📊 补丁统计

- **总补丁数：** 4个
- **修复的严重漏洞：** 4个
- **受影响文件：** 7个
- **预计应用时间：** 15-30分钟
- **预计测试时间：** 2-4小时

---

**创建日期：** 2025-12-16
**补丁版本：** 1.0.0
**适用版本：** ThinkPHP 3.2.x
**创建者：** Claude Sonnet 4.5
