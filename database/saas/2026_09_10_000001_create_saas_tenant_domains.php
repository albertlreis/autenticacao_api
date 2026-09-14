<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'saas_central';

    public function up(): void
    {
        $connection = DB::connection($this->connection);
        $driver = $connection->getDriverName();

        Schema::connection($this->connection)->create('saas_tenant_domains', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('host', 253)->unique();
            $table->boolean('is_canonical')->default(false);
            $table->boolean('active')->default(false);
            $table->string('verification_status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by', 191)->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            if ($driver === 'mysql') {
                $table->char('active_canonical_tenant_id', 36)
                    ->nullable()
                    ->storedAs('CASE WHEN is_canonical = 1 AND active = 1 THEN tenant_id ELSE NULL END');
                $table->unique('active_canonical_tenant_id', 'saas_domains_one_active_canonical');
            }
            // MySQL rejects a cascading FK when tenant_id is also the base of
            // the generated column used to enforce one active canonical host.
            // Tenants are never deleted by the control plane, so RESTRICT is
            // both compatible and the safer lifecycle rule here.
            $table->foreign('tenant_id')->references('id')->on('saas_tenants');
            $table->index(['tenant_id', 'is_canonical']);
        });

        if ($driver === 'sqlite') {
            $connection->statement(
                'CREATE UNIQUE INDEX saas_domains_one_active_canonical '
                .'ON saas_tenant_domains (tenant_id) WHERE is_canonical = 1 AND active = 1'
            );
        }

        $base = strtolower((string) config('saas.base_domain'));
        foreach (DB::connection($this->connection)->table('saas_tenants')->get(['id', 'slug', 'installation_type', 'frontend_url']) as $tenant) {
            $frontendHost = strtolower((string) parse_url((string) $tenant->frontend_url, PHP_URL_HOST));
            $host = ($tenant->installation_type ?? 'shared') === 'dedicated' && $frontendHost !== ''
                ? $frontendHost
                : strtolower($tenant->slug.'.'.$base);
            DB::connection($this->connection)->table('saas_tenant_domains')->insert([
                'tenant_id' => $tenant->id,
                'host' => $host,
                'is_canonical' => true,
                'active' => true,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => 'system:migration',
                'verification_notes' => 'Subdomínio Sierra verificado automaticamente.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('saas_tenant_domains');
    }
};
