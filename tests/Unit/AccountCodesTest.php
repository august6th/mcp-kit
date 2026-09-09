<?php

use Erp\McpKit\Support\AccountCodes;
use PHPUnit\Framework\TestCase;

class AccountCodesTest extends TestCase
{
    public function testNonSuperuserIsRestrictedToAuthorizedCodes()
    {
        $this->assertSame(['A'], AccountCodes::resolve(['A', 'B'], ['A'], false));
        $this->assertSame(['unknown'], AccountCodes::resolve(['A'], ['B'], false));
    }

    public function testEmptyAuthorizationFailsClosed()
    {
        $this->assertSame(['unknown'], AccountCodes::resolve([], [], false));
    }

    public function testSuperuserIsUnrestricted()
    {
        $this->assertSame([], AccountCodes::resolve(['A'], ['B'], true));
    }
}
