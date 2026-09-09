<?php

namespace Erp\McpKit;

use Illuminate\Support\ServiceProvider;
use Erp\McpKit\Audit\AuditLogger;
use Erp\McpKit\Audit\NullAuditLogger;
use Erp\McpKit\Console\DocsCommand;
use Erp\McpKit\Console\SyncGatewayCommand;

class McpKitServiceProvider extends ServiceProvider
{
    /**
     * 注册容器绑定。
     * 审计接口默认绑定空实现——完整审计在网关侧完成，源服务不落库。
     */
    public function register()
    {
        $this->app->singleton(AuditLogger::class, function () {
            return new NullAuditLogger();
        });
    }

    /**
     * 仅在 console 下注册命令，HTTP 启动期零负担。
     * mcp:docs         导出本服务已注册工具的 manifest / 文档
     * mcp:sync-gateway 把本服务的工具清单一键同步（bulk-register）到网关
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DocsCommand::class,
                SyncGatewayCommand::class,
            ]);
        }
    }
}
