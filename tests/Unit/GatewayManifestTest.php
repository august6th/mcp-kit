<?php

use Erp\McpKit\Support\GatewayManifest;
use Erp\McpKit\Tool\McpTool;
use Erp\McpKit\Tool\ToolSchema;
use PHPUnit\Framework\TestCase;

class GatewayManifestTest extends TestCase
{
    public function testBuildsPublicGatewayManifest()
    {
        $payload = (new GatewayManifest())->build([$this->tool('/inner/mcp/demo')], '订单与销售');
        $manifest = $payload['tools'];

        $this->assertSame('订单与销售', $payload['upstream_label']);
        $this->assertSame('demo', $manifest[0]['name']);
        $this->assertSame('查询演示数据', $manifest[0]['business_label']);
        $this->assertSame('按关键词查询演示数据。', $manifest[0]['business_description']);
        $this->assertSame('/inner/mcp/demo', $manifest[0]['upstream_path']);
        $this->assertArrayNotHasKey('inject_policy', $manifest[0]);
    }

    public function testRejectsEmptyUpstreamLabel()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([$this->tool('/inner/mcp/demo')], '');
    }

    public function testRejectsMissingToolBusinessMetadata()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([
            $this->tool('/inner/mcp/demo', [
                'business_label' => '',
                'business_description' => '按关键词查询演示数据。',
            ]),
        ], '订单与销售');
    }

    public function testRejectsMissingParameterBusinessMetadata()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([
            $this->tool('/inner/mcp/demo', null, [
                'query' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Query text',
                    'business_label' => '查询词',
                ],
            ]),
        ], '订单与销售');
    }

    public function testRejectsEnumBusinessValuesThatDoNotMatchValues()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([
            $this->tool('/inner/mcp/demo', null, [
                'status' => [
                    'type' => 'enum',
                    'values' => ['open', 'closed'],
                    'description' => 'open=处理中，closed=已关闭',
                    'business_label' => '状态',
                    'business_description' => '选择状态。',
                    'business_values' => ['open' => '处理中'],
                ],
            ]),
        ], '订单与销售');
    }

    public function testRejectsPartialManifestWhenAToolHasNoUpstreamPath()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([
            $this->tool('/inner/mcp/demo'),
            $this->tool(null),
        ], '订单与销售');
    }

    public function testRejectsEmptyManifestByDefault()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([], '订单与销售');
    }

    public function testAllowsExplicitEmptyManifestForFullDisable()
    {
        $this->assertSame([
            'upstream_label' => '订单与销售',
            'tools' => [],
        ], (new GatewayManifest())->build([], '订单与销售', true));
    }

    private function tool($upstreamPath, $business = null, $parameters = null)
    {
        return new class($upstreamPath, $business, $parameters) extends McpTool {
            private $upstreamPath;
            private $business;
            private $parameters;

            public function __construct($upstreamPath, $business, $parameters)
            {
                $this->upstreamPath = $upstreamPath;
                $this->business = $business === null ? [
                    'business_label' => '查询演示数据',
                    'business_description' => '按关键词查询演示数据。',
                ] : $business;
                $this->parameters = $parameters === null ? [
                    'query' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Query text',
                        'business_label' => '查询词',
                        'business_description' => '输入需要查询的关键词。',
                    ],
                ] : $parameters;
            }
            public function name() { return 'demo'; }
            public function schema()
            {
                return new ToolSchema(
                    'demo',
                    'Demo tool',
                    $this->parameters,
                    $this->upstreamPath,
                    $this->business
                );
            }
            public function whitelist() { return ['id']; }
            public function handle($params, $identity) { return ['total' => 0, 'list' => []]; }
        };
    }
}
