<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->string('approved_label_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->dropColumn('approved_label_hash');
        });
    }
};
