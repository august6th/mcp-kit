<?php

use Erp\McpKit\Support\BrandCodes;
use PHPUnit\Framework\TestCase;

class BrandCodesTest extends TestCase
{
    public function testDelegatesToAccountCodesSemantics()
    {
        $this->assertSame(['A'], BrandCodes::resolve(['A', 'B'], ['A'], false));
        $this->assertSame(['unknown'], BrandCodes::resolve(['A'], ['B'], false));
        $this->assertSame(['unknown'], BrandCodes::resolve([], [], false));
        $this->assertSame([], BrandCodes::resolve(['A'], ['B'], true));
    }
}
