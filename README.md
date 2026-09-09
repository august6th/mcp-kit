# erp/mcp-kit

ERP MCP 网关共享包（一期只读）。提供：MCP 只读工具框架、身份值对象、账号级数据权限解析、出参白名单/PII 脱敏、`mcp:docs` 文档命令、认证中间件。

> 风格约束：跟随 ERP 既有 Laravel 5.5 / PHP 7.0 代码风格——不写 `declare(strict_types)`、参数/返回值不加类型声明、构造器注入。

## 安装（OMS / COMMON 侧）

`oms/composer.json` 增加本地 path 仓库并依赖：

```json
"repositories": [
    { "type": "path", "url": "../mcp-kit", "options": { "symlink": true } }
],
"require": {
    "erp/mcp-kit": "@dev"
}
```

然后在 OMS / COMMON 运行环境（PHP 7.x）里：

```bash
# OMS
cd oms && composer update erp/mcp-kit --ignore-platform-reqs
# COMMON
cd common && composer update erp/mcp-kit --ignore-platform-reqs
```

`extra.laravel.providers` 已声明 `McpKitServiceProvider`，Laravel 包自动发现会加载它。

## 新增一个工具

1. 继承 `Erp\McpKit\Tool\McpTool`，实现 `name()/schema()/whitelist()/handle()`（可选 `piiFields()`）。
2. 注册进服务的 `config/mcp.php` 的 `tools` 数组。
3. `handle()` 用 `Erp\McpKit\Support\AccountCodes::resolve()` 定账号权限，再调本服务 Service 查询。
4. 出参过滤由基类 `run()` 统一完成（白名单 + 脱敏），子类不用管。

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
export MCP_GATEWAY_ADMIN_TOKEN=xxx

php artisan mcp:sync-gateway
```

行为说明：

- 三个环境变量（`MCP_GATEWAY_URL` / `MCP_GATEWAY_UPSTREAM_KEY` / `MCP_GATEWAY_ADMIN_TOKEN`）缺一不可，缺失会直接报错并以非 0 退出，不会发起请求。
- 同步是权威清单 reconcile。任一配置项不是 `McpTool`、`schema()` 返回值非法或缺少 `upstream_path` 时整次失败，不会跳过坏项后用部分清单覆盖线上。
- 请求体是 `{name, description, input_params, upstream_path}` 的工具数组，`input_params` 由 `ToolSchema::toGatewayParamSpec()` 从内部参数定义翻译成网关认的 param-spec DSL。
- **同步清单里绝不包含 `inject_policy`**——参数注入策略（如账号级过滤条件如何拼装）完全由网关侧治理，源服务/模块不可自行设置，也不该在这里出现。
- 命令内部用 PHP 内置 `curl_*` 发请求（本包不依赖 guzzle），POST 到 `{MCP_GATEWAY_URL}/admin/tools/bulk-register?upstream_key={MCP_GATEWAY_UPSTREAM_KEY}`，带 `Authorization: Bearer {MCP_GATEWAY_ADMIN_TOKEN}`；成功打印 upserted 的工具数量，失败打印网关返回的错误信息。
- 默认拒绝空清单。确需删除该 upstream 的最后一个工具时，显式执行 `php artisan mcp:sync-gateway --allow-empty`；网关会在同一事务中禁用全部旧工具。
