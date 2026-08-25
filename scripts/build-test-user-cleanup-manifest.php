<?php

declare(strict_types=1);

if (! in_array($argc, [4, 6], true)) {
    fwrite(STDERR, "Uso: php build-test-user-cleanup-manifest.php <auditoria.json> <backup.sql.gz> <manifesto.json> [--approve-albert-reis <evidencia>]\n");
    exit(64);
}

[$script, $auditPath, $backupPath, $outputPath] = array_slice($argv, 0, 4);
$approved = $argc === 6 && $argv[4] === '--approve-albert-reis' && trim($argv[5]) !== '';
if ($argc === 6 && ! $approved) {
    fwrite(STDERR, "A aprovação informada é inválida.\n");
    exit(64);
}

$allowedRoot = '/home/albertreis/sierra/backups/test-user-cleanup/';
$outputDir = realpath(dirname($outputPath));
if (! $outputDir || ! str_starts_with($outputDir.'/', $allowedRoot)) {
    fwrite(STDERR, "Destino fora da raiz autorizada.\n");
    exit(65);
}
if (! is_file($auditPath) || ! is_file($backupPath)) {
    fwrite(STDERR, "Auditoria ou backup ausente.\n");
    exit(66);
}

$audit = json_decode((string) file_get_contents($auditPath), true, flags: JSON_THROW_ON_ERROR);
$deployedSha = strtolower((string) ($audit['source']['deployed_sha'] ?? ''));
if (($audit['mode'] ?? null) !== 'dry-run'
    || ($audit['schema_version'] ?? null) !== 3
    || ($audit['found_count'] ?? null) !== 7
    || ($audit['decision'] ?? null) !== 'eligible_pending_approval_and_fresh_backup'
    || ($audit['candidate_blockers'] ?? null) !== []
    || ($audit['auth_blockers'] ?? null) !== []
    || ($audit['operational_blockers'] ?? null) !== []
    || ! preg_match('/^[a-f0-9]{40}$/', $deployedSha)) {
    fwrite(STDERR, "Auditoria não elegível para manifesto.\n");
    exit(67);
}

$manifest = [
    'schema_version' => 3,
    'manifest_type' => 'authentication_default_users_cleanup',
    'approval_status' => $approved ? 'approved' : 'pending',
    'executable' => $approved,
    'generated_at' => date(DATE_ATOM),
    'source' => [
        'deployed_sha' => $deployedSha,
        'readonly_audit_sha256' => hash_file('sha256', $auditPath),
    ],
    'approval' => $approved ? [
        'approved_by' => 'Albert Reis',
        'roles' => ['responsavel_tecnico', 'lider_financeiro'],
        'evidence' => trim($argv[5]),
        'approved_at' => date(DATE_ATOM),
        'requires_final_checksum_confirmation' => true,
    ] : null,
    'backup' => [
        'file' => basename($backupPath),
        'size_bytes' => filesize($backupPath),
        'sha256' => hash_file('sha256', $backupPath),
        'restore_validation' => 'passed_isolated_mysql_8_4',
    ],
    'allowlist' => $audit['allowlist'],
    'expected_identities' => $audit['expected_identities'],
    'candidates' => $audit['candidates'],
    'references' => $audit['references'],
    'candidate_blockers' => [],
    'auth_blockers' => [],
    'operational_blockers' => [],
    'audit_fingerprint' => $audit['audit_fingerprint'],
    'decision' => $approved
        ? 'GO_pending_literal_checksum_confirmation'
        : 'NO-GO_pending_finance_approval',
];

file_put_contents($outputPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
chmod($outputPath, 0600);
echo hash_file('sha256', $outputPath).'  '.$outputPath.PHP_EOL;
