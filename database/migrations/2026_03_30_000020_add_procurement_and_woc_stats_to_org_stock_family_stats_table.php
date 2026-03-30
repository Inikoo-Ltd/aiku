<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_family_stats', function (Blueprint $table) {
            $table->decimal('stock_value', 16)->default(0)->after('number_org_stocks_quantity_status_error');
            $table->decimal('on_the_way_po_value', 16)->default(0)->after('stock_value');
            $table->unsignedInteger('on_the_way_po_count')->default(0)->after('on_the_way_po_value');
            $table->float('week_of_cover')->nullable()->after('on_the_way_po_count');
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_family_stats', function (Blueprint $table) {
            $table->dropColumn(['stock_value', 'on_the_way_po_value', 'on_the_way_po_count', 'week_of_cover']);
        });
    }
};
