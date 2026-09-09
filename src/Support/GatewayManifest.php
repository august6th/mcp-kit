<?php

namespace Erp\McpKit\Support;

use Erp\McpKit\Tool\McpTool;
use Erp\McpKit\Tool\ToolSchema;
use InvalidArgumentException;

/**
 * 构建发往网关的权威工具清单。
 *
 * 清单用于 reconcile，不能跳过坏配置后继续发送部分结果，否则会把暂时配置错误
 * 误解释为工具已删除。清空整个 upstream 必须由调用方显式允许。
 */
class GatewayManifest
{
    public function build($tools, $allowEmpty = false)
    {
        $manifest = [];

        foreach ($tools as $tool) {
            if (! $tool instanceof McpTool) {
                throw new InvalidArgumentException('mcp.tools 中存在非 McpTool 配置，已拒绝同步。');
            }

            $schema = $tool->schema();
            if (! $schema instanceof ToolSchema) {
                throw new InvalidArgumentException("工具 {$tool->name()} 的 schema() 未返回 ToolSchema，已拒绝同步。");
            }
            if (empty($schema->upstreamPath)) {
                throw new InvalidArgumentException("工具 {$schema->name} 未设置 upstream_path，已拒绝同步。");
            }

            $manifest[] = [
                'name' => $schema->name,
                'description' => $schema->description,
                'input_params' => $schema->toGatewayParamSpec(),
                'upstream_path' => $schema->upstreamPath,
            ];
        }

        if (empty($manifest) && ! $allowEmpty) {
            throw new InvalidArgumentException('工具清单为空；如需禁用该 upstream 的全部工具，请显式使用 --allow-empty。');
        }

        return $manifest;
    }
}
