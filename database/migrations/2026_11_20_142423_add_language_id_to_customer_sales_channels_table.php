<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->unsignedSmallInteger('language_id')->nullable();
            $table->foreign('language_id')->references('id')->on('languages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropColumn('language_id');
        });
    }
};
