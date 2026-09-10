<?php

namespace Erp\McpKit\Console;

class EnableGatewayToolCommand extends GatewayToolStateCommand
{
    protected $signature = 'mcp:tool:enable {name : 需要恢复的 MCP 工具名}';

    protected $description = '清除指定 MCP 工具的人工暂停状态';

    public function handle()
    {
        return $this->changeState(false);
    }
}
