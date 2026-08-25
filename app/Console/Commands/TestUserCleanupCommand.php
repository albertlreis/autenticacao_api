<?php

namespace App\Console\Commands;

use App\Services\TestUserCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TestUserCleanupCommand extends Command
{
    protected $signature = 'app:test-users-cleanup
        {--output= : Caminho para gravar o relatório dry-run}
        {--deployed-sha= : SHA Git implantado, obrigatório para gerar manifesto produtivo}
        {--manifest= : Manifesto aprovado para execução}
        {--manifest-sha256= : SHA-256 esperado do manifesto}
        {--backup= : Backup restaurado e vinculado ao manifesto}
        {--backup-sha256= : SHA-256 esperado do backup}
        {--approval= : Frase de aprovação explícita}
        {--execute : Habilita a execução destrutiva após todas as validações}';

    protected $description = 'Audita usuários padrão de teste; dry-run é o modo obrigatório por padrão.';

    public function handle(TestUserCleanupService $service): int
    {
        try {
            if (! $this->option('execute')) {
                $audit = $service->audit();
                $deployedSha = strtolower((string) $this->option('deployed-sha'));
                if ($deployedSha !== '') {
                    if (! preg_match('/^[a-f0-9]{40}$/', $deployedSha)) {
                        throw new RuntimeException('O SHA implantado informado é inválido.');
                    }
                    $audit['source'] = ['deployed_sha' => $deployedSha];
                }
                $json = json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
                if ($path = $this->option('output')) {
                    file_put_contents((string) $path, $json);
                }
                $this->line($json);

                return $audit['decision'] === 'blocked' ? self::FAILURE : self::SUCCESS;
            }

            $path = (string) $this->option('manifest');
            if ($path === '' || ! is_file($path)) {
                throw new RuntimeException('Informe um manifesto existente.');
            }
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $result = $service->execute(
                $manifest,
                $path,
                (string) $this->option('manifest-sha256'),
                (string) $this->option('backup'),
                (string) $this->option('backup-sha256'),
                strtolower((string) $this->option('deployed-sha')),
                (string) $this->option('approval'),
            );
            Log::notice('authentication.default_test_users_cleanup', [
                'status' => $result['status'],
                'deleted' => $result['deleted'],
                'deleted_by_table' => $result['deleted_by_table'],
                'manifest_sha256' => strtolower((string) $this->option('manifest-sha256')),
                'deployed_sha' => strtolower((string) $this->option('deployed-sha')),
            ]);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
