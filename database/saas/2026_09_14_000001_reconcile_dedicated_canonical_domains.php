<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'saas_central';

    public function up(): void
    {
        $connection = DB::connection($this->connection);
        $connection->transaction(function () use ($connection): void {
            $tenants = $connection->table('saas_tenants')
                ->where('installation_type', 'dedicated')
                ->whereNotNull('frontend_url')
                ->get(['id', 'frontend_url']);

            foreach ($tenants as $tenant) {
                $host = strtolower((string) parse_url((string) $tenant->frontend_url, PHP_URL_HOST));
                if ($host === '' || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    throw new RuntimeException('Dedicated tenant has an invalid canonical frontend host.');
                }

                $conflict = $connection->table('saas_tenant_domains')
                    ->where('host', $host)
                    ->where('tenant_id', '!=', $tenant->id)
                    ->exists();
                if ($conflict) {
                    throw new RuntimeException('Dedicated canonical frontend host is already assigned.');
                }

                $updated = $connection->table('saas_tenant_domains')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_canonical', true)
                    ->where('active', true)
                    ->update([
                        'host' => $host,
                        'verification_status' => 'verified',
                        'verification_notes' => 'Domínio canônico dedicado reconciliado a partir do cadastro central.',
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    throw new RuntimeException('Dedicated tenant must have exactly one active canonical domain.');
                }
            }
        });
    }

    public function down(): void
    {
        // Forward-only data reconciliation: the previous host is not guessed.
    }
};
