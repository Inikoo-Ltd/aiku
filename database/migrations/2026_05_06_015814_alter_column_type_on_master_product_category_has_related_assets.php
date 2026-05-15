<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_category_has_related_assets', function (Blueprint $table) {
            $table->unsignedInteger('master_product_category_id')->change();
        });
    }


    public function down(): void
    {
        Schema::table('master_product_category_has_related_assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('master_product_category_id')->change();
        });
    }
};
