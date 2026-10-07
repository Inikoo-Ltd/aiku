<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('state')->nullable()->index();
            $table->jsonb('data')->default(DB::raw("'{}'::jsonb"));
        });

        Schema::table('upload_records', function (Blueprint $table) {
            $table->jsonb('data')->default(DB::raw("'{}'::jsonb"));
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->unsignedInteger('carton_weight')->nullable()->comment('grams');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->jsonb('dimensions')->nullable()->comment('l, w, h in cm');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn(['state', 'data']);
        });

        Schema::table('upload_records', function (Blueprint $table) {
            $table->dropColumn('data');
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn('carton_weight');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('dimensions');
        });
    }
};
