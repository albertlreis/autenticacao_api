<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'saas_central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('saas_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('admin_name');
            $table->string('admin_email');
            $table->timestamp('provision_requested_at')->nullable();
            $table->string('slug', 63)->unique();
            $table->string('database_name', 64)->unique();
            $table->string('connection_profile')->default('default');
            $table->string('status', 20)->default('pending');
            $table->json('modules');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
        });
        Schema::connection($this->connection)->create('saas_admins', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::connection($this->connection)->create('saas_admin_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('saas_admins');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::connection($this->connection)->create('saas_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('action');
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['saas_events', 'saas_admin_tokens', 'saas_admins', 'saas_tenants'] as $table) {
            Schema::connection($this->connection)->dropIfExists($table);
        }
    }
};
