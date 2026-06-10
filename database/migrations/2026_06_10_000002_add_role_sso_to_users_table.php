<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tambah kolom role untuk mapping SSO user ke role lokal
            $table->string('role')->default('operator')->after('email');
            // Tambah kolom sso_sub untuk menyimpan subject dari JWT payload
            $table->string('sso_sub')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'sso_sub']);
        });
    }
};
