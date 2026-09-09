<?php

namespace Erp\McpKit\Audit;

/**
 * 审计接口。源服务默认空实现（NullAuditLogger）；完整审计在网关侧落库。
 */
interface AuditLogger
{
    /**
     * @param string $tool 工具名
     * @param array $params 入参
     * @param array $identity 调用人身份（Identity::toArray()）
     * @param array $meta 附加信息（结果条数、耗时等）
     * @return void
     */
    public function log($tool, $params, $identity, $meta = []);
}
