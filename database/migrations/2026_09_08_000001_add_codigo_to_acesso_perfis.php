<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('acesso_perfis', 'codigo')) Schema::table('acesso_perfis', fn (Blueprint $t) => $t->string('codigo', 40)->nullable()->index());
        foreach (\App\Saas\TenantAccess::PROFILES as $code => $name) {
            DB::table('acesso_perfis')->whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower($name)])->update(['codigo' => $code]);
        }
    }
    public function down(): void { Schema::table('acesso_perfis', fn (Blueprint $t) => $t->dropColumn('codigo')); }
};
