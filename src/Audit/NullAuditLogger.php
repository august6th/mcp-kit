<?php

namespace Erp\McpKit\Audit;

/**
 * 空审计实现：源服务不落库，避免给只读查询增加写负担。
 */
class NullAuditLogger implements AuditLogger
{
    public function log($tool, $params, $identity, $meta = [])
    {
        // no-op：完整审计在网关侧实现。
    }
}
