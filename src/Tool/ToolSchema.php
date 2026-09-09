<?php

namespace Erp\McpKit\Tool;

/**
 * 工具 schema 值对象。
 *
 * 一处定义、四处复用：MCP tools/list、能力目录、mcp:docs 文档、网关注册表都读它，
 * 保证工具的参数契约只有一个事实源。
 */
class ToolSchema
{
    public $name;
    public $description;
    public $parameters;
    public $upstreamPath;

    public function __construct($name, $description, $parameters = [], $upstreamPath = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = is_array($parameters) ? $parameters : [];
        $this->upstreamPath = $upstreamPath;
    }

    public function toArray()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'upstream_path' => $this->upstreamPath,
        ];
    }

    /**
     * 把内部参数定义（关联数组，键=字段名，值=元信息）翻译成网关 bulk-register
     * 接口认的 param-spec DSL：字段对象组成的**顺序数组**（list），每项形如
     * {name, type, required?, description?, min?, max?, maxLength?, values?}。
     *
     * 类型映射规则（内部 $meta['type'] → 网关 type，缺省按 mcp:docs 惯例用 'string'）：
     *  - 'int' / 'integer'            → 'integer'
     *  - 'float' / 'double' / 'number' → 'number'
     *  - 'bool' / 'boolean'           → 'boolean'
     *  - 'array'                      → 'array'
     *  - 其余（含未设置）             → 'string'
     *  - 若内部定义了 'values' 或 'enum'（非空数组），不管上面推出的 type 是什么，
     *    一律覆盖为 'enum'，并把该数组原样放进网关 spec 的 'values'。
     *
     * 数值/字符串约束原样透传（若内部有设置）：
     *  - 'min' / 'max' → 网关 spec 同名字段（数值范围）
     *  - 'maxLength'   → 网关 spec 同名字段（字符串长度上限）
     *
     * @return array 顺序数组（list），不是关联数组
     */
    public function toGatewayParamSpec()
    {
        $spec = [];

        foreach ($this->parameters as $field => $meta) {
            if (! is_array($meta)) {
                $meta = [];
            }

            $internalType = isset($meta['type']) ? $meta['type'] : 'string';
            $type = $this->mapGatewayType($internalType);

            $values = null;
            if (! empty($meta['values']) && is_array($meta['values'])) {
                $values = $meta['values'];
            } elseif (! empty($meta['enum']) && is_array($meta['enum'])) {
                $values = $meta['enum'];
            }
            if ($values !== null) {
                $type = 'enum';
            }

            $entry = [
                'name' => $field,
                'type' => $type,
                'required' => ! empty($meta['required']),
            ];

            if (isset($meta['description']) && $meta['description'] !== '') {
                $entry['description'] = $meta['description'];
            }
            if ($values !== null) {
                $entry['values'] = $values;
            }
            if (isset($meta['min'])) {
                $entry['min'] = $meta['min'];
            }
            if (isset($meta['max'])) {
                $entry['max'] = $meta['max'];
            }
            if (isset($meta['maxLength'])) {
                $entry['maxLength'] = $meta['maxLength'];
            }

            $spec[] = $entry;
        }

        return $spec;
    }

    /**
     * 内部类型字符串 → 网关 param-spec type 的规范化映射，见 toGatewayParamSpec() 注释。
     * @param string $internalType
     * @return string
     */
    protected function mapGatewayType($internalType)
    {
        switch ($internalType) {
            case 'int':
            case 'integer':
                return 'integer';
            case 'float':
            case 'double':
            case 'number':
                return 'number';
            case 'bool':
            case 'boolean':
                return 'boolean';
            case 'array':
                return 'array';
            case 'enum':
                return 'enum';
            default:
                return 'string';
        }
    }
}
