<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_developments', function (Blueprint $table) {
            $table->string('kind')->default('product')->index();
        });
    }

    public function down(): void
    {
        Schema::table('product_developments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
