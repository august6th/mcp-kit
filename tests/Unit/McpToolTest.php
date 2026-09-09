<?php

use Erp\McpKit\Tool\McpTool;
use Erp\McpKit\Tool\ToolSchema;
use PHPUnit\Framework\TestCase;

class McpToolTest extends TestCase
{
    public function testRunAppliesWhitelistToListRows()
    {
        $tool = new class extends McpTool {
            public function name() { return 'demo'; }
            public function schema() { return new ToolSchema('demo', 'Demo'); }
            public function whitelist() { return ['id']; }
            public function handle($params, $identity)
            {
                return ['total' => 1, 'list' => [['id' => 1, 'secret' => 'drop']]];
            }
        };

        $result = $tool->run([], new stdClass());
        $this->assertSame([['id' => 1]], $result['list']);
        $this->assertSame(1, $result['total']);
    }

    public function testRunAppliesWhitelistToDetailWithoutList()
    {
        $tool = new class extends McpTool {
            public function name() { return 'demo_detail'; }
            public function schema() { return new ToolSchema('demo_detail', 'Demo'); }
            public function whitelist() { return ['id', 'items' => ['sku']]; }
            public function handle($params, $identity)
            {
                return [
                    'id' => 9,
                    'secret' => 'drop',
                    'items' => [['sku' => 'A', 'cost' => 1]],
                ];
            }
        };

        $result = $tool->run([], new stdClass());
        $this->assertSame(9, $result['id']);
        $this->assertArrayNotHasKey('secret', $result);
        $this->assertSame([['sku' => 'A']], $result['items']);
    }
}
