<?php

use Erp\McpKit\Support\GatewayManifest;
use Erp\McpKit\Tool\McpTool;
use Erp\McpKit\Tool\ToolSchema;
use PHPUnit\Framework\TestCase;

class GatewayManifestTest extends TestCase
{
    public function testBuildsPublicGatewayManifest()
    {
        $manifest = (new GatewayManifest())->build([$this->tool('/inner/mcp/demo')]);

        $this->assertSame('demo', $manifest[0]['name']);
        $this->assertSame('/inner/mcp/demo', $manifest[0]['upstream_path']);
        $this->assertArrayNotHasKey('inject_policy', $manifest[0]);
    }

    public function testRejectsPartialManifestWhenAToolHasNoUpstreamPath()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([
            $this->tool('/inner/mcp/demo'),
            $this->tool(null),
        ]);
    }

    public function testRejectsEmptyManifestByDefault()
    {
        $this->expectException(InvalidArgumentException::class);
        (new GatewayManifest())->build([]);
    }

    public function testAllowsExplicitEmptyManifestForFullDisable()
    {
        $this->assertSame([], (new GatewayManifest())->build([], true));
    }

    private function tool($upstreamPath)
    {
        return new class($upstreamPath) extends McpTool {
            private $upstreamPath;

            public function __construct($upstreamPath) { $this->upstreamPath = $upstreamPath; }
            public function name() { return 'demo'; }
            public function schema()
            {
                return new ToolSchema('demo', 'Demo tool', [
                    'query' => ['type' => 'string', 'required' => true],
                ], $this->upstreamPath);
            }
            public function whitelist() { return ['id']; }
            public function handle($params, $identity) { return ['total' => 0, 'list' => []]; }
        };
    }
}
