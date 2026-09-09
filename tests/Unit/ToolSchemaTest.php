<?php

use Erp\McpKit\Tool\ToolSchema;
use PHPUnit\Framework\TestCase;

class ToolSchemaTest extends TestCase
{
    public function testGatewaySpecIsAStableListWithTypeAndEnumMetadata()
    {
        $schema = new ToolSchema('orders', 'Orders', [
            'page' => ['type' => 'int', 'min' => 1, 'required' => true],
            'status' => ['type' => 'integer', 'values' => ['1', '2'], 'description' => '1=处理中，2=完成'],
        ], '/inner/mcp/orders');

        $this->assertSame([
            [
                'name' => 'page',
                'type' => 'integer',
                'required' => true,
                'min' => 1,
            ],
            [
                'name' => 'status',
                'type' => 'enum',
                'required' => false,
                'description' => '1=处理中，2=完成',
                'values' => ['1', '2'],
            ],
        ], $schema->toGatewayParamSpec());
    }
}
