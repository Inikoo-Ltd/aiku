<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->unsignedInteger('artefact_id')->nullable()->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->dropForeign(['artefact_id']);
            $table->dropColumn('artefact_id');
        });
    }
};
