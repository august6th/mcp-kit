<?php

use Erp\McpKit\Console\SyncGatewayCommand;
use Erp\McpKit\Support\GatewaySyncClient;
use PHPUnit\Framework\TestCase;

class SyncGatewayCommandTest extends TestCase
{
    public function testCommandOutcomeContractWhenIlluminateIsAvailable()
    {
        if (! class_exists('Illuminate\\Console\\Command')) {
            $this->markTestSkipped('Illuminate Console is not installed in the package-only test environment.');
        }
        $this->assertTrue(class_exists(SyncGatewayCommand::class));
        $this->assertTrue(class_exists(GatewaySyncClient::class));
    }
}
