<?php

use Erp\McpKit\Support\FieldWhitelist;
use PHPUnit\Framework\TestCase;

class FieldWhitelistTest extends TestCase
{
    public function testUnknownFieldsAreDroppedAndPiiIsMasked()
    {
        $filter = new FieldWhitelist(['order_code', 'email'], ['email']);
        $rows = $filter->applyToList([
            ['order_code' => 'O-1', 'email' => 'alice@example.com', 'secret' => 'drop-me'],
        ]);

        $this->assertSame('O-1', $rows[0]['order_code']);
        $this->assertSame('a***@example.com', $rows[0]['email']);
        $this->assertArrayNotHasKey('secret', $rows[0]);
    }

    public function testMaskKeepsNonStringValuesUntouched()
    {
        $filter = new FieldWhitelist(['count'], ['count']);
        $rows = $filter->applyToList([['count' => 3]]);
        $this->assertSame(3, $rows[0]['count']);
    }

    public function testNestedListFieldsAreFiltered()
    {
        $filter = new FieldWhitelist(
            ['order_code', 'products' => ['goods_sn', 'qty']],
            []
        );
        $row = $filter->applyToRow([
            'order_code' => 'O-1',
            'secret' => 'drop',
            'products' => [
                ['goods_sn' => 'G1', 'qty' => 2, 'buyer_note' => 'hide'],
                ['goods_sn' => 'G2', 'qty' => 1, 'buyer_note' => 'hide'],
            ],
        ]);

        $this->assertSame('O-1', $row['order_code']);
        $this->assertArrayNotHasKey('secret', $row);
        $this->assertSame(
            [['goods_sn' => 'G1', 'qty' => 2], ['goods_sn' => 'G2', 'qty' => 1]],
            $row['products']
        );
    }

    public function testNestedAssocAndNestedPiiAreMasked()
    {
        $filter = new FieldWhitelist(
            ['order_code', 'recipient' => ['country', 'phone']],
            ['recipient' => ['phone']]
        );
        $row = $filter->applyToRow([
            'order_code' => 'O-2',
            'recipient' => ['country' => 'US', 'phone' => '1234567', 'email' => 'a@b.com'],
        ]);

        $this->assertSame('O-2', $row['order_code']);
        $this->assertSame('US', $row['recipient']['country']);
        $this->assertSame('1*****7', $row['recipient']['phone']);
        $this->assertArrayNotHasKey('email', $row['recipient']);
    }
}
