<?php

namespace Erp\McpKit\Tool;

use Erp\McpKit\Support\FieldWhitelist;

/**
 * MCP 只读工具抽象基类。
 *
 * 设计目标："新增一个 MCP 工具 = 继承本类写一个子类"：
 *  - name()      工具唯一名（如 oms.search_orders）
 *  - schema()    参数契约（唯一事实源）
 *  - whitelist() 出参字段白名单（默认拒绝，未列出的一律不返回）
 *  - piiFields() 白名单内需要掩码的字段（默认空）
 *  - handle()    真正查询，返回 ['total'=>..,'list'=>[..]]
 * run() 在 handle() 之上统一套白名单 + 脱敏，子类不用关心出参过滤。
 */
abstract class McpTool
{
    /**
     * @return string
     */
    abstract public function name();

    /**
     * @return ToolSchema
     */
    abstract public function schema();

    /**
     * @return array
     */
    abstract public function whitelist();

    /**
     * @param array $params
     * @param \Erp\McpKit\Identity $identity
     * @return array
     */
    abstract public function handle($params, $identity);

    /**
     * 需要脱敏的字段（必须同时在 whitelist 内）。
     * @return array
     */
    public function piiFields()
    {
        return [];
    }

    /**
     * 统一入口：执行 handle 后，对结果 list 套白名单 + 脱敏。
     * @param array $params
     * @param \Erp\McpKit\Identity $identity
     * @return array
     */
    public function run($params, $identity)
    {
        $result = $this->handle($params, $identity);
        if (! is_array($result)) {
            return $result;
        }
        $filter = new FieldWhitelist($this->whitelist(), $this->piiFields());
        if (isset($result['list']) && is_array($result['list'])) {
            $result['list'] = $filter->applyToList($result['list']);
            return $result;
        }
        return $filter->applyToRow($result);
    }
}
