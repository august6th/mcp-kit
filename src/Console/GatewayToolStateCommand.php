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
            $result = $this->client->setDisabled(
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
        if ((isset($result['_http_status']) && (int)$result['_http_status'] === 202)
            || (isset($result['registry']['status']) && $result['registry']['status'] === 'retrying')) {
            $this->warn('工具状态已提交，客户端目录正在重试刷新：' . $this->argument('name'));
        } else {
            $this->info($verb . ' MCP 工具：' . $this->argument('name'));
        }
        return 0;
    }
}
