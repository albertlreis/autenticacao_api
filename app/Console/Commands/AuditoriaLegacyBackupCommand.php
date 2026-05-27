<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AuditoriaLegacyBackupCommand extends Command
{
    protected $signature = 'auditoria:legacy-backup {--delete-logs : Remove arquivos .log depois de arquivar}';

    protected $description = 'Exporta logs_metricas e arquivos de log legados antes da remocao.';

    public function handle(): int
    {
        $timestamp = now()->format('Ymd_His');
        $base = storage_path("app/backups/auditoria-legacy/{$timestamp}");
        $tablesDir = "{$base}/tables";
        $logsDir = "{$base}/logs/auth";

        File::ensureDirectoryExists($tablesDir);
        File::ensureDirectoryExists($logsDir);

        $manifest = [
            'created_at' => now()->toISOString(),
            'database' => DB::getDatabaseName(),
            'tables' => [],
            'logs' => [],
        ];

        if (Schema::hasTable('logs_metricas')) {
            $path = "{$tablesDir}/logs_metricas.jsonl";
            $handle = fopen($path, 'wb');
            $rows = 0;

            DB::table('logs_metricas')->orderBy('id')->chunkById(500, function ($items) use ($handle, &$rows): void {
                foreach ($items as $item) {
                    fwrite($handle, json_encode((array) $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
                    $rows++;
                }
            });

            fclose($handle);
            $manifest['tables']['logs_metricas'] = [
                'exists' => true,
                'rows' => $rows,
                'file' => 'tables/logs_metricas.jsonl',
                'sha256' => hash_file('sha256', $path),
            ];
        } else {
            $manifest['tables']['logs_metricas'] = ['exists' => false, 'rows' => 0];
        }

        foreach (glob(storage_path('logs') . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
            $target = $logsDir . DIRECTORY_SEPARATOR . basename($file);
            File::copy($file, $target);
            $manifest['logs'][] = [
                'source' => 'auth',
                'file' => 'logs/auth/' . basename($file),
                'bytes' => filesize($target) ?: 0,
                'sha256' => hash_file('sha256', $target),
            ];

            if ($this->option('delete-logs')) {
                @unlink($file);
            }
        }

        File::put(
            "{$base}/manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Backup legado criado em {$base}");

        return self::SUCCESS;
    }
}
