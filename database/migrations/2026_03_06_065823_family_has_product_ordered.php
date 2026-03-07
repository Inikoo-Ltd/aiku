<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('family_has_product_ordered', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('product_categories')->onDelete('cascade');
            $table->jsonb('product')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps();

            $table->index('family_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_has_product_ordered');
    }
};
