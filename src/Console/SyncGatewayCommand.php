<?php

namespace Erp\McpKit\Console;

use Illuminate\Console\Command;
use Erp\McpKit\Support\GatewayManifest;
use InvalidArgumentException;

/**
 * php artisan mcp:sync-gateway —— 把本服务已注册的 MCP 工具清单一键同步到网关。
 *
 * 读取 config('mcp.tools')（与 mcp:docs 同源），逐个实例化工具、取其 schema()，
 * 组成网关 bulk-register 认的 manifest（{name, description, input_params, upstream_path}），
 * 用原生 curl POST 到 `{MCP_GATEWAY_URL}/admin/tools/bulk-register?upstream_key={MCP_GATEWAY_UPSTREAM_KEY}`，
 * 带 `Authorization: Bearer {MCP_GATEWAY_ADMIN_TOKEN}`。
 *
 * 治理边界：manifest 里绝不放 inject_policy —— 那是网关侧的治理产物，源服务不可自设。
 *
 * reconcile 是权威清单覆盖：任何坏工具配置都会让整次同步失败，禁止跳过后发送部分清单。
 * 清空某个 upstream 的全部工具必须显式使用 --allow-empty，避免空配置误触发全量禁用。
 */
class SyncGatewayCommand extends Command
{
    protected $signature = 'mcp:sync-gateway
                            {--allow-empty : 允许发送空清单，以禁用该 upstream 的全部旧工具}';

    protected $description = '把本服务的 MCP 工具清单一键同步（bulk-register）到网关';

    public function handle()
    {
        $gatewayUrl = env('MCP_GATEWAY_URL');
        $upstreamKey = env('MCP_GATEWAY_UPSTREAM_KEY');
        $adminToken = env('MCP_GATEWAY_ADMIN_TOKEN');

        if (empty($gatewayUrl) || empty($upstreamKey) || empty($adminToken)) {
            $this->error('缺少同步网关所需的配置：请确认 MCP_GATEWAY_URL / MCP_GATEWAY_UPSTREAM_KEY / MCP_GATEWAY_ADMIN_TOKEN 均已设置。');
            return 1;
        }

        $toolClasses = config('mcp.tools', []);
        $toolInstances = [];

        foreach ($toolClasses as $toolClass) {
            $toolInstances[] = app($toolClass);
        }

        try {
            $tools = (new GatewayManifest())->build($toolInstances, $this->option('allow-empty'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $url = rtrim($gatewayUrl, '/') . '/admin/tools/bulk-register?upstream_key=' . urlencode($upstreamKey);
        $body = json_encode(['tools' => $tools], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $adminToken,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->error('同步网关失败（网络错误）：' . $error);
            return 1;
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : $response;
            $this->error("同步网关失败（HTTP {$statusCode}）：" . $message);
            return 1;
        }

        $upserted = is_array($decoded) && isset($decoded['upserted']) ? $decoded['upserted'] : count($tools);
        $this->info("同步成功：upserted {$upserted} 个工具。");
        return 0;
    }
}
