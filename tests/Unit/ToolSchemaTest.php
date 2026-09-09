<?php

use Erp\McpKit\Tool\ToolSchema;
use PHPUnit\Framework\TestCase;

class ToolSchemaTest extends TestCase
{
    public function testGatewaySpecCarriesBusinessMetadataWithoutChangingModelDescription()
    {
        $schema = new ToolSchema('orders', 'Model contract', [
            'status' => [
                'type' => 'enum',
                'values' => ['open', 'closed'],
                'description' => 'open=处理中，closed=已关闭',
                'business_label' => '订单状态',
                'business_description' => '选择需要查看的订单状态。',
                'business_values' => ['open' => '处理中', 'closed' => '已关闭'],
            ],
        ], '/inner/mcp/orders', [
            'business_label' => '查询订单',
            'business_description' => '按状态查询订单。',
        ]);

        $this->assertSame('查询订单', $schema->businessLabel);
        $this->assertSame('按状态查询订单。', $schema->businessDescription);
        $this->assertSame('Model contract', $schema->description);
        $this->assertSame([
            [
                'name' => 'status',
                'type' => 'enum',
                'required' => false,
                'description' => 'open=处理中，closed=已关闭',
                'values' => ['open', 'closed'],
                'business_label' => '订单状态',
                'business_description' => '选择需要查看的订单状态。',
                'business_values' => ['open' => '处理中', 'closed' => '已关闭'],
            ],
        ], $schema->toGatewayParamSpec());
    }

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
