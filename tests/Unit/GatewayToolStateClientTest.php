<?php

use Erp\McpKit\Support\GatewayToolStateClient;
use PHPUnit\Framework\TestCase;

class GatewayToolStateClientTest extends TestCase
{
    public function testSendsPersistentOperatorStateToTheCurrentUpstream()
    {
        if (! class_exists(GatewayToolStateClient::class)) {
            $this->fail('GatewayToolStateClient is not implemented');
        }

        $captured = [];
        $client = new GatewayToolStateClient(function ($url, $headers, $body) use (&$captured) {
            $captured = compact('url', 'headers', 'body');
            return [
                'status' => 200,
                'body' => json_encode([
                    'name' => 'oms_search_orders',
                    'upstreamKey' => 'oms',
                    'operatorDisabled' => true,
                ]),
            ];
        });

        $result = $client->setDisabled(
            'https://mcp.example.com/',
            'oms',
            'admin-secret',
            'oms_search_orders',
            true
        );

        $this->assertSame(
            'https://mcp.example.com/admin/tools/operator-state?upstream_key=oms',
            $captured['url']
        );
        $this->assertContains('Authorization: Bearer admin-secret', $captured['headers']);
        $this->assertSame([
            'name' => 'oms_search_orders',
            'disabled' => true,
        ], json_decode($captured['body'], true));
        $this->assertTrue($result['operatorDisabled']);
    }
}
