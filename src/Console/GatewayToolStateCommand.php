<?php

namespace Erp\McpKit\Console;

use Illuminate\Console\Command;
use Erp\McpKit\Support\GatewayToolStateClient;
use RuntimeException;

abstract class GatewayToolStateCommand extends Command
{
    private $client;

    public function __construct(GatewayToolStateClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    protected function changeState($isDisabled)
    {
        $gatewayUrl = env('MCP_GATEWAY_URL');
        $upstreamKey = env('MCP_GATEWAY_UPSTREAM_KEY');
        $adminToken = env('MCP_GATEWAY_ADMIN_TOKEN');
        if (empty($gatewayUrl) || empty($upstreamKey) || empty($adminToken)) {
            $this->error('缺少工具状态操作所需的配置：请确认 MCP_GATEWAY_URL / MCP_GATEWAY_UPSTREAM_KEY / MCP_GATEWAY_ADMIN_TOKEN 均已设置。');
            return 1;
        }

        try {
            $this->client->setDisabled(
                $gatewayUrl,
                $upstreamKey,
                $adminToken,
                $this->argument('name'),
                $isDisabled
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $verb = $isDisabled ? '已暂停' : '已恢复';
        $this->info($verb . ' MCP 工具：' . $this->argument('name'));
        return 0;
    }
}
