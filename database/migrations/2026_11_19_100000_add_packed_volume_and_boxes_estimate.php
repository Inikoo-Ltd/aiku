<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->decimal('packed_volume', 12, 4)->nullable();
            $table->unsignedSmallInteger('packed_volume_samples')->default(0);
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('estimated_boxes')->nullable();
            $table->jsonb('boxes_estimate')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn(['packed_volume', 'packed_volume_samples']);
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn(['estimated_boxes', 'boxes_estimate']);
        });
    }
};
