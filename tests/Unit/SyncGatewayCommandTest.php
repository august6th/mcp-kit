<?php

use Erp\McpKit\Console\SyncGatewayCommand;
use Erp\McpKit\Support\GatewaySyncClient;
use PHPUnit\Framework\TestCase;

if (! class_exists('Illuminate\\Console\\Command')) {
    class SyncGatewayCommandTest extends TestCase
    {
        public function testCommandOutcomeContractRequiresIlluminateConsole()
        {
            $this->markTestSkipped('Illuminate Console is not installed in the package-only test environment.');
        }
    }
} else {
class SyncGatewayCommandTest extends TestCase
{
    public function test200ReportsRefreshedSuccess()
    {
        $command = $this->commandWithResult([
            '_http_status' => 200,
            'upserted' => 2,
            'registry' => ['status' => 'refreshed'],
        ]);

        $this->assertSame(0, $command->handle());
        $this->assertSame(['同步成功：upserted 2 个工具。'], $command->infos);
        $this->assertSame([], $command->warnings);
        $this->assertSuccessOutputDoesNotClaimCursorUpdated($command);
    }

    public function test202ReportsCommittedAndRetrying()
    {
        $command = $this->commandWithResult([
            '_http_status' => 202,
            'upserted' => 2,
            'registry' => ['status' => 'retrying'],
        ]);

        $this->assertSame(0, $command->handle());
        $this->assertSame([], $command->infos);
        $this->assertSame(['工具状态已提交，客户端目录正在重试刷新'], $command->warnings);
        $this->assertSuccessOutputDoesNotClaimCursorUpdated($command);
    }

    public function test500ReportsExistingSafeFailure()
    {
        $client = new FakeGatewaySyncClient(null, new \RuntimeException('同步网关失败（HTTP 500）：safe failure'));
        $command = new TestableSyncGatewayCommand($client);

        $this->assertSame(1, $command->handle());
        $this->assertSame(['同步网关失败（HTTP 500）：safe failure'], $command->errors);
        $this->assertSame([], $command->infos);
        $this->assertSame([], $command->warnings);
    }

    private function commandWithResult($result)
    {
        return new TestableSyncGatewayCommand(new FakeGatewaySyncClient($result));
    }

    private function assertSuccessOutputDoesNotClaimCursorUpdated($command)
    {
        $output = implode("\n", array_merge($command->infos, $command->warnings));
        $this->assertNotContains('Cursor 已更新', $output);
    }
}

class FakeGatewaySyncClient extends GatewaySyncClient
{
    private $result;
    private $exception;

    public function __construct($result, $exception = null)
    {
        $this->result = $result;
        $this->exception = $exception;
    }

    public function sync($gatewayUrl, $upstreamKey, $adminToken, array $manifest)
    {
        if ($this->exception) {
            throw $this->exception;
        }
        return $this->result;
    }
}

class TestableSyncGatewayCommand extends SyncGatewayCommand
{
    public $infos = [];
    public $warnings = [];
    public $errors = [];

    public function info($string, $verbosity = null)
    {
        $this->infos[] = $string;
    }

    public function warn($string, $verbosity = null)
    {
        $this->warnings[] = $string;
    }

    public function error($string, $verbosity = null)
    {
        $this->errors[] = $string;
    }

    protected function gatewaySettings()
    {
        return [
            'gateway_url' => 'https://mcp.example.com',
            'upstream_key' => 'oms',
            'upstream_label' => '订单与销售',
            'admin_token' => 'admin-secret',
        ];
    }

    protected function buildManifest($upstreamLabel, $allowEmpty)
    {
        return [
            'upstream_label' => $upstreamLabel,
            'tools' => [['name' => 'first'], ['name' => 'second']],
        ];
    }

    protected function allowEmpty()
    {
        return false;
    }
}
}
