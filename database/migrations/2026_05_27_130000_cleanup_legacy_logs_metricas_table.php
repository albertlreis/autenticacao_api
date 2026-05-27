<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('logs_metricas');
    }

    public function down(): void
    {
        // Rollback operacional usa o backup gerado por auditoria:legacy-backup.
    }
};
