<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'saas_central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('saas_tenant_health', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            $table->boolean('api_ok')->nullable();
            $table->boolean('worker_ok')->nullable();
            $table->boolean('scheduler_ok')->nullable();
            $table->boolean('backup_ok')->nullable();
            $table->unsignedBigInteger('backup_age_seconds')->nullable();
            $table->unsignedBigInteger('queue_pending')->nullable();
            $table->unsignedBigInteger('queue_oldest_seconds')->nullable();
            $table->string('source', 100);
            $table->json('details')->nullable();
            $table->timestamp('observed_at')->index();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('saas_tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('saas_tenant_health');
    }
};
