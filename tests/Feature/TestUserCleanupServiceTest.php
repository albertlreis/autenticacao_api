<?php

namespace Tests\Feature;

use App\Services\TestUserCleanupService;
use App\Support\InitialData\AccessInitialDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TestUserCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    private TestUserCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TestUserCleanupService::class);
        $seed = app(AccessInitialDataService::class);
        $seed->seedPerfis();
        $seed->seedUsuariosPadrao();
        $seed->seedAssociacoes();
    }

    public function test_dry_run_detecta_allowlist_e_execucao_e_idempotente(): void
    {
        $audit = $this->service->audit();
        $this->assertSame(7, $audit['found_count']);
        $this->assertSame('eligible_pending_approval_and_fresh_backup', $audit['decision']);

        [$path, $checksum, $manifest, $backup, $backupChecksum] = $this->approvedManifest($audit);
        $first = $this->execute($manifest, $path, $checksum, $backup, $backupChecksum);
        $second = $this->execute($manifest, $path, $checksum, $backup, $backupChecksum);

        $this->assertSame('executed', $first['status']);
        $this->assertSame(7, $first['deleted']);
        $this->assertSame(7, $first['deleted_by_table']['acesso_usuarios']);
        $this->assertSame(['status' => 'already_applied', 'deleted' => 0, 'deleted_by_table' => []], $second);
        $this->assertSame(0, DB::table('acesso_usuarios')->whereIn('email', $this->service->expectedEmails())->count());
        @unlink($path);
        @unlink($backup);
    }

    public function test_bloqueia_candidato_com_referencia_operacional(): void
    {
        Schema::create('cleanup_operational_test', function ($table) {
            $table->id();
            $table->unsignedBigInteger('responsavel_usuario_id');
        });
        $id = DB::table('acesso_usuarios')->where('email', $this->service->expectedEmails()[0])->value('id');
        DB::table('cleanup_operational_test')->insert(['responsavel_usuario_id' => $id]);

        $audit = $this->service->audit();

        $this->assertSame('blocked', $audit['decision']);
        $this->assertNotEmpty($audit['operational_blockers']);
        Schema::drop('cleanup_operational_test');
    }

    public function test_aborta_por_checksum_ou_revalidacao_divergente(): void
    {
        $audit = $this->service->audit();
        [$path, $checksum, $manifest, $backup, $backupChecksum] = $this->approvedManifest($audit);

        try {
            $this->execute($manifest, $path, str_repeat('0', 64), $backup, $backupChecksum);
            $this->fail('Checksum divergente deveria abortar.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('checksum', strtolower($error->getMessage()));
        }

        DB::table('acesso_usuarios')->where('email', $this->service->expectedEmails()[0])->update(['nome' => 'Divergente']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('revalidação divergiu');
        try {
            $this->execute($manifest, $path, $checksum, $backup, $backupChecksum);
        } finally {
            @unlink($path);
            @unlink($backup);
        }
    }

    public function test_reverte_toda_a_transacao_quando_uma_exclusao_falha(): void
    {
        $audit = $this->service->audit();
        [$path, $checksum, $manifest, $backup, $backupChecksum] = $this->approvedManifest($audit);
        $candidateIds = array_column($audit['candidates'], 'id');
        $dependencyCount = DB::table('acesso_usuario_perfil')->whereIn('id_usuario', $candidateIds)->count();
        try {
            $this->service->execute(
                $manifest,
                $path,
                $checksum,
                $backup,
                $backupChecksum,
                str_repeat('a', 40),
                'CONFIRMO EXCLUSAO '.$checksum,
                fn () => throw new RuntimeException('rollback test'),
            );
            $this->fail('A falha injetada deveria interromper a exclusão.');
        } catch (\Throwable) {
            $this->assertSame(7, DB::table('acesso_usuarios')->whereIn('email', $this->service->expectedEmails())->count());
            $this->assertSame($dependencyCount, DB::table('acesso_usuario_perfil')->whereIn('id_usuario', $candidateIds)->count());
        } finally {
            @unlink($path);
            @unlink($backup);
        }
    }

    public function test_bloqueia_manifesto_pendente_ou_nao_executavel(): void
    {
        $audit = $this->service->audit();
        [$path, $checksum, $manifest, $backup, $backupChecksum] = $this->approvedManifest($audit);
        $manifest['approval_status'] = 'pending';
        $manifest['executable'] = false;
        file_put_contents($path, json_encode($manifest));
        $checksum = hash_file('sha256', $path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aprovação executável');
        try {
            $this->execute($manifest, $path, $checksum, $backup, $backupChecksum);
        } finally {
            @unlink($path);
            @unlink($backup);
        }
    }

    public function test_bloqueia_usuario_que_foi_utilizado(): void
    {
        DB::table('acesso_usuarios')
            ->where('email', $this->service->expectedEmails()[0])
            ->update(['ultimo_login_em' => now()]);

        $audit = $this->service->audit();

        $this->assertSame('blocked', $audit['decision']);
        $this->assertSame('usuario_utilizado_na_autenticacao', $audit['candidate_blockers'][0]['reasons'][0]);
    }

    public function test_bloqueia_backup_divergente(): void
    {
        $audit = $this->service->audit();
        [$path, $checksum, $manifest, $backup, $backupChecksum] = $this->approvedManifest($audit);
        file_put_contents($backup, 'backup-alterado');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backup obrigatório');
        try {
            $this->execute($manifest, $path, $checksum, $backup, $backupChecksum);
        } finally {
            @unlink($path);
            @unlink($backup);
        }
    }

    private function approvedManifest(array $audit): array
    {
        $backup = tempnam(sys_get_temp_dir(), 'cleanup-backup-');
        file_put_contents($backup, 'backup-restaurado');
        $backupChecksum = hash_file('sha256', $backup);
        $manifest = [
            ...$audit,
            'approval_status' => 'approved',
            'executable' => true,
            'approval' => ['approved_by' => 'Albert Reis', 'evidence' => 'teste automatizado'],
            'source' => ['deployed_sha' => str_repeat('a', 40)],
            'backup' => [
                'sha256' => $backupChecksum,
                'restore_validation' => 'passed_isolated_mysql_8_4',
            ],
        ];
        $path = tempnam(sys_get_temp_dir(), 'cleanup-manifest-');
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [$path, hash_file('sha256', $path), $manifest, $backup, $backupChecksum];
    }

    private function execute(array $manifest, string $path, string $checksum, string $backup, string $backupChecksum): array
    {
        return $this->service->execute(
            $manifest,
            $path,
            $checksum,
            $backup,
            $backupChecksum,
            str_repeat('a', 40),
            'CONFIRMO EXCLUSAO '.$checksum,
        );
    }
}
