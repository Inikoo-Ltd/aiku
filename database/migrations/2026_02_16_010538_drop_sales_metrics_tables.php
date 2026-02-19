<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('platform_shop_sales_metrics');
        Schema::dropIfExists('master_shop_sales_metrics');
        Schema::dropIfExists('platform_sales_metrics');
        Schema::dropIfExists('invoice_category_sales_metrics');
        Schema::dropIfExists('shop_sales_metrics');
        Schema::dropIfExists('organisation_sales_metrics');
        Schema::dropIfExists('group_sales_metrics');
        Schema::dropIfExists('intrastat_import_metrics');
        Schema::dropIfExists('intrastat_export_metrics');
    }


    public function down(): void
    {
        //
    }
};
