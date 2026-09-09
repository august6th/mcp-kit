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
    public function build($tools, $upstreamLabel, $allowEmpty = false)
    {
        if (! is_string($upstreamLabel) || trim($upstreamLabel) === '') {
            throw new InvalidArgumentException('缺少 MCP_GATEWAY_UPSTREAM_LABEL，已拒绝同步。');
        }

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

            $this->validateBusinessText($schema->businessLabel, "工具 {$schema->name} 的 business_label");
            $this->validateBusinessText($schema->businessDescription, "工具 {$schema->name} 的 business_description");

            $inputParams = $schema->toGatewayParamSpec();
            foreach ($inputParams as $parameter) {
                $parameterName = isset($parameter['name']) ? $parameter['name'] : 'unknown';
                $this->validateBusinessText(
                    isset($parameter['business_label']) ? $parameter['business_label'] : null,
                    "工具 {$schema->name} 参数 {$parameterName} 的 business_label"
                );
                $this->validateBusinessText(
                    isset($parameter['business_description']) ? $parameter['business_description'] : null,
                    "工具 {$schema->name} 参数 {$parameterName} 的 business_description"
                );

                if (isset($parameter['values'])) {
                    $this->validateBusinessValues($schema->name, $parameterName, $parameter);
                }
            }

            $manifest[] = [
                'name' => $schema->name,
                'description' => $schema->description,
                'business_label' => $schema->businessLabel,
                'business_description' => $schema->businessDescription,
                'input_params' => $inputParams,
                'upstream_path' => $schema->upstreamPath,
            ];
        }

        if (empty($manifest) && ! $allowEmpty) {
            throw new InvalidArgumentException('工具清单为空；如需禁用该 upstream 的全部工具，请显式使用 --allow-empty。');
        }

        return [
            'upstream_label' => trim($upstreamLabel),
            'tools' => $manifest,
        ];
    }

    private function validateBusinessText($value, $field)
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("{$field} 不能为空，已拒绝同步。");
        }
    }

    private function validateBusinessValues($toolName, $parameterName, $parameter)
    {
        if (! isset($parameter['business_values']) || ! is_array($parameter['business_values'])) {
            throw new InvalidArgumentException(
                "工具 {$toolName} 枚举参数 {$parameterName} 未设置 business_values，已拒绝同步。"
            );
        }

        $values = $parameter['values'];
        $businessValueKeys = array_keys($parameter['business_values']);
        sort($values);
        sort($businessValueKeys);

        if ($values !== $businessValueKeys) {
            throw new InvalidArgumentException(
                "工具 {$toolName} 枚举参数 {$parameterName} 的 business_values 必须与 values 完全一致，已拒绝同步。"
            );
        }
    }
}
