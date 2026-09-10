<?php

namespace Erp\McpKit\Console;

class DisableGatewayToolCommand extends GatewayToolStateCommand
{
    protected $signature = 'mcp:tool:disable {name : 需要临时暂停的 MCP 工具名}';

    protected $description = '人工暂停指定 MCP 工具，普通网关同步不会自动恢复';

    public function handle()
    {
        return $this->changeState(true);
    }
}
