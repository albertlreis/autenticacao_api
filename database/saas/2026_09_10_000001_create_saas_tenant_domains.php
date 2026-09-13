<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'saas_central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('saas_tenant_domains', function (Blueprint $table) {
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
            $table->foreign('tenant_id')->references('id')->on('saas_tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_canonical']);
        });

        $connection = DB::connection($this->connection);
        if ($connection->getDriverName() === 'mysql') {
            $connection->statement(
                'ALTER TABLE saas_tenant_domains '
                .'ADD active_canonical_tenant_id CHAR(36) GENERATED ALWAYS AS '
                .'(CASE WHEN is_canonical = 1 AND active = 1 THEN tenant_id ELSE NULL END) STORED, '
                .'ADD UNIQUE INDEX saas_domains_one_active_canonical (active_canonical_tenant_id)'
            );
        } elseif ($connection->getDriverName() === 'sqlite') {
            $connection->statement(
                'CREATE UNIQUE INDEX saas_domains_one_active_canonical '
                .'ON saas_tenant_domains (tenant_id) WHERE is_canonical = 1 AND active = 1'
            );
        }

        $base = strtolower((string) config('saas.base_domain'));
        foreach (DB::connection($this->connection)->table('saas_tenants')->get(['id', 'slug']) as $tenant) {
            DB::connection($this->connection)->table('saas_tenant_domains')->insert([
                'tenant_id' => $tenant->id,
                'host' => strtolower($tenant->slug.'.'.$base),
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
