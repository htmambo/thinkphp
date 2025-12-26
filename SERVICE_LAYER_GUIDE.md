# ThinkPHP 服务层引入指南

## 目录
- [1. 概述](#1-概述)
- [2. 当前架构分析](#2-当前架构分析)
- [3. 服务层设计方案](#3-服务层设计方案)
- [4. 实施步骤](#4-实施步骤)
- [5. 代码示例](#5-代码示例)
- [6. 最佳实践](#6-最佳实践)
- [7. 迁移检查清单](#7-迁移检查清单)

---

## 1. 概述

### 1.1 为什么需要服务层

在当前的 ThinkPHP 架构中，业务逻辑主要分布在**控制器**和**模型**中，随着业务复杂度增加，会面临以下问题：

- **控制器过重**：控制器承担了过多业务逻辑，违反单一职责原则
- **模型臃肿**：模型层混杂了数据访问和业务逻辑，难以维护
- **代码复用困难**：跨控制器的业务逻辑难以复用
- **测试困难**：业务逻辑与框架耦合严重，单元测试困难
- **事务管理复杂**：跨模型的事务管理复杂且容易出错

### 1.2 服务层的价值

引入服务层可以带来以下好处：

- **职责分离**：业务逻辑独立于控制器和模型
- **代码复用**：服务可在多个控制器中复用
- **易于测试**：业务逻辑与框架解耦，便于单元测试
- **统一事务**：服务层统一管理业务事务边界
- **清晰架构**：形成 控制器 → 服务 → 仓储 → 数据库 的清晰分层

---

## 2. 当前架构分析

### 2.1 现有代��结构

```
/src/
├── Controller.php       # 控制器基类
├── Model.php           # 模型基类
├── Db.php              # 数据库中间层
├── Container.php       # 依赖注入容器
├── App.php             # 应用程序类
├── Console/            # 命令行工具
│   └── Command/Queue/
│       ├── Service.php           # 服务基类（已存在）
│       ├── QueueService.php      # 队列服务
│       └── ProcessService.php    # 进程服务
├── Behavior/           # 行为扩展
├── Blade/              # 模板引擎
├── Driver/             # 驱动层
└── ...
```

### 2.2 现有优势

- ✅ **依赖注入容器**：`Container.php` 提供了 DI 容器支持
- ✅ **服务基类**：已有 `Service.php` 基础实现
- ✅ **清晰的分层**：MVC 分层明确
- ✅ **驱动模式**：良好的扩展性基础

### 2.3 待改进点

- ❌ **缺乏统一服务层规范**：没有完整的服务层架构
- ❌ **业务逻辑分散**：控制器和模型职责不清晰
- ❌ **缺乏服务注册机制**：服务手动管理，没有自动发现
- ❌ **缺乏接口抽象**：服务实现与接口未分离

---

## 3. 服务层设计方案

### 3.1 整体架构

```
┌─────────────────────────────────────────────────┐
│                   控制器层                        │
│         (Controller - 请求处理、响应返回)          │
└─────────────────┬───────────────────────────────┘
                  │ 调用
                  ↓
┌─────────────────────────────────────────────────┐
│                   服务层                         │
│      (Service - 业务逻辑、事务协调、业务规则)       │
│  ┌────────────┐  ┌────────────┐  ┌───────────┐  │
│  │ UserService│  │OrderService│  │...其他服务  │  │
│  └────────────┘  └────────────┘  └───────────┘  │
└─────────────────┬───────────────────────────────┘
                  │ 调用
                  ↓
┌─────────────────────────────────────────────────┐
│                  仓储层（可选）                    │
│      (Repository - 数据访问抽象、查询封装)          │
│  ┌────────────┐  ┌────────────┐  ┌───────────┐  │
│  │UserRepository│ │OrderRepository│ │...其他仓储│ │
│  └────────────┘  └────────────┘  └───────────┘  │
└─────────────────┬───────────────────────────────┘
                  │ 调用
                  ↓
┌─────────────────────────────────────────────────┐
│                   模型层                         │
│         (Model - ORM、数据验证、关联关系)          │
└─────────────────┬───────────────────────────────┘
                  │ 操作
                  ↓
┌─────────────────────────────────────────────────┐
│                   数据库                         │
│              (Database - 数据持久化)              │
└─────────────────────────────────────────────────┘
```

### 3.2 目录结构设计

```
/src/
├── Service/                    # 服务层（新增）
│   ├── Service.php            # 服务抽象基类
│   ├── ServiceInterface.php   # 服务接口
│   ├── ServiceManager.php     # 服务管理器
│   ├── UserService.php        # 用户服务示例
│   ├── OrderService.php       # 订单服务示例
│   └── ...                    # 其他业务服务
│
├── Service/Contract/          # 服务接口（新增）
│   ├── UserServiceInterface.php
│   ├── OrderServiceInterface.php
│   └── ...
│
├── Repository/                # 仓储层（可选，新增）
│   ├── RepositoryInterface.php
│   ├── UserRepository.php
│   └── ...
│
├── Controller.php             # 控制器基类（保持）
├── Model.php                  # 模型基类（保持）
└── ...
```

### 3.3 核心组件设计

#### 3.3.1 服务抽象基类

提供服务的通用功能：

```php
abstract class Service
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    // 开启事务
    protected function beginTransaction(): void;

    // 提交事务
    protected function commit(): void;

    // 回滚事务
    protected function rollback(): void;

    // 获取其他服务
    protected function getService(string $name): ?Service;
}
```

#### 3.3.2 服务接口

定义服务契约：

```php
interface UserServiceInterface extends ServiceInterface
{
    public function register(array $data): User;

    public function login(string $email, string $password): bool;

    public function updateProfile(int $userId, array $data): bool;

    public function deleteUser(int $userId): bool;
}
```

#### 3.3.3 服务管理器

负责服务的注册、获取和生命周期管理：

```php
class ServiceManager
{
    protected Container $container;
    protected array $services = [];

    public function register(string $name, string $concrete): void;

    public function get(string $name): ?Service;

    public function has(string $name): bool;

    public function alias(string $name, string $alias): void;
}
```

---

## 4. 实施步骤

### 阶段一：基础设施建设（第1-2周）

#### Step 1: 创建服务层基础类

1. **创建服务接口**
   - 文件：`/src/Service/ServiceInterface.php`
   - 定义服务的基本契约

2. **创建服务抽象基类**
   - 文件：`/src/Service/Service.php`
   - 提供事务、容器访问等通用功能

3. **创建服务管理器**
   - 文件：`/src/Service/ServiceManager.php`
   - 实现服务注册和获取机制

4. **集成到容器**
   - 修改 `Container.php`，集成服务管理器
   - 提供门面类 `Service` 便于访问

#### Step 2: 编写单元测试

为服务层基础组件编写测试，确保功能正确。

### 阶段二：试点服务开发（第3-4周）

#### Step 3: 选择试点模块

选择一个业务复杂度适中的模块作为试点，建议：
- 用户模块（UserService）
- 订单模块（OrderService）
- 商品模块（ProductService）

#### Step 4: 开发试点服务

1. **定义服务接口**
   ```php
   /src/Service/Contract/UserServiceInterface.php
   ```

2. **实现服务类**
   ```php
   /src/Service/UserService.php
   ```

3. **编写单元测试**
   ```php
   /tests/Service/UserServiceTest.php
   ```

4. **在控制器中使用**
   - 将原有业务逻辑迁移到服务层
   - 控制器通过服务调用业务逻辑

#### Step 5: 评估和优化

评估试点效果，优化服务层设计。

### 阶段三：全面推广（第5-8周）

#### Step 6: 制定服务开发规范

- 服务命名规范
- 接口设计规范
- 异常处理规范
- 事务管理规范

#### Step 7: 逐步迁移现有业务

1. 识别复杂业务逻辑
2. 按优先级迁移到服务层
3. 编写单元测试
4. 更新控制器代码

#### Step 8: 引入仓储层（可选）

如果数据访问逻辑复杂，可引入仓储层：
1. 定义仓储接口
2. 实现具体仓储
3. 服务层通过仓储访问数据

### 阶段四：持续优化（长期）

#### Step 9: 性能优化

- 服务懒加载
- 缓存策略
- 连接池优化

#### Step 10: 监控和日志

- 服务调用监控
- 性能日志
- 错误追踪

---

## 5. 代码示例

### 5.1 服务接口定义

```php
<?php
// src/Service/Contract/UserServiceInterface.php

namespace Service\Contract;

use Service\ServiceInterface;
use Model\User;

interface UserServiceInterface extends ServiceInterface
{
    /**
     * 用户注册
     *
     * @param array $data 用户数据 [email, password, name]
     * @return User
     * @throws \Exception
     */
    public function register(array $data): User;

    /**
     * 用户登录
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return array 返回用户信息和token
     * @throws \Exception
     */
    public function login(string $email, string $password): array;

    /**
     * 更新用户资料
     *
     * @param int $userId 用户ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateProfile(int $userId, array $data): bool;

    /**
     * 删除用户
     *
     * @param int $userId 用户ID
     * @return bool
     */
    public function deleteUser(int $userId): bool;
}
```

### 5.2 服务实现

```php
<?php
// src/Service/UserService.php

namespace Service;

use Service\Contract\UserServiceInterface;
use Service\Service;
use Model\User as UserModel;
use Exception\BadRequestException;
use Exception\NotFoundException;

class UserService extends Service implements UserServiceInterface
{
    /**
     * @var UserModel 用户模型
     */
    protected UserModel $userModel;

    /**
     * 构造函数
     */
    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * 用户注册
     */
    public function register(array $data): UserModel
    {
        // 1. 数据验证
        $this->validateRegisterData($data);

        // 2. 开启事务
        $this->beginTransaction();

        try {
            // 3. 检查邮箱是否已存在
            if ($this->userModel->where('email', $data['email'])->find()) {
                throw new BadRequestException('邮箱已被注册');
            }

            // 4. 创建用户
            $user = $this->userModel->create([
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'name' => $data['name'] ?? '',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 5. 创建用户资料（示例：调用其他服务）
            // $this->getService('Profile')->createProfile($user->id);

            // 6. 提交事务
            $this->commit();

            return $user;

        } catch (\Exception $e) {
            // 7. 回滚事务
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 用户登录
     */
    public function login(string $email, string $password): array
    {
        // 1. 查找用户
        $user = $this->userModel
            ->where('email', $email)
            ->find();

        if (!$user) {
            throw new NotFoundException('用户不存在');
        }

        // 2. 验证密码
        if (!password_verify($password, $user->password)) {
            throw new BadRequestException('密码错误');
        }

        // 3. 检查状态
        if ($user->status != 1) {
            throw new BadRequestException('账号已被禁用');
        }

        // 4. 生成token
        $token = $this->generateToken($user);

        // 5. 更新登录时间
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        return [
            'user' => $user->toArray(),
            'token' => $token,
        ];
    }

    /**
     * 更新用户资料
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            throw new NotFoundException('用户不存在');
        }

        // 只更新允许的字段
        $allowedFields = ['name', 'phone', 'avatar'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            throw new BadRequestException('没有可更新的字段');
        }

        return $user->save($updateData);
    }

    /**
     * 删除用户
     */
    public function deleteUser(int $userId): bool
    {
        $this->beginTransaction();

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                throw new NotFoundException('用户不存在');
            }

            // 软删除
            $user->delete();

            // 删除关联数据
            // $this->getService('Profile')->deleteByUserId($userId);

            $this->commit();
            return true;

        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 验证注册数据
     */
    protected function validateRegisterData(array $data): void
    {
        if (empty($data['email'])) {
            throw new BadRequestException('邮箱不能为空');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('邮箱格式不正确');
        }

        if (empty($data['password'])) {
            throw new BadRequestException('密码不能为空');
        }

        if (strlen($data['password']) < 6) {
            throw new BadRequestException('密码长度不能少于6位');
        }
    }

    /**
     * 生成token
     */
    protected function generateToken(UserModel $user): string
    {
        return md5($user->id . $user->email . time());
    }
}
```

### 5.3 控制器中使用服务

```php
<?php
// app/user/controller/UserController.php

namespace user\controller;

use Controller;
use Service\Contract\UserServiceInterface;
use Exception\BadRequestException;

class UserController extends Controller
{
    /**
     * @var UserServiceInterface
     */
    protected UserServiceInterface $userService;

    /**
     * 构造函数 - 依赖注入
     */
    public function __construct()
    {
        parent::__construct();
        $this->userService = service('User'); // 通过门面获取服务
    }

    /**
     * 用户注册
     *
     * @return \Think\Response
     */
    public function register()
    {
        try {
            // 1. 获取请求数据
            $data = $this->request->only(['email', 'password', 'name']);

            // 2. 调用服务层处理业务逻辑
            $user = $this->userService->register($data);

            // 3. 返回响应
            return $this->success('注册成功', $user->toArray());

        } catch (BadRequestException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            return $this->error('系统错误，请稍后重试');
        }
    }

    /**
     * 用户登录
     *
     * @return \Think\Response
     */
    public function login()
    {
        try {
            $email = $this->request->post('email');
            $password = $this->request->post('password');

            $result = $this->userService->login($email, $password);

            return $this->success('登录成功', $result);

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新资料
     *
     * @return \Think\Response
     */
    public function updateProfile()
    {
        try {
            $userId = $this->request->post('user_id');
            $data = $this->request->only(['name', 'phone', 'avatar']);

            $this->userService->updateProfile($userId, $data);

            return $this->success('更新成功');

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
```

### 5.4 服务管理器实现

```php
<?php
// src/Service/ServiceManager.php

namespace Service;

use Closure;
use Exception;
use Container;

class ServiceManager
{
    /**
     * 容器实例
     */
    protected Container $container;

    /**
     * 服务绑定
     * @var array
     */
    protected array $bindings = [];

    /**
     * 服务实例
     * @var array
     */
    protected array $instances = [];

    /**
     * 服务别名
     * @var array
     */
    protected array $aliases = [];

    /**
     * 构造函数
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 注册服务
     *
     * @param string $name 服务名称
     * @param string|Closure $concrete 服务实现类或工厂函数
     * @param bool $shared 是否共享实例
     * @return void
     */
    public function register(string $name, $concrete, bool $shared = true): void
    {
        $this->bindings[$name] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * 获取服务实例
     *
     * @param string $name 服务名称
     * @return Service|null
     * @throws Exception
     */
    public function get(string $name): ?Service
    {
        // 解析别名
        $name = $this->aliases[$name] ?? $name;

        // 如果已存在共享实例，直接返回
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        // 如果服务未注册，尝试自动解析
        if (!isset($this->bindings[$name])) {
            return $this->build($name);
        }

        $concrete = $this->bindings[$name]['concrete'];
        $shared = $this->bindings[$name]['shared'];

        // 构建服务实例
        if ($concrete instanceof Closure) {
            $instance = $concrete($this->container);
        } else {
            $instance = $this->build($concrete);
        }

        // 如果是共享服务，缓存实例
        if ($shared) {
            $this->instances[$name] = $instance;
        }

        return $instance;
    }

    /**
     * 构建服务实例
     *
     * @param string $class 类名
     * @return Service
     * @throws Exception
     */
    protected function build(string $class): Service
    {
        if (!class_exists($class)) {
            throw new Exception("Service class {$class} not found");
        }

        // 通过容器创建实例，支持依赖注入
        return $this->container->make($class);
    }

    /**
     * 检查服务是否已注册
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->bindings[$name]) || isset($this->aliases[$name]);
    }

    /**
     * 设置服务别名
     *
     * @param string $name 服务名称
     * @param string $alias 别名
     * @return void
     */
    public function alias(string $name, string $alias): void
    {
        $this->aliases[$alias] = $name;
    }

    /**
     * 注册单例服务
     *
     * @param string $name
     * @param string|Closure $concrete
     * @return void
     */
    public function singleton(string $name, $concrete): void
    {
        $this->register($name, $concrete, true);
    }

    /**
     * 清除所有实例
     *
     * @return void
     */
    public function flush(): void
    {
        $this->instances = [];
    }
}
```

### 5.5 服务门面

```php
<?php
// src/Service.php

namespace Service;

use Closure;

/**
 * 服务门面 - 提供全局访问入口
 */
class ServiceFacade
{
    /**
     * 服务管理器实例
     */
    protected static ?ServiceManager $manager = null;

    /**
     * 初始化服务管理器
     *
     * @param ServiceManager $manager
     * @return void
     */
    public static function setManager(ServiceManager $manager): void
    {
        static::$manager = $manager;
    }

    /**
     * 获取服务管理器
     *
     * @return ServiceManager
     */
    public static function getManager(): ServiceManager
    {
        if (static::$manager === null) {
            static::$manager = Container::getInstance()->make(ServiceManager::class);
        }
        return static::$manager;
    }

    /**
     * 获取服务实例
     *
     * @param string $name 服务名称
     * @return Service|null
     */
    public static function get(string $name): ?Service
    {
        return static::getManager()->get($name);
    }

    /**
     * 静态调用 - 支持动态方法
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $service = static::get($name);

        if ($service && method_exists($service, $arguments[0] ?? '')) {
            $method = array_shift($arguments);
            return $service->$method(...$arguments);
        }

        return $service;
    }
}

/**
 * 全局辅助函数
 *
 * @param string|null $name
 * @return ServiceManager|Service|null
 */
if (!function_exists('service')) {
    function service(?string $name = null)
    {
        $manager = ServiceFacade::getManager();

        if ($name === null) {
            return $manager;
        }

        return $manager->get($name);
    }
}
```

---

## 6. 最佳实践

### 6.1 服务设计原则

#### 1. 单一职责原则
每个服务只负责一个业务领域，服务内的方法职责明确。

```php
// ✅ 好的做法
UserService: 用户相关业务
OrderService: 订单相关业务
PaymentService: 支付相关业务

// ❌ 不好的做法
UserOrderService: 同时处理用户和订单业务
```

#### 2. 接口隔离原则
为每个服务定义接口，明确服务契约。

```php
// ✅ 好的做法
interface UserServiceInterface {
    public function register(array $data): User;
    public function login(string $email, string $password): array;
}

// ❌ 不好的做法
class UserService {
    // 没有接口，直接实现
}
```

#### 3. 依赖倒置原则
服务依赖接口而非具体实现。

```php
// ✅ 好的做法
class OrderService extends Service {
    protected UserServiceInterface $userService;
}

// ❌ 不好的做法
class OrderService extends Service {
    protected UserService $userService; // 依赖具体类
}
```

#### 4. 开闭原则
对扩展开放，对修改关闭。通过继承和接口扩展功能。

### 6.2 命名规范

#### 服务命名
- 服务类：`{模块}Service`，如 `UserService`、`OrderService`
- 服务接口：`{模块}ServiceInterface`，如 `UserServiceInterface`
- 服务目录：`/src/Service/`

#### 方法命名
- 使用动词开头，语义清晰
- 常用动词：`create`, `update`, `delete`, `get`, `find`, `verify`, `process`

```php
// ✅ 好的方法命名
public function createUser(array $data): User;
public function verifyPassword(string $password): bool;
public function processOrder(int $orderId): bool;

// ❌ 不好的方法命名
public function user(array $data): User;
public function check(string $password): bool;
public function do(int $orderId): bool;
```

### 6.3 异常处理

#### 定义业务异常
```php
// src/Exception/BusinessException.php
class BusinessException extends \Exception {}

// src/Exception/NotFoundException.php
class NotFoundException extends BusinessException {}

// src/Exception/BadRequestException.php
class BadRequestException extends BusinessException {}
```

#### 服务中抛出异常
```php
public function deleteUser(int $userId): bool
{
    $user = $this->userModel->find($userId);

    if (!$user) {
        throw new NotFoundException('用户不存在');
    }

    if ($user->id === 1) {
        throw new BadRequestException('不能删除超级管理员');
    }

    return $user->delete();
}
```

#### 控制器中捕获异常
```php
public function delete()
{
    try {
        $this->userService->deleteUser($userId);
        return $this->success('删除成功');
    } catch (NotFoundException $e) {
        return $this->error($e->getMessage(), 404);
    } catch (BadRequestException $e) {
        return $this->error($e->getMessage(), 400);
    } catch (\Exception $e) {
        // 记录日志
        Log::error($e->getMessage());
        return $this->error('系统错误', 500);
    }
}
```

### 6.4 事务管理

#### 在服务层管理事务
```php
public function transfer(int $fromUserId, int $toUserId, float $amount): bool
{
    $this->beginTransaction();

    try {
        // 扣除转出账户余额
        $this->userService->decreaseBalance($fromUserId, $amount);

        // 增加转入账户余额
        $this->userService->increaseBalance($toUserId, $amount);

        // 记录交易日志
        $this->transactionService->log($fromUserId, $toUserId, $amount);

        $this->commit();
        return true;

    } catch (\Exception $e) {
        $this->rollback();
        throw $e;
    }
}
```

#### 事务注意事项
- 事务边界在服务层，不在控制器或模型层
- 避免在事务中调用外部服务（如第三方API）
- 事务范围尽可能小，减少锁定时间

### 6.5 依赖注入

#### 构造函数注入
```php
class OrderService extends Service
{
    protected UserServiceInterface $userService;
    protected ProductRepository $productRepository;

    public function __construct(
        UserServiceInterface $userService,
        ProductRepository $productRepository
    ) {
        $this->userService = $userService;
        $this->productRepository = $productRepository;
    }
}
```

#### 容器自动解析
```php
// 在容器中注册服务绑定
$container->bind(UserServiceInterface::class, UserService::class);
$container->bind(ProductRepository::class, ProductRepository::class);

// 容器会自动注入依赖
$orderService = $container->make(OrderService::class);
```

### 6.6 服务间调用

#### 通过容器获取其他服务
```php
public function createOrder(int $userId, array $items)
{
    // 获取用户服务
    $userService = $this->getService('User');

    // 验证用户
    $user = $userService->getUser($userId);

    // 获取商品服务
    $productService = $this->getService('Product');

    // 验证商品
    foreach ($items as $item) {
        $productService->checkStock($item['product_id'], $item['quantity']);
    }

    // 创建订单...
}
```

#### 避免循环依赖
```php
// ❌ 循环依赖
UserService -> OrderService -> UserService

// ✅ 解决方案：引入事件或中间服务
UserService -> EventDispatcher -> OrderService
OrderService -> EventDispatcher -> UserService
```

### 6.7 数据验证

#### 在服务层验证数据
```php
public function register(array $data): User
{
    // 业务规则验证
    $validator = $this->createValidator($data, [
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'name' => 'required|max:50',
    ]);

    if ($validator->fails()) {
        throw new BadRequestException($validator->errors()->first());
    }

    // 业务逻辑...
}
```

#### 使用验证器类
```php
// src/Service/Validator/UserValidator.php
class UserValidator
{
    public function validateRegister(array $data): void
    {
        // 验证逻辑
    }
}

// 在服务中使用
public function register(array $data): User
{
    $this->userValidator->validateRegister($data);
    // 业务逻辑...
}
```

### 6.8 缓存策略

#### 在服务层使用缓存
```php
public function getUser(int $userId): ?User
{
    $cacheKey = "user:{$userId}";

    // 从缓存获取
    $user = Cache::get($cacheKey);
    if ($user !== null) {
        return $user;
    }

    // 从数据库获取
    $user = $this->userModel->find($userId);

    // 写入缓存
    if ($user) {
        Cache::set($cacheKey, $user, 3600);
    }

    return $user;
}
```

#### 缓存失效
```php
public function updateUser(int $userId, array $data): bool
{
    $result = $this->userModel->where('id', $userId)->update($data);

    if ($result) {
        // 删除缓存
        Cache::delete("user:{$userId}");
    }

    return $result;
}
```

### 6.9 单元测试

#### 测试服务类
```php
// tests/Service/UserServiceTest.php
class UserServiceTest extends TestCase
{
    protected UserService $userService;
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = $this->mockUserModel();
        $this->userService = new UserService($this->userModel);
    }

    public function testRegisterSuccess()
    {
        // Arrange
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'name' => 'Test User',
        ];

        $this->userModel->expects($this->once())
            ->method('create')
            ->willReturn($this->createMockUser());

        // Act
        $user = $this->userService->register($data);

        // Assert
        $this->assertInstanceOf(UserModel::class, $user);
    }

    public function testRegisterWithInvalidEmail()
    {
        // Arrange
        $data = [
            'email' => 'invalid-email',
            'password' => 'password123',
        ];

        // Assert
        $this->expectException(BadRequestException::class);

        // Act
        $this->userService->register($data);
    }
}
```

---

## 7. 迁移检查清单

### 准备阶段

- [ ] **阅读本指南**：理解服务层的设计理念和实施方案
- [ ] **团队培训**：确保团队成员理解服务层模式
- [ ] **选择试点模块**：选择合适的模块进行试点
- [ ] **制定开发规范**：约定服务命名、接口设计等规范

### 基础设施建设

- [ ] **创建 ServiceInterface 接口**
- [ ] **创建 Service 抽象基类**
- [ ] **创建 ServiceManager 服务管理器**
- [ ] **创建 ServiceFacade 服务门面**
- [ ] **集成到 Container**
- [ ] **编写单元测试**

### 试点开发

- [ ] **定义试点服务接口**
- [ ] **实现试点服务类**
- [ ] **编写服务单元测试**
- [ ] **更新控制器代码**
- [ ] **集成测试**
- [ ] **性能测试**
- [ ] **代码审查**

### 全面推广

- [ ] **识别所有需要迁移的业务逻辑**
- [ ] **按优先级排序迁移计划**
- [ ] **逐个迁移业务模块**
- [ ] **编写单元测试覆盖**
- [ ] **更新文档**
- [ ] **团队code review**

### 优化阶段

- [ ] **性能监控**
- [ ] **服务懒加载优化**
- [ ] **缓存策略优化**
- [ ] **日志和监控完善**
- [ ] **文档持续更新**

### 检查点

每个服务开发完成后，确保：

- [ ] 服务类继承自 `Service` 基类
- [ ] 实现了对应的服务接口
- [ ] 业务逻辑与数据访问分离
- [ ] 事务在服务层管理
- [ ] 异常处理完善
- [ ] 编写了单元测试
- [ ] 测试覆盖率 > 80%
- [ ] 通过了代码审查
- [ ] 更新了相关文档

---

## 8. 常见问题 FAQ

### Q1: 服务层和模型的区别是什么？

**A:**
- **模型（Model）**：专注于数据访问、ORM映射、数据验证
- **服务（Service）**：专注于业务逻辑、业务规则、事务协调

```
Model:  save(), find(), where(), delete()
Service: register(), login(), createOrder(), processPayment()
```

### Q2: 是否所有业务逻辑都要放在服务层？

**A:**
不是。根据复杂度决定：
- ✅ **简单CRUD**：可以直接在控制器中调用模型
- ✅ **复杂业务**：必须放在服务层
- ✅ **跨模型操作**：必须放在服务层
- ✅ **需要事务**：必须放在服务层

### Q3: 是否需要引入仓储层（Repository）？

**A:**
取决于项目规模：
- **中小型项目**：不强制，模型层足够
- **大型项目**：建议引入，便于数据访问抽象和测试

### Q4: 服务之间如何通信？

**A:**
通过依赖注入或服务门面：
```php
// 方式1：构造函数注入（推荐）
public function __construct(UserService $userService) {
    $this->userService = $userService;
}

// 方式2：通过服务门面
$userService = $this->getService('User');
```

### Q5: 如何避免服务类过于臃肿？

**A:**
- 遵循单一职责原则
- 拆分服务类，一个服务只负责一个领域
- 提取通用逻辑到辅助类
- 使用策略模式处理复杂业务

### Q6: 服务层是否影响性能？

**A:**
影响很小，优势更大：
- 增加了一层调用，但增加的开销可忽略不计
- 提升了代码可维护性和可测试性
- 便于实施缓存、监控等优化策略
- 支持服务懒加载

---

## 9. 参考资源

### 设计模式
- **服务层模式**（Martin Fowler）
- **依赖注入模式**（Inversion of Control）
- **仓储模式**（Repository Pattern）

### 框架参考
- **Laravel Service Layer**：https://laravel.com/docs/providers
- **Symfony Service Container**：https://symfony.com/doc/current/service_container.html

### 测试工具
- **PHPUnit**：https://phpunit.de/
- **Mockery**：https://github.com/mockery/mockery

---

## 10. 总结

引入服务层是 ThinkPHP 项目架构升级的重要一步，它能带来：

1. **更清晰的架构**：控制器 → 服务 → 模型的清晰分层
2. **更好的可维护性**：业务逻辑集中管理
3. **更强的可测试性**：业务逻辑独立于框架
4. **更高的复用性**：服务可在多处复用
5. **更易扩展**：符合 SOLID 原则

通过遵循本指南的实施步骤和最佳实践，您可以逐步将项目迁移到服务层架构，提升代码质量和团队开发效率。

---

**文档版本**：v1.0
**最后更新**：2025-12-26
**维护者**：ThinkPHP 开发团队
