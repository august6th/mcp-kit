# mcp-kit — 开发规则（Claude Code）

> 本文件由 Claude Code 在本目录下自动加载。mcp-kit 是 PHP composer 包（psr-4 `Erp\McpKit\`），
> 沿用 ERP 既有 PHP 规则（这些规则是通用的）。定位见仓库根 `MCP_GATEWAY_PLAN.md`（§3.1、D9/D11/D12）。

## 遵循 ERP 既有 PHP 规则
以下 `.cursor/rules/*.mdc`（仓库根 + `oms/` 同级）对本包同样适用，等同强约束：
- `php-common-grammer`：索引最左前缀、避免不必要 cast、金额用 bcmath、避免 N+1、增长表用游标/分批。
- `laravel` / `laravel-model`：Repository 模式；Model 用 `$guarded = []`（不用 `$fillable`）；最小改动。
- `growing-table-performance`：查询前确认真实索引、`whereIn` 带上限、`EXPLAIN` 验证、禁全表扫。
- `mysql-ddl-default-value`：建表 `NOT NULL + DEFAULT`，组合索引多样性从左到右递减。
- `model-name-prefix`：每次执行先输出 `当前模型：<模型名>`。
- `post-large-change-review-structure`：大改动后输出「文件职责梳理」。

## 本包特有约束
- **PHP 版本现实**：Laravel 5.5 / PHP 7.0 风格 —— **不写 `declare(strict_types=1)`**、属性/参数/返回值不加类型、构造器注入。与被 require 的 OMS(PHP 7.x) 保持一致，**不上 7.1+ 语法**。`composer.json` require `php: >=7.0.0`。
- **装配（D12）**：本包作为 OMS 的 composer 本地 path 包（symlink）被引用，**不内联**进 OMS 命名空间。OMS 侧指向本包的活线（Kernel 别名 / 路由组）必须在 `composer update erp/mcp-kit --ignore-platform-reqs` 真正装上后才接回，避免悬空引用。
- **唯一事实源**：工具的 `schema()` 是 MCP 清单 / 门户目录 / `mcp:docs` 文档的同源，改工具先改 schema。
- **同步 fail-closed**：`mcp:sync-gateway` 是权威清单 reconcile；任一工具类型/schema/upstream_path 非法时整次失败，禁止跳过后发送部分清单；清空最后一个工具必须显式使用 `--allow-empty`。
- **枚举/状态参数的 `description` 必须逐值列含义**：`schema()` 里状态/枚举类入参（如 `order_status`）的 `description` 要逐一写清取值含义（例：`订单状态：1=处理中，2=已完结，3=已取消`），并与本服务代码里的取值（枚举常量/DB 列 COMMENT）严格一致。这段 `description` 经 `mcp:sync-gateway` 落到网关 `mcp_tools.input_params`，网关再经 `.describe()` 带进工具 `inputSchema`——**这是大模型唯一能看到枚举含义的地方**（模型只读 `tools/list`）。改了含义走 `mcp:sync-gateway` 重新 upsert，**不新增迁移**。
- **安全**：`FieldWhitelist` 默认拒绝、显式白名单出参并剔除买家 PII；`McpSignatureCheck` 克隆自 `InnerSignatureCheck`，不改原件（D11）。审计接口在源服务默认空实现（`NullAuditLogger`），完整审计在网关侧。

## 工具设计：数据体量控制（D16，加工具前必读）
大模型只「挑工具 + 填参 + 转述」，返回多少数据由**工具代码**决定、不靠模型自觉。每新增一个工具，先回答三问：**返回聚合还是明细？上限多少？超限怎么办？**
- **默认聚合，而非明细**：能用 SQL `GROUP BY` 聚合回答的（如「近一年销量」），就返回聚合数字（约十几行），不要吐明细。
- **明细工具强制分页 + 硬上限**：`page_size` 焊死上限（现 100），并返回 `total` 总数；禁止无界 `get()`/`all()`。
- **执行前体量闸门**：预估/`COUNT` 命中行数，超阈值**不执行**，返回结构化的诚实提示（「约 X 条，太大，请缩小时间范围或改用汇总」），交给上层转述用户（接 D10 增长回路）。
- **限制最大时间窗、走 range 索引**：对齐 F4，不传时间兜底近 90 天 + `time_type=sales_time`；超大时间窗只允许聚合、不允许明细，禁全表扫（`EXPLAIN` 验证）。
- **真·批量走导出逃生口**：确需一年行级数据的，提示改用 ERP 导出功能/触发导出任务，不经大模型。
- 安全由确定性代码保证：上限/闸门是安全底线（永远在）；聚合工具是好体验（缺了只是难用，靠反馈回路补齐）。
