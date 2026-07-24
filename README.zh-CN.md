# Raise Import — Import for Filament

> 中文文档 · English version: [README.md](README.md)

> Raise Import — Import for Filament：最简单、最完整的 CSV/Excel 导入插件。

**它解决**的是在 Filament 后台里接入可投产的 CSV/XLSX/ODS 导入功能这一痛点——上传、自动列映射、逐行校验、重复数据处理和预览向导，全部开箱即用，无需你自己搭建这些底层管线。**如果手写等效功能**，你得自己接 Livewire 上传组件、OpenSpout 解析、模糊表头匹配、Laravel 校验、批量插入事务和错误报告，通常要 200+ 行代码分散在多个文件里。**而用 Raise Import 只需一行**（`ImportAction::make()->model(User::class)`），把原本半天的脚手架工作压缩成一分钟的配置。

[![Latest Version](https://img.shields.io/packagist/v/raise-studio/import.svg)](https://packagist.org/packages/raise-studio/import)
[![Total Downloads](https://img.shields.io/packagist/dt/raise-studio/import.svg)](https://packagist.org/packages/raise-studio/import)
[![License](https://img.shields.io/packagist/l/raise-studio/import.svg)](https://github.com/RaiseStudio/import/blob/main/LICENSE)

## 功能特性

### 社区版（免费）

| | 功能 | 说明 |
|---|---------|-------------|
| ✅ | CSV / XLSX / ODS 导入 | 基于 OpenSpout，逐行读取，内存友好 |
| ✅ | 字段自动识别 | 自动读取你的 Eloquent 模型字段 |
| ✅ | 字段配置 API | `Field::make()->label()->rules()->default()->options()` |
| ✅ | 自动列映射 | 模糊匹配（similar_text ≥70%）+ 中英文别名 |
| ✅ | 导入前数据预览 | 表格预览，显示每行校验状态 |
| ✅ | 行级校验 | 基于 Laravel Validator |
| ✅ | 重复处理 | 3 种策略：**跳过** / **更新** / **报错** |
| ✅ | 三步向导 UI | 上传 → 映射 → 预览 流程 |
| ✅ | CSV 分隔符自动识别 | 逗号、分号、制表符、竖线 |
| ✅ | 模板下载 | 带表头 + 示例数据的 CSV 模板 |
| ✅ | 导入结果报告 | 通知中显示成功/失败/跳过数量 |
| ✅ | mutateBeforeCreate 钩子 | 在写入数据库前修改数据 |
| ✅ | 上传文件校验 | 扩展名白名单、50MB 限制、空文件检查 |
| ✅ | 暗色模式 | 所有视图均包含 `dark:` CSS 类 |
| ✅ | 多语言 | 英文（en）与简体中文（zh_CN） |
| ✅ | Filament 插件 | `RaiseImportPlugin::make()` 一行注册 |

### 专业版（付费）

| | 功能 | 说明 |
|---|---------|-------------|
| 🔷 | **管道系统（Pipeline）** | 8 个内置管道 + 自定义 Closure 管道 |
| 🔷 | TrimStringsPipe | 自动去除所有字符串字段首尾空格 |
| 🔷 | LowercasePipe | 将邮箱/用户名转为小写 |
| 🔷 | BcryptPipe | 对密码字段进行哈希 |
| 🔷 | DateFormatPipe | 统一日期格式 |
| 🔷 | DefaultValuePipe | 为空字段填充默认值 |
| 🔷 | MergeColumnsPipe | 合并多个 CSV 列为一个字段 |
| 🔷 | SplitColumnPipe | 将一个 CSV 列拆分为多个字段 |
| 🔷 | ClosurePipe | 将任意 Closure 包装为管道 |
| 🔷 | **高级列映射** | 合并、拆分、忽略、重排列 |
| 🔷 | **导入历史（日志）** | 带统计的完整 CRUD 资源页 |
| 🔷 | **5 个 REST API 端点** | upload / preview / import / template / errors |
| 🔷 | **队列支持** | 大数据量通过 ShouldQueue 自动入队 |
| 🔷 | **导入统计 Widget** | 4 张统计卡片：总数、已导入、失败、跳过 |
| 🔷 | **重新导入** | 重试失败/部分导入 |
| 🔷 | **错误报告下载** | 含逐行错误详情的 CSV 文件 |
| 🔷 | **多列映射** | 将一列 CSV 映射到多个字段 |
| 🔷 | **忽略列复选框** | 映射时跳过不需要的列 |

## 安装

```bash
composer require raise-studio/import
```

### 导入历史（Import Log）

要在 Filament 侧边栏看到 **Import Log** 菜单，请在 `PanelProvider` 中注册插件：

```php
use RaiseStudio\Import\RaiseImportPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(RaiseImportPlugin::make())
        ;
}
```

你可以自定义菜单的位置、名称和图标：

```php
->plugin(
    RaiseImportPlugin::make()
        ->navigationGroup('Admin')                    // 放到「Admin」分组
        ->navigationLabel(__('Import Logs'))          // 自定义菜单名
        ->navigationIcon('heroicon-o-document-arrow-down')  // 自定义图标
)
```

或者，直接注册资源以获得完全控制：

```php
use RaiseStudio\Import\Pro\Resources\ImportLogResource;

->resources([
    ImportLogResource::class,
])
```

> **注意**：Import Log 是专业版功能。本地开发环境会自动启用；生产环境需要有效的授权密钥（见下方「配置」）。

## 用法

### 最简方式 — 一行代码：

```php
use RaiseStudio\Import\Actions\ImportAction;

public static function table(Table $table): Table
{
    return $table
        ->headerActions([
            ImportAction::make()
                ->model(User::class),
        ]);
}
```

### 完整配置：

```php
ImportAction::make('import')
    ->model(User::class)
    ->label('Import Users')
    ->icon('arrow-up-tray')
    ->fields([
        Field::make('name')->label('Name')->required(),
        Field::make('email')->label('Email')->rules('email|unique:users'),
        Field::make('phone')->label('Phone')->rules('numeric'),
    ])
    ->uniqueBy('email')
    ->onDuplicate('skip')
    ->chunkSize(500)
    ->rules([
        'name' => 'required|string|max:100',
        'email' => 'email|max:255',
    ])
    ->mutateBeforeCreate(function (array $row) {
        $row['password'] = bcrypt('default123');
        return $row;
    });
```

## 环境要求

- PHP 8.2+
- Filament 4.x 或 5.x
- Livewire 3.x（Filament 4）或 4.x（Filament 5）
- OpenSpout 4.x

## 配置

发布配置文件：

```bash
php artisan vendor:publish --tag=raise-import-config
```

## 翻译

发布翻译文件：

```bash
php artisan vendor:publish --tag=raise-import-translations
```

## 测试

```bash
vendor/bin/phpunit
```

## 更新日志

版本历史请参阅 [CHANGELOG](CHANGELOG.md)。

## Pro 授权

要解锁专业版功能，请在 `.env` 中配置授权密钥（以及与服务端共享的密钥）：

```bash
RAISE_IMPORT_LICENSE_KEY=your-license-key-here
RAISE_IMPORT_LICENSE_SECRET=shared-hmac-secret
RAISE_IMPORT_LICENSE_PRODUCT=raise-import
```

- `RAISE_IMPORT_LICENSE_KEY` — 你的 Pro 授权密钥。
- `RAISE_IMPORT_LICENSE_SECRET` — 与 `raise-license-server` 共享的 HMAC 密钥
  （必须与服务器的 `LICENSE_SIGNATURE_KEY` 一致）。缺少它，插件会拒绝**任何**
  验证通过的响应，因此伪造/转发的授权端点无法冒充有效授权。
- `RAISE_IMPORT_LICENSE_PRODUCT` — 验证时发送给服务器的产品标识。
  必须与 `raise-license-server` 上的 Product 标识一致。

前往 [https://raise-studio.com](https://raise-studio.com) 获取授权。

### 验证请求与响应契约

插件会向 `RAISE_IMPORT_LICENSE_VERIFY_URL` 发送 `POST` 请求：

```json
{
  "license_key": "your-license-key-here",
  "site_url": "https://example.com",
  "product": "raise-import"
}
```

端点必须返回用 HMAC-SHA256 对 `valid|domain|expires_at|edition` 签名后的 JSON
（其中 `valid` 为字面量 `true`/`false`）：

```json
{
  "valid": true,
  "domain": "example.com",
  "expires_at": "2026-12-31",
  "edition": "pro",
  "signature": "HMAC_SHA256('true|example.com|2026-12-31|pro', SECRET)"
}
```

插件会校验签名，强制要求 `edition === 'pro'`，并将密钥锁定到返回的 `domain`
（支持 `*.example.com` 通配符以覆盖子域名）。

**本地豁免被刻意收窄。** 仅回环地址（`localhost`、`127.0.0.1`、`[::1]`、`0.0.0.0`）
可免密钥使用 Pro 功能。`*.test` / `*.local` 域名以及私有 IP 段（`10.x`、`172.16–31.x`、
`192.168.x`）**不再**豁免——它们必须像生产环境一样提供有效授权。此举收紧了
免费版与专业版的边界（参见 2026-07-07 策略说明：收敛豁免）。

### 分布式校验门（纵深防御）

每个 Pro 功能执行点（`ProImportAction` 向导的初始化/运行、入队的 `ProcessImportJob`、
以及 `ImportController`）都会**直接**调用 `License::gatePro()`，而非仅依赖缓存的
`License::isPro()` 结果。`gatePro()` 从不读取静态 `isPro()` 缓存，而是自行重新评估授权
（密钥有效性 + 签名 + 域名锁定 + 完整性自校验）。这意味着即便把 `isPro()` 改成一
直返回 `true`，也不足以解锁真正的 Pro 功能——每个关键文件都会独立重新校验。

### 完整性自校验（防篡改）

除了在线验证，插件还会校验自身的 Pro 守门文件（`License.php`、`ProImportAction.php`、
`RaiseImportServiceProvider.php`）是否被人篡改以强制开启 Pro 模式。每个文件的 SHA-256
会与配置中内置的期望值比对。若某文件哈希不匹配，插件会拒绝 Pro 功能，并**静默回退到
社区版**（仅记录一条 `warning` 日志）——绝不会崩溃。

这是一种威慑，而非硬性锁：PHP 源码始终运行在客户机器上，因此下定决心的攻击者仍能绕
过它。它提高的是篡改授权门槛的成本。

在 `config/raise-import.php`（或 `.env`）中配置：

```php
'integrity_disabled' => env('RAISE_IMPORT_INTEGRITY_DISABLED', false),
'integrity_version'  => '1.0.0',
'integrity_hashes'   => [
    'src/License.php' => '...',
    'src/Pro/Actions/ProImportAction.php' => '...',
    'src/RaiseImportServiceProvider.php' => '...',
],
```

- `RAISE_IMPORT_INTEGRITY_DISABLED=true` — 完全禁用该检查
  （用于合法调试或你有意为之的源码修改）。
- `integrity_version` 必须与已安装包版本一致。若不一致（例如升级后未重新生成哈希），
  检查会被**跳过**，而非把合法用户强制降回社区版。
- 空的 `integrity_hashes` → 检查被**跳过**（优雅默认）。这正是发布时的状态，
  因此该特性在你主动开启前不会生效。

每次发版时用自带命令重新生成哈希：

```bash
php artisan raise-import:integrity:rehash
```

它会打印版本号以及可直接粘贴到 `config/raise-import.php` 的
`integrity_version` / `integrity_hashes` 配置块。

## 许可证

MIT 许可证（MIT）。详情请参阅 [许可证文件](LICENSE)。
