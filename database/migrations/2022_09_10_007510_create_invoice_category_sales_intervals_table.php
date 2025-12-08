<?php

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::create('invoice_category_sales_intervals', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('invoice_category_id')->index();
            $table->foreign('invoice_category_id')->references('id')->on('invoice_categories')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->decimalDateIntervals($table, [
                'sales',
                'sales_org_currency',
                'sales_grp_currency',
            ]);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_category_sales_intervals');
    }
};
