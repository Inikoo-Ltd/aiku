<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('gpsr_warnings_i8n')->nullable();
            $table->jsonb('gpsr_manual_i8n')->nullable();
            $table->boolean('is_gpsr_warnings_reviewed')->nullable();
            $table->boolean('is_gpsr_manual_reviewed')->nullable();
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->jsonb('gpsr_warnings_i8n')->nullable();
            $table->jsonb('gpsr_manual_i8n')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'gpsr_warnings_i8n',
                'gpsr_manual_i8n',
                'is_gpsr_warnings_reviewed',
                'is_gpsr_manual_reviewed',
            ]);
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn([
                'gpsr_warnings_i8n',
                'gpsr_manual_i8n',
            ]);
        });
    }
};
