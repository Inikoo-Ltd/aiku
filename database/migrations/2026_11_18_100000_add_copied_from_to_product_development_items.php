<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HELP-4782: a new SKU can start with the information of an existing product (same product, new packaging).
 * The SKU remembers which product it was copied from and which parts, so compliance knows what to review.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->unsignedInteger('copied_from_trade_unit_id')->nullable()->index();
            $table->foreign('copied_from_trade_unit_id')->references('id')->on('trade_units')->nullOnDelete();
            $table->jsonb('copied_sections')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->dropForeign(['copied_from_trade_unit_id']);
            $table->dropColumn(['copied_from_trade_unit_id', 'copied_sections']);
        });
    }
};
