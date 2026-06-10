<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('team_id')->default('TEAM-11');
            $table->string('activity_name');
            $table->longText('log_content');
            $table->string('receipt_number')->nullable(); // dari response SOAP dosen
            $table->string('status')->default('SUCCESS');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
