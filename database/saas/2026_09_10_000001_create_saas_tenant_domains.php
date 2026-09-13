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
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('saas_tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_canonical']);
        });

        $base = strtolower((string) config('saas.base_domain'));
        foreach (DB::connection($this->connection)->table('saas_tenants')->get(['id', 'slug']) as $tenant) {
            DB::connection($this->connection)->table('saas_tenant_domains')->insert([
                'tenant_id' => $tenant->id,
                'host' => strtolower($tenant->slug.'.'.$base),
                'is_canonical' => true,
                'active' => true,
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
