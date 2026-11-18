<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HELP-4800: a formulation stage has a type (base blend, intermediate or the finished product), its own brief and,
 * when it is a blend or an intermediate, its own artefact in the factory with its own recipe. A trial run keeps
 * the artefact it was made on. The last stage of each SKU is its finished product; earlier ones are base blends.
 * Existing trial runs keep the SKU's artefact they were made on.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_formulations', function (Blueprint $table) {
            $table->string('type')->default('finished_product')->index();
            $table->text('brief')->nullable();
            $table->unsignedInteger('artefact_id')->nullable()->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts')->nullOnDelete();
        });

        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->unsignedInteger('artefact_id')->nullable()->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts')->nullOnDelete();
        });

        DB::statement("
            UPDATE product_development_formulations f SET type = 'base_blend'
            WHERE f.position < (SELECT max(other.position) FROM product_development_formulations other WHERE other.product_development_item_id = f.product_development_item_id)
        ");
        DB::statement('
            UPDATE product_development_samples s SET artefact_id = i.artefact_id
            FROM product_development_items i WHERE i.id = s.product_development_item_id AND i.artefact_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->dropForeign(['artefact_id']);
            $table->dropColumn('artefact_id');
        });
        Schema::table('product_development_formulations', function (Blueprint $table) {
            $table->dropForeign(['artefact_id']);
            $table->dropColumn(['type', 'brief', 'artefact_id']);
        });
    }
};
