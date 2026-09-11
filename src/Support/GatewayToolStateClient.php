<?php

namespace Erp\McpKit\Support;

use RuntimeException;

class GatewayToolStateClient
{
    private $transport;

    public function __construct($transport = null)
    {
        $this->transport = $transport;
    }

    public function setDisabled($gatewayUrl, $upstreamKey, $adminToken, $toolName, $isDisabled)
    {
        $url = rtrim($gatewayUrl, '/')
            . '/admin/tools/operator-state?upstream_key=' . urlencode($upstreamKey);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $adminToken,
        ];
        $body = json_encode([
            'name' => $toolName,
            'disabled' => (bool)$isDisabled,
        ], JSON_UNESCAPED_UNICODE);

        $response = $this->transport
            ? call_user_func($this->transport, $url, $headers, $body)
            : $this->send($url, $headers, $body);

        $status = isset($response['status']) ? (int)$response['status'] : 0;
        $responseBody = isset($response['body']) ? $response['body'] : '';
        $decoded = json_decode($responseBody, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && isset($decoded['error'])
                ? $decoded['error']
                : 'gateway request failed';
            throw new RuntimeException('修改 MCP 工具状态失败：' . $message);
        }
        if (! is_array($decoded) || ! isset($decoded['operatorDisabled'])) {
            throw new RuntimeException('修改 MCP 工具状态失败：网关返回格式异常');
        }
        $decoded['_http_status'] = $status;
        return $decoded;
    }

    private function send($url, $headers, $body)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('修改 MCP 工具状态失败（网络错误）：' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $response];
    }
}
