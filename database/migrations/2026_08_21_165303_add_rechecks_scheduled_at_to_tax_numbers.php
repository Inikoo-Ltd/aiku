<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tax_numbers', function (Blueprint $table) {
            $table->timestampTz('rechecks_scheduled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tax_numbers', function (Blueprint $table) {
            $table->dropColumn('rechecks_scheduled_at');
        });
    }
};
