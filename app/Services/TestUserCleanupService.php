<?php

namespace App\Services;

use App\Support\InitialData\AccessInitialDataService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TestUserCleanupService
{
    private const AUTH_TABLES = [
        'acesso_usuario_perfil',
        'personal_access_tokens',
        'acesso_refresh_tokens',
        'refresh_tokens',
        'password_reset_tokens',
        'password_resets',
    ];

    private const AUDIT_TABLES = ['audit_logs', 'auditoria_logs'];

    public function __construct(private readonly AccessInitialDataService $initialData)
    {
    }

    public function expectedIdentities(): array
    {
        return $this->initialData->identidadesUsuariosPadrao();
    }

    public function expectedEmails(): array
    {
        return array_column($this->expectedIdentities(), 'email');
    }

    public function audit(bool $lockCandidates = false): array
    {
        $database = (string) DB::connection()->getDatabaseName();
        $identities = collect($this->expectedIdentities())->keyBy('email');
        $emails = $identities->keys()->all();
        $usersQuery = DB::table('acesso_usuarios')->whereIn('email', $emails)->orderBy('id');
        if ($lockCandidates) {
            $usersQuery->lockForUpdate();
        }
        $users = $usersQuery->get();
        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $profiles = $this->profilesByUser($ids);
        $candidateBlockers = [];

        $candidates = $users->map(function ($user) use ($identities, $profiles, &$candidateBlockers) {
            $expected = $identities->get((string) $user->email);
            $userProfiles = $profiles->get((int) $user->id, collect())->values()->all();
            $snapshot = [
                'id' => (int) $user->id,
                'email' => (string) $user->email,
                'nome' => (string) $user->nome,
                'ativo' => (bool) $user->ativo,
                'created_at' => (string) $user->created_at,
                'updated_at' => (string) $user->updated_at,
                'senha_alterada_em' => (string) ($user->senha_alterada_em ?? ''),
                'perfis' => $userProfiles,
                'possui_login' => ! empty($user->ultimo_login_em)
                    || ! empty($user->ultimo_login_ip)
                    || ! empty($user->ultimo_login_user_agent),
                'possui_avatar' => ! empty($user->avatar_path),
                'possui_dados_perfil' => ! empty($user->telefone) || ! empty($user->cargo),
                'tentativas_login' => (int) ($user->tentativas_login ?? 0),
                'esta_bloqueado' => ! empty($user->bloqueado_ate),
                'forcar_troca_senha' => (bool) ($user->forcar_troca_senha ?? false),
            ];

            $reasons = [];
            if (! $expected || $snapshot['nome'] !== $expected['nome']) {
                $reasons[] = 'identidade_divergente';
            }
            if (! $snapshot['ativo']) {
                $reasons[] = 'usuario_inativo';
            }
            if ($snapshot['updated_at'] !== $snapshot['created_at']
                || $snapshot['senha_alterada_em'] !== $snapshot['created_at']) {
                $reasons[] = 'timestamps_divergentes_da_carga_inicial';
            }
            if ($snapshot['perfis'] !== [($expected['perfil'] ?? '')]) {
                $reasons[] = 'perfil_divergente';
            }
            if ($snapshot['possui_login'] || $snapshot['tentativas_login'] > 0 || $snapshot['esta_bloqueado']) {
                $reasons[] = 'usuario_utilizado_na_autenticacao';
            }
            if ($snapshot['possui_avatar'] || $snapshot['possui_dados_perfil']) {
                $reasons[] = 'cadastro_personalizado';
            }
            if ($snapshot['forcar_troca_senha']) {
                $reasons[] = 'troca_de_senha_pendente';
            }
            if ($reasons !== []) {
                $candidateBlockers[] = [
                    'user_id' => $snapshot['id'],
                    'email' => $snapshot['email'],
                    'reasons' => $reasons,
                ];
            }

            return $snapshot;
        })->all();

        $references = [];
        foreach ($this->candidateReferenceColumns($database) as $column) {
            $table = (string) $column->TABLE_NAME;
            $name = (string) $column->COLUMN_NAME;
            $count = $this->referenceQuery($table, $name, $ids, $emails)->count();
            if ($count > 0) {
                $references[] = [
                    'table' => $table,
                    'column' => $name,
                    'count' => $count,
                    'category' => $this->referenceCategory($table),
                ];
            }
        }

        $operational = array_values(array_filter(
            $references,
            fn (array $reference) => $reference['category'] === 'operational'
        ));
        $authBlockers = array_values(array_filter(
            $references,
            fn (array $reference) => $reference['category'] === 'auth_dependency'
                && $reference['table'] !== 'acesso_usuario_perfil'
        ));
        $eligible = count($candidates) === count($emails)
            && $candidateBlockers === []
            && $authBlockers === []
            && $operational === [];

        $payload = [
            'schema_version' => 3,
            'mode' => 'dry-run',
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'database' => $database,
            'allowlist' => $emails,
            'expected_identities' => $identities->values()->all(),
            'found_count' => count($candidates),
            'candidates' => $candidates,
            'references' => $references,
            'candidate_blockers' => $candidateBlockers,
            'auth_blockers' => $authBlockers,
            'operational_blockers' => $operational,
            'decision' => $eligible ? 'eligible_pending_approval_and_fresh_backup' : 'blocked',
        ];
        $payload['audit_fingerprint'] = $this->fingerprint($payload);

        return $payload;
    }

    public function execute(
        array $manifest,
        string $manifestPath,
        string $expectedManifestChecksum,
        string $backupPath,
        string $expectedBackupChecksum,
        string $deployedSha,
        string $approval,
        ?callable $afterDependenciesDeleted = null,
    ): array {
        $this->validateExecutionArtifacts(
            $manifest,
            $manifestPath,
            $expectedManifestChecksum,
            $backupPath,
            $expectedBackupChecksum,
            $deployedSha,
            $approval,
        );

        return DB::transaction(function () use ($manifest, $afterDependenciesDeleted) {
            $fresh = $this->audit(true);
            if ($fresh['found_count'] === 0) {
                return ['status' => 'already_applied', 'deleted' => 0, 'deleted_by_table' => []];
            }
            if ($fresh['found_count'] !== count($this->expectedEmails())
                || $fresh['decision'] !== 'eligible_pending_approval_and_fresh_backup'
                || ! hash_equals((string) ($manifest['audit_fingerprint'] ?? ''), (string) $fresh['audit_fingerprint'])) {
                throw new RuntimeException('A revalidação divergiu do manifesto; nenhuma exclusão foi executada.');
            }

            $ids = array_column($fresh['candidates'], 'id');
            $deletedByTable = $this->deleteAuthDependencies($ids);
            if ($afterDependenciesDeleted) {
                $afterDependenciesDeleted();
            }
            $deleted = DB::table('acesso_usuarios')
                ->whereIn('id', $ids)
                ->whereIn('email', $this->expectedEmails())
                ->delete();
            if ($deleted !== count($this->expectedEmails())) {
                throw new RuntimeException('Quantidade excluída divergente; transação revertida.');
            }
            $deletedByTable['acesso_usuarios'] = $deleted;

            return ['status' => 'executed', 'deleted' => $deleted, 'deleted_by_table' => $deletedByTable];
        }, 1);
    }

    private function validateExecutionArtifacts(
        array $manifest,
        string $manifestPath,
        string $expectedManifestChecksum,
        string $backupPath,
        string $expectedBackupChecksum,
        string $deployedSha,
        string $approval,
    ): void {
        $expectedConfirmation = 'CONFIRMO EXCLUSAO '.strtolower($expectedManifestChecksum);
        if (! hash_equals($expectedConfirmation, trim($approval))) {
            throw new RuntimeException('A frase de aprovação explícita é inválida.');
        }
        if (! is_file($manifestPath)
            || ! preg_match('/^[a-f0-9]{64}$/', strtolower($expectedManifestChecksum))
            || ! hash_equals(strtolower($expectedManifestChecksum), hash_file('sha256', $manifestPath))) {
            throw new RuntimeException('O checksum obrigatório do manifesto diverge.');
        }
        if (($manifest['approval_status'] ?? null) !== 'approved'
            || ($manifest['executable'] ?? null) !== true
            || ($manifest['approval']['approved_by'] ?? null) !== 'Albert Reis'
            || empty($manifest['approval']['evidence'])) {
            throw new RuntimeException('O manifesto não possui aprovação executável válida.');
        }
        if (! preg_match('/^[a-f0-9]{40}$/', strtolower($deployedSha))
            || ! hash_equals(strtolower((string) ($manifest['source']['deployed_sha'] ?? '')), strtolower($deployedSha))) {
            throw new RuntimeException('O SHA implantado diverge da auditoria produtiva.');
        }
        if (($manifest['allowlist'] ?? []) !== $this->expectedEmails()) {
            throw new RuntimeException('A allowlist do manifesto diverge do código.');
        }
        $manifestCandidates = array_column($manifest['candidates'] ?? [], 'email');
        $expectedCandidates = $this->expectedEmails();
        sort($manifestCandidates);
        sort($expectedCandidates);
        if ($manifestCandidates !== $expectedCandidates) {
            throw new RuntimeException('Os candidatos do manifesto divergem da allowlist fechada.');
        }
        if (! is_file($backupPath)
            || ! preg_match('/^[a-f0-9]{64}$/', strtolower($expectedBackupChecksum))
            || ! hash_equals(strtolower($expectedBackupChecksum), hash_file('sha256', $backupPath))
            || ! hash_equals(strtolower((string) ($manifest['backup']['sha256'] ?? '')), strtolower($expectedBackupChecksum))
            || ($manifest['backup']['restore_validation'] ?? null) !== 'passed_isolated_mysql_8_4') {
            throw new RuntimeException('O backup obrigatório está ausente, divergente ou não foi restaurado.');
        }
    }

    private function profilesByUser(array $ids): Collection
    {
        if ($ids === [] || ! Schema::hasTable('acesso_usuario_perfil')) {
            return collect();
        }

        return DB::table('acesso_usuario_perfil as up')
            ->join('acesso_perfis as p', 'p.id', '=', 'up.id_perfil')
            ->whereIn('up.id_usuario', $ids)
            ->orderBy('p.nome')
            ->get(['up.id_usuario', 'p.nome'])
            ->groupBy(fn ($row) => (int) $row->id_usuario)
            ->map(fn (Collection $rows) => $rows->pluck('nome'));
    }

    private function candidateReferenceColumns(string $database): Collection
    {
        $named = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->whereIn('DATA_TYPE', ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'])
            ->whereRaw("LOWER(COLUMN_NAME) REGEXP '(usuario|user|vendedor|responsavel|aprovador|criador|created_by|updated_by|owner|actor_id|causer_id)'")
            ->get(['TABLE_NAME', 'COLUMN_NAME']);
        $emailColumns = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', '<>', 'acesso_usuarios')
            ->whereIn('DATA_TYPE', ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'])
            ->whereRaw("LOWER(COLUMN_NAME) REGEXP '(^email$|email.*(usuario|user)|(usuario|user).*email)'")
            ->get(['TABLE_NAME', 'COLUMN_NAME']);
        $foreign = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('REFERENCED_TABLE_SCHEMA', $database)
            ->where('REFERENCED_TABLE_NAME', 'acesso_usuarios')
            ->get(['TABLE_NAME', 'COLUMN_NAME']);

        $auth = collect();
        foreach (self::AUTH_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            $column = collect(['id_usuario', 'usuario_id', 'user_id', 'tokenable_id', 'email'])
                ->first(fn ($candidate) => in_array($candidate, $columns, true));
            if ($column) {
                $auth->push((object) ['TABLE_NAME' => $table, 'COLUMN_NAME' => $column]);
            }
        }

        return $named->merge($emailColumns)->merge($foreign)->merge($auth)
            ->unique(fn ($column) => $column->TABLE_NAME.'.'.$column->COLUMN_NAME)
            ->sortBy(fn ($column) => $column->TABLE_NAME.'.'.$column->COLUMN_NAME)
            ->values();
    }

    private function referenceQuery(string $table, string $column, array $ids, array $emails): Builder
    {
        $values = str_contains(strtolower($column), 'email') ? $emails : $ids;
        $query = DB::table($table)->whereIn($column, $values);
        if ($table === 'personal_access_tokens' && Schema::hasColumn($table, 'tokenable_type')) {
            $query->where('tokenable_type', 'like', '%AcessoUsuario%');
        }

        return $query;
    }

    private function referenceCategory(string $table): string
    {
        if (in_array($table, self::AUTH_TABLES, true)) {
            return 'auth_dependency';
        }
        if (in_array($table, self::AUDIT_TABLES, true)) {
            return 'audit_history_retained';
        }

        return 'operational';
    }

    private function fingerprint(array $audit): string
    {
        return hash('sha256', json_encode([
            'allowlist' => $audit['allowlist'],
            'expected_identities' => $audit['expected_identities'],
            'candidates' => $audit['candidates'],
            'references' => $audit['references'],
            'candidate_blockers' => $audit['candidate_blockers'],
            'auth_blockers' => $audit['auth_blockers'],
            'operational_blockers' => $audit['operational_blockers'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function deleteAuthDependencies(array $ids): array
    {
        $deletedByTable = [];
        foreach (self::AUTH_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            $idColumn = collect(['id_usuario', 'usuario_id', 'user_id', 'tokenable_id'])
                ->first(fn ($column) => in_array($column, $columns, true));
            $deleted = 0;
            if ($idColumn) {
                $query = DB::table($table)->whereIn($idColumn, $ids);
                if ($table === 'personal_access_tokens' && in_array('tokenable_type', $columns, true)) {
                    $query->where('tokenable_type', 'like', '%AcessoUsuario%');
                }
                $deleted = $query->delete();
            } elseif (in_array('email', $columns, true)) {
                $deleted = DB::table($table)->whereIn('email', $this->expectedEmails())->delete();
            }
            if ($deleted > 0) {
                $deletedByTable[$table] = $deleted;
            }
        }

        return $deletedByTable;
    }
}
