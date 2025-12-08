<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_categories', function (Blueprint $table) {
            $table->smallIncrements('id')->change();
            $table->smallInteger('priority')->default(0);
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('type')->index();
            $table->jsonb('settings');
            $table->boolean('show_in_dashboards')->default(true);
            $table->jsonb('data');
            $table->dropColumn('deleted_at');
        });

        Schema::table('invoice_category_stats', function (Blueprint $table) {
            $table->smallIncrements('id')->change();
        });

        Schema::table('invoice_category_sales_intervals', function (Blueprint $table) {
            $table->smallIncrements('id')->change();
            $table->unsignedSmallInteger('invoice_category_id')->change();
        });

        Schema::table('invoice_category_ordering_intervals', function (Blueprint $table) {
            $table->unsignedSmallInteger('invoice_category_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_categories', function (Blueprint $table) {
            $table->dropColumn('data');
            $table->dropColumn('settings');
            $table->dropColumn('type');
            $table->dropColumn('currency_id');
            $table->dropColumn('priority');
            $table->softDeletesTz();
        });
    }
};
