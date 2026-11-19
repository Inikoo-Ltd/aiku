<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HELP-4780: an in-house SKU is formulated in named stages (essential oil blend, then the finished product),
 * each with its own trial runs, approval and proposed artisan. Every SKU starts with one stage holding the
 * trial runs it already has.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_development_formulations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_development_id')->index();
            $table->foreign('product_development_id')->references('id')->on('product_developments')->cascadeOnDelete();
            $table->unsignedInteger('product_development_item_id')->index();
            $table->foreign('product_development_item_id')->references('id')->on('product_development_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->unsignedInteger('artisan_id')->nullable()->index();
            $table->foreign('artisan_id')->references('id')->on('employees')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['product_development_item_id', 'position']);
        });

        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->unsignedInteger('product_development_formulation_id')->nullable()->index();
            $table->foreign('product_development_formulation_id')->references('id')->on('product_development_formulations')->cascadeOnDelete();
        });

        DB::statement("
            INSERT INTO product_development_formulations (product_development_id, product_development_item_id, position, name, created_at, updated_at)
            SELECT product_development_id, id, 1, 'Formulation', now(), now() FROM product_development_items
        ");
        DB::statement('
            UPDATE product_development_samples s SET product_development_formulation_id = f.id
            FROM product_development_formulations f WHERE f.product_development_item_id = s.product_development_item_id
        ');
    }

    public function down(): void
    {
        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->dropForeign(['product_development_formulation_id']);
            $table->dropColumn('product_development_formulation_id');
        });
        Schema::dropIfExists('product_development_formulations');
    }
};
