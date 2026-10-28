<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->jsonb('platform_errors')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->dropColumn('platform_errors');
        });
    }
};
