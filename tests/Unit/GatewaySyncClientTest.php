<?php

use Erp\McpKit\Support\GatewaySyncClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GatewaySyncClientTest extends TestCase
{
    public function testPostsManifestAndMarksHttpStatus()
    {
        $captured = [];
        $client = new GatewaySyncClient(function ($url, $headers, $body) use (&$captured) {
            $captured = compact('url', 'headers', 'body');
            return ['status' => 200, 'body' => json_encode(['upserted' => 2, 'registry' => ['status' => 'refreshed']])];
        });
        $manifest = ['upstream_label' => '订单与销售', 'tools' => [['name' => 'search']]];

        $result = $client->sync('https://mcp.example.com/', 'oms', 'admin-secret', $manifest);

        $this->assertSame('https://mcp.example.com/admin/tools/bulk-register?upstream_key=oms', $captured['url']);
        $this->assertContains('Authorization: Bearer admin-secret', $captured['headers']);
        $this->assertSame($manifest, json_decode($captured['body'], true));
        $this->assertSame(200, $result['_http_status']);
    }

    public function testRetains202RetryingResponse()
    {
        $client = new GatewaySyncClient(function () {
            return ['status' => 202, 'body' => json_encode(['upserted' => 2, 'registry' => ['status' => 'retrying']])];
        });

        $result = $client->sync('https://mcp.example.com', 'oms', 'admin-secret', ['tools' => []]);

        $this->assertSame(202, $result['_http_status']);
        $this->assertSame('retrying', $result['registry']['status']);
    }

    public function testNon2xxErrorsAreSafe()
    {
        $client = new GatewaySyncClient(function () {
            return ['status' => 500, 'body' => json_encode(['error' => 'safe failure', 'secret' => 'do-not-leak'])];
        });

        try {
            $client->sync('https://mcp.example.com', 'oms', 'admin-secret', ['tools' => []]);
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('同步网关失败（HTTP 500）：safe failure', $e->getMessage());
        }
    }
}
