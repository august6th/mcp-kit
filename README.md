# erp/mcp-kit

ERP MCP 网关共享包（一期只读）。提供：MCP 只读工具框架、身份值对象、账号级数据权限解析、出参白名单/PII 脱敏、`mcp:docs` 文档命令、认证中间件。

> 风格约束：跟随 ERP 既有 Laravel 5.5 / PHP 7.0 代码风格——不写 `declare(strict_types)`、参数/返回值不加类型声明、构造器注入。

## 安装（OMS / IC / COMMON）

包名 `erp/mcp-kit`，源码在 [github.com/august6th/mcp-kit](https://github.com/august6th/mcp-kit)。**不要上 Packagist**，消费者用 VCS 源；**UUA 不装本包**。

在 `composer.json` 增加：

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/august6th/mcp-kit.git" }
],
"require": {
    "erp/mcp-kit": "^0.1"
}
```

必须在 Laradock **workspace**（PHP 7.1）里装，不要用宿主机 PHP 8：

```bash
# 容器内，路径按挂载：/var/www/erp/{oms,ic,common}
composer update erp/mcp-kit --ignore-platform-reqs --no-plugins
```

装完后 `vendor/erp/mcp-kit` 是实目录（不是 `../mcp-kit` symlink）。OMS / IC / COMMON 会把 lock + vendor 一并提交。

`extra.laravel.providers` 已声明 `McpKitServiceProvider`，Laravel 包自动发现会加载它。

本地改 kit、尚未发版时，可临时把 path 源加回该仓联调，联调完删掉，避免 lock 再变回 path：

```json
{ "type": "path", "url": "../mcp-kit", "options": { "symlink": true } }
```

## 新增一个工具

1. 继承 `Erp\McpKit\Tool\McpTool`，实现 `name()/schema()/whitelist()/handle()`（可选 `piiFields()`）。
2. 注册进服务的 `config/mcp.php` 的 `tools` 数组。
3. `handle()` 用 `Erp\McpKit\Support\AccountCodes::resolve()` 定账号权限，再调本服务 Service 查询。
4. 出参过滤由基类 `run()` 统一完成（白名单 + 脱敏），子类不用管。

`schema()` 同时维护两套面向不同读者的说明：

- `description` 是给大模型的完整技术契约，必须保留枚举含义、边界、权限和结果限制。
- `business_label` / `business_description` 是给门户业务用户看的名称和用途说明。
- 每个公开参数也必须同时提供 `description`、`business_label`、`business_description`。
- 枚举参数还必须提供 `business_values`，键与 `values` 完全一致，值是业务用户能理解的选项名称。

```php
return new ToolSchema('oms.search_orders', '查询订单；最多返回 100 条。', [
    'status' => [
        'type' => 'enum',
        'values' => ['open', 'closed'],
        'description' => '订单状态：open=处理中，closed=已关闭',
        'business_label' => '订单状态',
        'business_description' => '选择需要查看的订单状态。',
        'business_values' => ['open' => '处理中', 'closed' => '已关闭'],
    ],
], '/inner/mcp/orders', [
    'business_label' => '查询订单',
    'business_description' => '按状态查找订单及处理进度。',
]);
```

## 认证

`Erp\McpKit\Http\McpSignatureCheck`：以 OMS `InnerSignatureCheck` 为蓝本的 HMAC 校验（`INNER_INTERFACE_KEY`），额外从签名 `data` 注入 `account_codes/brand_codes/is_superuser/mcp_client_id` 到 `login_user`。local 环境跳过校验。

## 文档

```bash
php artisan mcp:docs          # markdown + manifest
php artisan mcp:docs --json   # 仅 manifest JSON
```

## 同步到网关

给工具的 `schema()` 设置 `upstream_path`（`ToolSchema` 构造器第 4 个参数，可选）后，用一条命令把工具清单注册进网关的 `mcp_tools` 表：

```bash
export MCP_GATEWAY_URL=https://mcp-gateway.example.com
export MCP_GATEWAY_UPSTREAM_KEY=oms
export MCP_GATEWAY_UPSTREAM_LABEL=订单与销售
export MCP_GATEWAY_ADMIN_TOKEN=xxx

php artisan mcp:sync-gateway
```

行为说明：

- 四个环境变量（`MCP_GATEWAY_URL` / `MCP_GATEWAY_UPSTREAM_KEY` / `MCP_GATEWAY_UPSTREAM_LABEL` / `MCP_GATEWAY_ADMIN_TOKEN`）缺一不可，缺失会直接报错并以非 0 退出，不会发起请求。`MCP_GATEWAY_UPSTREAM_LABEL` 是门户展示的业务域名称，不在门户中硬编码。
- 同步是权威清单 reconcile。任一配置项不是 `McpTool`、`schema()` 返回值非法或缺少 `upstream_path` 时整次失败，不会跳过坏项后用部分清单覆盖线上。
- 请求体包含 `upstream_label` 和 `tools`；每个工具携带技术说明、业务说明和参数业务元数据。`input_params` 由 `ToolSchema::toGatewayParamSpec()` 从内部参数定义翻译成网关认的 param-spec DSL。
- 任一工具或参数缺少业务名称、业务说明，或枚举 `business_values` 与 `values` 不完全一致时，整次同步失败。业务字段只供门户使用，不得替换或拼接进大模型看到的 `description`。
- **同步清单里绝不包含 `inject_policy`**——参数注入策略（如账号级过滤条件如何拼装）完全由网关侧治理，源服务/模块不可自行设置，也不该在这里出现。
- 命令内部用 PHP 内置 `curl_*` 发请求（本包不依赖 guzzle），POST 到 `{MCP_GATEWAY_URL}/admin/tools/bulk-register?upstream_key={MCP_GATEWAY_UPSTREAM_KEY}`，带 `Authorization: Bearer {MCP_GATEWAY_ADMIN_TOKEN}`；成功打印 upserted 的工具数量，失败打印网关返回的错误信息。
- 默认拒绝空清单。确需删除该 upstream 的最后一个工具时，显式执行 `php artisan mcp:sync-gateway --allow-empty`；网关会在同一事务中禁用全部旧工具。

## 临时暂停指定工具

运维需要紧急下线单个能力时，可人工暂停并在确认后恢复：

```bash
php artisan mcp:tool:disable oms_search_orders
php artisan mcp:tool:enable oms_search_orders
```

人工暂停状态持久化在网关，普通 `mcp:sync-gateway` 不会自动恢复。命令只操作
`MCP_GATEWAY_UPSTREAM_KEY` 对应业务域内的同名工具；如果工具已经从源服务权威清单删除，执行恢复命令也不会重新发布它。
