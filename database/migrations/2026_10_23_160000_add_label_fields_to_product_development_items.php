<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->jsonb('label_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->dropColumn('label_fields');
        });
    }
};
