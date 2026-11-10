<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('competitor_products', function (Blueprint $table) {
            $table->text('page_text')->nullable();
            $table->timestampTz('page_read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competitor_products', function (Blueprint $table) {
            $table->dropColumn(['page_text', 'page_read_at']);
        });
    }
};
