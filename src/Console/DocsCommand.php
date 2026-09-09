<?php

namespace Erp\McpKit\Console;

use Illuminate\Console\Command;
use Erp\McpKit\Tool\McpTool;

/**
 * php artisan mcp:docs —— 导出当前服务已注册 MCP 工具的 manifest 与文档。
 *
 * 读取 config('mcp.tools')，实例化每个工具，输出其 schema()。
 * 证明"schema 是唯一事实源"：文档、目录、将来网关注册表都从这里派生。
 */
class DocsCommand extends Command
{
    protected $signature = 'mcp:docs {--json : 仅输出 manifest JSON}';

    protected $description = '导出当前服务已注册 MCP 工具的 manifest 与文档（schema 唯一事实源）';

    public function handle()
    {
        $service = config('mcp.service', 'unknown');
        $toolClasses = config('mcp.tools', []);

        $manifest = [
            'service' => $service,
            'tools' => [],
        ];

        foreach ($toolClasses as $toolClass) {
            $tool = app($toolClass);
            if (! $tool instanceof McpTool) {
                continue;
            }
            $manifest['tools'][] = $tool->schema()->toArray();
        }

        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return 0;
        }

        $this->line('# MCP 能力清单：' . $service);
        $this->line('');
        foreach ($manifest['tools'] as $tool) {
            $this->line('## ' . $tool['name']);
            $this->line('');
            $this->line($tool['description']);
            $this->line('');
            $this->line('### 参数');
            foreach ($tool['parameters'] as $field => $meta) {
                $type = isset($meta['type']) ? $meta['type'] : 'string';
                $required = ! empty($meta['required']) ? '必填' : '可选';
                $desc = isset($meta['description']) ? $meta['description'] : '';
                $this->line("- `{$field}` ({$type}, {$required}) {$desc}");
            }
            $this->line('');
        }

        $this->line('<!-- manifest json -->');
        $this->line(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return 0;
    }
}
